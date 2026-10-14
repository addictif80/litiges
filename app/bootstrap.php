<?php
declare(strict_types=1);

/**
 * Amorçage de l'application : autoload, configuration, base de donnees, session.
 * Ce fichier ne doit etre inclus que par le point d'entree (public/index.php,
 * installeur, scripts cron).
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Core\Settings;
use App\Core\Request;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// --- Autoloader (Composer si present, sinon PSR-4 maison) --------------
$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file     = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    require_once APP_ROOT . '/app/helpers.php';
}

// --- Configuration ----------------------------------------------------
Config::load(APP_ROOT . '/config/config.php', APP_ROOT);

date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Paris'));
mb_internal_encoding('UTF-8');
setlocale(LC_TIME, (string) Config::get('app.locale', 'fr_FR') . '.UTF-8', 'fr_FR.UTF-8', 'fra');

// --- Gestion des erreurs ---------------------------------------------
if (Config::isDebug()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

set_exception_handler(static function (\Throwable $e): void {
    try {
        \App\Core\Audit::log('exception', null, null, [
            'message' => $e->getMessage(),
            'fichier' => $e->getFile() . ':' . $e->getLine(),
        ]);
    } catch (\Throwable) {
        // ignore
    }

    if (Config::isDebug()) {
        echo '<pre style="padding:20px;font:14px monospace;background:#1e1e1e;color:#f66">';
        echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n\n";
        echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    } else {
        http_response_code(500);
        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title></head><body style="font-family:system-ui;padding:40px">'
            . '<h1>Une erreur est survenue</h1><p>L\'incident a ete enregistre. Merci de contacter l\'administrateur.</p></body></html>';
    }
});

// --- Base de donnees --------------------------------------------------
Database::init((array) Config::get('db', []));

// --- Repertoires de travail ------------------------------------------
foreach (['storage', 'storage/uploads', 'storage/logs'] as $dir) {
    $path = APP_ROOT . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

// --- Session ----------------------------------------------------------
Session::start();
