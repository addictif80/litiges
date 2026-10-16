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
 * Pour l'ensemble des traitements quotidiens (rappels, penalites, surveillance
 * BODACC, synchronisation BLG, RGPD, rapport hebdomadaire...), utilisez plutot
 * cron/quotidien.php.
 *
 * Exemple de crontab (tous les jours a 7h) :
 *   0 7 * * * /usr/bin/php /chemin/vers/locavente/cron/rappels.php --quiet
 */

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
    $service = new ReminderService();
    $stats   = $service->run($dryRun);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erreur lors de la generation des rappels : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$total = 0;
foreach (array_keys(ReminderService::LIBELLES) as $cle) {
    $total += (int) ($stats[$cle] ?? 0);
}

// --- Envoi du recapitulatif par e-mail (optionnel) ---------------------
if (!$dryRun && $total > 0) {
    try {
        $stats['emails'] = $service->envoyerRecapitulatif($stats);
    } catch (\Throwable $e) {
        // L'email est secondaire : on n'echoue pas la tache pour autant.
        $ecrire('Avertissement e-mail : ' . $e->getMessage());
    }
}

// --- Journalisation -----------------------------------------------------
$morceaux = [];
foreach ($stats as $cle => $valeur) {
    $morceaux[] = $cle . '=' . (int) $valeur;
}
$ligne = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $dryRun ? 'DRY-RUN ' : '', implode(' ', $morceaux));

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
