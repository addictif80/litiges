<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\ApiToken;

/**
 * Authentification de l'API REST par jeton "Bearer".
 *
 * Un jeton agit au nom d'un utilisateur (role et modules respectes) ; il est
 * stocke sous forme d'empreinte SHA-256 : la valeur en clair n'est montree
 * qu'une fois, a la creation.
 */
class ApiAuth
{
    public const PREFIXE = 'lit_';
    private const LIMITE_PAR_MINUTE = 120;

    /** @var array<string,mixed>|null */
    private static ?array $jeton = null;

    /** Genere un jeton. @return array{clair:string, prefixe:string, empreinte:string} */
    public static function generer(): array
    {
        $clair = self::PREFIXE . bin2hex(random_bytes(20));
        return ['clair' => $clair, 'prefixe' => substr($clair, 0, 10), 'empreinte' => self::empreinte($clair)];
    }

    public static function empreinte(string $clair): string
    {
        return hash('sha256', $clair);
    }

    /** Le chemin demande appartient-il a l'API ? */
    public static function estRequeteApi(): bool
    {
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $base = Request::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        return str_starts_with('/' . ltrim($uri, '/'), '/api/v1');
    }

    /**
     * Verifie le jeton ; termine la requete en JSON (401/403/429) si refuse.
     * Retourne true si l'acces est accorde.
     */
    public static function exiger(bool $ecriture): bool
    {
        $cleEchecs = 'apifail:' . Request::ip();
        $bloque = RateLimiter::bloque($cleEchecs, 30, 60);
        if ($bloque !== null) {
            header('Retry-After: ' . $bloque);
            self::refuser(429, 'trop_de_requetes', 'Trop de jetons invalides depuis cette adresse.');
        }

        $clair = self::extraireJeton();
        if ($clair === null || !str_starts_with($clair, self::PREFIXE)) {
            RateLimiter::hit($cleEchecs, 30, 60);
            self::refuser(401, 'non_authentifie', 'Jeton manquant : utilisez l\'en-tête Authorization: Bearer <jeton>.');
        }

        $attente = RateLimiter::hit('api:' . substr(self::empreinte($clair), 0, 16), self::LIMITE_PAR_MINUTE, 60);
        if ($attente !== null) {
            header('Retry-After: ' . $attente);
            self::refuser(429, 'trop_de_requetes', 'Limite de ' . self::LIMITE_PAR_MINUTE . ' requêtes par minute atteinte.');
        }

        $jeton = (new ApiToken())->parEmpreinte(self::empreinte($clair));
        if (!$jeton || (int) $jeton['user_actif'] !== 1) {
            RateLimiter::hit($cleEchecs, 30, 60);
            Audit::log('api_jeton_refuse', 'api', null, ['prefixe' => substr($clair, 0, 10)]);
            self::refuser(401, 'jeton_invalide', 'Jeton invalide, expiré ou révoqué.');
        }

        if ($ecriture && ((int) $jeton['ecriture'] !== 1 || !in_array($jeton['user_role'], ['admin', 'gestionnaire'], true))) {
            self::refuser(403, 'lecture_seule', 'Ce jeton est en lecture seule.');
        }

        (new ApiToken())->marquerUtilise((int) $jeton['id']);
        self::$jeton = $jeton;

        // Les services s'appuient sur l'utilisateur courant (audit, historique).
        $_SESSION['user_id'] = (int) $jeton['user_id'];
        Auth::refresh();
        return true;
    }

    /** @return array<string,mixed>|null */
    public static function jeton(): ?array
    {
        return self::$jeton;
    }

    public static function refuser(int $code, string $erreur, string $message): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['error' => ['code' => $erreur, 'message' => $message]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function extraireJeton(): ?string
    {
        $entete = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($entete === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $nom => $valeur) {
                if (strtolower((string) $nom) === 'authorization') {
                    $entete = (string) $valeur;
                }
            }
        }
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($entete), $m)) {
            return $m[1];
        }
        return null;
    }
}
