<?php
declare(strict_types=1);

/**
 * Fonctions utilitaires globales.
 */

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;

if (!function_exists('e')) {
    /** Echappe une valeur pour l'affichage HTML. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Construit une URL absolue a partir du chemin relatif. */
    function url(string $path = '/'): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        if ($base === '') {
            $base = Request::basePath();
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $code = 302): never
    {
        header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, $code);
        exit;
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Flash::add($type, $message);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('error')) {
    function error(string $key): ?string
    {
        return $_SESSION['_errors'][$key] ?? null;
    }
}

if (!function_exists('user')) {
    function user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('csp_nonce')) {
    /** Nonce CSP de la requete courante (a mettre sur chaque balise <script> inline). */
    function csp_nonce(): string
    {
        return \App\Core\SecurityHeaders::nonce();
    }
}

if (!function_exists('mentions_html')) {
    /**
     * Echappe un texte puis met en valeur les mentions @identifiant.
     * Sans danger : l'echappement est fait avant l'ajout des balises.
     */
    function mentions_html(?string $texte): string
    {
        $securise = htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
        return preg_replace(
            '/(^|[\s(])@([A-Za-z0-9_.-]{2,60})/u',
            '$1<span class="mention">@$2</span>',
            $securise
        ) ?? $securise;
    }
}

if (!function_exists('money')) {
    /** Formate un montant en euros. */
    function money(mixed $value, string $devise = 'EUR'): string
    {
        $symbol = $devise === 'EUR' ? ' €' : ' ' . $devise;
        return number_format((float) $value, 2, ',', ' ') . $symbol;
    }
}

if (!function_exists('date_fr')) {
    function date_fr(?string $date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '—';
        }
        $ts = strtotime($date);
        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('datetime_fr')) {
    function datetime_fr(?string $date): string
    {
        return date_fr($date, 'd/m/Y H:i');
    }
}

if (!function_exists('relative_time')) {
    function relative_time(?string $datetime): string
    {
        if (!$datetime) {
            return '—';
        }
        $ts   = strtotime($datetime);
        $diff = time() - $ts;

        if ($diff < 60) {
            return "a l'instant";
        }
        if ($diff < 3600) {
            return 'il y a ' . floor($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'il y a ' . floor($diff / 3600) . ' h';
        }
        if ($diff < 2592000) {
            return 'il y a ' . floor($diff / 86400) . ' j';
        }
        return date_fr($datetime);
    }
}

if (!function_exists('truncate')) {
    function truncate(?string $text, int $length = 120): string
    {
        $text = trim((string) $text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 1) . '…';
    }
}

if (!function_exists('initials')) {
    function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }
        $parts   = preg_split('/\s+/', $name) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $letters ?: '?';
    }
}

if (!function_exists('nom_utilisateur')) {
    /**
     * Nom d'affichage d'un utilisateur (accepte full_name, user_nom ou username).
     * Ne renvoie jamais de valeur vide.
     */
    function nom_utilisateur(?array $u): string
    {
        foreach (['full_name', 'user_nom', 'username'] as $cle) {
            $valeur = trim((string) ($u[$cle] ?? ''));
            if ($valeur !== '') {
                return $valeur;
            }
        }
        return 'Inconnu';
    }
}

if (!function_exists('normalize_phone')) {
    /** Nettoie un numero pour permettre les liens tel:. */
    function normalize_phone(?string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', (string) $phone) ?? '';
    }
}

if (!function_exists('is_active_menu')) {
    function is_active_menu(string $prefix): string
    {
        $path = Request::path();
        return str_starts_with($path, $prefix) ? ' is-active' : '';
    }
}

if (!function_exists('json_response')) {
    function json_response(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('days_until')) {
    function days_until(?string $date): ?int
    {
        if (!$date) {
            return null;
        }
        $ts = strtotime($date);
        if (!$ts) {
            return null;
        }
        return (int) floor(($ts - strtotime('today')) / 86400);
    }
}
