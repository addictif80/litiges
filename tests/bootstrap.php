<?php
declare(strict_types=1);

/**
 * Amorcage des tests unitaires : autoloader uniquement (aucune base de donnees).
 */

define('APP_ROOT', dirname(__DIR__));

$composer = APP_ROOT . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $classe): void {
        foreach (['App\\' => APP_ROOT . '/app/', 'Tests\\' => APP_ROOT . '/tests/'] as $prefixe => $dossier) {
            if (str_starts_with($classe, $prefixe)) {
                $fichier = $dossier . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
                if (is_file($fichier)) {
                    require $fichier;
                }
            }
        }
    });
    require_once APP_ROOT . '/app/helpers.php';
}
