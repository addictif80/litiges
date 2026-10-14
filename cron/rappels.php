#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Tache planifiee (cron) : generation des rappels automatiques.
 *
 * Usage :
 *   php cron/rappels.php              # genere les rappels et envoie l'e-mail recapitulatif
 *   php cron/rappels.php --dry-run    # simulation : compte les rappels sans rien ecrire
 *   php cron/rappels.php --quiet      # aucune sortie sur la console (pour le cron)
 *   php cron/rappels.php --help       # affiche cette aide
 *
 * Exemple de crontab (tous les jours a 7h) :
 *   0 7 * * * /usr/bin/php /chemin/vers/locavente/cron/rappels.php --quiet
 */

use App\Core\Database;
use App\Services\Mailer;
use App\Services\ReminderService;

// --- Securite : execution en ligne de commande uniquement ---------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Ce script doit etre execute en ligne de commande (cron).';
    exit(1);
}

// Evite les avertissements de session en contexte CLI.
@ini_set('session.use_cookies', '0');
@ini_set('session.cache_limiter', '');

define('APP_ROOT', dirname(__DIR__));

$aide = "Generateur de rappels automatiques (litiges).\n\n"
    . "Options :\n"
    . "  --dry-run   Simule l'execution sans rien ecrire en base.\n"
    . "  --quiet     N'affiche rien sur la console.\n"
    . "  --help      Affiche cette aide.\n";

$options = getopt('', ['dry-run', 'quiet', 'help']) ?: [];

if (array_key_exists('help', $options)) {
    fwrite(STDOUT, $aide);
    exit(0);
}

$dryRun = array_key_exists('dry-run', $options);
$quiet  = array_key_exists('quiet', $options);

$ecrire = static function (string $message) use ($quiet): void {
    if (!$quiet) {
        fwrite(STDOUT, $message . PHP_EOL);
    }
};

// --- Verifications prealables ------------------------------------------
if (!is_file(APP_ROOT . '/config/config.php')) {
    fwrite(STDERR, "Configuration introuvable (config/config.php). L'application n'est pas installee." . PHP_EOL);
    exit(1);
}

require APP_ROOT . '/app/bootstrap.php';

try {
    $stats = (new ReminderService())->run($dryRun);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erreur lors de la generation des rappels : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$total = (int) $stats['relance_due'] + (int) $stats['promesse_depassee'] + (int) $stats['echeance_proche'];

// --- Envoi du recapitulatif par e-mail (optionnel) ---------------------
if (!$dryRun && $total > 0) {
    try {
        $mailer = new Mailer();
        if ($mailer->isEnabled()) {
            $admins = Database::fetchAll(
                "SELECT full_name, username, email FROM users
                 WHERE role = 'admin' AND actif = 1 AND email IS NOT NULL AND email <> ''"
            );

            if ($admins !== []) {
                $lignes = [
                    'Relances a effectuer'      => (int) $stats['relance_due'],
                    'Promesses non honorees'    => (int) $stats['promesse_depassee'],
                    'Echeances proches'         => (int) $stats['echeance_proche'],
                ];
                $contenu = '<p>Rappels generes le ' . htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') . ' :</p><ul>';
                foreach ($lignes as $libelle => $nb) {
                    $contenu .= '<li>' . htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') . ' : <strong>' . $nb . '</strong></li>';
                }
                $contenu .= '</ul><p>Connectez-vous a l\'application pour traiter ces relances.</p>';

                $html = $mailer->gabarit('Rappels du ' . date('d/m/Y'), $contenu);
                foreach ($admins as $admin) {
                    if ($mailer->send((string) $admin['email'], '[Litiges] Rappels du ' . date('d/m/Y'), $html)) {
                        $stats['emails']++;
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // L'email est secondaire : on n'echoue pas la tache pour autant.
        $ecrire('Avertissement e-mail : ' . $e->getMessage());
    }
}

// --- Journalisation -----------------------------------------------------
$ligne = sprintf(
    '[%s] %srelance_due=%d promesse_depassee=%d echeance_proche=%d emails=%d',
    date('Y-m-d H:i:s'),
    $dryRun ? 'DRY-RUN ' : '',
    (int) $stats['relance_due'],
    (int) $stats['promesse_depassee'],
    (int) $stats['echeance_proche'],
    (int) $stats['emails']
);

try {
    $dossierLogs = APP_ROOT . '/storage/logs';
    if (!is_dir($dossierLogs)) {
        @mkdir($dossierLogs, 0775, true);
    }
    @file_put_contents($dossierLogs . '/cron-rappels.log', $ligne . PHP_EOL, FILE_APPEND);
} catch (\Throwable) {
    // La journalisation ne doit jamais interrompre la tache.
}

$ecrire($ligne);
if ($dryRun) {
    $ecrire('Mode simulation : aucune donnee n\'a ete ecrite.');
}
$ecrire($total > 0 ? "Termine : {$total} rappel(s) traite(s)." : 'Termine : aucun rappel a generer.');

exit(0);
