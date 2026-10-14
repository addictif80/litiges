<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Services\CompanyLookupService;

/**
 * Endpoints JSON d'interrogation des APIs entreprises (recherche + detail).
 * Utilises par le widget de recherche SIREN des formulaires client.
 */
class CompanyController extends Controller
{
    public function search(): void
    {
        $q = trim((string) $this->input('q', ''));
        if (mb_strlen($q) < 2) {
            $this->json(['results' => [], 'message' => 'Saisissez au moins 2 caracteres.']);
        }

        $service = new CompanyLookupService();
        $resultat = $service->search($q, 8);

        $results = array_map(static function (array $r): array {
            return [
                'siren'          => $r['siren'] ?? '',
                'siret'          => $r['siret'] ?? '',
                'nom'            => $r['raison_sociale'] ?? '',
                'adresse'        => trim(($r['adresse'] ?? '') . ' ' . ($r['code_postal'] ?? '') . ' ' . ($r['ville'] ?? '')),
                'ville'          => $r['ville'] ?? '',
                'statut'         => $r['statut_entreprise'] ?? '',
                'procedure'      => $r['procedure_collective'] ?? '',
                'source'         => $r['source_api'] ?? '',
            ];
        }, $resultat['results']);

        $this->json([
            'results' => $results,
            'source'  => $resultat['source'],
            'sources' => $service->sourcesDisponibles(),
        ]);
    }

    public function detail(string $identifiant): void
    {
        $service = new CompanyLookupService();
        $donnees = $service->lookup($identifiant);

        if ($donnees === null) {
            $this->json(['ok' => false, 'message' => "Aucune entreprise trouvee pour {$identifiant}."], 404);
        }

        Audit::log('api_entreprise', 'client', null, ['siren' => $identifiant, 'source' => $donnees['_source'] ?? null]);

        $donnees['ok']  = true;
        $donnees['_champs_client'] = $service->filtrerChamps($donnees);
        $this->json($donnees);
    }
}
