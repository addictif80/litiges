<?php
declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Services\HttpClient;

/**
 * API publique et gratuite "Recherche d'entreprises" (Etat francais).
 * Documentation : https://recherche-entreprises.api.gouv.fr/docs
 * Aucune cle API requise.
 */
class RechercheEntreprisesApi
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) Config::get('apis.recherche_entreprises.base_url', 'https://recherche-entreprises.api.gouv.fr'), '/');
        $this->timeout = (int) Config::get('apis.recherche_entreprises.timeout', 8);
    }

    public function isConfigured(): bool
    {
        return (bool) Config::get('apis.recherche_entreprises.actif', true);
    }

    /**
     * Recherche multicritere. Renvoie une liste normalisee.
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, int $limit = 10, ?string $codePostal = null): array
    {
        $params = ['q' => $query, 'page' => 1, 'per_page' => max(1, min(25, $limit))];
        if ($codePostal) {
            $params['code_postal'] = $codePostal;
        }

        $response = HttpClient::get(HttpClient::urlWithQuery($this->baseUrl . '/search', $params), [], $this->timeout);

        if ($response['error'] !== null || $response['status'] >= 400 || !is_array($response['json'])) {
            return [];
        }

        $results = $response['json']['results'] ?? [];
        return array_map(fn (array $item) => $this->normalize($item), $results);
    }

    public function findBySiren(string $siren): ?array
    {
        $siren = preg_replace('/\D/', '', $siren) ?? '';
        if (strlen($siren) !== 9) {
            return null;
        }

        $params   = ['q' => $siren, 'page' => 1, 'per_page' => 1];
        $response = HttpClient::get(HttpClient::urlWithQuery($this->baseUrl . '/search', $params), [], $this->timeout);

        if ($response['error'] !== null || $response['status'] >= 400 || !is_array($response['json'])) {
            return null;
        }

        foreach ($response['json']['results'] ?? [] as $item) {
            if (($item['siren'] ?? null) === $siren) {
                return $this->normalize($item);
            }
        }
        return null;
    }

    /** Transforme la reponse brute en structure commune a l'application. */
    public function normalize(array $item): array
    {
        $siege    = $item['siege'] ?? [];
        $finances = $item['finances'] ?? [];

        // On retient l'exercice le plus recent disponible.
        $annee = null;
        $ca = $resultat = null;
        if (is_array($finances) && $finances !== []) {
            $annees = array_filter(array_keys($finances), 'is_numeric');
            if ($annees !== []) {
                $annee = (int) max($annees);
                $f     = $finances[(string) $annee] ?? $finances[$annee] ?? [];
                $ca     = $f['ca'] ?? null;
                $resultat = $f['resultat_net'] ?? null;
            }
        }

        $effectif = $this->effectifLabel($item['tranche_effectif_salarie'] ?? ($siege['tranche_effectif_salarie'] ?? null));

        $etat = $item['etat_administratif'] ?? ($siege['etat_administratif'] ?? null);
        $statut = $etat === 'A' ? 'Active' : ($etat === 'C' ? 'Cessee' : null);

        $adresse = trim((string) ($siege['adresse'] ?? $siege['geo_adresse'] ?? ''));

        return [
            'raison_sociale'     => $item['nom_raison_sociale'] ?? ($item['nom_complet'] ?? ($item['nom_entreprise'] ?? '')),
            'forme_juridique'    => $item['nature_juridique'] ?? null,
            'siren'              => $item['siren'] ?? null,
            'siret'              => $siege['siret'] ?? null,
            'tva_intra'          => null,
            'ape_naf'            => $item['activite_principale'] ?? ($siege['activite_principale'] ?? null),
            'libelle_ape'        => null,
            'adresse'            => $adresse !== '' ? $adresse : null,
            'complement_adresse' => $siege['complement_adresse'] ?? null,
            'code_postal'        => $siege['code_postal'] ?? null,
            'ville'              => $siege['libelle_commune'] ?? ($siege['commune'] ?? null),
            'pays'               => 'France',
            'effectif'           => $effectif,
            'date_creation'      => $item['date_creation'] ?? ($siege['date_creation'] ?? null),
            'capital'            => $item['capital_social'] ?? null,
            'chiffre_affaires'   => $ca,
            'resultat_net'       => $resultat,
            'annee_finances'     => $annee,
            'statut_entreprise'  => $statut,
            'procedure_collective' => $this->procedureCollective($item),
            'score_solvabilite'  => null,
            'source_api'         => 'recherche-entreprises',
            'raw_data'           => json_encode($item, JSON_UNESCAPED_UNICODE),
            'dirigeants'         => $item['dirigeants'] ?? [],
        ];
    }

    private function procedureCollective(array $item): ?string
    {
        if (!empty($item['procedure_collective'])) {
            return is_string($item['procedure_collective'])
                ? $item['procedure_collective']
                : 'Procedure collective en cours';
        }
        if (!empty($item['complements']['est_en_procedure_collective'])) {
            return 'Procedure collective en cours';
        }
        return null;
    }

    private function effectifLabel(?string $code): ?string
    {
        $tranches = [
            '00' => '0 salarie', '01' => '1 a 2 salaries', '02' => '3 a 5 salaries',
            '03' => '6 a 9 salaries', '11' => '10 a 19 salaries', '12' => '20 a 49 salaries',
            '21' => '50 a 99 salaries', '22' => '100 a 199 salaries', '31' => '200 a 249 salaries',
            '32' => '250 a 499 salaries', '41' => '500 a 999 salaries', '42' => '1000 a 1999 salaries',
            '51' => '2000 a 4999 salaries', '52' => '5000 a 9999 salaries', '53' => '10000 salaries et plus',
        ];
        if ($code === null || $code === '') {
            return null;
        }
        return $tranches[$code] ?? null;
    }
}
