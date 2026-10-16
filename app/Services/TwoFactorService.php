<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Totp;
use App\Models\User;

/**
 * Double authentification TOTP : activation, verification a la connexion,
 * codes de secours. Le secret est chiffre en base (voir App\Core\Crypto).
 */
class TwoFactorService
{
    /** @return array{secret:string, uri:string} */
    public function preparerActivation(array $user, string $emetteur): array
    {
        $secret = Totp::genererSecret();
        $compte = (string) ($user['email'] ?: $user['username']);
        return ['secret' => $secret, 'uri' => Totp::uri($compte, $emetteur, $secret)];
    }

    /**
     * Active la 2FA si le code est correct.
     *
     * @return string[]|null Codes de secours en clair (a afficher une fois) ou null si le code est faux.
     */
    public function activer(int $userId, string $secret, string $code): ?array
    {
        $pas = Totp::verifier($secret, $code);
        if ($pas === null) {
            return null;
        }
        $codes = Totp::codesSecours();
        (new User())->update($userId, [
            'totp_secret'    => Crypto::chiffrer($secret),
            'totp_enabled'   => 1,
            'totp_recovery'  => $this->empreintes($codes),
            'totp_last_step' => $pas,
        ]);
        Audit::log('2fa_activee', 'user', $userId);
        return $codes;
    }

    public function desactiver(int $userId): void
    {
        (new User())->update($userId, [
            'totp_secret'    => null,
            'totp_enabled'   => 0,
            'totp_recovery'  => null,
            'totp_last_step' => null,
        ]);
        Audit::log('2fa_desactivee', 'user', $userId);
    }

    /** @return string[] Nouveaux codes de secours en clair. */
    public function regenererCodes(int $userId): array
    {
        $codes = Totp::codesSecours();
        (new User())->update($userId, ['totp_recovery' => $this->empreintes($codes)]);
        Audit::log('2fa_codes_regeneres', 'user', $userId);
        return $codes;
    }

    /** Verifie un code TOTP (sans rejeu) ou un code de secours (a usage unique). */
    public function verifierConnexion(array $user, string $code): bool
    {
        $code = trim($code);
        $users = new User();

        $secret = Crypto::dechiffrer((string) ($user['totp_secret'] ?? ''));
        if ($secret !== null && preg_match('/^\s*\d{3}\s?\d{3}\s*$/', $code)) {
            $pas = Totp::verifier($secret, $code);
            $dernier = $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : -1;
            if ($pas !== null && $pas > $dernier) {
                $users->update((int) $user['id'], ['totp_last_step' => $pas]);
                return true;
            }
            return false;
        }

        return $this->consommerCodeSecours($user, $code);
    }

    private function consommerCodeSecours(array $user, string $code): bool
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($code) !== 12) {
            return false;
        }
        $code = substr($code, 0, 6) . '-' . substr($code, 6);

        $liste = json_decode((string) ($user['totp_recovery'] ?? '[]'), true);
        if (!is_array($liste)) {
            return false;
        }
        $empreinte = Crypto::empreinte($code);
        foreach ($liste as $i => $attendue) {
            if (hash_equals((string) $attendue, $empreinte)) {
                unset($liste[$i]);
                (new User())->update((int) $user['id'], ['totp_recovery' => json_encode(array_values($liste))]);
                Audit::log('2fa_code_secours_utilise', 'user', (int) $user['id'], ['restants' => count($liste)]);
                return true;
            }
        }
        return false;
    }

    /** @param string[] $codes */
    private function empreintes(array $codes): string
    {
        return (string) json_encode(array_map(static fn (string $c): string => Crypto::empreinte($c), $codes));
    }

    /** Nombre de codes de secours encore disponibles. */
    public function codesRestants(array $user): int
    {
        $liste = json_decode((string) ($user['totp_recovery'] ?? '[]'), true);
        return is_array($liste) ? count($liste) : 0;
    }
}
