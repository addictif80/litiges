#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Envoie le rapport hebdomadaire par e-mail.
 *
 *   php cron/rapport.php            # respecte le jour et l'activation configures
 *   php cron/rapport.php --force    # envoie immediatement (test)
 */

use App\Services\PeriodicReportService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Ce script doit etre execute en ligne de commande (cron).';
    exit(1);
}

@ini_set('session.use_cookies', '0');
define('APP_ROOT', dirname(__DIR__));

if (!is_file(APP_ROOT . '/config/config.php')) {
    fwrite(STDERR, "Configuration introuvable (config/config.php)." . PHP_EOL);
    exit(1);
}
require APP_ROOT . '/app/bootstrap.php';

$resultat = (new PeriodicReportService())->envoyer(in_array('--force', $argv, true));
fwrite(STDOUT, $resultat['envoye']
    ? "Rapport envoyé à {$resultat['destinataires']} destinataire(s).\n"
    : 'Rien envoyé : ' . $resultat['raison'] . "\n");
exit(0);
