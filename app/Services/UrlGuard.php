<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Protection contre les requetes serveur vers le reseau interne (SSRF) :
 * une URL saisie par un administrateur (webhook...) doit pointer vers une
 * adresse publique. Cette verification est refaite a chaque envoi et
 * l'adresse validee est "epinglee" pour la connexion (anti DNS-rebinding).
 */
class UrlGuard
{
    /**
     * @return array{ok:bool, erreur:?string, hote:?string, ip:?string, port:?int, schema:?string}
     */
    public static function valider(string $url): array
    {
        $parties = parse_url($url);
        if ($parties === false || empty($parties['host']) || empty($parties['scheme'])) {
            return self::refus('URL invalide.');
        }

        $schema = strtolower((string) $parties['scheme']);
        if (!in_array($schema, ['https', 'http'], true)) {
            return self::refus('Seuls les schémas http et https sont acceptés.');
        }
        if ($schema === 'http' && !(bool) Config::get('integrations.autoriser_http', false)) {
            return self::refus('Une URL https est requise.');
        }
        if (isset($parties['user']) || isset($parties['pass'])) {
            return self::refus('Les identifiants dans l\'URL ne sont pas acceptés.');
        }

        $hote = strtolower(trim((string) $parties['host'], '[]'));
        $port = (int) ($parties['port'] ?? ($schema === 'https' ? 443 : 80));
        $autoriserPrive = (bool) Config::get('integrations.autoriser_reseau_prive', false);

        $ips = filter_var($hote, FILTER_VALIDATE_IP) ? [$hote] : self::resoudre($hote);
        if ($ips === []) {
            return self::refus("Le nom d'hôte ne peut pas être résolu.");
        }
        foreach ($ips as $ip) {
            if (!$autoriserPrive && !self::ipEstPublique($ip)) {
                return self::refus('Cette adresse pointe vers un réseau privé ou réservé : refusée.');
            }
        }

        return ['ok' => true, 'erreur' => null, 'hote' => $hote, 'ip' => $ips[0], 'port' => $port, 'schema' => $schema];
    }

    /** L'adresse IP est-elle publique (ni privee, ni loopback, ni reservee, ni lien-local) ? */
    public static function ipEstPublique(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (str_contains($ip, ':')) {
            $minuscule = strtolower($ip);
            if ($minuscule === '::1' || $minuscule === '::'
                || str_starts_with($minuscule, 'fc') || str_starts_with($minuscule, 'fd')
                || str_starts_with($minuscule, 'fe8') || str_starts_with($minuscule, 'fe9')
                || str_starts_with($minuscule, 'fea') || str_starts_with($minuscule, 'feb')
                || str_starts_with($minuscule, '::ffff:')) {
                return false;
            }
        }
        return true;
    }

    /** @return string[] */
    private static function resoudre(string $hote): array
    {
        $ips = gethostbynamel($hote) ?: [];
        $aaaa = @dns_get_record($hote, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $enregistrement) {
                if (!empty($enregistrement['ipv6'])) {
                    $ips[] = (string) $enregistrement['ipv6'];
                }
            }
        }
        return array_values(array_unique($ips));
    }

    private static function refus(string $message): array
    {
        return ['ok' => false, 'erreur' => $message, 'hote' => null, 'ip' => null, 'port' => null, 'schema' => null];
    }
}
