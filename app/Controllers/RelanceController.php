<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\Relance;

class RelanceController extends Controller
{
    public function store(string $litigeId): void
    {
        $id     = (int) $litigeId;
        $litige = (new Litige())->find($id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }

        $typeAction = (string) $this->post('type_action', 'relance');
        if (!array_key_exists($typeAction, Relance::TYPES)) {
            $typeAction = 'relance';
        }
        $canal = (string) $this->post('canal', 'telephone');
        if (!array_key_exists($canal, Relance::CANAUX)) {
            $canal = 'telephone';
        }

        $dateAction = $this->normaliserDateHeure((string) $this->post('date_action', '')) ?: date('Y-m-d H:i:s');
        $datePromesse = (string) $this->post('date_promesse', '') ?: null;
        $prochaine = (string) $this->post('prochaine_action_at', '') ?: null;

        $contenu = trim((string) $this->post('contenu', ''));
        $objet   = trim((string) $this->post('objet', ''));
        if ($contenu === '' && $objet === '') {
            flash('error', "Merci de renseigner l'objet ou le detail de la relance.");
            $this->back('/litiges/' . $id);
        }

        $relances = new Relance();
        $relances->create([
            'litige_id'          => $id,
            'contact_id'         => $this->int('contact_id') ?: null,
            'type_action'        => $typeAction,
            'canal'              => $canal,
            'date_action'        => $dateAction,
            'objet'              => $objet !== '' ? $objet : (Relance::TYPES[$typeAction] ?? 'Relance'),
            'contenu'            => $contenu,
            'resultat'           => trim((string) $this->post('resultat', '')) ?: null,
            'montant_promis'     => $this->post('montant_promis') !== '' ? $this->post('montant_promis') : null,
            'date_promesse'      => $datePromesse,
            'prochaine_action_at' => $prochaine ? $this->normaliserDateHeure($prochaine) : null,
            'created_by'         => (int) Auth::id(),
        ]);

        // Mise a jour du suivi du litige.
        $delai = max(1, (int) ($litige['delai_relance_jours'] ?? 15));
        $prochaineRelance = $prochaine
            ? date('Y-m-d', strtotime($prochaine))
            : date('Y-m-d', strtotime($dateAction . " +{$delai} days"));

        // Une promesse de paiement non depassee repousse la relance a la date promise.
        if ($datePromesse && strtotime($datePromesse) >= strtotime('today')) {
            $prochaineRelance = $datePromesse;
        }

        (new Litige())->update($id, [
            'derniere_relance_at' => $dateAction,
            'prochaine_relance_at' => $prochaineRelance,
        ]);

        (new LitigeHistorique())->log(
            $id,
            (int) Auth::id(),
            'relance',
            'Relance',
            null,
            Relance::TYPES[$typeAction] ?? $typeAction,
            trim($objet . ' — ' . $contenu)
        );
        Audit::log('relance_ajout', 'litige', $id, ['type' => $typeAction, 'canal' => $canal]);

        flash('success', 'Relance enregistree.');
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

        flash('success', 'Relance supprimee.');
        $this->redirect('/litiges/' . $litigeId . '#suivi');
    }

    private function normaliserDateHeure(string $valeur): ?string
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }
        $valeur = str_replace('T', ' ', $valeur);
        $ts     = strtotime($valeur);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
