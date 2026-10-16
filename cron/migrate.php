#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Applique les migrations de base de donnees en attente (ligne de commande).
 *
 *   php cron/migrate.php            # applique les migrations
 *   php cron/migrate.php --status   # affiche l'etat sans rien modifier
 *
 * A lancer apres chaque mise a jour du code lorsque la migration automatique est
 * desactivee (config : 'app' => ['auto_migrate' => false]), ce qui est recommande
 * en production : aucune requete web ne modifie alors la structure de la base.
 */

use App\Core\Migrator;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Ce script doit etre execute en ligne de commande.';
    exit(1);
}

@ini_set('session.use_cookies', '0');
define('APP_ROOT', dirname(__DIR__));

if (!is_file(APP_ROOT . '/config/config.php')) {
    fwrite(STDERR, "Configuration introuvable (config/config.php)." . PHP_EOL);
    exit(1);
}
require APP_ROOT . '/app/bootstrap.php';

$migrator = new Migrator();

if (in_array('--status', $argv, true)) {
    foreach ($migrator->status() as $m) {
        fwrite(STDOUT, sprintf("%-60s %s%s\n", $m['migration'], $m['applied'] ? 'appliquée le ' . $m['applied_at'] : 'EN ATTENTE', $m['erreur'] ? ' (erreur : ' . $m['erreur'] . ')' : ''));
    }
    exit(0);
}

$rapport = $migrator->force();
if ($rapport === []) {
    fwrite(STDOUT, "La base de données est à jour.\n");
    exit(0);
}

$echec = false;
foreach ($rapport as $ligne) {
    fwrite(STDOUT, sprintf("%-60s %s%s\n", $ligne['nom'], $ligne['statut'], isset($ligne['erreur']) ? ' : ' . $ligne['erreur'] : ''));
    $echec = $echec || ($ligne['statut'] ?? '') === 'erreur';
}
exit($echec ? 1 : 0);
