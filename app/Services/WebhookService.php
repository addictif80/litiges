<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Webhook;

/**
 * Webhooks sortants : chaque evenement est mis en file puis envoye signe
 * (HMAC-SHA256) aux URL abonnees, avec reessais espaces.
 *
 * En-tetes envoyes :
 *   X-Litiges-Event      nom de l'evenement
 *   X-Litiges-Delivery   identifiant de la livraison
 *   X-Litiges-Signature  t=<timestamp>,v1=<hmac_sha256(timestamp + "." + corps)>
 */
class WebhookService
{
    /** Delais (secondes) avant chaque nouvelle tentative. */
    private const DELAIS = [60, 300, 1800, 7200, 43200];
    private const ECHECS_AVANT_DESACTIVATION = 20;

    private static bool $arretEnregistre = false;

    /** Met un evenement en file pour tous les webhooks abonnes. Ne leve jamais d'exception. */
    public static function emettre(string $evenement, array $donnees): void
    {
        try {
            $webhooks = (new Webhook())->actifsPourEvenement($evenement);
            if ($webhooks === []) {
                return;
            }
            $charge = json_encode([
                'evenement' => $evenement,
                'date'      => date('c'),
                'donnees'   => $donnees,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($webhooks as $w) {
                Database::insert('webhook_livraisons', [
                    'webhook_id'             => (int) $w['id'],
                    'evenement'              => $evenement,
                    'charge'                 => $charge,
                    'prochaine_tentative_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Envoi apres la reponse HTTP pour ne pas ralentir l'utilisateur.
            if (!self::$arretEnregistre && PHP_SAPI !== 'cli') {
                self::$arretEnregistre = true;
                register_shutdown_function(static function (): void {
                    if (function_exists('fastcgi_finish_request')) {
                        @fastcgi_finish_request();
                    }
                    try {
                        (new self())->traiterFile(10);
                    } catch (\Throwable $e) {
                        Logger::exception($e, 'Webhooks : traitement de la file');
                    }
                });
            }
        } catch (\Throwable $e) {
            Logger::exception($e, 'Webhooks : mise en file impossible');
        }
    }

    /** Envoie les livraisons en attente ou a rejouer. Renvoie le nombre traite. */
    public function traiterFile(int $limite = 20): int
    {
        $livraisons = Database::fetchAll(
            "SELECT * FROM webhook_livraisons
             WHERE statut IN ('en_attente','echec') AND prochaine_tentative_at <= NOW()
             ORDER BY id ASC LIMIT " . max(1, min(200, $limite))
        );

        $webhooks = new Webhook();
        $n = 0;
        foreach ($livraisons as $livraison) {
            $webhook = $webhooks->find((int) $livraison['webhook_id']);
            if (!$webhook || (int) $webhook['actif'] !== 1) {
                Database::update('webhook_livraisons', ['statut' => 'abandonne', 'erreur' => 'Webhook désactivé ou supprimé'], 'id = :id', ['id' => (int) $livraison['id']]);
                continue;
            }
            $this->envoyer($livraison, $webhook);
            $n++;
        }
        return $n;
    }

    /** Envoi de test depuis l'administration. @return array{ok:bool, message:string} */
    public function tester(array $webhook): array
    {
        $livraison = [
            'id' => 0, 'evenement' => 'test.ping', 'tentatives' => 0,
            'charge' => json_encode(['evenement' => 'test.ping', 'date' => date('c'), 'donnees' => ['message' => 'Test de connexion']]),
        ];
        $resultat = $this->appeler($livraison, $webhook);
        return [
            'ok'      => $resultat['ok'],
            'message' => $resultat['ok'] ? 'Réponse HTTP ' . $resultat['code'] : ($resultat['erreur'] ?? 'Échec'),
        ];
    }

    private function envoyer(array $livraison, array $webhook): void
    {
        $resultat   = $this->appeler($livraison, $webhook);
        $tentatives = (int) $livraison['tentatives'] + 1;

        if ($resultat['ok']) {
            Database::update('webhook_livraisons', [
                'statut' => 'livre', 'tentatives' => $tentatives, 'code_http' => $resultat['code'], 'erreur' => null,
            ], 'id = :id', ['id' => (int) $livraison['id']]);
            Database::update('webhooks', [
                'dernier_statut' => $resultat['code'], 'dernier_envoi_at' => date('Y-m-d H:i:s'), 'echecs_consecutifs' => 0,
            ], 'id = :id', ['id' => (int) $webhook['id']]);
            return;
        }

        $abandon = $tentatives > count(self::DELAIS);
        $delai   = self::DELAIS[min($tentatives - 1, count(self::DELAIS) - 1)];
        Database::update('webhook_livraisons', [
            'statut'                 => $abandon ? 'abandonne' : 'echec',
            'tentatives'             => $tentatives,
            'code_http'              => $resultat['code'] ?: null,
            'erreur'                 => mb_substr((string) $resultat['erreur'], 0, 250),
            'prochaine_tentative_at' => date('Y-m-d H:i:s', time() + $delai),
        ], 'id = :id', ['id' => (int) $livraison['id']]);

        $echecs = (int) $webhook['echecs_consecutifs'] + 1;
        $maj = ['dernier_statut' => $resultat['code'] ?: null, 'dernier_envoi_at' => date('Y-m-d H:i:s'), 'echecs_consecutifs' => $echecs];
        if ($echecs >= self::ECHECS_AVANT_DESACTIVATION) {
            $maj['actif'] = 0;
            Logger::warning('Webhook desactive apres echecs repetes', ['webhook_id' => (int) $webhook['id']]);
        }
        Database::update('webhooks', $maj, 'id = :id', ['id' => (int) $webhook['id']]);
    }

    /** @return array{ok:bool, code:int, erreur:?string} */
    private function appeler(array $livraison, array $webhook): array
    {
        $verif = UrlGuard::valider((string) $webhook['url']);
        if (!$verif['ok']) {
            return ['ok' => false, 'code' => 0, 'erreur' => $verif['erreur']];
        }

        $secret = Crypto::dechiffrer((string) $webhook['secret']) ?? '';
        $corps  = (string) $livraison['charge'];
        $t      = (string) time();
        $signature = hash_hmac('sha256', $t . '.' . $corps, $secret);

        $reponse = HttpClient::postEpingle(
            (string) $webhook['url'],
            $corps,
            (string) $verif['hote'],
            (int) $verif['port'],
            (string) $verif['ip'],
            [
                'X-Litiges-Event'     => (string) $livraison['evenement'],
                'X-Litiges-Delivery'  => (string) $livraison['id'],
                'X-Litiges-Signature' => "t={$t},v1={$signature}",
            ]
        );

        $ok = $reponse['error'] === null && $reponse['status'] >= 200 && $reponse['status'] < 300;
        return [
            'ok'     => $ok,
            'code'   => (int) $reponse['status'],
            'erreur' => $ok ? null : ($reponse['error'] ?? ('HTTP ' . $reponse['status'])),
        ];
    }
}
