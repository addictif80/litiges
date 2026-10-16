<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Exceptions\ValidationException;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\Paiement;
use App\Services\EcheanceService;
use App\Services\PaiementService;
use App\Services\PenaltyService;

/**
 * Paiements, echeanciers, penalites de retard et declaration de creance
 * d'un litige (actions depuis la fiche litige).
 */
class RecouvrementController extends Controller
{
    // --- Paiements ---------------------------------------------------------

    public function storePaiement(string $litigeId): void
    {
        $id = $this->exigerLitige($litigeId);
        try {
            (new PaiementService())->enregistrer($id, [
                'date_paiement' => $this->post('date_paiement', date('Y-m-d')),
                'montant'       => $this->post('montant'),
                'mode'          => $this->post('mode', 'virement'),
                'reference'     => $this->post('reference', ''),
                'commentaire'   => $this->post('commentaire', ''),
            ], Auth::id());
        } catch (ValidationException $e) {
            flash('error', e(implode(' ', $e->erreurs)));
            $this->back('/litiges/' . $id . '#paiements');
        }

        flash('success', 'Paiement enregistré.');
        $this->redirect('/litiges/' . $id . '#paiements');
    }

    public function destroyPaiement(string $litigeId, string $paiementId): void
    {
        $id = $this->exigerLitige($litigeId);
        $paiement = (new Paiement())->find((int) $paiementId);
        if (!$paiement || (int) $paiement['litige_id'] !== $id) {
            $this->notFound('Paiement introuvable.');
        }
        if (!Auth::isAdmin() && (int) $paiement['created_by'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres paiements.');
        }

        try {
            (new PaiementService())->supprimer((int) $paiementId, Auth::id());
        } catch (ValidationException $e) {
            flash('error', e($e->getMessage()));
            $this->back('/litiges/' . $id . '#paiements');
        }

        flash('success', 'Paiement supprimé.');
        $this->redirect('/litiges/' . $id . '#paiements');
    }

    // --- Echeancier --------------------------------------------------------

    public function storeEcheancier(string $litigeId): void
    {
        $id = $this->exigerLitige($litigeId);
        try {
            $n = (new EcheanceService())->creerPlan($id, [
                'nombre'          => $this->int('nombre', 0),
                'premiere_date'   => $this->post('premiere_date', ''),
                'periodicite'     => $this->post('periodicite', 'mensuelle'),
                'montant_total'   => $this->post('montant_total'),
                'premier_montant' => $this->post('premier_montant'),
            ], Auth::id());
        } catch (ValidationException $e) {
            flash('error', e(implode(' ', $e->erreurs)));
            $this->back('/litiges/' . $id . '#echeancier');
        }

        flash('success', "Échéancier créé ({$n} échéance(s)).");
        $this->redirect('/litiges/' . $id . '#echeancier');
    }

    public function destroyEcheancier(string $litigeId): void
    {
        $id = $this->exigerLitige($litigeId);
        (new EcheanceService())->supprimerPlan($id, Auth::id());
        flash('success', 'Échéancier supprimé.');
        $this->redirect('/litiges/' . $id . '#echeancier');
    }

    // --- Penalites ---------------------------------------------------------

    /** Enregistre les parametres de penalites du litige puis recalcule. */
    public function majPenalites(string $litigeId): void
    {
        $id = $this->exigerLitige($litigeId);
        $taux = trim((string) $this->post('taux_penalite', ''));
        $taux = $taux === '' ? null : str_replace(',', '.', $taux);
        if ($taux !== null && (!is_numeric($taux) || (float) $taux < 0 || (float) $taux > 100)) {
            flash('error', 'Le taux doit être compris entre 0 et 100 %.');
            $this->back('/litiges/' . $id . '#penalites');
        }
        $depuis = trim((string) $this->post('penalites_depuis', ''));
        if ($depuis !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $depuis)) {
            flash('error', 'La date de départ est invalide.');
            $this->back('/litiges/' . $id . '#penalites');
        }

        $litiges = new Litige();
        $litiges->update($id, [
            'penalites_auto'        => $this->post('penalites_auto') !== null ? 1 : 0,
            'taux_penalite'         => $taux,
            'indemnite_forfaitaire' => $this->post('indemnite_forfaitaire') !== null ? 1 : 0,
            'penalites_depuis'      => $depuis !== '' ? $depuis : null,
        ]);

        $litige = $litiges->find($id);
        if ((int) $litige['penalites_auto'] === 1) {
            (new PenaltyService())->appliquer($litige, Auth::id());
        }
        Audit::log('penalites_parametrage', 'litige', $id);

        flash('success', 'Paramètres de pénalités enregistrés.');
        $this->redirect('/litiges/' . $id . '#penalites');
    }

    // --- Declaration de creance -------------------------------------------

    public function creanceDeclaree(string $litigeId): void
    {
        $id = $this->exigerLitige($litigeId);
        $date = trim((string) $this->post('creance_declaree_le', date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash('error', 'Date invalide.');
            $this->back('/litiges/' . $id);
        }

        (new Litige())->update($id, ['creance_declaree_le' => $this->post('annuler') !== null ? null : $date]);
        (new LitigeHistorique())->log(
            $id,
            Auth::id(),
            'creance',
            'Déclaration de créance',
            null,
            $this->post('annuler') !== null ? null : $date,
            $this->post('annuler') !== null ? 'Déclaration annulée' : 'Créance déclarée au mandataire'
        );
        Audit::log('creance_declaree', 'litige', $id);

        flash('success', 'Déclaration de créance mise à jour.');
        $this->redirect('/litiges/' . $id);
    }

    private function exigerLitige(string $litigeId): int
    {
        $id = (int) $litigeId;
        if (!(new Litige())->find($id)) {
            $this->notFound('Litige introuvable.');
        }
        return $id;
    }
}
