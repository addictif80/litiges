<?php
declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Settings;
use App\Services\HttpClient;
use App\Services\ReferentielService;

/**
 * API Pappers v2 (https://www.pappers.fr/api).
 * Necessite un jeton (payant selon l'usage). Si aucun jeton n'est present,
 * l'API est consideree comme non configuree.
 */
class PappersApi
{
    private string $baseUrl;
    private int $timeout;
    private string $token;

    public function __construct(?string $token = null)
    {
        $this->baseUrl = rtrim((string) Config::get('apis.pappers.base_url', 'https://api.pappers.fr/v2'), '/');
        $this->timeout = (int) Config::get('apis.pappers.timeout', 8);
        $this->token   = $token ?? (string) (Settings::get('pappers_api_token') ?: Config::get('apis.pappers.api_token', ''));
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    /** @return array<int,array<string,mixed>> */
    public function search(string $query, int $limit = 10): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $response = HttpClient::get(
            HttpClient::urlWithQuery($this->baseUrl . '/recherche', [
                'api_token' => $this->token,
                'q'         => $query,
                'par_page'  => max(1, min(25, $limit)),
            ]),
            [],
            $this->timeout
        );

        if ($response['error'] !== null || $response['status'] >= 400 || !is_array($response['json'])) {
            return [];
        }

        $resultats = $response['json']['resultats'] ?? ($response['json']['results'] ?? []);

        return array_map(function (array $item): array {
            $normalized = $this->normalizeEnrichi($item);
            return $normalized;
        }, $resultats);
    }

    public function findBySiren(string $siren): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $siren = preg_replace('/\D/', '', $siren) ?? '';
        if (strlen($siren) !== 9) {
            return null;
        }

        $response = HttpClient::get(
            HttpClient::urlWithQuery($this->baseUrl . '/entreprise', [
                'api_token' => $this->token,
                'siren'     => $siren,
            ]),
            [],
            $this->timeout
        );

        if ($response['error'] !== null || $response['status'] >= 400 || !is_array($response['json'])) {
            return null;
        }

        return $this->normalizeEnrichi($response['json']);
    }

    public function normalizeEnrichi(array $data): array
    {
        // Pappers peut renvoyer les donnees a la racine ou sous "entreprise".
        $e = $data['entreprise'] ?? $data;
        $siege = $data['siege'] ?? ($e['siege'] ?? []);

        $finances = $data['finances'] ?? ($e['finances'] ?? []);
        $annee = null; $ca = null; $resultat = null;
        if (is_array($finances) && $finances !== []) {
            if ($this->estListe($finances)) {
                $dernier = $finances[0] ?? [];
                $annee    = $dernier['annee'] ?? null;
                $ca       = $dernier['chiffre_affaires'] ?? null;
                $resultat = $dernier['resultat'] ?? ($dernier['resultat_net'] ?? null);
            } else {
                $annees = array_filter(array_keys($finances), 'is_numeric');
                if ($annees !== []) {
                    $annee = (int) max($annees);
                    $f     = $finances[(string) $annee] ?? $finances[$annee] ?? [];
                    $ca       = $f['chiffre_affaires'] ?? ($f['ca'] ?? null);
                    $resultat = $f['resultat'] ?? ($f['resultat_net'] ?? null);
                }
            }
        }

        $procedure = null;
        if (!empty($data['procedure_collective']) || !empty($e['procedure_collective'])) {
            $pc = $data['procedure_collective'] ?? $e['procedure_collective'] ?? null;
            $procedure = is_array($pc)
                ? ($pc['type'] ?? $pc['libelle'] ?? 'Procedure collective en cours')
                : (string) $pc;
        } elseif (!empty($data['procedure_collective_existe']) || !empty($e['procedure_collective_existe'])) {
            $procedure = 'Procedure collective en cours';
        }

        $statut = $e['statut_rcs'] ?? ($e['statut'] ?? null);
        if (!$statut && !empty($e['date_radiation'])) {
            $statut = 'Radiee le ' . $e['date_radiation'];
        }

        $apeCode = $e['code_naf'] ?? null;

        return [
            'raison_sociale'     => $e['nom_entreprise'] ?? ($e['nom_complet'] ?? ($e['denomination'] ?? '')),
            'forme_juridique'    => ReferentielService::formeJuridique(
                isset($e['forme_juridique']) ? (string) $e['forme_juridique'] : null
            ),
            'forme_juridique_code' => $e['forme_juridique_code'] ?? null,
            'siren'              => $e['siren'] ?? null,
            'siret'              => $siege['siret'] ?? ($e['siret'] ?? null),
            'tva_intra'          => $e['numero_tva_intracommunautaire'] ?? ($e['tva_intracommunautaire'] ?? null),
            'ape_naf'            => $apeCode,
            'libelle_ape'        => $e['libelle_code_naf'] ?? ReferentielService::libelleApe($apeCode !== null ? (string) $apeCode : null),
            'section_ape'          => ReferentielService::sectionCode($apeCode !== null ? (string) $apeCode : null),
            'section_ape_libelle'  => ReferentielService::section($apeCode !== null ? (string) $apeCode : null),
            'adresse'            => $siege['adresse'] ?? ($e['adresse'] ?? null),
            'complement_adresse' => $siege['complement_adresse'] ?? null,
            'code_postal'        => $siege['code_postal'] ?? null,
            'ville'              => $siege['ville'] ?? null,
            'pays'               => $siege['pays'] ?? 'France',
            'effectif'           => isset($e['effectif']) ? (string) $e['effectif'] : null,
            'date_creation'      => $e['date_creation'] ?? null,
            'capital'            => $e['capital'] ?? ($e['capital_formate'] ?? null),
            'chiffre_affaires'   => $ca,
            'resultat_net'       => $resultat,
            'annee_finances'     => $annee,
            'finances_historique' => $this->financesHistorique($finances),
            'statut_entreprise'  => is_string($statut) ? $statut : null,
            'procedure_collective' => $procedure,
            'score_solvabilite'  => isset($data['score_financier']['score'])
                ? (string) $data['score_financier']['score']
                : null,
            'source_api'         => 'pappers',
            'raw_data'           => json_encode($data, JSON_UNESCAPED_UNICODE),
            'dirigeants'         => RechercheEntreprisesApi::normaliserDirigeants($data['dirigeants'] ?? ($e['dirigeants'] ?? [])),
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
        if ($this->estListe($finances)) {
            foreach ($finances as $f) {
                if (!is_array($f) || !isset($f['annee'])) {
                    continue;
                }
                $out[] = [
                    'annee'        => (int) $f['annee'],
                    'ca'           => $f['chiffre_affaires'] ?? ($f['ca'] ?? null),
                    'resultat_net' => $f['resultat'] ?? ($f['resultat_net'] ?? null),
                ];
            }
        } else {
            foreach ($finances as $annee => $f) {
                if (!is_numeric($annee) || !is_array($f)) {
                    continue;
                }
                $out[] = [
                    'annee'        => (int) $annee,
                    'ca'           => $f['chiffre_affaires'] ?? ($f['ca'] ?? null),
                    'resultat_net' => $f['resultat'] ?? ($f['resultat_net'] ?? null),
                ];
            }
        }
        usort($out, static fn (array $a, array $b): int => $b['annee'] <=> $a['annee']);
        return $out;
    }

    /** Indique si un tableau est une liste (indices 0..n-1). Compatible PHP 8.0. */
    private function estListe(array $t): bool
    {
        return $t !== [] && array_keys($t) === range(0, count($t) - 1);
    }
}
