<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Acces simplifie a la requete HTTP courante.
 */
class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    /** Chemin relatif a la base d'installation. */
    public static function path(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    public static function basePath(): string
    {
        // Detecte le sous-repertoire dans lequel l'app est installee.
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir    = rtrim(dirname($script), '/');
        return $dir === '/' ? '' : $dir;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public static function integer(string $key, int $default = 0): int
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;
        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }
        return is_numeric($value) ? (float) $value : $default;
    }

    public static function bool(string $key): bool
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;
        return in_array($value, ['1', 1, true, 'on', 'true', 'oui'], true);
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public static function ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_X_REAL_IP'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if ($candidate) {
                $ip = trim(explode(',', $candidate)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function wantsJson(): bool
    {
        return self::isAjax()
            || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
