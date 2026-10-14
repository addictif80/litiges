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
        $this->view('partages/index', [
            'titre'    => 'Acces en lecture seule',
            'partages' => (new Partage())->listAll(),
            'scopes'   => Partage::SCOPES,
            'agences'  => (new Agence())->listActives(),
            'clients'  => (new Client())->all('actif = 1', [], 'raison_sociale ASC'),
        ]);
    }

    public function store(): void
    {
        $libelle = trim((string) $this->post('libelle', ''));
        $scope   = (string) $this->post('scope', 'agence');
        $scopeId = $this->int('scope_id') ?: null;
        $expires = (string) $this->post('expires_at', '') ?: null;

        if ($libelle === '') {
            flash('error', 'Merci de donner un libelle.');
            $this->redirect('/partages');
        }
        if (!array_key_exists($scope, Partage::SCOPES)) {
            $scope = 'global';
        }
        if ($scope !== 'global' && !$scopeId) {
            flash('error', 'Selectionnez l\'element concerne par cet acces.');
            $this->redirect('/partages');
        }

        $model  = new Partage();
        $partage = [
            'code'       => $model->generateCode(),
            'token'      => $model->generateToken(),
            'libelle'    => $libelle,
            'scope'      => $scope,
            'scope_id'   => $scope === 'global' ? null : $scopeId,
            'expires_at' => $expires ? $expires . ' 23:59:59' : null,
            'actif'      => 1,
            'created_by' => (int) Auth::id(),
        ];

        $id = $model->create($partage);
        Audit::log('partage_creation', 'partage', $id, ['scope' => $scope, 'scope_id' => $scopeId]);

        flash('success', 'Code d\'acces cree : ' . $partage['code']);
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
        $existant = $model->findBy('scope_id', (int) $id);
        if ($existant && (string) $existant['scope'] === 'litige' && (int) $existant['actif'] === 1) {
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
                'expires_at' => null,
                'actif'      => 1,
                'created_by' => (int) Auth::id(),
            ]);
            Audit::log('partage_creation', 'partage', $partageId, ['scope' => 'litige', 'scope_id' => (int) $id]);
        }

        flash('success', 'Lien de partage (lecture seule) : ' . url('/partage/lien/' . $token));
        $this->redirect('/litiges/' . (int) $id);
    }

    public function toggle(string $id): void
    {
        $model   = new Partage();
        $partage = $model->find((int) $id);
        if (!$partage) {
            $this->notFound('Acces introuvable.');
        }

        $model->update((int) $id, ['actif' => (int) $partage['actif'] === 1 ? 0 : 1]);
        Audit::log('partage_toggle', 'partage', (int) $id);

        flash('success', 'Statut de l\'acces modifie.');
        $this->redirect('/partages');
    }

    public function destroy(string $id): void
    {
        $model   = new Partage();
        $partage = $model->find((int) $id);
        if (!$partage) {
            $this->notFound('Acces introuvable.');
        }

        $model->delete((int) $id);
        Audit::log('partage_suppression', 'partage', (int) $id);

        flash('success', 'Acces supprime.');
        $this->redirect('/partages');
    }
}
