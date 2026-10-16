<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Journal applicatif simple (fichiers mensuels dans storage/logs).
 * Ne leve jamais d'exception : un echec d'ecriture est ignore.
 */
class Logger
{
    public static function error(string $message, array $contexte = []): void
    {
        self::ecrire('ERROR', $message, $contexte);
    }

    public static function warning(string $message, array $contexte = []): void
    {
        self::ecrire('WARNING', $message, $contexte);
    }

    public static function info(string $message, array $contexte = []): void
    {
        self::ecrire('INFO', $message, $contexte);
    }

    /** Journalise une exception avec son origine. */
    public static function exception(\Throwable $e, string $message = ''): void
    {
        self::ecrire('ERROR', $message !== '' ? $message : $e->getMessage(), [
            'exception' => $e::class,
            'message'   => $e->getMessage(),
            'fichier'   => $e->getFile() . ':' . $e->getLine(),
        ]);
    }

    private static function ecrire(string $niveau, string $message, array $contexte): void
    {
        try {
            $racine = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
            $dossier = $racine . '/storage/logs';
            if (!is_dir($dossier)) {
                @mkdir($dossier, 0775, true);
            }
            $ligne = sprintf(
                "%s [%s] %s%s\n",
                date('c'),
                $niveau,
                str_replace(["\r", "\n"], ' ', $message),
                $contexte === [] ? '' : ' ' . json_encode($contexte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            @file_put_contents($dossier . '/app-' . date('Y-m') . '.log', $ligne, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Le journal ne doit jamais casser l'application.
        }
    }
}
