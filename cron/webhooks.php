#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Envoie (ou rejoue) les livraisons de webhooks en attente.
 *
 * Crontab conseille (toutes les 5 minutes) :
 *   * /5 * * * * /usr/bin/php /chemin/vers/litiges/cron/webhooks.php --quiet
 * (retirer l'espace dans "* /5" ; il n'est la que pour ne pas fermer ce commentaire)
 */

use App\Services\WebhookService;

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

$n = (new WebhookService())->traiterFile(100);
if (!in_array('--quiet', $argv, true)) {
    fwrite(STDOUT, "{$n} livraison(s) traitée(s).\n");
}
exit(0);
