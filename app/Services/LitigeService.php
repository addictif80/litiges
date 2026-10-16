<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Validator;
use App\Enums\Priorite;
use App\Exceptions\DoublonException;
use App\Exceptions\ValidationException;
use App\Exceptions\WorkflowException;
use App\Models\Client;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\LitigeStatut;

/**
 * Regles metier des litiges (creation, modification, changement de statut,
 * suppression). Utilise par l'interface web, l'API REST et les imports :
 * un seul endroit pour l'historique, l'audit, les notifications et les webhooks.
 */
class LitigeService
{
    private Litige $litiges;

    public function __construct()
    {
        $this->litiges = new Litige();
    }

    // ------------------------------------------------------------------
    //  Creation
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data  champs du formulaire (voir normaliser())
     * @throws ValidationException|DoublonException
     */
    public function creer(array $data, ?int $userId, bool $forcerDoublon = false, bool $notifier = true): int
    {
        $data = $this->normaliser($data);
        $this->valider($data);

        if (!$forcerDoublon) {
            $doublons = $this->litiges->doublons(
                (int) $data['client_id'],
                $data['numero_facture'],
                $data['numero_contrat'],
                (float) $data['montant_principal']
            );
            if ($doublons !== []) {
                throw new DoublonException($doublons);
            }
        }

        $data['reference']  = $this->litiges->generateReference();
        $data['created_by'] = $userId;
        unset($data['montant_recupere']); // alimente par le journal des paiements

        if (empty($data['prochaine_relance_at'])) {
            $data['prochaine_relance_at'] = $this->calculerProchaineRelance($data['date_ouverture'], (int) $data['delai_relance_jours']);
        }

        $statut = (new LitigeStatut())->find((int) $data['statut_id']);
        $data['date_cloture'] = ($statut && (int) $statut['cloturant'] === 1) ? date('Y-m-d') : null;

        $id = $this->litiges->create($data);

        if ((int) $data['penalites_auto'] === 1) {
            (new PenaltyService())->recalculerSiAuto($id, $userId);
        }

        (new LitigeHistorique())->log($id, $userId, 'creation', null, null, $data['reference'], 'Création du litige');
        Audit::log('litige_creation', 'litige', $id, ['reference' => $data['reference']]);

        $this->recalculerScoreClient((int) $data['client_id']);

        if ($notifier) {
            (new NotificationService())->litigeCree(['id' => $id, 'reference' => $data['reference']], (int) $userId);
        }
        WebhookService::emettre('litige.cree', $this->charge($id));

        return $id;
    }

    // ------------------------------------------------------------------
    //  Modification
    // ------------------------------------------------------------------

    /**
     * @throws ValidationException|WorkflowException
     */
    public function modifier(int $id, array $data, ?int $userId, bool $forcerWorkflow = false): void
    {
        $avant = $this->litiges->find($id);
        if (!$avant) {
            throw new ValidationException(['litige' => 'Litige introuvable.']);
        }

        $data = $this->normaliser($data);
        $this->valider($data);

        // Les montants recuperes viennent uniquement du journal des paiements.
        unset($data['montant_recupere']);

        // En calcul automatique, le champ "penalites" est pilote par le systeme.
        if ((int) $data['penalites_auto'] === 1) {
            $data['montant_penalites'] = $avant['montant_penalites'];
        }

        if ((int) $data['statut_id'] !== (int) $avant['statut_id'] && !$forcerWorkflow) {
            $erreurs = (new WorkflowService())->verifier($avant, (int) $data['statut_id']);
            if ($erreurs !== []) {
                throw new WorkflowException($erreurs);
            }
        }

        $data['prochaine_relance_at'] = $data['prochaine_relance_at'] ?: $this->calculerProchaineRelance(
            $avant['derniere_relance_at'] ? date('Y-m-d', strtotime((string) $avant['derniere_relance_at'])) : $data['date_ouverture'],
            (int) $data['delai_relance_jours']
        );

        $statut  = (new LitigeStatut())->find((int) $data['statut_id']);
        $cloture = $statut && (int) $statut['cloturant'] === 1;
        $data['date_cloture'] = $cloture ? ($avant['date_cloture'] ?: date('Y-m-d')) : null;

        $this->litiges->update($id, $data);

        if ((int) $data['penalites_auto'] === 1) {
            (new PenaltyService())->recalculerSiAuto($id, $userId);
        }

        (new LitigeHistorique())->diff($id, $userId, $avant, array_merge($avant, $data));
        Audit::log('litige_modification', 'litige', $id, ['reference' => $avant['reference']]);

        (new NotificationService())->litigeModifie(['id' => $id, 'reference' => $avant['reference']], (int) $userId);
        WebhookService::emettre('litige.modifie', $this->charge($id));
        if ((int) $data['statut_id'] !== (int) $avant['statut_id']) {
            $this->emettreChangementStatut($avant, $statut ?? [], $cloture);
        }
        if ((int) $data['client_id'] !== (int) $avant['client_id']) {
            $this->recalculerScoreClient((int) $avant['client_id']);
        }
        $this->recalculerScoreClient((int) $data['client_id']);
    }

    // ------------------------------------------------------------------
    //  Changement de statut
    // ------------------------------------------------------------------

    /**
     * @throws ValidationException|WorkflowException
     */
    public function changerStatut(int $id, int $statutId, ?int $finaliteId, string $commentaire, ?int $userId, bool $forcerWorkflow = false): void
    {
        $avant = $this->litiges->find($id);
        if (!$avant) {
            throw new ValidationException(['litige' => 'Litige introuvable.']);
        }

        $statut = (new LitigeStatut())->find($statutId);
        if (!$statut || $statut['categorie'] !== 'statut') {
            throw new ValidationException(['statut_id' => 'Statut invalide.']);
        }

        if (!$forcerWorkflow) {
            $erreurs = (new WorkflowService())->verifier($avant, $statutId);
            if ($erreurs !== []) {
                throw new WorkflowException($erreurs);
            }
        }

        $cloturant = (int) $statut['cloturant'] === 1;
        $this->litiges->update($id, [
            'statut_id'    => $statutId,
            'finalite_id'  => $finaliteId && $finaliteId > 0 ? $finaliteId : null,
            'date_cloture' => $cloturant ? ($avant['date_cloture'] ?: date('Y-m-d')) : null,
        ]);

        (new LitigeHistorique())->log(
            $id,
            $userId,
            'changement_statut',
            'Statut',
            (string) $avant['statut_id'],
            (string) $statutId,
            $commentaire !== '' ? $commentaire : ('Nouveau statut : ' . $statut['libelle'])
        );
        Audit::log('litige_statut', 'litige', $id, ['statut' => $statut['libelle'], 'force' => $forcerWorkflow]);

        $notif = new NotificationService();
        $ref   = ['id' => $id, 'reference' => $avant['reference']];
        if ($cloturant) {
            $notif->litigeResolu($ref, (int) $userId, (string) $statut['libelle']);
        } else {
            $notif->litigeModifie($ref, (int) $userId);
        }

        $this->emettreChangementStatut($avant, $statut, $cloturant);
    }

    // ------------------------------------------------------------------
    //  Suppression
    // ------------------------------------------------------------------

    public function supprimer(int $id): void
    {
        $litige = $this->litiges->find($id);
        if (!$litige) {
            throw new ValidationException(['litige' => 'Litige introuvable.']);
        }

        // Les fichiers physiques des pieces jointes sont supprimes avec le litige.
        $upload = new UploadService();
        foreach (Database::fetchAll('SELECT id FROM documents WHERE litige_id = :id', ['id' => $id]) as $doc) {
            $upload->delete((int) $doc['id']);
        }

        $this->litiges->delete($id);
        Audit::log('litige_suppression', 'litige', $id, ['reference' => $litige['reference']]);
        $this->recalculerScoreClient((int) $litige['client_id']);
    }

    // ------------------------------------------------------------------
    //  Actions groupees
    // ------------------------------------------------------------------

    /**
     * Applique une modification simple a plusieurs litiges.
     *
     * @param int[] $ids
     * @param array{responsable_id?:?int, priorite?:string, statut_id?:int} $changement
     * @return array{modifies:int, refuses:array<int,string>}
     */
    public function actionGroupee(array $ids, array $changement, ?int $userId, bool $forcerWorkflow = false): array
    {
        $resultat = ['modifies' => 0, 'refuses' => []];
        $historique = new LitigeHistorique();

        foreach (array_slice(array_unique(array_map('intval', $ids)), 0, 500) as $id) {
            $litige = $this->litiges->find($id);
            if (!$litige) {
                continue;
            }

            try {
                if (isset($changement['statut_id'])) {
                    $this->changerStatut($id, (int) $changement['statut_id'], null, 'Modification groupée', $userId, $forcerWorkflow);
                }
                if (array_key_exists('responsable_id', $changement)) {
                    $this->litiges->update($id, ['responsable_id' => $changement['responsable_id']]);
                    $historique->log($id, $userId, 'modification', 'Responsable', (string) $litige['responsable_id'], (string) $changement['responsable_id'], 'Modification groupée');
                }
                if (isset($changement['priorite']) && in_array($changement['priorite'], Priorite::valeurs(), true)) {
                    $this->litiges->update($id, ['priorite' => $changement['priorite']]);
                    $historique->log($id, $userId, 'modification', 'Priorité', (string) $litige['priorite'], (string) $changement['priorite'], 'Modification groupée');
                }
                $resultat['modifies']++;
            } catch (WorkflowException $e) {
                $resultat['refuses'][$id] = $litige['reference'] . ' : ' . implode(' ', $e->erreurs);
            } catch (ValidationException $e) {
                $resultat['refuses'][$id] = $litige['reference'] . ' : ' . $e->getMessage();
            }
        }

        Audit::log('litige_action_groupee', 'litige', null, ['nb' => $resultat['modifies'], 'changement' => array_keys($changement)]);
        return $resultat;
    }

    // ------------------------------------------------------------------
    //  Utilitaires
    // ------------------------------------------------------------------

    /** Recalcule le score de solvabilite interne (gratuit) avec l'encours a jour. */
    public function recalculerScoreClient(int $clientId): void
    {
        try {
            $clients = new Client();
            $client  = $clients->find($clientId);
            if (!$client) {
                return;
            }

            $encours = $clients->encours($clientId);
            $score   = (new CompanyLookupService())->scoreSolvabilite($client, $encours);

            $clients->update($clientId, ['score_solvabilite' => $score]);
            Audit::log('score_client', 'client', $clientId, ['encours' => $encours, 'score' => $score]);
        } catch (\Throwable $e) {
            Logger::exception($e, 'Recalcul du score de solvabilité impossible (client ' . $clientId . ')');
        }
    }

    /**
     * Normalise les entrees (montants "1 234,50", cases a cocher, valeurs vides)
     * et ajoute les valeurs par defaut.
     *
     * @return array<string,mixed>
     */
    public function normaliser(array $d): array
    {
        $nombre = static function (mixed $v): float {
            $propre = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $v);
            return is_numeric($propre) ? round((float) $propre, 2) : 0.0;
        };
        $texte = static fn (mixed $v): ?string => ($t = trim((string) $v)) === '' ? null : $t;

        $taux = $texte($d['taux_penalite'] ?? null);

        return [
            'client_id'            => (int) ($d['client_id'] ?? 0),
            'agence_id'            => !empty($d['agence_id']) ? (int) $d['agence_id'] : null,
            'objet'                => trim((string) ($d['objet'] ?? '')),
            'description'          => $texte($d['description'] ?? null),
            'type_litige'          => $texte($d['type_litige'] ?? null),
            'montant_principal'    => $nombre($d['montant_principal'] ?? 0),
            'montant_penalites'    => $nombre($d['montant_penalites'] ?? 0),
            'montant_recupere'     => $nombre($d['montant_recupere'] ?? 0),
            'date_ouverture'       => trim((string) ($d['date_ouverture'] ?? '')),
            'date_echeance'        => $texte($d['date_echeance'] ?? null),
            'statut_id'            => (int) ($d['statut_id'] ?? 0),
            'finalite_id'          => !empty($d['finalite_id']) ? (int) $d['finalite_id'] : null,
            'responsable_id'       => !empty($d['responsable_id']) ? (int) $d['responsable_id'] : null,
            'priorite'             => (string) ($d['priorite'] ?? 'normale'),
            'numero_facture'       => $texte($d['numero_facture'] ?? null),
            'date_facture'         => $texte($d['date_facture'] ?? null),
            'numero_contrat'       => $texte($d['numero_contrat'] ?? null),
            'materiel'             => $texte($d['materiel'] ?? null),
            'reference_externe'    => $texte($d['reference_externe'] ?? null),
            'delai_relance_jours'  => max(1, (int) ($d['delai_relance_jours'] ?? 15)),
            'prochaine_relance_at' => $texte($d['prochaine_relance_at'] ?? null),
            'penalites_auto'       => !empty($d['penalites_auto']) ? 1 : 0,
            'taux_penalite'        => ($taux !== null && is_numeric(str_replace(',', '.', $taux))) ? (float) str_replace(',', '.', $taux) : null,
            'indemnite_forfaitaire' => !empty($d['indemnite_forfaitaire']) ? 1 : 0,
            'penalites_depuis'     => $texte($d['penalites_depuis'] ?? null),
        ];
    }

    /** @throws ValidationException */
    public function valider(array $data): void
    {
        $validator = new Validator($data, [
            'client_id' => 'Le client', 'objet' => "L'objet", 'date_ouverture' => "La date d'ouverture",
            'statut_id' => 'Le statut',
        ]);

        $validator
            ->custom('client_id', (int) $data['client_id'] > 0, 'Le client est obligatoire.')
            ->required('objet')->length('objet', 3, 190)
            ->required('date_ouverture')->date('date_ouverture')
            ->custom('statut_id', (int) $data['statut_id'] > 0, 'Le statut est obligatoire.')
            ->in('priorite', Priorite::valeurs())
            ->custom('montant_principal', (float) $data['montant_principal'] >= 0, 'Le montant ne peut pas être négatif.');

        foreach (['date_echeance', 'date_facture', 'penalites_depuis', 'prochaine_relance_at'] as $champ) {
            if (!empty($data[$champ])) {
                $validator->date($champ);
            }
        }
        if ($data['taux_penalite'] !== null && ($data['taux_penalite'] < 0 || $data['taux_penalite'] > 100)) {
            $validator->custom('taux_penalite', false, 'Le taux doit être compris entre 0 et 100 %.');
        }
        if ((int) $data['client_id'] > 0 && !Database::exists('clients', 'id = :id', ['id' => (int) $data['client_id']])) {
            $validator->custom('client_id', false, 'Le client sélectionné est introuvable.');
        }
        if ((int) $data['statut_id'] > 0 && !Database::exists('litige_statuts', "id = :id AND categorie = 'statut'", ['id' => (int) $data['statut_id']])) {
            $validator->custom('statut_id', false, 'Le statut sélectionné est introuvable.');
        }

        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
    }

    public function calculerProchaineRelance(string $base, int $jours): string
    {
        $jours = $jours > 0 ? $jours : 15;
        $ts    = strtotime($base) ?: time();
        return date('Y-m-d', strtotime("+{$jours} days", $ts));
    }

    /** Donnees envoyees aux webhooks. @return array<string,mixed> */
    public function charge(int $id): array
    {
        $l = $this->litiges->findFull($id);
        if (!$l) {
            return ['id' => $id];
        }
        return [
            'id'                => (int) $l['id'],
            'reference'         => $l['reference'],
            'client'            => ['id' => (int) $l['client_id'], 'raison_sociale' => $l['raison_sociale'], 'siren' => $l['client_siren']],
            'objet'             => $l['objet'],
            'statut'            => $l['statut_libelle'],
            'finalite'          => $l['finalite_libelle'],
            'priorite'          => $l['priorite'],
            'montant_principal' => (float) $l['montant_principal'],
            'montant_penalites' => (float) $l['montant_penalites'],
            'montant_recupere'  => (float) $l['montant_recupere'],
            'encours'           => (float) $l['encours'],
            'date_ouverture'    => $l['date_ouverture'],
            'date_echeance'     => $l['date_echeance'],
            'numero_facture'    => $l['numero_facture'],
            'url'               => url('/litiges/' . (int) $l['id']),
        ];
    }

    private function emettreChangementStatut(array $avant, array $nouveauStatut, bool $cloture): void
    {
        $ancien = (new LitigeStatut())->find((int) $avant['statut_id']);
        $charge = $this->charge((int) $avant['id']) + [
            'ancien_statut'   => $ancien['libelle'] ?? null,
            'nouveau_statut'  => $nouveauStatut['libelle'] ?? null,
        ];
        WebhookService::emettre('litige.statut_change', $charge);
        if ($cloture) {
            WebhookService::emettre('litige.cloture', $charge);
        }
    }
}
