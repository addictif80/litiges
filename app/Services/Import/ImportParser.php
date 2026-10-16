<?php
declare(strict_types=1);

namespace App\Services\Import;

/**
 * Fonctions pures d'analyse de fichiers importes (CSV, nombres et dates francais).
 */
final class ImportParser
{
    /** Normalise un en-tete : minuscules, sans accents, ponctuation reduite a un espace. */
    public static function normaliserEntete(string $entete): string
    {
        $entete = trim(self::enUtf8($entete));
        $entete = mb_strtolower($entete);
        $entete = strtr($entete, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', '°' => '', 'º' => '',
        ]);
        $entete = preg_replace('/[^a-z0-9]+/', ' ', $entete) ?? '';
        return trim($entete);
    }

    /** "1 234,56" | "1.234,56" | "1234.56" | "12,5 €" | "(45,00)" => float, ou null. */
    public static function nombre(mixed $valeur): ?float
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim((string) $valeur);
        if ($texte === '') {
            return null;
        }
        $negatif = str_starts_with($texte, '(') && str_ends_with($texte, ')');
        $texte = preg_replace('/[^\d,.\-]/u', '', $texte) ?? '';
        if ($texte === '' || $texte === '-') {
            return null;
        }
        $virgule = strrpos($texte, ',');
        $point   = strrpos($texte, '.');
        if ($virgule !== false && $point !== false) {
            // Le dernier separateur est le separateur decimal.
            if ($virgule > $point) {
                $texte = str_replace('.', '', $texte);
                $texte = str_replace(',', '.', $texte);
            } else {
                $texte = str_replace(',', '', $texte);
            }
        } elseif ($virgule !== false) {
            $texte = str_replace(',', '.', $texte);
        } elseif ($point !== false && substr_count($texte, '.') > 1) {
            $texte = str_replace('.', '', $texte);
        }
        if (!is_numeric($texte)) {
            return null;
        }
        $nombre = (float) $texte;
        return $negatif ? -abs($nombre) : $nombre;
    }

    /** "31/12/2025" | "31-12-25" | "2025-12-31" | numero de serie Excel => AAAA-MM-JJ, ou null. */
    public static function date(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim((string) $valeur);
        if ($texte === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $texte, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4}|\d{2})(?!\d)/', $texte, $m)) {
            $annee = (int) $m[3];
            if ($annee < 100) {
                $annee += $annee >= 70 ? 1900 : 2000;
            }
            return checkdate((int) $m[2], (int) $m[1], $annee) ? sprintf('%04d-%02d-%02d', $annee, $m[2], $m[1]) : null;
        }
        if (preg_match('/^\d{5}(\.\d+)?$/', $texte)) {
            $serie = (int) floor((float) $texte);
            if ($serie > 20000 && $serie < 80000) {
                return (new \DateTimeImmutable('1899-12-30'))->modify("+{$serie} days")->format('Y-m-d');
            }
        }
        return null;
    }

    /** Detecte le separateur le plus probable sur la premiere ligne. */
    public static function detecterSeparateur(string $premiereLigne): string
    {
        $meilleur = ';';
        $max = -1;
        foreach ([';', ',', "\t", '|'] as $sep) {
            $n = substr_count($premiereLigne, $sep);
            if ($n > $max) {
                $max = $n;
                $meilleur = $sep;
            }
        }
        return $meilleur;
    }

    /** Convertit en UTF-8 (les exports Windows sont souvent en Windows-1252). */
    public static function enUtf8(string $texte): string
    {
        if (str_starts_with($texte, "\xEF\xBB\xBF")) {
            $texte = substr($texte, 3);
        }
        if (mb_check_encoding($texte, 'UTF-8')) {
            return $texte;
        }
        return mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
    }

    /**
     * Analyse un texte CSV.
     *
     * @return array{entetes:string[], lignes:array<int,string[]>, separateur:string}
     */
    public static function lireCsvTexte(string $contenu): array
    {
        $contenu = self::enUtf8($contenu);
        $contenu = str_replace(["\r\n", "\r"], "\n", $contenu);
        $premiere = strtok($contenu, "\n") ?: '';
        $sep = self::detecterSeparateur($premiere);

        $flux = fopen('php://memory', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);

        $entetes = [];
        $lignes  = [];
        while (($ligne = fgetcsv($flux, 0, $sep, '"', '\\')) !== false) {
            if ($ligne === [null] || $ligne === []) {
                continue;
            }
            $ligne = array_map(static fn ($c): string => trim((string) $c), $ligne);
            if ($entetes === []) {
                $entetes = $ligne;
                continue;
            }
            if (implode('', $ligne) === '') {
                continue;
            }
            $lignes[] = $ligne;
        }
        fclose($flux);

        return ['entetes' => $entetes, 'lignes' => $lignes, 'separateur' => $sep];
    }

    /** SIREN valide (9 chiffres) extrait d'une valeur, sinon null. */
    public static function siren(mixed $valeur): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $valeur) ?? '';
        if (strlen($chiffres) === 14) {
            $chiffres = substr($chiffres, 0, 9);
        }
        return strlen($chiffres) === 9 ? $chiffres : null;
    }
}
