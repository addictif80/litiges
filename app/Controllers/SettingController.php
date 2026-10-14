<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\LitigeStatut;

class SettingController extends Controller
{
    public function statuts(): void
    {
        $model = new LitigeStatut();
        $this->view('settings/statuts', [
            'titre'    => 'Statuts & finalites',
            'statuts'  => $model->statuts(false),
            'finalites' => $model->finalites(false),
        ]);
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
        flash('success', 'Element ajoute.');
        $this->redirect('/parametres/statuts');
    }

    public function updateStatut(string $id): void
    {
        $model  = new LitigeStatut();
        $statut = $model->find((int) $id);
        if (!$statut) {
            $this->notFound('Element introuvable.');
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
        flash('success', 'Element mis a jour.');
        $this->redirect('/parametres/statuts');
    }

    public function destroyStatut(string $id): void
    {
        $model  = new LitigeStatut();
        $statut = $model->find((int) $id);
        if (!$statut) {
            $this->notFound('Element introuvable.');
        }
        if ((int) $statut['systeme'] === 1) {
            flash('error', 'Cet element est systeme et ne peut pas etre supprime. Desactivez-le plutot.');
            $this->redirect('/parametres/statuts');
        }

        $utilise = (int) Database::value(
            'SELECT COUNT(*) FROM litiges WHERE statut_id = :statut_id OR finalite_id = :finalite_id',
            ['statut_id' => (int) $id, 'finalite_id' => (int) $id],
            0
        );
        if ($utilise > 0) {
            flash('error', "Impossible de supprimer : cet element est utilise par {$utilise} litige(s). Desactivez-le plutot.");
            $this->redirect('/parametres/statuts');
        }

        $model->delete((int) $id);
        Audit::log('statut_suppression', 'statut', (int) $id);
        flash('success', 'Element supprime.');
        $this->redirect('/parametres/statuts');
    }

    public function parametres(): void
    {
        $this->view('settings/parametres', [
            'titre'    => 'Parametres',
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

        flash('success', 'Parametres enregistres.');
        $this->redirect('/parametres');
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
            $validator->custom('code', false, 'Ce code est deja utilise.');
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
