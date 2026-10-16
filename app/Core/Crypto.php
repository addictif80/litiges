<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Chiffrement symetrique (AES-256-GCM) pour les secrets stockes en base
 * (secret TOTP, secrets de webhooks, jetons d'integration).
 * La cle est derivee de app.key (config/config.php).
 */
class Crypto
{
    private const PREFIXE = 'v1:';

    public static function chiffrer(string $clair): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $chiffre = openssl_encrypt($clair, 'aes-256-gcm', self::cle(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($chiffre === false) {
            throw new \RuntimeException('Chiffrement impossible.');
        }
        return self::PREFIXE . base64_encode($iv . $tag . $chiffre);
    }

    public static function dechiffrer(?string $valeur): ?string
    {
        if ($valeur === null || $valeur === '' || !str_starts_with($valeur, self::PREFIXE)) {
            return null;
        }
        $brut = base64_decode(substr($valeur, strlen(self::PREFIXE)), true);
        if ($brut === false || strlen($brut) < 29) {
            return null;
        }
        $iv      = substr($brut, 0, 12);
        $tag     = substr($brut, 12, 16);
        $chiffre = substr($brut, 28);
        $clair   = openssl_decrypt($chiffre, 'aes-256-gcm', self::cle(), OPENSSL_RAW_DATA, $iv, $tag);
        return $clair === false ? null : $clair;
    }

    /** Empreinte HMAC (codes de secours, jetons) avec la cle applicative. */
    public static function empreinte(string $valeur): string
    {
        return hash_hmac('sha256', $valeur, self::cle());
    }

    private static function cle(): string
    {
        $source = (string) Config::get('app.key', '');
        if ($source === '') {
            $source = 'locavente-cle-par-defaut';
        }
        return hash('sha256', $source, true);
    }
}
