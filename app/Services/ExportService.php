<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Exports de tableaux : CSV (compatible Excel), XLSX et PDF (si dompdf est installe).
 */
class ExportService
{
    /**
     * Neutralise l'injection de formules (=, +, -, @) lorsque le fichier est ouvert dans Excel.
     */
    public static function nettoyerCellule(mixed $valeur): mixed
    {
        if (is_string($valeur) && $valeur !== '' && in_array($valeur[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($valeur)) {
            return "'" . $valeur;
        }
        return $valeur;
    }

    /**
     * Envoie un CSV en flux direct (sans stockage intermediaire).
     *
     * @param string[] $colonnes
     * @param array<int,array<string,mixed>> $lignes
     */
    public function csv(string $nomFichier, array $colonnes, array $lignes): never
    {
        $nomFichier = $this->nomSur($nomFichier, 'csv');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }

        // BOM UTF-8 pour un affichage correct des accents dans Excel.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array_values($colonnes), ';', '"', '\\', "\r\n");
        foreach ($lignes as $ligne) {
            $valeurs = [];
            foreach (array_keys($colonnes) as $cle) {
                $valeur = $ligne[$cle] ?? '';
                if (is_numeric($valeur) && is_string($valeur) && preg_match('/^-?\d+\.\d+$/', $valeur)) {
                    $valeur = str_replace('.', ',', $valeur);
                }
                $valeurs[] = self::nettoyerCellule($valeur);
            }
            fputcsv($out, $valeurs, ';', '"', '\\', "\r\n");
        }

        fclose($out);
        exit;
    }

    /**
     * Envoie un classeur Excel.
     *
     * @param array<string,string> $colonnes
     * @param array<int,array<string,mixed>> $lignes
     * @param array<string,string> $types cle => texte|nombre|date
     */
    public function xlsx(string $nomFichier, array $colonnes, array $lignes, array $types = [], string $feuille = 'Export'): never
    {
        $nomFichier = $this->nomSur($nomFichier, 'xlsx');
        $contenu = XlsxWriter::generer($colonnes, $lignes, $types, $feuille);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
        header('Content-Length: ' . strlen($contenu));
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $contenu;
        exit;
    }

    /** Genere un fichier xlsx en memoire (pour l'envoyer en piece jointe). */
    public function xlsxBinaire(array $colonnes, array $lignes, array $types = [], string $feuille = 'Export'): string
    {
        return XlsxWriter::generer($colonnes, $lignes, $types, $feuille);
    }

    /** Un moteur PDF (dompdf) est-il installe ? */
    public static function pdfDisponible(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    /** Convertit du HTML en PDF binaire. @throws \RuntimeException si dompdf est absent */
    public static function htmlVersPdf(string $html, string $format = 'A4', string $orientation = 'portrait'): string
    {
        if (!self::pdfDisponible()) {
            throw new \RuntimeException('Le moteur PDF (dompdf) n\'est pas installé : composer require dompdf/dompdf.');
        }
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($format, $orientation);
        $dompdf->render();
        return (string) $dompdf->output();
    }

    private function nomSur(string $nom, string $extension): string
    {
        $nom = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $nom) ?: 'export';
        return str_ends_with($nom, '.' . $extension) ? $nom : $nom . '.' . $extension;
    }
}
