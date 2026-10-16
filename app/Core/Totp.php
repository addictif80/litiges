<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Mots de passe a usage unique bases sur le temps (RFC 6238, SHA-1, 6 chiffres, 30 s).
 * Compatible Google Authenticator, Microsoft Authenticator, Authy, 1Password...
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function genererSecret(int $octets = 20): string
    {
        return self::base32Encoder(random_bytes($octets));
    }

    public static function base32Encoder(string $binaire): string
    {
        $bits = '';
        foreach (str_split($binaire) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $sortie = '';
        foreach (str_split($bits, 5) as $morceau) {
            $sortie .= self::ALPHABET[bindec(str_pad($morceau, 5, '0', STR_PAD_RIGHT))];
        }
        return $sortie;
    }

    public static function base32Decoder(string $texte): string
    {
        $texte = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texte) ?? '');
        $bits = '';
        foreach (str_split($texte) as $c) {
            $position = strpos(self::ALPHABET, $c);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $sortie = '';
        foreach (str_split($bits, 8) as $octet) {
            if (strlen($octet) === 8) {
                $sortie .= chr(bindec($octet));
            }
        }
        return $sortie;
    }

    /** Code a 6 chiffres pour un pas de temps donne. */
    public static function code(string $secretBase32, int $pas): string
    {
        $cle = self::base32Decoder($secretBase32);
        $compteur = pack('N2', 0, $pas);
        $hash = hash_hmac('sha1', $compteur, $cle, true);
        $decalage = ord($hash[19]) & 0x0F;
        $valeur = ((ord($hash[$decalage]) & 0x7F) << 24)
            | ((ord($hash[$decalage + 1]) & 0xFF) << 16)
            | ((ord($hash[$decalage + 2]) & 0xFF) << 8)
            | (ord($hash[$decalage + 3]) & 0xFF);
        return str_pad((string) ($valeur % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifie un code. Renvoie le pas de temps valide (pour empecher la
     * reutilisation du meme code) ou null si le code est faux.
     */
    public static function verifier(string $secretBase32, string $code, ?int $maintenant = null, int $fenetre = 1): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $pasCourant = intdiv($maintenant ?? time(), 30);
        for ($d = -$fenetre; $d <= $fenetre; $d++) {
            if (hash_equals(self::code($secretBase32, $pasCourant + $d), $code)) {
                return $pasCourant + $d;
            }
        }
        return null;
    }

    /** URI otpauth:// (a saisir ou a ouvrir dans l'application d'authentification). */
    public static function uri(string $compte, string $emetteur, string $secretBase32): string
    {
        return 'otpauth://totp/' . rawurlencode($emetteur . ':' . $compte)
            . '?secret=' . $secretBase32
            . '&issuer=' . rawurlencode($emetteur)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /** Codes de secours (affiches une seule fois). @return string[] */
    public static function codesSecours(int $nombre = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $nombre; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
        }
        return $codes;
    }
}
