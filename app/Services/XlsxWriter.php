<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Generateur minimal de classeurs Excel (.xlsx) sans dependance externe
 * (necessite l'extension PHP zip). Une feuille, en-tete en gras, colonnes
 * typees (texte, nombre, date).
 */
class XlsxWriter
{
    public static function disponible(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /**
     * @param array<string,string>            $colonnes cle => libelle
     * @param array<int,array<string,mixed>>  $lignes
     * @param array<string,string>            $types    cle => texte|nombre|date (defaut : texte)
     * @return string contenu binaire du fichier .xlsx
     */
    public static function generer(array $colonnes, array $lignes, array $types = [], string $feuille = 'Export'): string
    {
        if (!self::disponible()) {
            throw new \RuntimeException("L'extension PHP zip est requise pour les exports Excel.");
        }

        $cles = array_keys($colonnes);
        $xmlLignes = [];

        // En-tete
        $cellules = '';
        foreach (array_values($colonnes) as $i => $libelle) {
            $cellules .= self::cellTexte(self::ref($i, 1), (string) $libelle, 1);
        }
        $xmlLignes[] = '<row r="1">' . $cellules . '</row>';

        $largeurs = array_map(static fn (string $l): int => max(10, mb_strlen($l) + 2), array_values($colonnes));

        foreach ($lignes as $n => $ligne) {
            $r = $n + 2;
            $cellules = '';
            foreach ($cles as $i => $cle) {
                $valeur = $ligne[$cle] ?? null;
                $type   = $types[$cle] ?? 'texte';
                $ref    = self::ref($i, $r);
                if ($valeur === null || $valeur === '') {
                    continue;
                }
                if ($type === 'nombre' && is_numeric($valeur)) {
                    $cellules .= '<c r="' . $ref . '" s="2"><v>' . (float) $valeur . '</v></c>';
                } elseif ($type === 'date' && ($serie = self::serieExcel((string) $valeur)) !== null) {
                    $cellules .= '<c r="' . $ref . '" s="3"><v>' . $serie . '</v></c>';
                } else {
                    $cellules .= self::cellTexte($ref, (string) $valeur, 0);
                    $largeurs[$i] = min(60, max($largeurs[$i], mb_strlen((string) $valeur) + 2));
                }
            }
            $xmlLignes[] = '<row r="' . $r . '">' . $cellules . '</row>';
        }

        $cols = '';
        foreach ($largeurs as $i => $l) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $l . '" customWidth="1"/>';
        }

        $feuilleXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . implode('', $xmlLignes) . '</sheetData>'
            . '</worksheet>';

        $nomFeuille = htmlspecialchars(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $feuille) ?? 'Export', 0, 31), ENT_XML1);

        $fichiers = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $nomFeuille . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
                . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
                . '<fill><patternFill patternType="solid"><fgColor rgb="FF12467B"/><bgColor indexed="64"/></patternFill></fill></fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="4">'
                . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
                . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                . '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                . '</cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => $feuilleXml,
        ];

        $temporaire = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        if ($zip->open($temporaire, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Création du fichier Excel impossible.');
        }
        foreach ($fichiers as $nom => $contenu) {
            $zip->addFromString($nom, $contenu);
        }
        $zip->close();

        $binaire = (string) file_get_contents($temporaire);
        @unlink($temporaire);
        return $binaire;
    }

    /** Reference de cellule : (0,1) => A1, (27,3) => AB3. */
    public static function ref(int $colonne, int $ligne): string
    {
        $lettres = '';
        $n = $colonne;
        do {
            $lettres = chr(65 + ($n % 26)) . $lettres;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);
        return $lettres . $ligne;
    }

    /** Numero de serie Excel d'une date AAAA-MM-JJ (null si invalide). */
    public static function serieExcel(string $date): ?int
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        $base = new \DateTimeImmutable('1899-12-30');
        return (int) $base->diff(new \DateTimeImmutable("{$m[1]}-{$m[2]}-{$m[3]}"))->days;
    }

    private static function cellTexte(string $ref, string $texte, int $style): string
    {
        // Neutralise les formules (injection CSV/Excel) et les caracteres XML interdits.
        if ($texte !== '' && in_array($texte[0], ['=', '+', '-', '@'], true) && !is_numeric($texte)) {
            $texte = "'" . $texte;
        }
        $texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texte) ?? '';
        return '<c r="' . $ref . '" t="inlineStr"' . ($style ? ' s="' . $style . '"' : '') . '><is><t xml:space="preserve">'
            . htmlspecialchars($texte, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
    }
}
