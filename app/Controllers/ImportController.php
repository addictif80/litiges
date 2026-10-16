<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Settings;
use App\Services\Import\BlgService;
use App\Services\Import\ImportService;
use App\Services\Import\XlsxReader;

/**
 * Import de clients, factures impayees et reglements (CSV / Excel), avec
 * apercu et simulation avant ecriture. Les exports de l'ERP BLG passent par
 * le meme mecanisme (profil de correspondance enregistrable).
 */
class ImportController extends Controller
{
    private const TAILLE_MAX = 10 * 1024 * 1024;

    public function index(): void
    {
        $this->exigerModules();
        $this->view('import/index', [
            'titre'     => 'Import de données',
            'types'     => ImportService::TYPES,
            'journal'   => Database::fetchAll(
                'SELECT j.*, u.full_name AS user_nom FROM import_journal j LEFT JOIN users u ON u.id = j.created_by ORDER BY j.id DESC LIMIT 20'
            ),
            'rapport'   => $_SESSION['_import_rapport'] ?? null,
            'xlsx'      => XlsxReader::disponible(),
            'blgActif'  => BlgService::actif(),
            'blgDossier' => BlgService::dossier(),
        ]);
        unset($_SESSION['_import_rapport']);
    }

    /** Etape 1 : depot du fichier. */
    public function analyser(): void
    {
        $this->exigerModules();
        $type = (string) $this->post('type', '');
        if (!isset(ImportService::TYPES[$type])) {
            flash('error', "Choisissez le type d'import.");
            $this->redirect('/import');
        }

        $fichier = Request::file('fichier');
        if (!$fichier || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $fichier['tmp_name'])) {
            flash('error', 'Aucun fichier reçu ou transfert interrompu.');
            $this->redirect('/import');
        }
        if ((int) $fichier['size'] > self::TAILLE_MAX) {
            flash('error', 'Fichier trop volumineux (10 Mo maximum).');
            $this->redirect('/import');
        }
        $extension = strtolower(pathinfo((string) $fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            flash('error', 'Formats acceptés : .csv, .txt, .xlsx.');
            $this->redirect('/import');
        }

        $dossier = APP_ROOT . '/storage/imports';
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
        $jeton = bin2hex(random_bytes(16));
        $chemin = $dossier . '/' . $jeton . '.' . $extension;
        if (!move_uploaded_file((string) $fichier['tmp_name'], $chemin)) {
            flash('error', "Impossible d'enregistrer le fichier.");
            $this->redirect('/import');
        }

        $_SESSION['_import'] = ['jeton' => $jeton, 'extension' => $extension, 'nom' => (string) $fichier['name'], 'type' => $type];
        unset($_SESSION['_import_mapping'], $_SESSION['_import_options']);
        $this->redirect('/import/apercu');
    }

    /** Etape 2 : correspondance des colonnes + simulation. */
    public function apercu(): void
    {
        $this->exigerModules();
        [$session, $chemin] = $this->fichierEnCours();

        try {
            $donnees = ImportService::lireFichier($chemin, $session['nom']);
        } catch (\Throwable $e) {
            flash('error', e($e->getMessage()));
            $this->redirect('/import');
        }

        $type = $session['type'];
        $mapping = $_SESSION['_import_mapping'] ?? null;
        if ($mapping === null) {
            $mapping = ImportService::mappingDepuisProfil($type, $donnees['entetes'], BlgService::profil($type));
        }
        $options = $_SESSION['_import_options'] ?? ['doublons' => 'ignorer', 'creer_clients' => false];

        $simulation = (new ImportService())->importer($type, $donnees['lignes'], $mapping, $options, false, Auth::id());

        $this->view('import/apercu', [
            'titre'      => 'Import — correspondance des colonnes',
            'session'    => $session,
            'type'       => $type,
            'entetes'    => $donnees['entetes'],
            'echantillon' => array_slice($donnees['lignes'], 0, 5),
            'nbLignes'   => count($donnees['lignes']),
            'champs'     => ImportService::champs($type),
            'mapping'    => $mapping,
            'options'    => $options,
            'simulation' => $simulation,
        ]);
    }

    /** Memorise le mapping et les options choisis, puis relance la simulation. */
    public function majApercu(): void
    {
        $this->exigerModules();
        [$session] = $this->fichierEnCours();
        $this->memoriser($session['type']);
        $this->redirect('/import/apercu');
    }

    /** Etape 3 : ecriture. */
    public function executer(): void
    {
        $this->exigerModules();
        [$session, $chemin] = $this->fichierEnCours();
        $this->memoriser($session['type']);

        try {
            $donnees = ImportService::lireFichier($chemin, $session['nom']);
        } catch (\Throwable $e) {
            flash('error', e($e->getMessage()));
            $this->redirect('/import');
        }

        $manquants = [];
        foreach (ImportService::champs($session['type']) as $champ => [$libelle, $requis]) {
            if ($requis && ($_SESSION['_import_mapping'][$champ] ?? null) === null) {
                $manquants[] = $libelle;
            }
        }
        if ($manquants !== []) {
            flash('error', 'Associez une colonne aux champs obligatoires : ' . e(implode(', ', $manquants)) . '.');
            $this->redirect('/import/apercu');
        }

        $rapport = (new ImportService())->importer(
            $session['type'],
            $donnees['lignes'],
            $_SESSION['_import_mapping'],
            $_SESSION['_import_options'] ?? [],
            true,
            Auth::id()
        );

        Database::insert('import_journal', [
            'type'       => $session['type'],
            'fichier'    => mb_substr($session['nom'], 0, 255),
            'empreinte'  => sha1_file($chemin) ?: null,
            'lignes'     => $rapport['lignes'],
            'crees'      => $rapport['crees'],
            'mis_a_jour' => $rapport['mis_a_jour'],
            'ignores'    => $rapport['ignores'],
            'erreurs'    => $rapport['erreurs'],
            'details'    => json_encode($rapport['messages'], JSON_UNESCAPED_UNICODE),
            'source'     => 'manuel',
            'created_by' => Auth::id(),
        ]);

        @unlink($chemin);
        unset($_SESSION['_import'], $_SESSION['_import_mapping'], $_SESSION['_import_options']);
        $_SESSION['_import_rapport'] = ['type' => $session['type'], 'nom' => $session['nom']] + $rapport;

        flash('success', sprintf('Import terminé : %d créé(s), %d mis à jour, %d ignoré(s), %d erreur(s).',
            $rapport['crees'], $rapport['mis_a_jour'], $rapport['ignores'], $rapport['erreurs']));
        $this->redirect('/import');
    }

    public function annuler(): void
    {
        $this->exigerModules();
        $session = $_SESSION['_import'] ?? null;
        if (is_array($session)) {
            @unlink(APP_ROOT . '/storage/imports/' . basename($session['jeton']) . '.' . basename($session['extension']));
        }
        unset($_SESSION['_import'], $_SESSION['_import_mapping'], $_SESSION['_import_options']);
        $this->redirect('/import');
    }

    /** Enregistre la correspondance courante comme profil de l'ERP BLG (administrateur). */
    public function enregistrerProfil(): void
    {
        if (!Auth::isAdmin()) {
            $this->forbidden('Réservé aux administrateurs.');
        }
        [$session, $chemin] = $this->fichierEnCours();
        $this->memoriser($session['type']);

        $cle = BlgService::PROFILS[$session['type']] ?? null;
        if ($cle === null) {
            $this->redirect('/import/apercu');
        }

        $donnees = ImportService::lireFichier($chemin, $session['nom']);
        $profil = [];
        foreach ($_SESSION['_import_mapping'] as $champ => $index) {
            if ($index !== null && isset($donnees['entetes'][$index])) {
                $profil[$champ] = $donnees['entetes'][$index];
            }
        }
        Settings::set($cle, json_encode($profil, JSON_UNESCAPED_UNICODE));
        Audit::log('blg_profil_enregistre', 'settings', null, ['type' => $session['type']]);

        flash('success', 'Profil de correspondance enregistré : il sera utilisé pour la synchronisation automatique BLG.');
        $this->redirect('/import/apercu');
    }

    // ------------------------------------------------------------------

    private function exigerModules(): void
    {
        if (!Auth::canWrite() || !Auth::peut('litiges') || !Auth::peut('clients')) {
            $this->forbidden("L'import nécessite les droits d'écriture sur les litiges et les clients.");
        }
    }

    /** @return array{0:array{jeton:string,extension:string,nom:string,type:string},1:string} */
    private function fichierEnCours(): array
    {
        $session = $_SESSION['_import'] ?? null;
        if (!is_array($session) || !preg_match('/^[a-f0-9]{32}$/', (string) ($session['jeton'] ?? ''))) {
            flash('warning', "Aucun fichier en cours d'import.");
            $this->redirect('/import');
        }
        $chemin = APP_ROOT . '/storage/imports/' . $session['jeton'] . '.' . basename((string) $session['extension']);
        if (!is_file($chemin)) {
            unset($_SESSION['_import']);
            flash('warning', 'Le fichier a expiré. Recommencez.');
            $this->redirect('/import');
        }
        return [$session, $chemin];
    }

    private function memoriser(string $type): void
    {
        $mapping = [];
        $envoye  = (array) ($_POST['map'] ?? []);
        foreach (array_keys(ImportService::champs($type)) as $champ) {
            $v = $envoye[$champ] ?? '';
            $mapping[$champ] = ($v !== '' && is_numeric($v)) ? (int) $v : null;
        }
        $_SESSION['_import_mapping'] = $mapping;
        $_SESSION['_import_options'] = [
            'doublons'      => $this->post('doublons') === 'mettre_a_jour' ? 'mettre_a_jour' : 'ignorer',
            'creer_clients' => $this->post('creer_clients') !== null,
            'notifier'      => false,
        ];
    }
}
