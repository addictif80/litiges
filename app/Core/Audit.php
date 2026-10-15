<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Journal d'audit : trace toutes les actions sensibles.
 */
class Audit
{
    public static function log(string $action, ?string $entite = null, ?int $entiteId = null, array $details = []): void
    {
        try {
            Database::insert('audit_log', [
                'user_id'   => $_SESSION['user_id'] ?? null,
                'username'  => self::currentUsername(),
                'action'    => $action,
                'entite'    => $entite,
                'entite_id' => $entiteId,
                'ip'        => Request::ip(),
                'details'   => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // Le journal ne doit jamais faire echouer l'action metier.
        }
    }

    private static function currentUsername(): ?string
    {
        if (class_exists(Auth::class) && Auth::user()) {
            return (string) Auth::user()['username'];
        }
        return ($_SESSION['user_id'] ?? null) === null ? null : (string) ($_SESSION['username'] ?? null);
    }
}
