<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Chargement et acces a la configuration (notation pointee : "db.host").
 */
class Config
{
    private static array $items = [];
    private static string $basePath = '';

    public static function load(string $file, string $basePath = ''): void
    {
        if (!is_file($file)) {
            throw new \RuntimeException("Fichier de configuration introuvable : {$file}");
        }
        $data = require $file;
        if (!is_array($data)) {
            throw new \RuntimeException("La configuration doit retourner un tableau.");
        }
        self::$items    = $data;
        self::$basePath = $basePath !== '' ? $basePath : dirname(dirname($file));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value    = self::$items;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref      = &self::$items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public static function all(): array
    {
        return self::$items;
    }

    public static function basePath(string $append = ''): string
    {
        return self::$basePath . ($append !== '' ? '/' . ltrim($append, '/') : '');
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('app.debug', false);
    }
}
