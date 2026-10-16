<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Models\Agence;
use App\Models\Client;
use App\Models\Litige;
use App\Models\Partage;

class PartageController extends Controller
{
    public function index(): void
    {
        $model    = new Partage();
        $partages = $model->listAll();

        $this->view('partages/index', [
            'titre'    => 'Accès en lecture seule',
            'partages' => $partages,
            'scopes'   => Partage::SCOPES,
            'agences'  => (new Agence())->listActives(),
            'clients'  => (new Client())->all('actif = 1', [], 'raison_sociale ASC', 2000),
            'journal'  => $model->journalPar(array_map(static fn (array $p): int => (int) $p['id'], $partages)),
            'expirationDefautJours' => (int) \App\Core\Settings::get('partage_expiration_defaut_jours', 30),
        ]);
    }

    public function store(): void
    {
        $libelle = trim((string) $this->post('libelle', ''));
        $scope   = (string) $this->post('scope', 'agence');
        $scopeId = $this->int('scope_id') ?: null;
        $expires = trim((string) $this->post('expires_at', ''));
        $motDePasse = (string) $this->post('mot_de_passe', '');

        if ($libelle === '') {
            flash('error', 'Merci de donner un libellé.');
            $this->redirect('/partages');
        }
        if (!array_key_exists($scope, Partage::SCOPES)) {
            $scope = 'global';
        }
        if ($scope !== 'global' && !$scopeId) {
            flash('error', "Sélectionnez l'élément concerné par cet accès.");
            $this->redirect('/partages');
        }
        if ($motDePasse !== '' && mb_strlen($motDePasse) < 8) {
            flash('error', 'Le mot de passe du partage doit contenir au moins 8 caractères.');
            $this->redirect('/partages');
        }
        if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
            flash('error', "La date d'expiration est invalide.");
            $this->redirect('/partages');
        }

        $model  = new Partage();
        $partage = [
            'code'       => $model->generateCode(),
            'token'      => $model->generateToken(),
            'libelle'    => mb_substr($libelle, 0, 150),
            'scope'      => $scope,
            'scope_id'   => $scope === 'global' ? null : $scopeId,
            // Sans date saisie : expiration par defaut (parametre, 30 jours) ; "jamais" = case dediee.
            'expires_at' => $expires !== '' ? $expires . ' 23:59:59' : ($this->post('sans_expiration') !== null ? null : Partage::expirationParDefaut()),
            'actif'      => 1,
            'created_by' => (int) Auth::id(),
            'mot_de_passe_hash'  => $motDePasse !== '' ? password_hash($motDePasse, PASSWORD_BCRYPT) : null,
            'afficher_documents' => $this->post('afficher_documents') !== null ? 1 : 0,
        ];

        $id = $model->create($partage);
        Audit::log('partage_creation', 'partage', $id, [
            'scope' => $scope, 'scope_id' => $scopeId,
            'mot_de_passe' => $motDePasse !== '', 'documents' => $partage['afficher_documents'],
        ]);

        flash('success', "Code d'accès créé : " . e($partage['code'])
            . ($partage['expires_at'] ? " (valable jusqu'au " . date_fr($partage['expires_at']) . ')' : ' (sans expiration)'));
        $this->redirect('/partages');
    }

    /** Cree (ou reutilise) un lien de partage public pour un litige precis. */
    public function storeLitige(string $id): void
    {
        $litige = (new Litige())->find((int) $id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }

        $model = new Partage();

        // Reutilise un partage actif existant sur ce litige, sinon en cree un.
        $existant = $model->dernierPourLitige((int) $id);
        if ($existant && (int) $existant['actif'] === 1) {
            $token = (string) ($existant['token'] ?? '');
            if ($token === '') {
                $token = $model->generateToken();
                $model->update((int) $existant['id'], ['token' => $token]);
            }
        } else {
            $token = $model->generateToken();
            $partageId = $model->create([
                'code'       => $model->generateCode(),
                'token'      => $token,
                'libelle'    => 'Litige ' . $litige['reference'] . ' — partage',
                'scope'      => 'litige',
                'scope_id'   => (int) $id,
                'expires_at' => Partage::expirationParDefaut(),
                'actif'      => 1,
                'created_by' => (int) Auth::id(),
                'afficher_documents' => 0,
            ]);
            Audit::log('partage_creation', 'partage', $partageId, ['scope' => 'litige', 'scope_id' => (int) $id]);
        }

        flash('success', 'Lien de partage (lecture seule) : ' . e(url('/partage/lien/' . $token))
            . ' — vous pouvez y ajouter un mot de passe, des documents et une expiration depuis « Accès lecture seule ».');
        $this->redirect('/litiges/' . (int) $id);
    }

    public function toggle(string $id): void
    {
        $model   = new Partage();
        $partage = $model->find((int) $id);
        if (!$partage) {
            $this->notFound('Accès introuvable.');
        }

        $model->update((int) $id, ['actif' => (int) $partage['actif'] === 1 ? 0 : 1]);
        Audit::log('partage_toggle', 'partage', (int) $id);

        flash('success', "Statut de l'accès modifié.");
        $this->redirect('/partages');
    }

    /** Ajoute, change ou retire le mot de passe d'un partage ; active/desactive les documents. */
    public function securiser(string $id): void
    {
        $model   = new Partage();
        $partage = $model->find((int) $id);
        if (!$partage) {
            $this->notFound('Accès introuvable.');
        }

        $maj = ['afficher_documents' => $this->post('afficher_documents') !== null ? 1 : 0];

        if ($this->post('retirer_mot_de_passe') !== null) {
            $maj['mot_de_passe_hash'] = null;
        } else {
            $motDePasse = (string) $this->post('mot_de_passe', '');
            if ($motDePasse !== '') {
                if (mb_strlen($motDePasse) < 8) {
                    flash('error', 'Le mot de passe du partage doit contenir au moins 8 caractères.');
                    $this->redirect('/partages');
                }
                $maj['mot_de_passe_hash'] = password_hash($motDePasse, PASSWORD_BCRYPT);
            }
        }

        $prolonger = $this->int('prolonger_jours');
        if ($prolonger > 0 && $prolonger <= 365) {
            $maj['expires_at'] = date('Y-m-d 23:59:59', strtotime("+{$prolonger} days"));
        }

        $model->update((int) $id, $maj);
        Audit::log('partage_securise', 'partage', (int) $id, [
            'mot_de_passe' => array_key_exists('mot_de_passe_hash', $maj) ? ($maj['mot_de_passe_hash'] !== null) : 'inchange',
            'documents'    => $maj['afficher_documents'],
        ]);

        flash('success', 'Paramètres de sécurité du partage enregistrés.');
        $this->redirect('/partages');
    }

    public function destroy(string $id): void
    {
        $model   = new Partage();
        $partage = $model->find((int) $id);
        if (!$partage) {
            $this->notFound('Accès introuvable.');
        }

        $model->delete((int) $id);
        Audit::log('partage_suppression', 'partage', (int) $id);

        flash('success', 'Accès supprimé.');
        $this->redirect('/partages');
    }
}
