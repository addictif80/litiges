<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Parametres applicatifs stockes en base (table settings), avec cache memoire.
 */
class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::fetchAll('SELECT cle, valeur FROM settings') as $row) {
                    self::$cache[(string) $row['cle']] = (string) ($row['valeur'] ?? '');
                }
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) && $all[$key] !== '' ? $all[$key] : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, null);
        if ($value === null) {
            return $default;
        }
        return in_array($value, ['1', 'on', 'true', 'oui'], true);
    }

    public static function set(string $key, mixed $value): void
    {
        $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        Database::run(
            'INSERT INTO settings (cle, valeur) VALUES (:cle, :valeur)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)',
            ['cle' => $key, 'valeur' => $value]
        );
        self::$cache = null;
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            self::set((string) $key, $value);
        }
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
