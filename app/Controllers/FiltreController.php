<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Models\FiltreEnregistre;

/** Filtres de la liste des litiges enregistres par les utilisateurs. */
class FiltreController extends Controller
{
    /** Criteres que l'on accepte de memoriser (liste blanche). */
    private const CRITERES = [
        'q', 'client_id', 'agence_id', 'statut_id', 'finalite_id', 'responsable_id', 'priorite',
        'type_litige', 'date_from', 'date_to', 'etat', 'relance_due', 'promesse_depassee', 'tri',
    ];

    public function store(): void
    {
        $nom = trim((string) $this->post('nom', ''));
        if ($nom === '' || mb_strlen($nom) > 100) {
            flash('error', 'Donnez un nom au filtre (100 caractères maximum).');
            $this->back('/litiges');
        }

        $parametres = [];
        foreach (self::CRITERES as $cle) {
            $valeur = $this->post($cle);
            if (is_string($valeur) && $valeur !== '' && $valeur !== '0') {
                $parametres[$cle] = $valeur;
            }
        }

        $id = (new FiltreEnregistre())->create([
            'user_id'    => (int) Auth::id(),
            'module'     => 'litiges',
            'nom'        => $nom,
            'parametres' => json_encode($parametres, JSON_UNESCAPED_UNICODE),
            'partage'    => ($this->post('partage') !== null && Auth::canWrite()) ? 1 : 0,
        ]);
        Audit::log('filtre_enregistre', 'filtre', $id, ['nom' => $nom]);

        flash('success', 'Filtre « ' . e($nom) . ' » enregistré.');
        $this->redirect('/litiges?filtre=' . $id);
    }

    public function destroy(string $id): void
    {
        $filtres = new FiltreEnregistre();
        $filtre  = $filtres->find((int) $id);
        if (!$filtre) {
            $this->notFound('Filtre introuvable.');
        }
        if (!Auth::isAdmin() && (int) $filtre['user_id'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres filtres.');
        }

        $filtres->delete((int) $id);
        flash('success', 'Filtre supprimé.');
        $this->redirect('/litiges');
    }
}
