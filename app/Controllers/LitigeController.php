<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Enums\Priorite;
use App\Enums\TypeLitige;
use App\Exceptions\DoublonException;
use App\Exceptions\ValidationException;
use App\Exceptions\WorkflowException;
use App\Models\Agence;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Courrier;
use App\Models\Document;
use App\Models\FiltreEnregistre;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\LitigeStatut;
use App\Models\ModeleCourrier;
use App\Models\Note;
use App\Models\Paiement;
use App\Models\Relance;
use App\Models\Tache;
use App\Models\User;
use App\Services\BodaccWatchService;
use App\Services\EcheanceService;
use App\Services\ExportService;
use App\Services\LitigeService;
use App\Services\PenaltyService;
use App\Services\WorkflowService;
use App\Services\XlsxWriter;

class LitigeController extends Controller
{
    /** Colonnes des exports : cle => [libelle, type]. */
    private const COLONNES_EXPORT = [
        'reference'         => ['Référence', 'texte'],
        'date_ouverture'    => ["Date d'ouverture", 'date'],
        'raison_sociale'    => ['Client', 'texte'],
        'client_siren'      => ['SIREN', 'texte'],
        'objet'             => ['Objet', 'texte'],
        'type_libelle'      => ['Type', 'texte'],
        'statut_libelle'    => ['Statut', 'texte'],
        'finalite_libelle'  => ['Finalité', 'texte'],
        'montant_principal' => ['Montant principal', 'nombre'],
        'montant_penalites' => ['Pénalités', 'nombre'],
        'montant_recupere'  => ['Montant récupéré', 'nombre'],
        'encours'           => ['Encours', 'nombre'],
        'date_echeance'     => ['Échéance', 'date'],
        'date_cloture'      => ['Date clôture', 'date'],
        'responsable_nom'   => ['Responsable', 'texte'],
        'priorite'          => ['Priorité', 'texte'],
        'numero_facture'    => ['N° facture', 'texte'],
        'agence_nom'        => ['Agence', 'texte'],
    ];

    public function index(): void
    {
        $this->appliquerFiltreEnregistre();
        $filtres = $this->filtres();
        $page    = max(1, $this->int('page', 1));
        $perPage = max(10, min(100, $this->int('par_page', 25)));

        $litiges = new Litige();

        $format = (string) $this->input('format', '');
        if (in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            $this->export($litiges->pourExport($filtres), $format);
        }

        $resultat = $litiges->search($filtres, $page, $perPage);
        $statutModel = new LitigeStatut();

        $this->view('litiges/index', [
            'titre'        => 'Litiges',
            'litiges'      => $resultat['rows'],
            'total'        => $resultat['total'],
            'page'         => $resultat['page'],
            'pages'        => $resultat['pages'],
            'perPage'      => $perPage,
            'filtres'      => $filtres,
            'statuts'      => $statutModel->statuts(),
            'finalites'    => $statutModel->finalites(),
            'types'        => TypeLitige::options(),
            'priorites'    => Priorite::options(),
            'agences'      => (new Agence())->listActives(),
            'responsables' => (new User())->listActifs(),
            'client'       => $filtres['client_id'] ? (new Client())->find((int) $filtres['client_id']) : null,
            'criteres'     => Litige::criteresDeTri(),
            'filtresSauvegardes' => (new FiltreEnregistre())->visiblesPour((int) Auth::id(), 'litiges'),
            'xlsxDisponible' => XlsxWriter::disponible(),
        ]);
    }

    private function filtres(): array
    {
        // Tri multi-criteres : "tri" (principal) + "tri2" (secondaire) => "priorite,echeance".
        $tri  = (string) $this->input('tri', 'recent');
        $tri2 = (string) $this->input('tri2', '');
        if ($tri2 !== '' && $tri2 !== $tri && !str_contains($tri, ',')) {
            $tri .= ',' . $tri2;
        }

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
            'promesse_depassee' => $this->int('promesse_depassee'),
            'tri'            => $tri,
        ];
    }

    /** ?filtre=12 : charge les criteres d'un filtre enregistre (si aucun autre critere n'est fourni). */
    private function appliquerFiltreEnregistre(): void
    {
        $id = $this->int('filtre');
        if ($id <= 0) {
            return;
        }
        $filtre = (new FiltreEnregistre())->find($id);
        if (!$filtre || ((int) $filtre['user_id'] !== (int) Auth::id() && (int) $filtre['partage'] !== 1)) {
            return;
        }
        $params = json_decode((string) $filtre['parametres'], true);
        if (!is_array($params)) {
            return;
        }
        foreach ($params as $cle => $valeur) {
            if (is_scalar($valeur) && !isset($_GET[$cle])) {
                $_GET[$cle] = (string) $valeur;
            }
        }
    }

    public function show(string $id): void
    {
        $litiges = new Litige();
        $litige  = $litiges->findFull((int) $id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }

        $litigeId = (int) $id;
        $workflow = new WorkflowService();
        $statutModel = new LitigeStatut();

        $prerequis = [];
        foreach ($statutModel->statuts() as $s) {
            $regles = $workflow->prerequis((int) $s['id']);
            if ($regles !== []) {
                $prerequis[(int) $s['id']] = array_map(static fn (string $r): string => WorkflowService::REGLES[$r], $regles);
            }
        }

        $client = (new Client())->find((int) $litige['client_id']);

        $this->view('litiges/show', [
            'titre'      => 'Litige ' . $litige['reference'],
            'litige'     => $litige,
            'relances'   => (new Relance())->forLitige($litigeId),
            'notes'      => (new Note())->forLitige($litigeId),
            'documents'  => (new Document())->forLitige($litigeId),
            'historique' => (new LitigeHistorique())->forLitige($litigeId),
            'contacts'   => (new Contact())->forClient((int) $litige['client_id']),
            'statuts'    => $statutModel->statuts(),
            'finalites'  => $statutModel->finalites(),
            'types'      => TypeLitige::options(),
            'responsables' => (new User())->listActifs(),
            'paiements'  => (new Paiement())->forLitige($litigeId),
            'echeances'  => (new EcheanceService())->avecStatuts($litigeId),
            'courriers'  => (new Courrier())->forLitige($litigeId),
            'taches'     => (new Tache())->forLitige($litigeId),
            'modeles'    => (new ModeleCourrier())->actifs(),
            'penalites'  => (new PenaltyService())->calculer($litige),
            'transitions' => $workflow->transitionsDepuis((int) $litige['statut_id']),
            'prerequis'  => $prerequis,
            'client'     => $client,
            'creance'    => $client ? BodaccWatchService::infoCreance($client, $litige) : null,
        ]);
    }

    public function create(): void
    {
        $clientId = $this->int('client_id');
        $statutModel = new LitigeStatut();

        $this->view('litiges/form', [
            'titre'   => 'Nouveau litige',
            'litige'  => [
                'client_id'           => $clientId,
                'date_ouverture'      => date('Y-m-d'),
                'statut_id'           => $statutModel->defaultStatutId(),
                'responsable_id'      => Auth::id(),
                'priorite'            => 'normale',
                'montant_principal'   => 0,
                'montant_penalites'   => 0,
                'montant_recupere'    => 0,
                'delai_relance_jours' => (int) \App\Core\Settings::get('delai_relance_defaut_jours', 15),
            ],
            'mode'    => 'creation',
            // Client pre-selectionne : parametre d'URL, ou saisie precedente apres une erreur de validation.
            'clientPre' => ($clientId ?: (int) ($_SESSION['_old']['client_id'] ?? 0))
                ? (new Client())->find($clientId ?: (int) $_SESSION['_old']['client_id'])
                : null,
            'statuts' => $statutModel->statuts(),
            'finalites' => $statutModel->finalites(),
            'agences' => (new Agence())->listActives(),
            'responsables' => (new User())->listActifs(),
            'types'   => TypeLitige::options(),
            'priorites' => Priorite::options(),
            'doublons' => $_SESSION['_doublons'] ?? [],
        ]);
        unset($_SESSION['_doublons']);
    }

    public function store(): void
    {
        $service = new LitigeService();
        try {
            $id = $service->creer($this->donneesFormulaire(), Auth::id(), $this->post('confirmer_doublon') !== null);
        } catch (ValidationException $e) {
            $this->backWithErrors('/litiges/nouveau', $e->erreurs, $_POST);
        } catch (DoublonException $e) {
            $_SESSION['_doublons'] = $e->doublons;
            $this->backWithErrors('/litiges/nouveau', ['doublon' => $e->getMessage()], $_POST);
        }

        $litige = (new Litige())->find($id);
        flash('success', 'Litige ' . e($litige['reference'] ?? '') . ' créé.');
        $this->redirect('/litiges/' . $id);
    }

    public function edit(string $id): void
    {
        $litige = (new Litige())->findFull((int) $id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }
        $statutModel = new LitigeStatut();

        $this->view('litiges/form', [
            'titre'   => 'Modifier ' . $litige['reference'],
            'litige'  => $litige,
            'mode'    => 'edition',
            'clientPre' => (new Client())->find((int) $litige['client_id']),
            'statuts' => $statutModel->statuts(),
            'finalites' => $statutModel->finalites(),
            'agences' => (new Agence())->listActives(),
            'responsables' => (new User())->listActifs(),
            'types'   => TypeLitige::options(),
            'priorites' => Priorite::options(),
            'doublons' => [],
        ]);
    }

    public function update(string $id): void
    {
        $service = new LitigeService();
        try {
            $service->modifier((int) $id, $this->donneesFormulaire(), Auth::id(), $this->peutForcerWorkflow());
        } catch (ValidationException $e) {
            if (isset($e->erreurs['litige'])) {
                $this->notFound('Litige introuvable.');
            }
            $this->backWithErrors('/litiges/' . $id . '/modifier', $e->erreurs, $_POST);
        } catch (WorkflowException $e) {
            $this->backWithErrors('/litiges/' . $id . '/modifier', ['statut_id' => implode(' ', $e->erreurs)], $_POST);
        }

        flash('success', 'Litige mis à jour.');
        $this->redirect('/litiges/' . $id);
    }

    /** Changement rapide de statut depuis la fiche litige. */
    public function changerStatut(string $id): void
    {
        $service = new LitigeService();
        $statutId = $this->int('statut_id');
        try {
            $service->changerStatut(
                (int) $id,
                $statutId,
                $this->int('finalite_id') ?: null,
                trim((string) $this->post('commentaire', '')),
                Auth::id(),
                $this->peutForcerWorkflow()
            );
        } catch (WorkflowException $e) {
            flash('error', e(implode(' ', $e->erreurs)) . (Auth::isAdmin() ? ' (Un administrateur peut forcer le changement.)' : ''));
            $this->back('/litiges/' . $id);
        } catch (ValidationException $e) {
            flash('error', e($e->getMessage()));
            $this->back('/litiges/' . $id);
        }

        $statut = (new LitigeStatut())->find($statutId);
        flash('success', 'Statut mis à jour : ' . e($statut['libelle'] ?? '') . '.');
        $this->redirect('/litiges/' . $id);
    }

    /** Actions groupees depuis la liste (responsable, priorite, statut). */
    public function actionGroupee(): void
    {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        if ($ids === []) {
            flash('warning', 'Aucun litige sélectionné.');
            $this->back('/litiges');
        }

        $changement = [];
        $action = (string) $this->post('action', '');
        if ($action === 'responsable') {
            $changement['responsable_id'] = $this->int('responsable_id') ?: null;
        } elseif ($action === 'priorite') {
            $changement['priorite'] = (string) $this->post('priorite', '');
        } elseif ($action === 'statut') {
            $changement['statut_id'] = $this->int('statut_id');
        } else {
            flash('error', 'Action inconnue.');
            $this->back('/litiges');
        }

        $resultat = (new LitigeService())->actionGroupee($ids, $changement, Auth::id(), $this->peutForcerWorkflow());

        flash('success', $resultat['modifies'] . ' litige(s) mis à jour.');
        if ($resultat['refuses'] !== []) {
            flash('warning', 'Non modifiés : ' . e(implode(' | ', $resultat['refuses'])));
        }
        $this->back('/litiges');
    }

    public function destroy(string $id): void
    {
        if (!Auth::isAdmin()) {
            $this->forbidden('Seul un administrateur peut supprimer un litige.');
        }

        try {
            (new LitigeService())->supprimer((int) $id);
        } catch (ValidationException) {
            $this->notFound('Litige introuvable.');
        }

        flash('success', 'Litige supprimé.');
        $this->redirect('/litiges');
    }

    // ------------------------------------------------------------------

    /** Seul un administrateur peut passer outre le workflow (case "forcer"). */
    private function peutForcerWorkflow(): bool
    {
        return Auth::isAdmin() && $this->post('forcer_workflow') !== null;
    }

    private function donneesFormulaire(): array
    {
        return [
            'client_id'           => $this->post('client_id'),
            'agence_id'           => $this->post('agence_id'),
            'objet'               => $this->post('objet', ''),
            'description'         => $this->post('description', ''),
            'type_litige'         => $this->post('type_litige', ''),
            'montant_principal'   => $this->post('montant_principal', '0'),
            'montant_penalites'   => $this->post('montant_penalites', '0'),
            'date_ouverture'      => $this->post('date_ouverture', ''),
            'date_echeance'       => $this->post('date_echeance', ''),
            'statut_id'           => $this->post('statut_id'),
            'finalite_id'         => $this->post('finalite_id'),
            'responsable_id'      => $this->post('responsable_id'),
            'priorite'            => $this->post('priorite', 'normale'),
            'numero_facture'      => $this->post('numero_facture', ''),
            'date_facture'        => $this->post('date_facture', ''),
            'numero_contrat'      => $this->post('numero_contrat', ''),
            'materiel'            => $this->post('materiel', ''),
            'reference_externe'   => $this->post('reference_externe', ''),
            'delai_relance_jours' => $this->post('delai_relance_jours', 15),
            'prochaine_relance_at' => $this->post('prochaine_relance_at', ''),
            'penalites_auto'      => $this->post('penalites_auto') !== null,
            'taux_penalite'       => $this->post('taux_penalite', ''),
            'indemnite_forfaitaire' => $this->post('indemnite_forfaitaire') !== null,
            'penalites_depuis'    => $this->post('penalites_depuis', ''),
        ];
    }

    private function export(array $lignes, string $format): never
    {
        $types = TypeLitige::options();
        foreach ($lignes as &$l) {
            $l['type_libelle'] = $types[$l['type_litige'] ?? ''] ?? ($l['type_litige'] ?? '');
        }
        unset($l);

        $colonnes = array_map(static fn (array $c): string => $c[0], self::COLONNES_EXPORT);
        $typesCol = array_map(static fn (array $c): string => $c[1], self::COLONNES_EXPORT);
        $nom      = 'litiges_' . date('Ymd_His');

        Audit::log('export_' . $format, 'litige', null, ['nb' => count($lignes)]);

        if ($format === 'xlsx') {
            if (!XlsxWriter::disponible()) {
                flash('error', "L'extension PHP zip est requise pour l'export Excel.");
                $this->back('/litiges');
            }
            (new ExportService())->xlsx($nom, $colonnes, $lignes, $typesCol, 'Litiges');
        }

        if ($format === 'pdf') {
            $html = \App\Core\View::render('litiges/rapport', [
                'titre' => 'Liste des litiges',
                'lignes' => $lignes,
                'colonnes' => $colonnes,
                'nb' => count($lignes),
            ], null);
            if (ExportService::pdfDisponible()) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $nom . '.pdf"');
                echo ExportService::htmlVersPdf($html, 'A4', 'landscape');
                exit;
            }
            // Sans moteur PDF : page imprimable (Ctrl+P > Enregistrer au format PDF).
            echo $html;
            exit;
        }

        (new ExportService())->csv($nom, $colonnes, $lignes);
    }
}
