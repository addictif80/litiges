<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Migrator;

/**
 * Suivi des migrations de base de donnees (administrateurs).
 *
 * Les migrations sont deja appliquees automatiquement au chargement des
 * pages ; cette page sert surtout a verifier l'etat et a relancer
 * manuellement en cas d'erreur (droits MySQL, etc.).
 */
class MigrationController extends Controller
{
    public function index(): void
    {
        $migrator = new Migrator();

        $this->view('migrations/index', [
            'titre'      => 'Base de donnees',
            'migrations' => $migrator->status(),
            'enAttente'  => $migrator->pendingCount(),
        ]);
    }

    public function run(): void
    {
        $migrator = new Migrator();
        $rapport  = $migrator->force();

        $erreurs = array_filter($rapport, static fn (array $r): bool => ($r['statut'] ?? '') === 'erreur');
        if ($erreurs !== []) {
            $messages = array_map(
                static fn (array $r): string => $r['nom'] . ' : ' . ($r['erreur'] ?? 'erreur inconnue'),
                $erreurs
            );
            flash('error', 'Certaines migrations ont echoue : ' . implode(' | ', $messages));
        } elseif ($rapport === []) {
            flash('success', 'La base de donnees est deja a jour.');
        } else {
            $noms = array_map(static fn (array $r): string => (string) $r['nom'], $rapport);
            flash('success', count($noms) . ' migration(s) appliquee(s) : ' . implode(', ', $noms));
        }

        $this->redirect('/parametres/migrations');
    }
}
