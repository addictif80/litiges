<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Litige;
use App\Services\Api\OneMinAiService;
use App\Services\CompanyLookupService;
use App\Services\NotificationService;

class ClientController extends Controller
{
    public function index(): void
    {
        $q       = (string) $this->input('q', '');
        $page    = max(1, $this->int('page', 1));
        $clients = new Client();
        $resultat = $clients->search($q, $page, 25);

        $this->view('clients/index', [
            'titre'   => 'Clients',
            'clients' => $resultat['rows'],
            'total'   => $resultat['total'],
            'page'    => $resultat['page'],
            'pages'   => (int) max(1, (int) ceil($resultat['total'] / 25)),
            'q'       => $q,
        ]);
    }

    public function show(string $id): void
    {
        $client = (new Client())->find((int) $id);
        if (!$client) {
            $this->notFound('Client introuvable.');
        }

        $this->view('clients/show', [
            'titre'      => $client['raison_sociale'],
            'client'     => $client,
            'contacts'   => (new Contact())->forClient((int) $id),
            'litiges'    => (new Client())->litiges((int) $id),
            'documents'  => (new Document())->forClient((int) $id),
            'clientNotes' => (new ClientNote())->forClient((int) $id),
            'iaAnalyse'  => $this->decoderAnalyse($client['ia_analyse'] ?? null),
        ]);
    }

    public function create(): void
    {
        // Pre-remplissage possible depuis l'inspection d'entreprise (?siren=...&raison_sociale=...).
        $client = ['pays' => 'France', 'actif' => 1];
        $champs = [
            'raison_sociale', 'forme_juridique', 'siren', 'siret', 'tva_intra', 'ape_naf',
            'libelle_ape', 'adresse', 'complement_adresse', 'code_postal', 'ville', 'effectif',
            'date_creation', 'capital', 'chiffre_affaires', 'resultat_net', 'statut_entreprise',
        ];
        foreach ($champs as $champ) {
            $valeur = $this->input($champ, null);
            if ($valeur !== null && $valeur !== '') {
                $client[$champ] = (string) $valeur;
            }
        }

        $this->view('clients/form', [
            'titre'  => 'Nouveau client',
            'client' => $client,
            'mode'   => 'creation',
        ]);
    }

    public function store(): void
    {
        $data      = $this->collect();
        $validator = $this->validate($data);
        $clients   = new Client();

        if ($data['siren'] !== null && $clients->findBySiren($data['siren'])) {
            $validator->custom('siren', false, 'Un client avec ce SIREN existe deja.');
        }

        if ($validator->fails()) {
            $this->backWithErrors('/clients/nouveau', $validator->errors(), $_POST);
        }

        $data['created_by'] = (int) Auth::id();
        $id = $clients->create($data);

        Audit::log('client_creation', 'client', $id, ['raison_sociale' => $data['raison_sociale']]);

        $client = $clients->find((int) $id);
        if ($client) {
            (new NotificationService())->clientCree($client, (int) Auth::id());
        }

        flash('success', 'Client cree.');
        $this->redirect('/clients/' . $id);
    }

    public function edit(string $id): void
    {
        $client = (new Client())->find((int) $id);
        if (!$client) {
            $this->notFound('Client introuvable.');
        }

        $this->view('clients/form', [
            'titre'  => 'Modifier ' . $client['raison_sociale'],
            'client' => $client,
            'mode'   => 'edition',
        ]);
    }

    public function update(string $id): void
    {
        $clients = new Client();
        $avant   = $clients->find((int) $id);
        if (!$avant) {
            $this->notFound('Client introuvable.');
        }

        $data      = $this->collect();
        $validator = $this->validate($data);

        if ($data['siren'] !== null) {
            $existant = $clients->findBySiren($data['siren']);
            if ($existant && (int) $existant['id'] !== (int) $id) {
                $validator->custom('siren', false, 'Un autre client utilise ce SIREN.');
            }
        }

        if ($validator->fails()) {
            $this->backWithErrors('/clients/' . $id . '/modifier', $validator->errors(), $_POST);
        }

        $clients->update((int) $id, $data);
        Audit::log('client_modification', 'client', (int) $id);

        flash('success', 'Client mis a jour.');
        $this->redirect('/clients/' . $id);
    }

    public function destroy(string $id): void
    {
        if (!Auth::isAdmin()) {
            $this->forbidden('Seul un administrateur peut supprimer un client.');
        }

        $clients = new Client();
        $client  = $clients->find((int) $id);
        if (!$client) {
            $this->notFound('Client introuvable.');
        }

        $nbLitiges = (new Litige())->count('client_id = :c', ['c' => (int) $id]);
        if ($nbLitiges > 0) {
            flash('error', "Impossible de supprimer : {$nbLitiges} litige(s) sont rattaches a ce client.");
            $this->redirect('/clients/' . $id);
        }

        $clients->delete((int) $id);
        Audit::log('client_suppression', 'client', (int) $id, ['raison_sociale' => $client['raison_sociale']]);

        flash('success', 'Client supprime.');
        $this->redirect('/clients');
    }

    /** Recharge les donnees legales/depuis l'API et met a jour la fiche. */
    public function rafraichir(string $id): void
    {
        $clients = new Client();
        $client  = $clients->find((int) $id);
        if (!$client) {
            $this->notFound('Client introuvable.');
        }
        if (empty($client['siren'])) {
            flash('error', "Aucun SIREN renseigne : impossible d'interroger l'API.");
            $this->redirect('/clients/' . $id);
        }

        $service = new CompanyLookupService();
        $encours = $clients->encours((int) $id);
        $donnees = $service->lookup((string) $client['siren'], $encours);

        if ($donnees === null) {
            flash('error', "Aucune donnee trouvee pour le SIREN " . $client['siren'] . '.');
            $this->redirect('/clients/' . $id);
        }

        $champs = $service->filtrerChamps($donnees);
        unset($champs['raison_sociale']); // On conserve la denomination interne.
        $champs['data_synced_at'] = date('Y-m-d H:i:s');

        $clients->update((int) $id, $champs);
        Audit::log('client_sync_api', 'client', (int) $id, ['source' => $donnees['_source'] ?? null]);

        flash('success', 'Donnees mises a jour depuis ' . ($donnees['_source'] ?? 'l\'API') . '.');
        $this->redirect('/clients/' . $id);
    }

    /** Analyse de confiance via l'IA 1min.ai. */
    public function analyserIa(string $id): void
    {
        $clients = new Client();
        $client  = $clients->find((int) $id);
        if (!$client) {
            $this->notFound('Client introuvable.');
        }

        $service = new OneMinAiService();
        if (!$service->isEnabled()) {
            flash('error', "L'analyse IA n'est pas configuree (Parametres > Intelligence artificielle).");
            $this->redirect('/clients/' . $id);
        }

        $encours = $clients->encours((int) $id);
        $resultat = $service->analyserConfiance($client, $encours);

        if (($resultat['decision'] ?? '') === 'erreur') {
            flash('error', "L'analyse IA a echoue : " . ($resultat['raison'] ?? 'erreur inconnue'));
            $this->redirect('/clients/' . $id);
        }

        $analyse = [
            'confiance'       => (bool) ($resultat['confiance'] ?? false),
            'score'           => (int) ($resultat['score'] ?? 0),
            'decision'        => (string) ($resultat['decision'] ?? 'faire_attention'),
            'raison'          => (string) ($resultat['raison'] ?? ''),
            'risques'         => $resultat['risques'] ?? [],
            'recommandations' => $resultat['recommandations'] ?? [],
            'analyse'         => (string) ($resultat['analyse'] ?? ''),
            'encours'         => $encours,
            'date'            => date('Y-m-d H:i:s'),
        ];

        $clients->update((int) $id, [
            'ia_score'     => (int) $analyse['score'],
            'ia_decision'  => mb_substr($analyse['decision'], 0, 30),
            'ia_analyse'   => json_encode($analyse, JSON_UNESCAPED_UNICODE),
            'ia_synced_at' => date('Y-m-d H:i:s'),
        ]);

        Audit::log('client_analyse_ia', 'client', (int) $id, ['decision' => $analyse['decision']]);

        flash('success', "Analyse IA enregistree (decision : " . $analyse['decision'] . ").");
        $this->redirect('/clients/' . $id . '#ia');
    }

    /** Ajoute une note sur la fiche client (visible en lecture seule par les agences). */
    public function storeNote(string $id): void
    {
        $client = (new Client())->find((int) $id);
        if (!$client) {
            $this->notFound('Client introuvable.');
        }

        $contenu = trim((string) $this->post('contenu', ''));
        if ($contenu === '') {
            flash('error', 'La note ne peut pas etre vide.');
            $this->redirect('/clients/' . $id . '#notes-client');
        }

        $noteId = (new ClientNote())->create([
            'client_id' => (int) $id,
            'user_id'   => (int) Auth::id(),
            'contenu'   => mb_substr($contenu, 0, 5000),
        ]);

        Audit::log('client_note_ajout', 'client', (int) $id, ['note_id' => $noteId]);

        flash('success', 'Note ajoutee.');
        $this->redirect('/clients/' . $id . '#notes-client');
    }

    public function destroyNote(string $id, string $noteId): void
    {
        $notes = new ClientNote();
        if (!$notes->belongsTo((int) $noteId, (int) $id)) {
            $this->notFound('Note introuvable.');
        }

        $note = $notes->find((int) $noteId);
        if (!Auth::isAdmin() && (int) $note['user_id'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres notes.');
        }

        $notes->delete((int) $noteId);
        Audit::log('client_note_suppression', 'client', (int) $id, ['note_id' => (int) $noteId]);

        flash('success', 'Note supprimee.');
        $this->redirect('/clients/' . $id . '#notes-client');
    }

    /** @return array<string,mixed>|null */
    private function decoderAnalyse(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    // ------------------------------------------------------------------

    private function collect(): array
    {
        return [
            'raison_sociale'     => (string) $this->post('raison_sociale', ''),
            'forme_juridique'    => (string) $this->post('forme_juridique', '') ?: null,
            'siren'              => preg_replace('/\s+/', '', (string) $this->post('siren', '')) ?: null,
            'siret'              => preg_replace('/\s+/', '', (string) $this->post('siret', '')) ?: null,
            'tva_intra'          => (string) $this->post('tva_intra', '') ?: null,
            'ape_naf'            => (string) $this->post('ape_naf', '') ?: null,
            'libelle_ape'        => (string) $this->post('libelle_ape', '') ?: null,
            'adresse'            => (string) $this->post('adresse', '') ?: null,
            'complement_adresse' => (string) $this->post('complement_adresse', '') ?: null,
            'code_postal'        => (string) $this->post('code_postal', '') ?: null,
            'ville'              => (string) $this->post('ville', '') ?: null,
            'pays'               => (string) $this->post('pays', 'France') ?: 'France',
            'effectif'           => (string) $this->post('effectif', '') ?: null,
            'date_creation'      => (string) $this->post('date_creation', '') ?: null,
            'capital'            => (string) $this->post('capital', '') ?: null,
            'chiffre_affaires'   => (string) $this->post('chiffre_affaires', '') ?: null,
            'resultat_net'       => (string) $this->post('resultat_net', '') ?: null,
            'statut_entreprise'  => (string) $this->post('statut_entreprise', '') ?: null,
            'procedure_collective' => (string) $this->post('procedure_collective', '') ?: null,
            'score_solvabilite'  => (string) $this->post('score_solvabilite', '') ?: null,
            'notes'              => (string) $this->post('notes', '') ?: null,
            'actif'              => $this->post('actif') !== null ? 1 : 0,
        ];
    }

    private function validate(array $data): Validator
    {
        $validator = new Validator($data, ['raison_sociale' => 'La raison sociale']);
        $validator
            ->required('raison_sociale')->length('raison_sociale', 2, 190)
            ->siren('siren')->siret('siret');

        if (!empty($data['date_creation'])) {
            $validator->date('date_creation');
        }
        return $validator;
    }
}
