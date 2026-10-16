<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use App\Models\Client;
use App\Models\LitigeHistorique;
use App\Services\Api\BodaccApi;

/**
 * Surveillance planifiee du BODACC : detecte l'ouverture d'une procedure
 * collective chez un client qui a des litiges en cours, alerte les
 * responsables et calcule le delai de declaration de creance.
 */
class BodaccWatchService
{
    /** Types d'annonces qui ouvrent une procedure et declenchent le delai de declaration. */
    private const OUVERTURES = ['Liquidation judiciaire', 'Redressement judiciaire', 'Sauvegarde'];

    /**
     * Controle un lot de clients (les moins recemment verifies d'abord).
     *
     * @return array{controles:int, nouveautes:int, erreurs:int}
     */
    public function surveiller(?int $lot = null, bool $simulation = false): array
    {
        $stats = ['controles' => 0, 'nouveautes' => 0, 'erreurs' => 0];
        $api = new BodaccApi();
        if (!$api->isConfigured() || !Settings::bool('bodacc_surveillance_active', true)) {
            return $stats;
        }

        $lot ??= max(1, (int) Settings::get('bodacc_surveillance_lot', 40));

        $clients = Database::fetchAll(
            "SELECT c.id, c.raison_sociale, c.siren, c.bodacc_derniere_annonce
             FROM clients c
             WHERE c.actif = 1 AND c.siren IS NOT NULL AND c.siren <> ''
               AND EXISTS (SELECT 1 FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
                           WHERE l.client_id = c.id AND s.cloturant = 0)
               AND (c.bodacc_checked_at IS NULL OR c.bodacc_checked_at < DATE_SUB(NOW(), INTERVAL 20 HOUR))
             ORDER BY c.bodacc_checked_at IS NULL DESC, c.bodacc_checked_at ASC
             LIMIT " . $lot
        );

        foreach ($clients as $client) {
            try {
                $stats['controles']++;
                if ($this->controlerClient($client, $api, $simulation)) {
                    $stats['nouveautes']++;
                }
            } catch (\Throwable $e) {
                $stats['erreurs']++;
                Logger::exception($e, 'Surveillance BODACC : client ' . (int) $client['id']);
            }
            usleep(250000); // politesse envers l'API publique
        }

        return $stats;
    }

    private function controlerClient(array $client, BodaccApi $api, bool $simulation): bool
    {
        $annonces = $api->annonces((string) $client['siren'], 'Procédures%', 10);

        if (!$simulation) {
            Database::update('clients', ['bodacc_checked_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $client['id']]);
        }
        if ($annonces === []) {
            return false;
        }

        $derniere = $annonces[0];
        $cle = (string) ($derniere['numero'] ?? '') . '|' . (string) ($derniere['date_parution'] ?? '');
        if ($cle === '|' || $cle === ($client['bodacc_derniere_annonce'] ?? null)) {
            return false;
        }

        $premiereFois = empty($client['bodacc_derniere_annonce']);
        $recente = !empty($derniere['date_parution'])
            && strtotime((string) $derniere['date_parution']) >= strtotime('-90 days');

        // Premier controle d'un client : on enregistre l'etat connu sans alerter
        // sur de vieilles annonces.
        $alerter = !$premiereFois || $recente;

        if ($simulation) {
            return $alerter;
        }

        $maj = ['bodacc_derniere_annonce' => $cle];
        $libelle = (string) ($derniere['libelle'] ?? 'Procédure collective');
        $maj['procedure_collective'] = mb_substr($libelle, 0, 190);

        // Date de reference du delai de declaration : derniere annonce d'ouverture.
        foreach ($annonces as $a) {
            if (in_array($a['type'] ?? '', self::OUVERTURES, true) && !empty($a['date_parution'])) {
                $maj['procedure_date_parution'] = substr((string) $a['date_parution'], 0, 10);
                break;
            }
        }
        (new Client())->update((int) $client['id'], $maj);

        if (!$alerter) {
            return false;
        }

        $litiges = Database::fetchAll(
            'SELECT l.id, l.reference, l.responsable_id
             FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
             WHERE l.client_id = :c AND s.cloturant = 0',
            ['c' => (int) $client['id']]
        );

        $historique = new LitigeHistorique();
        foreach ($litiges as $l) {
            $historique->log((int) $l['id'], null, 'procedure_collective', 'Procédure collective', null, $libelle, 'Annonce BODACC détectée automatiquement.');
        }

        (new NotificationService())->procedureCollective($client, $litiges, $libelle);
        Audit::log('bodacc_procedure_detectee', 'client', (int) $client['id'], ['annonce' => $cle, 'libelle' => $libelle]);
        WebhookService::emettre('procedure_collective.detectee', [
            'client_id'      => (int) $client['id'],
            'raison_sociale' => $client['raison_sociale'],
            'siren'          => $client['siren'],
            'procedure'      => $libelle,
            'date_parution'  => $derniere['date_parution'] ?? null,
            'litiges'        => array_map(static fn (array $l): string => (string) $l['reference'], $litiges),
        ]);

        return true;
    }

    /**
     * Informations de declaration de creance pour la fiche litige.
     *
     * @return array{procedure:string, date_parution:?string, limite:?string, jours:?int, declaree:?string, mois:int}|null
     */
    public static function infoCreance(array $client, array $litige): ?array
    {
        $procedure = trim((string) ($client['procedure_collective'] ?? ''));
        if ($procedure === '') {
            return null;
        }

        $mois = max(1, (int) Settings::get('creance_delai_mois', 2));
        $parution = !empty($client['procedure_date_parution']) ? (string) $client['procedure_date_parution'] : null;
        $limite = null;
        $jours = null;
        if ($parution !== null) {
            $limite = (new \DateTimeImmutable($parution))->modify("+{$mois} months")->format('Y-m-d');
            $jours = days_until($limite);
        }

        return [
            'procedure'     => $procedure,
            'date_parution' => $parution,
            'limite'        => $limite,
            'jours'         => $jours,
            'declaree'      => !empty($litige['creance_declaree_le']) ? (string) $litige['creance_declaree_le'] : null,
            'mois'          => $mois,
        ];
    }
}
