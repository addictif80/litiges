<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Validator;
use App\Enums\Priorite;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\Tache;
use App\Models\User;
use App\Services\NotificationService;

class TacheController extends Controller
{
    public function index(): void
    {
        $vue = (string) $this->input('vue', 'moi');
        $filtres = ['statut' => (string) $this->input('statut', 'ouvertes')];
        if ($vue === 'moi') {
            $filtres['assigne_a'] = (int) Auth::id();
        } elseif ($vue === 'retard') {
            $filtres['en_retard'] = true;
        } elseif ($vue === 'utilisateur' && $this->int('utilisateur') > 0) {
            $filtres['assigne_a'] = $this->int('utilisateur');
        }

        $this->view('taches/index', [
            'titre'    => 'Tâches',
            'taches'   => (new Tache())->liste($filtres),
            'vue'      => $vue,
            'filtres'  => $filtres,
            'statutFiltre' => $filtres['statut'],
            'utilisateurs' => (new User())->listActifs(),
            'priorites' => Priorite::options(),
            'statuts'  => Tache::STATUTS,
        ]);
    }

    public function store(): void
    {
        $litigeId = $this->int('litige_id') ?: null;
        if ($litigeId !== null && !(new Litige())->find($litigeId)) {
            $this->notFound('Litige introuvable.');
        }

        $data = [
            'litige_id'   => $litigeId,
            'titre'       => (string) $this->post('titre', ''),
            'description' => (string) $this->post('description', '') ?: null,
            'assigne_a'   => $this->int('assigne_a') ?: (int) Auth::id(),
            'echeance'    => (string) $this->post('echeance', '') ?: null,
            'priorite'    => (string) $this->post('priorite', 'normale'),
            'statut'      => 'a_faire',
            'created_by'  => (int) Auth::id(),
        ];

        $validator = new Validator($data, ['titre' => 'Le titre']);
        $validator->required('titre')->length('titre', 2, 190)->in('priorite', Priorite::valeurs());
        if ($data['echeance'] !== null) {
            $validator->date('echeance');
        }
        if ($validator->fails()) {
            flash('error', e($validator->firstError() ?? 'Données invalides.'));
            $this->back($litigeId ? '/litiges/' . $litigeId . '#taches' : '/taches');
        }

        $id = (new Tache())->create($data);
        Audit::log('tache_creation', 'tache', $id, ['litige_id' => $litigeId]);
        if ($litigeId) {
            (new LitigeHistorique())->log($litigeId, Auth::id(), 'tache', 'Tâche', null, mb_substr($data['titre'], 0, 200), 'Tâche créée');
        }
        (new NotificationService())->tacheAssignee($data, (int) Auth::id());

        flash('success', 'Tâche créée.');
        $this->redirect($litigeId ? '/litiges/' . $litigeId . '#taches' : '/taches');
    }

    public function statut(string $id): void
    {
        $taches = new Tache();
        $tache  = $taches->find((int) $id);
        if (!$tache) {
            $this->notFound('Tâche introuvable.');
        }

        $statut = (string) $this->post('statut', '');
        if (!isset(Tache::STATUTS[$statut])) {
            flash('error', 'Statut invalide.');
            $this->back('/taches');
        }

        $taches->update((int) $id, [
            'statut'  => $statut,
            'done_at' => $statut === 'faite' ? date('Y-m-d H:i:s') : null,
        ]);
        Audit::log('tache_statut', 'tache', (int) $id, ['statut' => $statut]);

        $this->back($tache['litige_id'] ? '/litiges/' . (int) $tache['litige_id'] . '#taches' : '/taches');
    }

    public function destroy(string $id): void
    {
        $taches = new Tache();
        $tache  = $taches->find((int) $id);
        if (!$tache) {
            $this->notFound('Tâche introuvable.');
        }
        if (!Auth::isAdmin() && (int) $tache['created_by'] !== (int) Auth::id() && (int) $tache['assigne_a'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres tâches.');
        }

        $taches->delete((int) $id);
        Audit::log('tache_suppression', 'tache', (int) $id);
        flash('success', 'Tâche supprimée.');
        $this->back($tache['litige_id'] ? '/litiges/' . (int) $tache['litige_id'] . '#taches' : '/taches');
    }
}
