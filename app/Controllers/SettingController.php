<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\LitigeStatut;
use App\Services\Api\OneMinAiService;
use App\Services\WorkflowService;

class SettingController extends Controller
{
    public function statuts(): void
    {
        $model = new LitigeStatut();
        $workflow = new WorkflowService();
        $statuts = $model->statuts(false);

        $transitions = [];
        $regles = [];
        foreach ($statuts as $s) {
            $transitions[(int) $s['id']] = $workflow->transitionsDepuis((int) $s['id']) ?? [];
            $regles[(int) $s['id']] = $workflow->prerequis((int) $s['id']);
        }

        $this->view('settings/statuts', [
            'titre'    => 'Statuts & finalités',
            'statuts'  => $statuts,
            'finalites' => $model->finalites(false),
            'transitions' => $transitions,
            'reglesStatut' => $regles,
            'reglesDisponibles' => WorkflowService::REGLES,
        ]);
    }

    /** Workflow d'un statut : statuts de destination autorises + pre-requis. */
    public function updateWorkflow(string $id): void
    {
        $model  = new LitigeStatut();
        $statut = $model->find((int) $id);
        if (!$statut || $statut['categorie'] !== 'statut') {
            $this->notFound('Statut introuvable.');
        }

        $vers = array_map('intval', (array) ($_POST['transitions'] ?? []));
        $regles = array_values(array_intersect(array_keys(WorkflowService::REGLES), (array) ($_POST['regles'] ?? [])));
        (new WorkflowService())->configurer((int) $id, $vers, $regles);

        Audit::log('workflow_modification', 'statut', (int) $id, ['transitions' => count($vers), 'regles' => $regles]);
        flash('success', 'Workflow du statut « ' . e($statut['libelle']) . ' » enregistré.');
        $this->redirect('/parametres/statuts#statut-' . (int) $id);
    }

    public function storeStatut(): void
    {
        $model     = new LitigeStatut();
        $categorie = $this->post('categorie') === 'finalite' ? 'finalite' : 'statut';
        $data      = $this->collectStatut($categorie);
        $validator = $this->validateStatut($data, null);

        if ($validator->fails()) {
            $this->backWithErrors('/parametres/statuts', $validator->errors(), $_POST);
        }

        $data['ordre'] = $data['ordre'] ?: $model->nextOrdre($categorie);
        $id = $model->create($data);

        Audit::log('statut_creation', 'statut', $id, ['libelle' => $data['libelle']]);
        flash('success', 'Élément ajouté.');
        $this->redirect('/parametres/statuts');
    }

    public function updateStatut(string $id): void
    {
        $model  = new LitigeStatut();
        $statut = $model->find((int) $id);
        if (!$statut) {
            $this->notFound('Élément introuvable.');
        }

        $data      = $this->collectStatut((string) $statut['categorie']);
        $validator = $this->validateStatut($data, (int) $id);
        if ($validator->fails()) {
            $this->backWithErrors('/parametres/statuts', $validator->errors(), $_POST);
        }

        // Un statut systeme conserve son code et sa nature cloturante.
        if ((int) $statut['systeme'] === 1) {
            $data['code'] = $statut['code'];
        }

        $model->update((int) $id, $data);
        Audit::log('statut_modification', 'statut', (int) $id);
        flash('success', 'Élément mis à jour.');
        $this->redirect('/parametres/statuts');
    }

    public function destroyStatut(string $id): void
    {
        $model  = new LitigeStatut();
        $statut = $model->find((int) $id);
        if (!$statut) {
            $this->notFound('Élément introuvable.');
        }
        if ((int) $statut['systeme'] === 1) {
            flash('error', 'Cet élément est système et ne peut pas être supprimé. Désactivez-le plutôt.');
            $this->redirect('/parametres/statuts');
        }

        $utilise = (int) Database::value(
            'SELECT COUNT(*) FROM litiges WHERE statut_id = :statut_id OR finalite_id = :finalite_id',
            ['statut_id' => (int) $id, 'finalite_id' => (int) $id],
            0
        );
        if ($utilise > 0) {
            flash('error', "Impossible de supprimer : cet élément est utilisé par {$utilise} litige(s). Désactivez-le plutôt.");
            $this->redirect('/parametres/statuts');
        }

        $model->delete((int) $id);
        Audit::log('statut_suppression', 'statut', (int) $id);
        flash('success', 'Élément supprimé.');
        $this->redirect('/parametres/statuts');
    }

    public function parametres(): void
    {
        $this->view('settings/parametres', [
            'titre'    => 'Paramètres',
            'settings' => Settings::all(),
        ]);
    }

    public function updateParametres(): void
    {
        $cles = [
            'app_nom', 'societe_nom', 'delai_relance_defaut_jours', 'seuil_alerte_retard_jours',
            'company_api_provider', 'recuperation_auto_active', 'verrouillage_tentatives',
            'verrouillage_minutes', 'max_upload_mo',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_secure', 'smtp_from', 'smtp_from_nom',
            'oneminai_enabled', 'oneminai_model',
            'perm_gestionnaire_litiges', 'perm_gestionnaire_clients', 'perm_gestionnaire_relances', 'perm_gestionnaire_chat',
            'perm_consultant_litiges',   'perm_consultant_clients',   'perm_consultant_relances',   'perm_consultant_chat',
            'perm_lecteur_litiges',      'perm_lecteur_clients',      'perm_lecteur_relances',      'perm_lecteur_chat',
        ];

        $pairs = [];
        foreach ($cles as $cle) {
            $valeur = $this->post($cle);
            $pairs[$cle] = $valeur === null ? '' : (string) $valeur;
        }
        $pairs['email_notifications'] = $this->post('email_notifications') !== null ? '1' : '0';
        $pairs['bodacc_actif']        = $this->post('bodacc_actif') !== null ? '1' : '0';
        $pairs['score_interne_actif'] = $this->post('score_interne_actif') !== null ? '1' : '0';
        $pairs['oneminai_enabled']    = $this->post('oneminai_enabled') !== null ? '1' : '0';

        // --- Societe, recouvrement, securite, integrations (nouveaux parametres) ---
        foreach (['societe_adresse', 'societe_telephone', 'societe_email', 'societe_signataire', 'societe_iban', 'blg_dossier'] as $cle) {
            $pairs[$cle] = mb_substr(trim((string) $this->post($cle, '')), 0, 500);
        }
        if (str_contains($pairs['blg_dossier'], '..')) {
            $pairs['blg_dossier'] = '';
        }

        $nombre = function (string $cle, float $min, float $max, float $defaut) {
            $v = str_replace(',', '.', trim((string) $this->post($cle, '')));
            return (string) (is_numeric($v) ? max($min, min($max, (float) $v)) : $defaut);
        };
        $pairs['courrier_delai_jours']          = $nombre('courrier_delai_jours', 1, 90, 8);
        $pairs['penalite_taux_annuel']          = $nombre('penalite_taux_annuel', 0, 100, 12);
        $pairs['penalite_indemnite_forfaitaire'] = $nombre('penalite_indemnite_forfaitaire', 0, 10000, 40);
        $pairs['creance_delai_mois']            = $nombre('creance_delai_mois', 1, 12, 2);
        $pairs['bodacc_surveillance_lot']       = $nombre('bodacc_surveillance_lot', 1, 500, 40);
        $pairs['rapport_hebdo_jour']            = $nombre('rapport_hebdo_jour', 1, 7, 1);
        $pairs['partage_expiration_defaut_jours'] = $nombre('partage_expiration_defaut_jours', 0, 3650, 30);

        $pairs['penalite_activer_cron']          = $this->post('penalite_activer_cron') !== null ? '1' : '0';
        $pairs['bodacc_surveillance_active']     = $this->post('bodacc_surveillance_active') !== null ? '1' : '0';
        $pairs['clamav_actif']                   = $this->post('clamav_actif') !== null ? '1' : '0';
        $pairs['clamav_bloquer_si_indisponible'] = $this->post('clamav_bloquer_si_indisponible') !== null ? '1' : '0';
        $pairs['rapport_hebdo_actif']            = $this->post('rapport_hebdo_actif') !== null ? '1' : '0';
        $pairs['blg_actif']                      = $this->post('blg_actif') !== null ? '1' : '0';

        $regle2fa = (string) $this->post('2fa_obligatoire', '0');
        $pairs['2fa_obligatoire'] = in_array($regle2fa, ['0', 'admin', 'tous'], true) ? $regle2fa : '0';

        $clamav = trim((string) $this->post('clamav_adresse', ''));
        $pairs['clamav_adresse'] = (str_starts_with($clamav, 'unix://') || str_starts_with($clamav, 'tcp://')) ? $clamav : '';
        if ($pairs['clamav_adresse'] === '') {
            $pairs['clamav_actif'] = '0';
        }

        $destinataires = trim((string) $this->post('rapport_hebdo_destinataires', 'admin'));
        $pairs['rapport_hebdo_destinataires'] = $destinataires !== '' ? mb_substr($destinataires, 0, 500) : 'admin';

        // Permissions des roles
        $pairs['perm_gestionnaire_litiges']  = $this->post('perm_gestionnaire_litiges')  !== null ? '1' : '0';
        $pairs['perm_gestionnaire_clients']  = $this->post('perm_gestionnaire_clients')  !== null ? '1' : '0';
        $pairs['perm_gestionnaire_relances'] = $this->post('perm_gestionnaire_relances') !== null ? '1' : '0';
        $pairs['perm_gestionnaire_chat']     = $this->post('perm_gestionnaire_chat')     !== null ? '1' : '0';
        $pairs['perm_consultant_litiges']    = $this->post('perm_consultant_litiges')    !== null ? '1' : '0';
        $pairs['perm_consultant_clients']    = $this->post('perm_consultant_clients')    !== null ? '1' : '0';
        $pairs['perm_consultant_relances']   = $this->post('perm_consultant_relances')   !== null ? '1' : '0';
        $pairs['perm_consultant_chat']       = $this->post('perm_consultant_chat')       !== null ? '1' : '0';
        $pairs['perm_lecteur_litiges']       = $this->post('perm_lecteur_litiges')       !== null ? '1' : '0';
        $pairs['perm_lecteur_clients']       = $this->post('perm_lecteur_clients')       !== null ? '1' : '0';
        $pairs['perm_lecteur_relances']      = $this->post('perm_lecteur_relances')      !== null ? '1' : '0';
        $pairs['perm_lecteur_chat']          = $this->post('perm_lecteur_chat')          !== null ? '1' : '0';

        // Le mot de passe SMTP n'est modifie que s'il est renseigne.
        $smtpPass = (string) $this->post('smtp_pass', '');
        if ($smtpPass !== '') {
            $pairs['smtp_pass'] = $smtpPass;
        }

        // Le jeton Pappers n'est remplace que s'il est fourni.
        $token = trim((string) $this->post('pappers_api_token', ''));
        if ($token !== '' && !str_starts_with($token, '••••')) {
            $pairs['pappers_api_token'] = $token;
        }

        // Cle API 1min.ai n'est remplacee que si fournie.
        $iaKey = trim((string) $this->post('oneminai_api_key', ''));
        if ($iaKey !== '' && !str_starts_with($iaKey, '••••')) {
            $pairs['oneminai_api_key'] = $iaKey;
        }

        Settings::setMany($pairs);
        Audit::log('parametres_modification', 'settings');

        flash('success', 'Paramètres enregistrés.');
        $this->redirect('/parametres');
    }

    /** Teste la connexion a l'IA 1min.ai (cle API + modele). */
    public function testerIa(): void
    {
        // Priorite aux valeurs saisies dans le formulaire, pas encore enregistrees.
        $modele = trim((string) $this->post('oneminai_model', ''));
        $cle    = trim((string) $this->post('oneminai_api_key', ''));
        if ($cle !== '' && str_starts_with($cle, '••••')) {
            $cle = '';
        }

        $resultat = (new OneMinAiService())->tester($modele !== '' ? $modele : null, $cle !== '' ? $cle : null);

        Audit::log('ia_test_connexion', 'settings', null, [
            'ok'      => $resultat['ok'],
            'message' => mb_substr($resultat['message'], 0, 300),
        ]);

        flash($resultat['ok'] ? 'success' : 'error', 'Test IA — ' . e($resultat['message']));
        $this->redirect('/parametres#ia');
    }

    // ------------------------------------------------------------------

    private function collectStatut(string $categorie): array
    {
        $libelle = (string) $this->post('libelle', '');
        $code    = (string) $this->post('code', '');
        if ($code === '') {
            $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $this->sansAccents($libelle)) ?? '');
            $code = trim($code, '_');
        }

        return [
            'categorie' => $categorie,
            'libelle'   => $libelle,
            'code'      => mb_substr($code, 0, 40),
            'couleur'   => (string) $this->post('couleur', '#6c757d'),
            'ordre'     => $this->int('ordre'),
            'cloturant' => $this->post('cloturant') !== null ? 1 : 0,
            'actif'     => $this->post('actif') !== null ? 1 : 0,
        ];
    }

    private function validateStatut(array $data, ?int $exceptId): Validator
    {
        $validator = new Validator($data, ['libelle' => 'Le libelle', 'code' => 'Le code']);
        $validator->required('libelle')->length('libelle', 2, 80)->required('code')->length('code', 2, 40);

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $data['couleur'])) {
            $validator->custom('couleur', false, 'Couleur invalide (format #RRGGBB).');
        }

        $sql    = 'SELECT COUNT(*) FROM litige_statuts WHERE code = :c';
        $params = ['c' => $data['code']];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        if ((int) Database::value($sql, $params, 0) > 0) {
            $validator->custom('code', false, 'Ce code est déjà utilisé.');
        }

        return $validator;
    }

    private function sansAccents(string $texte): string
    {
        return strtr($texte, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
            'À' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ç' => 'C',
        ]);
    }
}
