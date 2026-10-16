<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;

/**
 * Synchronisation avec l'ERP BLG (blgCloud) par echange de fichiers.
 *
 * Aucune API publique de blgCloud n'est documentee : l'integration repose sur
 * les exports (CSV / Excel) que l'ERP sait produire, deposes dans un dossier
 * surveille par le cron (copie manuelle, export planifie, synchronisation
 * SFTP ou dossier partage). Les colonnes sont reconnues automatiquement ou via
 * un profil de correspondance enregistre dans l'application.
 *
 * Nom des fichiers (insensible a la casse) :
 *   *client*                      => clients
 *   *facture* | *impaye* | *echeance* => litiges (factures impayees)
 *   *reglement* | *paiement* | *encaissement* => reglements (rapprochement)
 */
class BlgService
{
    public const PROFILS = [
        'clients'    => 'blg_profil_clients',
        'litiges'    => 'blg_profil_factures',
        'reglements' => 'blg_profil_reglements',
    ];

    public static function actif(): bool
    {
        return Settings::bool('blg_actif', false);
    }

    /** Dossier surveille (chemin absolu, ou relatif a storage/). */
    public static function dossier(): ?string
    {
        $dossier = trim((string) Settings::get('blg_dossier', ''));
        if ($dossier === '') {
            return null;
        }
        $absolu = preg_match('#^([A-Za-z]:[\\\\/]|/)#', $dossier) === 1 ? $dossier : APP_ROOT . '/storage/' . ltrim($dossier, '/\\');
        $reel = realpath($absolu);
        return ($reel !== false && is_dir($reel)) ? $reel : null;
    }

    /** Type d'import deduit du nom de fichier. */
    public static function typeDepuisNom(string $nom): ?string
    {
        $n = ImportParser::normaliserEntete(pathinfo($nom, PATHINFO_FILENAME));
        if (preg_match('/reglement|paiement|encaissement/', $n)) {
            return 'reglements';
        }
        if (preg_match('/facture|impaye|echeance/', $n)) {
            return 'litiges';
        }
        if (str_contains($n, 'client')) {
            return 'clients';
        }
        return null;
    }

    /** @return array<string,string> champ => nom d'en-tete enregistre */
    public static function profil(string $type): array
    {
        $cle = self::PROFILS[$type] ?? '';
        $json = $cle !== '' ? (string) Settings::get($cle, '') : '';
        $profil = $json !== '' ? json_decode($json, true) : null;
        return is_array($profil) ? $profil : [];
    }

    /**
     * Importe tous les nouveaux fichiers du dossier surveille.
     *
     * @return array{fichiers:int, crees:int, mis_a_jour:int, ignores:int, erreurs:int, details:array<int,string>}
     */
    public function synchroniser(bool $simulation = false): array
    {
        $stats = ['fichiers' => 0, 'crees' => 0, 'mis_a_jour' => 0, 'ignores' => 0, 'erreurs' => 0, 'details' => []];
        $dossier = self::dossier();
        if (!self::actif() || $dossier === null) {
            return $stats;
        }

        $fichiers = [];
        foreach (glob($dossier . '/*') ?: [] as $chemin) {
            $ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
            $type = self::typeDepuisNom(basename($chemin));
            if (is_file($chemin) && $type !== null && in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
                $fichiers[] = ['chemin' => $chemin, 'type' => $type];
            }
        }
        // Ordre logique : clients, puis factures impayees, puis reglements.
        $ordre = ['clients' => 0, 'litiges' => 1, 'reglements' => 2];
        usort($fichiers, static fn (array $a, array $b): int => $ordre[$a['type']] <=> $ordre[$b['type']]);

        $service = new ImportService();
        foreach ($fichiers as $f) {
            $empreinte = sha1_file($f['chemin']) ?: '';
            if (Database::exists('import_journal', "type = :t AND empreinte = :e AND source = 'blg'", ['t' => $f['type'], 'e' => $empreinte])) {
                if (!$simulation) {
                    $this->archiver($f['chemin'], $dossier);
                }
                $stats['details'][] = basename($f['chemin']) . ' : déjà importé, archivé';
                continue;
            }

            try {
                $stats['fichiers']++;
                $donnees = ImportService::lireFichier($f['chemin'], basename($f['chemin']));
                $mapping = ImportService::mappingDepuisProfil($f['type'], $donnees['entetes'], self::profil($f['type']));

                $manquants = [];
                foreach (ImportService::champs($f['type']) as $champ => [$libelle, $requis]) {
                    if ($requis && ($mapping[$champ] ?? null) === null) {
                        $manquants[] = $libelle;
                    }
                }
                if ($manquants !== []) {
                    throw new \RuntimeException('Colonnes obligatoires non reconnues : ' . implode(', ', $manquants) . '. Enregistrez un profil de correspondance (Import / ERP BLG).');
                }

                $rapport = $service->importer(
                    $f['type'],
                    $donnees['lignes'],
                    $mapping,
                    ['doublons' => 'mettre_a_jour', 'creer_clients' => true, 'notifier' => false],
                    !$simulation,
                    null,
                    'blg'
                );

                foreach (['crees', 'mis_a_jour', 'ignores', 'erreurs'] as $k) {
                    $stats[$k] += $rapport[$k];
                }
                $stats['details'][] = sprintf('%s (%s) : %d créé(s), %d mis à jour, %d ignoré(s), %d erreur(s)',
                    basename($f['chemin']), $f['type'], $rapport['crees'], $rapport['mis_a_jour'], $rapport['ignores'], $rapport['erreurs']);

                if (!$simulation) {
                    Database::insert('import_journal', [
                        'type'       => $f['type'],
                        'fichier'    => mb_substr(basename($f['chemin']), 0, 255),
                        'empreinte'  => $empreinte,
                        'lignes'     => $rapport['lignes'],
                        'crees'      => $rapport['crees'],
                        'mis_a_jour' => $rapport['mis_a_jour'],
                        'ignores'    => $rapport['ignores'],
                        'erreurs'    => $rapport['erreurs'],
                        'details'    => json_encode($rapport['messages'], JSON_UNESCAPED_UNICODE),
                        'source'     => 'blg',
                    ]);
                    $this->archiver($f['chemin'], $dossier);
                }
            } catch (\Throwable $e) {
                $stats['erreurs']++;
                $stats['details'][] = basename($f['chemin']) . ' : ' . $e->getMessage();
                Logger::exception($e, 'Synchronisation BLG : ' . basename($f['chemin']));
            }
        }

        return $stats;
    }

    private function archiver(string $chemin, string $dossier): void
    {
        $archive = $dossier . '/archive';
        if (!is_dir($archive)) {
            @mkdir($archive, 0775, true);
        }
        @rename($chemin, $archive . '/' . date('Ymd_His') . '_' . basename($chemin));
    }
}
