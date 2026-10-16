<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiAuth;
use App\Core\Auth;
use App\Core\Database;
use App\Exceptions\DoublonException;
use App\Exceptions\ValidationException;
use App\Exceptions\WorkflowException;
use App\Models\Client;
use App\Models\Litige;
use App\Models\Paiement;
use App\Models\Relance;
use App\Services\EcheanceService;
use App\Services\LitigeService;
use App\Services\PaiementService;
use App\Services\RelanceService;

/**
 * API REST v1 (JSON). Authentification : en-tete "Authorization: Bearer lit_...".
 * Les mises a jour se font en POST (pas de PUT/PATCH). Voir docs/api.md.
 */
class ApiV1Controller extends Controller
{
    // --- Litiges -----------------------------------------------------------

    public function litiges(): void
    {
        $this->exigerModule('litiges');
        $filtres = [
            'q'         => (string) $this->input('q', ''),
            'client_id' => $this->int('client_id'),
            'statut_id' => $this->int('statut_id'),
            'etat'      => (string) $this->input('etat', ''),
            'agence_id' => $this->int('agence_id'),
            'priorite'  => (string) $this->input('priorite', ''),
            'tri'       => (string) $this->input('tri', 'recent'),
        ];
        $page    = max(1, $this->int('page', 1));
        $perPage = max(1, min(100, $this->int('par_page', 25)));

        $res = (new Litige())->search($filtres, $page, $perPage);
        $this->json([
            'data' => array_map([$this, 'litigeVersJson'], $res['rows']),
            'meta' => ['page' => $res['page'], 'pages' => $res['pages'], 'total' => $res['total'], 'par_page' => $perPage],
        ]);
    }

    public function litige(string $id): void
    {
        $this->exigerModule('litiges');
        $litige = (new Litige())->findFull((int) $id);
        if (!$litige) {
            ApiAuth::refuser(404, 'introuvable', 'Litige introuvable.');
        }

        $data = $this->litigeVersJson($litige);
        $data['paiements'] = array_map(static fn (array $p): array => [
            'id' => (int) $p['id'], 'date' => $p['date_paiement'], 'montant' => (float) $p['montant'],
            'mode' => $p['mode'], 'reference' => $p['reference'],
        ], (new Paiement())->forLitige((int) $id));
        $data['echeances'] = array_map(static fn (array $e): array => [
            'numero' => (int) $e['numero'], 'date' => $e['date_echeance'], 'montant' => (float) $e['montant'],
            'regle' => (float) $e['montant_regle'], 'statut' => $e['statut'],
        ], (new EcheanceService())->avecStatuts((int) $id));
        $data['relances'] = array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'type' => $r['type_action'], 'canal' => $r['canal'], 'date' => $r['date_action'],
            'objet' => $r['objet'], 'resultat' => $r['resultat'],
        ], (new Relance())->forLitige((int) $id));

        $this->json(['data' => $data]);
    }

    public function creerLitige(): void
    {
        $this->exigerModule('litiges');
        $corps = $this->corps();

        // Le client peut etre designe par son identifiant, son SIREN ou son code ERP.
        $clientId = (int) ($corps['client_id'] ?? 0);
        if ($clientId <= 0) {
            $clients = new Client();
            $client = (!empty($corps['client_siren']) ? $clients->findBySiren(preg_replace('/\D/', '', (string) $corps['client_siren']) ?? '') : null)
                ?? (!empty($corps['client_code_erp']) ? $clients->findByCodeErp((string) $corps['client_code_erp']) : null);
            $clientId = (int) ($client['id'] ?? 0);
        }
        $corps['client_id'] = $clientId;

        if (empty($corps['statut_id'])) {
            $corps['statut_id'] = (new \App\Models\LitigeStatut())->defaultStatutId();
        }
        if (empty($corps['date_ouverture'])) {
            $corps['date_ouverture'] = date('Y-m-d');
        }

        try {
            $id = (new LitigeService())->creer($corps, Auth::id(), !empty($corps['forcer_doublon']), false);
        } catch (ValidationException $e) {
            ApiAuth::refuser(422, 'donnees_invalides', implode(' ', $e->erreurs));
        } catch (DoublonException $e) {
            ApiAuth::refuser(409, 'doublon', $e->getMessage() . ' (ajoutez "forcer_doublon": true pour créer malgré tout)');
        }

        http_response_code(201);
        $this->json(['data' => $this->litigeVersJson((new Litige())->findFull($id) ?? [])]);
    }

    public function statutLitige(string $id): void
    {
        $this->exigerModule('litiges');
        $corps = $this->corps();
        try {
            (new LitigeService())->changerStatut(
                (int) $id,
                (int) ($corps['statut_id'] ?? 0),
                !empty($corps['finalite_id']) ? (int) $corps['finalite_id'] : null,
                (string) ($corps['commentaire'] ?? ''),
                Auth::id(),
                false
            );
        } catch (WorkflowException $e) {
            ApiAuth::refuser(409, 'workflow', implode(' ', $e->erreurs));
        } catch (ValidationException $e) {
            ApiAuth::refuser(422, 'donnees_invalides', implode(' ', $e->erreurs));
        }
        $this->json(['data' => $this->litigeVersJson((new Litige())->findFull((int) $id) ?? [])]);
    }

    public function creerPaiement(string $id): void
    {
        $this->exigerModule('litiges');
        $corps = $this->corps();
        try {
            $paiementId = (new PaiementService())->enregistrer((int) $id, [
                'date_paiement' => $corps['date_paiement'] ?? date('Y-m-d'),
                'montant'       => $corps['montant'] ?? null,
                'mode'          => $corps['mode'] ?? 'virement',
                'reference'     => $corps['reference'] ?? null,
                'commentaire'   => $corps['commentaire'] ?? null,
            ], Auth::id(), 'api', isset($corps['reference_externe']) ? substr((string) $corps['reference_externe'], 0, 120) : null);
        } catch (ValidationException $e) {
            ApiAuth::refuser(422, 'donnees_invalides', implode(' ', $e->erreurs));
        }

        http_response_code($paiementId === 0 ? 200 : 201);
        $this->json(['data' => ['id' => $paiementId, 'deja_enregistre' => $paiementId === 0]]);
    }

    public function creerRelance(string $id): void
    {
        $this->exigerModule('relances');
        try {
            $relanceId = (new RelanceService())->enregistrer((int) $id, $this->corps(), Auth::id());
        } catch (ValidationException $e) {
            ApiAuth::refuser(422, 'donnees_invalides', implode(' ', $e->erreurs));
        }
        http_response_code(201);
        $this->json(['data' => ['id' => $relanceId]]);
    }

    // --- Clients -----------------------------------------------------------

    public function clients(): void
    {
        $this->exigerModule('clients');
        $page = max(1, $this->int('page', 1));
        $perPage = max(1, min(100, $this->int('par_page', 25)));
        $res = (new Client())->search((string) $this->input('q', ''), $page, $perPage);
        $this->json([
            'data' => array_map([$this, 'clientVersJson'], $res['rows']),
            'meta' => ['page' => $page, 'total' => $res['total'], 'par_page' => $perPage],
        ]);
    }

    public function client(string $id): void
    {
        $this->exigerModule('clients');
        $client = (new Client())->find((int) $id);
        if (!$client) {
            ApiAuth::refuser(404, 'introuvable', 'Client introuvable.');
        }
        $this->json(['data' => $this->clientVersJson($client) + ['encours' => (new Client())->encours((int) $id)]]);
    }

    // --- Utilitaires ---------------------------------------------------------

    /** Corps de la requete : JSON si fourni, formulaire sinon. @return array<string,mixed> */
    private function corps(): array
    {
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json')) {
            $decode = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($decode)) {
                ApiAuth::refuser(400, 'json_invalide', 'Corps JSON invalide.');
            }
            return $decode;
        }
        return $_POST;
    }

    private function exigerModule(string $module): void
    {
        if (!Auth::peut($module)) {
            ApiAuth::refuser(403, 'module_interdit', "L'utilisateur du jeton n'a pas accès au module « {$module} ».");
        }
    }

    /** @return array<string,mixed> */
    private function litigeVersJson(array $l): array
    {
        if ($l === []) {
            return [];
        }
        return [
            'id'                => (int) $l['id'],
            'reference'         => $l['reference'],
            'client'            => ['id' => (int) $l['client_id'], 'raison_sociale' => $l['raison_sociale'] ?? null, 'siren' => $l['client_siren'] ?? null],
            'agence'            => $l['agence_nom'] ?? null,
            'objet'             => $l['objet'],
            'type'              => $l['type_litige'],
            'statut'            => ['id' => (int) $l['statut_id'], 'libelle' => $l['statut_libelle'] ?? null],
            'finalite'          => $l['finalite_libelle'] ?? null,
            'priorite'          => $l['priorite'],
            'responsable'       => $l['responsable_nom'] ?? null,
            'montant_principal' => (float) $l['montant_principal'],
            'montant_penalites' => (float) $l['montant_penalites'],
            'montant_recupere'  => (float) $l['montant_recupere'],
            'encours'           => (float) ($l['encours'] ?? 0),
            'date_ouverture'    => $l['date_ouverture'],
            'date_echeance'     => $l['date_echeance'],
            'date_cloture'      => $l['date_cloture'],
            'numero_facture'    => $l['numero_facture'],
            'numero_contrat'    => $l['numero_contrat'],
            'prochaine_relance' => $l['prochaine_relance_at'],
            'url'               => url('/litiges/' . (int) $l['id']),
        ];
    }

    /** @return array<string,mixed> */
    private function clientVersJson(array $c): array
    {
        return [
            'id'                   => (int) $c['id'],
            'raison_sociale'       => $c['raison_sociale'],
            'siren'                => $c['siren'] ?? null,
            'siret'                => $c['siret'] ?? null,
            'code_erp'             => $c['code_erp'] ?? null,
            'ville'                => $c['ville'] ?? null,
            'score_solvabilite'    => $c['score_solvabilite'] ?? null,
            'procedure_collective' => $c['procedure_collective'] ?? null,
        ];
    }
}
