<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Export CSV (compatible Excel : BOM UTF-8 + separateur point-virgule).
 */
class ExportService
{
    /**
     * Envoie un CSV en flux direct (sans stockage intermediaire).
     *
     * @param string[] $colonnes
     * @param array<int,array<string,mixed>> $lignes
     */
    public function csv(string $nomFichier, array $colonnes, array $lignes): never
    {
        $nomFichier = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $nomFichier) ?: 'export.csv';
        if (!str_ends_with($nomFichier, '.csv')) {
            $nomFichier .= '.csv';
        }

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
                $valeurs[] = $valeur;
            }
            fputcsv($out, $valeurs, ';', '"', '\\', "\r\n");
        }

        fclose($out);
        exit;
    }
}
