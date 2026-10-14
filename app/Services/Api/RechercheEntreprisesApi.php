<?php
declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Services\HttpClient;
use App\Services\ReferentielService;

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

        $apeCode  = $item['activite_principale'] ?? ($siege['activite_principale'] ?? null);
        $apeNaf25 = $item['activite_principale_naf25'] ?? ($siege['activite_principale_naf25'] ?? null);
        $formeCode = $item['nature_juridique'] ?? null;

        return [
            'raison_sociale'     => $item['nom_raison_sociale'] ?? ($item['nom_complet'] ?? ($item['nom_entreprise'] ?? '')),
            'forme_juridique'    => ReferentielService::formeJuridique($formeCode !== null ? (string) $formeCode : null),
            'forme_juridique_code' => $formeCode,
            'siren'              => $item['siren'] ?? null,
            'siret'              => $siege['siret'] ?? null,
            'tva_intra'          => null,
            'ape_naf'            => $apeCode,
            'ape_naf_2025'       => $apeNaf25,
            'libelle_ape'        => ReferentielService::libelleApe($apeCode !== null ? (string) $apeCode : null)
                                    ?? ReferentielService::naf2025($apeNaf25 !== null ? (string) $apeNaf25 : null),
            'section_ape'          => ReferentielService::sectionCode($apeCode !== null ? (string) $apeCode : null),
            'section_ape_libelle'  => ReferentielService::section($apeCode !== null ? (string) $apeCode : null),
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
            'finances_historique' => $this->financesHistorique($finances),
            'statut_entreprise'  => $statut,
            'procedure_collective' => $this->procedureCollective($item),
            'score_solvabilite'  => null,
            'source_api'         => 'recherche-entreprises',
            'raw_data'           => json_encode($item, JSON_UNESCAPED_UNICODE),
            'dirigeants'         => self::normaliserDirigeants($item['dirigeants'] ?? []),
        ];
    }

    /**
     * Historique des exercices comptables, du plus recent au plus ancien.
     * @return array<int,array<string,mixed>>
     */
    private function financesHistorique(mixed $finances): array
    {
        if (!is_array($finances) || $finances === []) {
            return [];
        }
        $out = [];
        foreach ($finances as $annee => $f) {
            if (!is_numeric($annee) || !is_array($f)) {
                continue;
            }
            $out[] = [
                'annee'        => (int) $annee,
                'ca'           => $f['ca'] ?? null,
                'resultat_net' => $f['resultat_net'] ?? null,
            ];
        }
        usort($out, static fn (array $a, array $b): int => $b['annee'] <=> $a['annee']);
        return $out;
    }

    /**
     * Normalise la liste des dirigeants (personnes physiques et morales).
     * @return array<int,array<string,mixed>>
     */
    public static function normaliserDirigeants(mixed $dirigeants): array
    {
        if (!is_array($dirigeants)) {
            return [];
        }
        $out = [];
        foreach ($dirigeants as $d) {
            if (!is_array($d)) {
                continue;
            }
            $type = $d['type_dirigeant'] ?? (isset($d['denomination']) ? 'personne morale' : 'personne physique');
            $out[] = [
                'nom'             => $d['nom'] ?? null,
                'prenoms'         => $d['prenoms'] ?? ($d['prenom'] ?? null),
                'qualite'         => $d['qualite'] ?? null,
                'annee_naissance' => $d['annee_de_naissance'] ?? null,
                'type'            => $type,
                'denomination'    => $d['denomination'] ?? null,
                'siren'           => $d['siren'] ?? null,
                'represente_par'  => $d['represente_par'] ?? null,
            ];
        }
        return $out;
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
