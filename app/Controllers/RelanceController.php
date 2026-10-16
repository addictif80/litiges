<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Exceptions\ValidationException;
use App\Models\Litige;
use App\Models\Relance;
use App\Services\RelanceService;

class RelanceController extends Controller
{
    public function store(string $litigeId): void
    {
        $id = (int) $litigeId;
        if (!(new Litige())->find($id)) {
            $this->notFound('Litige introuvable.');
        }

        try {
            (new RelanceService())->enregistrer($id, [
                'type_action'         => $this->post('type_action', 'relance'),
                'canal'               => $this->post('canal', 'telephone'),
                'date_action'         => $this->post('date_action', ''),
                'objet'               => $this->post('objet', ''),
                'contenu'             => $this->post('contenu', ''),
                'resultat'            => $this->post('resultat', ''),
                'montant_promis'      => $this->post('montant_promis'),
                'date_promesse'       => $this->post('date_promesse', ''),
                'prochaine_action_at' => $this->post('prochaine_action_at', ''),
                'contact_id'          => $this->int('contact_id'),
            ], Auth::id());
        } catch (ValidationException $e) {
            flash('error', e($e->getMessage()));
            $this->back('/litiges/' . $id);
        }

        flash('success', 'Relance enregistrée.');
        $this->redirect('/litiges/' . $id . '#suivi');
    }

    public function destroy(string $litigeId, string $relanceId): void
    {
        $relance = (new Relance())->find((int) $relanceId);
        if (!$relance || (int) $relance['litige_id'] !== (int) $litigeId) {
            $this->notFound('Relance introuvable.');
        }

        if (!Auth::isAdmin() && (int) $relance['created_by'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres relances.');
        }

        (new Relance())->delete((int) $relanceId);
        Audit::log('relance_suppression', 'litige', (int) $litigeId, ['relance_id' => (int) $relanceId]);

        flash('success', 'Relance supprimée.');
        $this->redirect('/litiges/' . $litigeId . '#suivi');
    }
}
