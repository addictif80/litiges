<?php
declare(strict_types=1);

namespace App\Services\Import;

/**
 * Lecture minimale d'un classeur .xlsx (premiere feuille) sans dependance :
 * necessite l'extension PHP zip. Les formules ne sont pas evaluees (valeurs en cache).
 */
final class XlsxReader
{
    public static function disponible(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /**
     * @return array<int,array<int,string>> lignes de cellules (texte), index 0 = premiere ligne
     */
    public static function lire(string $chemin, int $maxLignes = 20000): array
    {
        if (!self::disponible()) {
            throw new \RuntimeException("L'extension PHP zip est requise pour lire les fichiers Excel.");
        }
        $zip = new \ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new \RuntimeException('Fichier Excel illisible.');
        }

        $partages = [];
        $xmlPartages = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlPartages !== false) {
            $doc = self::xml($xmlPartages);
            if ($doc !== null) {
                foreach ($doc->si as $si) {
                    $texte = '';
                    if (isset($si->t)) {
                        $texte = (string) $si->t;
                    } else {
                        foreach ($si->r as $r) {
                            $texte .= (string) $r->t;
                        }
                    }
                    $partages[] = $texte;
                }
            }
        }

        $feuille = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($feuille === false) {
            $zip->close();
            throw new \RuntimeException('Première feuille introuvable dans le classeur.');
        }
        $zip->close();

        $doc = self::xml($feuille);
        if ($doc === null) {
            throw new \RuntimeException('Feuille Excel invalide.');
        }

        $lignes = [];
        foreach ($doc->sheetData->row as $row) {
            if (count($lignes) >= $maxLignes) {
                break;
            }
            $cellules = [];
            foreach ($row->c as $c) {
                $colonne = self::indexColonne((string) $c['r']);
                $type = (string) $c['t'];
                if ($type === 's') {
                    $valeur = $partages[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $valeur = (string) $c->is->t;
                } else {
                    $valeur = (string) $c->v;
                }
                $cellules[$colonne] = $valeur;
            }
            if ($cellules === []) {
                continue;
            }
            $max = max(array_keys($cellules));
            $ligne = [];
            for ($i = 0; $i <= $max; $i++) {
                $ligne[] = trim($cellules[$i] ?? '');
            }
            $lignes[] = $ligne;
        }

        return $lignes;
    }

    /** "AB12" => 27 (index 0 pour A). */
    public static function indexColonne(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $m);
        $lettres = strtoupper($m[0] ?? 'A');
        $n = 0;
        foreach (str_split($lettres) as $l) {
            $n = $n * 26 + (ord($l) - 64);
        }
        return $n - 1;
    }

    private static function xml(string $contenu): ?\SimpleXMLElement
    {
        $precedent = libxml_use_internal_errors(true);
        // Pas d'entites externes : LIBXML_NONET, et le chargement d'entites est desactive par defaut en PHP 8.
        $doc = simplexml_load_string($contenu, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($precedent);
        return $doc === false ? null : $doc;
    }
}
