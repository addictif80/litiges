<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Services\CompanyLookupService;

/**
 * Inspection d'une entreprise SANS creer de client :
 *   - recherche multicritere (/entreprise) ;
 *   - dossier complet (/entreprise/{siren|siret}) : identite, libelles des
 *     codes (forme juridique, APE), historique financier, procedures
 *     collectives actuelles et par annee, dirigeants et entreprises liees,
 *     score de solvabilite.
 */
class InspectionController extends Controller
{
    public function index(): void
    {
        $q         = trim((string) $this->input('q', ''));
        $resultats = [];
        $source    = null;
        $service   = new CompanyLookupService();

        if (mb_strlen($q) >= 2) {
            $recherche = $service->search($q, 12);
            $resultats = $recherche['results'];
            $source    = $recherche['source'];
        }

        $this->view('entreprise/index', [
            'titre'    => 'Inspecter une entreprise',
            'q'        => $q,
            'resultats' => $resultats,
            'source'   => $source,
            'sources'  => $service->sourcesDisponibles(),
        ]);
    }

    public function show(string $identifiant): void
    {
        $service = new CompanyLookupService();
        $dossier = $service->dossier($identifiant);

        if ($dossier === null) {
            flash('error', "Aucune entreprise trouvée pour « {$identifiant} ».");
            $this->redirect('/entreprise');
        }

        Audit::log('entreprise_inspection', 'entreprise', null, [
            'siren'  => $dossier['siren'] ?? $identifiant,
            'source' => $dossier['_source'] ?? null,
        ]);

        $this->view('entreprise/show', [
            'titre'   => (string) ($dossier['raison_sociale'] ?: 'Entreprise'),
            'e'       => $dossier,
        ]);
    }
}
