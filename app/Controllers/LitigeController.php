<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Agence;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Note;
use App\Models\Document;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\LitigeStatut;
use App\Models\Relance;
use App\Models\User;
use App\Services\ExportService;
use App\Services\CompanyLookupService;

class LitigeController extends Controller
{
    public const TYPES = [
        'impaye'          => 'Impaye',
        'retard_paiement' => 'Retard de paiement',
        'degradation'     => 'Degradation materiel',
        'restriction'     => 'Non-restitution',
        'vol'             => 'Vol / disparition',
        'sinistre'        => 'Sinistre / accident',
        'penalites'       => 'Penalites de retard',
        'carburant'       => 'Carburant',
        'assurance'       => 'Assurance / franchise',
        'autre'           => 'Autre',
    ];

    public function index(): void
    {
        $filtres = $this->filtres();
        $page    = max(1, $this->int('page', 1));
        $perPage = max(10, min(100, $this->int('par_page', 25)));

        $litiges = new Litige();
        $resultat = $litiges->search($filtres, $page, $perPage);

        if ($this->input('format') === 'csv') {
            $this->export($resultat['rows']);
        }

        $this->view('litiges/index', [
            'titre'      => 'Litiges',
            'litiges'    => $resultat['rows'],
            'total'      => $resultat['total'],
            'page'       => $resultat['page'],
            'pages'      => $resultat['pages'],
            'perPage'    => $perPage,
            'filtres'    => $filtres,
            'statuts'    => (new LitigeStatut())->statuts(),
            'finalites'  => (new LitigeStatut())->finalites(),
            'types'      => self::TYPES,
            'agences'    => (new Agence())->listActives(),
            'responsables' => (new User())->listActifs(),
            'clients'    => (new Client())->all('actif = 1', [], 'raison_sociale ASC'),
        ]);
    }

    private function filtres(): array
    {
        return [
            'q'              => (string) $this->input('q', ''),
            'client_id'      => $this->int('client_id'),
            'agence_id'      => $this->int('agence_id'),
            'statut_id'      => $this->int('statut_id'),
            'finalite_id'    => $this->int('finalite_id'),
            'responsable_id' => $this->int('responsable_id'),
            'priorite'       => (string) $this->input('priorite', ''),
            'type_litige'    => (string) $this->input('type_litige', ''),
            'date_from'      => (string) $this->input('date_from', ''),
            'date_to'        => (string) $this->input('date_to', ''),
            'etat'           => (string) $this->input('etat', ''),
            'relance_due'    => $this->int('relance_due'),
            'tri'            => (string) $this->input('tri', 'recent'),
        ];
    }

    public function show(string $id): void
    {
        $litige = (new Litige())->findFull((int) $id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }

        $this->view('litiges/show', [
            'titre'    => 'Litige ' . $litige['reference'],
            'litige'   => $litige,
            'relances' => (new Relance())->forLitige((int) $id),
            'notes'    => (new Note())->forLitige((int) $id),
            'documents' => (new Document())->forLitige((int) $id),
            'historique' => (new LitigeHistorique())->forLitige((int) $id),
            'contacts' => (new Contact())->forClient((int) $litige['client_id']),
            'statuts'  => (new LitigeStatut())->statuts(),
            'finalites' => (new LitigeStatut())->finalites(),
            'types'    => self::TYPES,
            'responsables' => (new User())->listActifs(),
        ]);
    }

    public function create(): void
    {
        $clientId = $this->int('client_id');
        $clients  = (new Client())->all('actif = 1', [], 'raison_sociale ASC');

        $this->view('litiges/form', [
            'titre'   => 'Nouveau litige',
            'litige'  => [
                'client_id'           => $clientId,
                'date_ouverture'      => date('Y-m-d'),
                'statut_id'           => (new LitigeStatut())->defaultStatutId(),
                'responsable_id'      => Auth::id(),
                'priorite'            => 'normale',
                'montant_principal'   => 0,
                'montant_penalites'   => 0,
                'montant_recupere'    => 0,
                'delai_relance_jours' => (int) \App\Core\Settings::get('delai_relance_defaut_jours', 15),
            ],
            'mode'    => 'creation',
            'clients' => $clients,
            'statuts' => (new LitigeStatut())->statuts(),
            'finalites' => (new LitigeStatut())->finalites(),
            'agences' => (new Agence())->listActives(),
            'responsables' => (new User())->listActifs(),
            'types'   => self::TYPES,
        ]);
    }

    public function store(): void
    {
        $data      = $this->collect();
        $validator = $this->validate($data);

        if ($validator->fails()) {
            $this->backWithErrors('/litiges/nouveau', $validator->errors(), $_POST);
        }

        $litiges = new Litige();
        $data['reference']  = $litiges->generateReference();
        $data['created_by'] = (int) Auth::id();

        // Prochaine relance calculee automatiquement.
        $data['prochaine_relance_at'] = $this->calculerProchaineRelance($data['date_ouverture'], (int) $data['delai_relance_jours']);

        // Coherence cloture.
        $statut = (new LitigeStatut())->find((int) $data['statut_id']);
        $data['date_cloture'] = ($statut && (int) $statut['cloturant'] === 1) ? date('Y-m-d') : null;

        $id = $litiges->create($data);

        (new LitigeHistorique())->log($id, (int) Auth::id(), 'creation', null, null, $data['reference'], 'Creation du litige');
        Audit::log('litige_creation', 'litige', $id, ['reference' => $data['reference']]);

        $this->syncClientSolvabilite((int) $data['client_id']);

        flash('success', 'Litige ' . $data['reference'] . ' cree.');
        $this->redirect('/litiges/' . $id);
    }

    public function edit(string $id): void
    {
        $litige = (new Litige())->findFull((int) $id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }

        $this->view('litiges/form', [
            'titre'   => 'Modifier ' . $litige['reference'],
            'litige'  => $litige,
            'mode'    => 'edition',
            'clients' => (new Client())->all('actif = 1', [], 'raison_sociale ASC'),
            'statuts' => (new LitigeStatut())->statuts(),
            'finalites' => (new LitigeStatut())->finalites(),
            'agences' => (new Agence())->listActives(),
            'responsables' => (new User())->listActifs(),
            'types'   => self::TYPES,
        ]);
    }

    public function update(string $id): void
    {
        $litiges = new Litige();
        $avant   = $litiges->find((int) $id);
        if (!$avant) {
            $this->notFound('Litige introuvable.');
        }

        $data      = $this->collect();
        $validator = $this->validate($data);

        if ($validator->fails()) {
            $this->backWithErrors('/litiges/' . $id . '/modifier', $validator->errors(), $_POST);
        }

        $data['prochaine_relance_at'] = $this->calculerProchaineRelance(
            $avant['derniere_relance_at'] ? date('Y-m-d', strtotime((string) $avant['derniere_relance_at'])) : $data['date_ouverture'],
            (int) $data['delai_relance_jours']
        );

        $statut  = (new LitigeStatut())->find((int) $data['statut_id']);
        $cloture = $statut && (int) $statut['cloturant'] === 1;
        $data['date_cloture'] = $cloture
            ? ($avant['date_cloture'] ?: date('Y-m-d'))
            : null;

        $litiges->update((int) $id, $data);
        (new LitigeHistorique())->diff((int) $id, (int) Auth::id(), $avant, array_merge($avant, $data));
        Audit::log('litige_modification', 'litige', (int) $id, ['reference' => $avant['reference']]);

        flash('success', 'Litige mis a jour.');
        $this->redirect('/litiges/' . $id);
    }

    /** Changement rapide de statut depuis la fiche litige. */
    public function changerStatut(string $id): void
    {
        $litiges = new Litige();
        $avant   = $litiges->find((int) $id);
        if (!$avant) {
            $this->notFound('Litige introuvable.');
        }

        $statutId   = $this->int('statut_id');
        $finaliteId = $this->int('finalite_id');
        $commentaire = trim((string) $this->post('commentaire', ''));

        $statut = (new LitigeStatut())->find($statutId);
        if (!$statut) {
            flash('error', 'Statut invalide.');
            $this->back('/litiges/' . $id);
        }

        $cloturant = (int) $statut['cloturant'] === 1;
        $update = [
            'statut_id'   => $statutId,
            'finalite_id' => $finaliteId > 0 ? $finaliteId : null,
            'date_cloture' => $cloturant ? ($avant['date_cloture'] ?: date('Y-m-d')) : null,
        ];

        // Montant recupere ajustable lors d'une cloture.
        if ($cloturant && $this->post('montant_recupere') !== null) {
            $update['montant_recupere'] = $this->post('montant_recupere');
        }

        $litiges->update((int) $id, $update);

        (new LitigeHistorique())->log(
            (int) $id,
            (int) Auth::id(),
            'changement_statut',
            'Statut',
            (string) $avant['statut_id'],
            (string) $statutId,
            $commentaire !== '' ? $commentaire : ('Nouveau statut : ' . $statut['libelle'])
        );
        Audit::log('litige_statut', 'litige', (int) $id, ['statut' => $statut['libelle']]);

        flash('success', 'Statut mis a jour : ' . $statut['libelle'] . '.');
        $this->redirect('/litiges/' . $id);
    }

    public function destroy(string $id): void
    {
        if (!Auth::isAdmin()) {
            $this->forbidden('Seul un administrateur peut supprimer un litige.');
        }

        $litiges = new Litige();
        $litige  = $litiges->find((int) $id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }

        $litiges->delete((int) $id);
        Audit::log('litige_suppression', 'litige', (int) $id, ['reference' => $litige['reference']]);

        flash('success', 'Litige supprime.');
        $this->redirect('/litiges');
    }

    // ------------------------------------------------------------------

    private function collect(): array
    {
        return [
            'client_id'           => $this->int('client_id'),
            'agence_id'           => $this->int('agence_id') ?: null,
            'objet'               => (string) $this->post('objet', ''),
            'description'         => (string) $this->post('description', ''),
            'type_litige'         => (string) $this->post('type_litige', ''),
            'montant_principal'   => $this->post('montant_principal', '0'),
            'montant_penalites'   => $this->post('montant_penalites', '0'),
            'montant_recupere'    => $this->post('montant_recupere', '0'),
            'date_ouverture'      => (string) $this->post('date_ouverture', ''),
            'date_echeance'       => (string) $this->post('date_echeance', '') ?: null,
            'statut_id'           => $this->int('statut_id'),
            'finalite_id'         => $this->int('finalite_id') ?: null,
            'responsable_id'      => $this->int('responsable_id') ?: null,
            'priorite'            => (string) $this->post('priorite', 'normale'),
            'numero_facture'      => (string) $this->post('numero_facture', '') ?: null,
            'date_facture'        => (string) $this->post('date_facture', '') ?: null,
            'numero_contrat'      => (string) $this->post('numero_contrat', '') ?: null,
            'materiel'            => (string) $this->post('materiel', '') ?: null,
            'reference_externe'   => (string) $this->post('reference_externe', '') ?: null,
            'delai_relance_jours' => $this->int('delai_relance_jours', 15),
            'prochaine_relance_at' => (string) $this->post('prochaine_relance_at', '') ?: null,
        ];
    }

    private function validate(array $data): Validator
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
            ->numeric('montant_principal')->numeric('montant_penalites')->numeric('montant_recupere')
            ->in('priorite', ['basse', 'normale', 'haute', 'critique'])
            ->custom('montant_principal', (float) str_replace(',', '.', (string) $data['montant_principal']) >= 0, 'Le montant ne peut pas etre negatif.');

        if (!empty($data['date_echeance'])) {
            $validator->date('date_echeance');
        }
        if (!empty($data['date_facture'])) {
            $validator->date('date_facture');
        }
        if ((int) $data['client_id'] > 0 && !Database::exists('clients', 'id = :id', ['id' => (int) $data['client_id']])) {
            $validator->custom('client_id', false, 'Le client selectionne est introuvable.');
        }

        return $validator;
    }

    private function calculerProchaineRelance(string $base, int $jours): string
    {
        $jours = $jours > 0 ? $jours : 15;
        $ts    = strtotime($base) ?: time();
        return date('Y-m-d', strtotime("+{$jours} days", $ts));
    }

    private function syncClientSolvabilite(int $clientId): void
    {
        // Recalcul du score de solvabilite interne (gratuit) avec l'encours a jour.
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
        } catch (\Throwable) {
            // ignore
        }
    }

    private function export(array $lignes): never
    {
        $colonnes = [
            'reference'         => 'Reference',
            'date_ouverture'    => "Date d'ouverture",
            'raison_sociale'    => 'Client',
            'client_siren'      => 'SIREN',
            'objet'             => 'Objet',
            'type_litige'       => 'Type',
            'statut_libelle'    => 'Statut',
            'finalite_libelle'  => 'Finalite',
            'montant_principal' => 'Montant principal',
            'montant_penalites' => 'Penalites',
            'montant_recupere'  => 'Montant recupere',
            'encours'           => 'Encours',
            'date_echeance'     => 'Echeance',
            'date_cloture'      => 'Date cloture',
            'responsable_nom'   => 'Responsable',
            'priorite'          => 'Priorite',
            'numero_facture'    => 'N. facture',
            'agence_nom'        => 'Agence',
        ];

        Audit::log('export_csv', 'litige', null, ['nb' => count($lignes)]);
        (new ExportService())->csv('litiges_' . date('Ymd_His'), $colonnes, $lignes);
    }
}
