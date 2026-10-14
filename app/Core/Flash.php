<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Messages flash (affiches une seule fois apres redirection).
 */
class Flash
{
    private const KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        $_SESSION[self::KEY][$type][] = $message;
    }

    /** @return array<string, string[]> */
    public static function pull(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return $messages;
    }
}
