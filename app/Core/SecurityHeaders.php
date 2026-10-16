<?php
declare(strict_types=1);

namespace App\Core;

/**
 * En-tetes de securite HTTP (CSP avec nonce, HSTS, etc.) et redirection HTTPS.
 *
 * Reglage via config.php :
 *   security.csp          : 'enforce' (defaut) | 'report-only' | 'off'
 *   security.force_https  : true => redirection 301 vers HTTPS (hors localhost)
 *   security.trusted_proxies : IP des reverse-proxies de confiance
 */
class SecurityHeaders
{
    private static string $nonce = '';

    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        }
        return self::$nonce;
    }

    public static function construireCsp(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    public static function envoyer(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');

        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $mode = (string) Config::get('security.csp', 'enforce');
        if ($mode !== 'off') {
            $nom = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            header($nom . ': ' . self::construireCsp(self::nonce()));
        }
    }

    /** Redirige vers HTTPS si demande (GET/HEAD uniquement, hors localhost). */
    public static function forcerHttps(): void
    {
        if (PHP_SAPI === 'cli' || !(bool) Config::get('security.force_https', false)) {
            return;
        }
        if (self::requeteSecuriseeProbable() || !in_array(Request::method(), ['GET', 'HEAD'], true)) {
            return;
        }
        $hote = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
        if ($hote === '' || self::estLocal($hote)) {
            return;
        }
        header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? $hote) . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }

    /**
     * Pour decider d'une redirection uniquement : on accepte aussi les en-tetes
     * d'un reverse-proxy / CDN (X-Forwarded-Proto, CF-Visitor...), meme non declare
     * dans trusted_proxies. Usurper ces en-tetes ne permet que d'eviter la
     * redirection pour soi-meme ; cela evite surtout les boucles de redirection
     * derriere un terminateur TLS.
     */
    public static function requeteSecuriseeProbable(): bool
    {
        if (Request::isHttps()) {
            return true;
        }
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
            || str_contains(strtolower((string) ($_SERVER['HTTP_CF_VISITOR'] ?? '')), '"scheme":"https"')) {
            return true;
        }
        return false;
    }

    public static function estLocal(string $hote): bool
    {
        return $hote === 'localhost'
            || $hote === '127.0.0.1'
            || $hote === '[::1]'
            || str_ends_with($hote, '.localhost')
            || str_ends_with($hote, '.test');
    }
}
