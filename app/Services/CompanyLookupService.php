<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Services\Api\PappersApi;
use App\Services\Api\RechercheEntreprisesApi;

/**
 * Orchestrateur de la recuperation automatique des donnees d'entreprise.
 *
 * Ordre de priorite (parametrable via "company_api_provider") :
 *   - auto                : Pappers si un jeton est present, sinon API de l'Etat
 *   - pappers             : Pappers uniquement
 *   - recherche_entreprises : API gratuite de l'Etat uniquement
 */
class CompanyLookupService
{
    private RechercheEntreprisesApi $etat;
    private PappersApi $pappers;
    private string $provider;

    public function __construct(?string $pappersToken = null)
    {
        $this->etat     = new RechercheEntreprisesApi();
        $this->pappers  = new PappersApi($pappersToken);
        $this->provider = (string) Settings::get('company_api_provider', 'auto');
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function sourcesDisponibles(): array
    {
        return [
            'recherche_entreprises' => $this->etat->isConfigured(),
            'pappers'               => $this->pappers->isConfigured(),
        ];
    }

    /**
     * @return array{source:?string, results: array<int,array<string,mixed>>}
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['source' => null, 'results' => []];
        }

        if ($this->usePappers()) {
            $results = $this->pappers->search($query, $limit);
            if ($results !== []) {
                return ['source' => 'pappers', 'results' => $results];
            }
            if ($this->provider === 'pappers') {
                return ['source' => 'pappers', 'results' => []];
            }
        }

        return ['source' => 'recherche-entreprises', 'results' => $this->etat->search($query, $limit)];
    }

    /** Recherche par SIREN ou SIRET (14 chiffres -> on garde les 9 premiers). */
    public function lookup(string $identifiant): ?array
    {
        $digits = preg_replace('/\D/', '', $identifiant) ?? '';
        if (strlen($digits) === 14) {
            $digits = substr($digits, 0, 9);
        }
        if (strlen($digits) !== 9) {
            return null;
        }

        $resultat = null;
        $source   = null;

        if ($this->usePappers()) {
            $resultat = $this->pappers->findBySiren($digits);
            $source   = $resultat ? 'pappers' : null;
        }

        if ($resultat === null && $this->provider !== 'pappers') {
            $resultat = $this->etat->findBySiren($digits);
            $source   = $resultat ? 'recherche-entreprises' : null;
        }

        if ($resultat === null) {
            return null;
        }

        $resultat['_source'] = $source;
        return $resultat;
    }

    /**
     * Recherche par SIRET en ciblant un etablissement precis (API Etat).
     */
    public function lookupEtablissement(string $siret): ?array
    {
        $digits = preg_replace('/\D/', '', $siret) ?? '';
        if (strlen($digits) !== 14) {
            return null;
        }

        $response = HttpClient::get(
            HttpClient::urlWithQuery(
                'https://recherche-entreprises.api.gouv.fr/search',
                ['q' => $digits, 'page' => 1, 'per_page' => 1]
            ),
            [],
            8
        );

        if (($response['json']['results'] ?? null) === null) {
            return null;
        }

        return $this->etat->normalize($response['json']['results'][0]);
    }

    private function usePappers(): bool
    {
        return $this->pappers->isConfigured() && in_array($this->provider, ['auto', 'pappers'], true);
    }

    /** Champs client alimentes depuis la reponse API. */
    public const CHAMPS_IMPORTABLES = [
        'raison_sociale', 'forme_juridique', 'siren', 'siret', 'tva_intra', 'ape_naf',
        'libelle_ape', 'adresse', 'complement_adresse', 'code_postal', 'ville', 'pays',
        'effectif', 'date_creation', 'capital', 'chiffre_affaires', 'resultat_net',
        'annee_finances', 'statut_entreprise', 'procedure_collective', 'score_solvabilite',
        'source_api', 'raw_data',
    ];

    public function filtrerChamps(array $donnees): array
    {
        return array_intersect_key($donnees, array_flip(self::CHAMPS_IMPORTABLES));
    }
}
