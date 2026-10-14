<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Services\Api\BodaccApi;
use App\Services\Api\PappersApi;
use App\Services\Api\RechercheEntreprisesApi;

/**
 * Orchestrateur de la recuperation automatique des donnees d'entreprise.
 *
 * Ordre de priorite (parametrable via "company_api_provider") :
 *   - auto                : Pappers si un jeton est present, sinon API de l'Etat
 *   - pappers             : Pappers uniquement
 *   - recherche_entreprises : API gratuite de l'Etat uniquement
 *
 * Dans tous les cas, la fiche peut etre enrichie avec des sources GRATUITES :
 *   - BODACC : procedures collectives / radiations (annonces officielles) ;
 *   - score de solvabilite interne (calcul local, sans service payant).
 */
class CompanyLookupService
{
    private RechercheEntreprisesApi $etat;
    private PappersApi $pappers;
    private BodaccApi $bodacc;
    private SolvabiliteService $solvabilite;
    private string $provider;

    public function __construct(?string $pappersToken = null)
    {
        $this->etat        = new RechercheEntreprisesApi();
        $this->pappers     = new PappersApi($pappersToken);
        $this->bodacc      = new BodaccApi();
        $this->solvabilite = new SolvabiliteService();
        $this->provider    = (string) Settings::get('company_api_provider', 'auto');
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function sourcesDisponibles(): array
    {
        return [
            'recherche_entreprises' => $this->etat->isConfigured(),
            'bodacc'                => $this->bodacc->isConfigured(),
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
    public function lookup(string $identifiant, ?float $encours = null): ?array
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

        $resultat = $this->enrichir($resultat, $encours);
        $resultat['_source'] = $source;
        return $resultat;
    }

    /**
     * Enrichit une fiche entreprise avec les sources gratuites :
     * procedures collectives (BODACC) et score de solvabilite interne.
     *
     * @param array<string,mixed> $donnees
     * @return array<string,mixed>
     */
    public function enrichir(array $donnees, ?float $encours = null): array
    {
        $siren = (string) ($donnees['siren'] ?? '');

        if ($siren !== '' && $this->bodacc->isConfigured()) {
            $procedure = $this->bodacc->procedureCollective($siren);
            if ($procedure !== null) {
                $donnees['procedure_collective']         = $procedure['libelle'];
                $donnees['procedure_collective_detail']  = $procedure;
                $donnees['_bodacc']                      = true;
            }
        }

        if ($this->scoreInterneActif()) {
            $donnees['score_solvabilite'] = $this->solvabilite->chaine($donnees, $encours);
        }

        return $donnees;
    }

    /** Details du score de solvabilite interne (score, label, couleur, facteurs). */
    public function analyseSolvabilite(array $entreprise, ?float $encours = null): array
    {
        return $this->solvabilite->calculer($entreprise, $encours);
    }

    /** Score de solvabilite interne formate (ex. "72/100 Correct"). */
    public function scoreSolvabilite(array $entreprise, ?float $encours = null): string
    {
        return $this->solvabilite->chaine($entreprise, $encours);
    }

    private function scoreInterneActif(): bool
    {
        return Settings::bool('score_interne_actif', true);
    }

    /**
     * Dossier complet d'une entreprise, utilisable SANS creer de client :
     * identite enrichie (libelles), historique financier, procedures
     * collectives actuelles et historiques (BODACC), dirigeants et autres
     * entreprises liees, et score de solvabilite.
     *
     * @return array<string,mixed>|null
     */
    public function dossier(string $identifiant, ?float $encours = null): ?array
    {
        $donnees = $this->lookup($identifiant, $encours);
        if ($donnees === null) {
            return null;
        }

        $siren = (string) ($donnees['siren'] ?? '');

        // Procedures collectives : actuelles et historiques (BODACC).
        $annonces = $siren !== '' ? $this->bodacc->proceduresEtRadiations($siren) : [];
        $donnees['procedures']          = $annonces;
        $donnees['procedures_par_annee'] = BodaccApi::parAnnee($annonces);
        $donnees['procedure_en_cours']   = $this->procedureEnCours($annonces);
        if ($donnees['procedure_en_cours'] !== null && empty($donnees['procedure_collective'])) {
            $donnees['procedure_collective'] = $donnees['procedure_en_cours']['libelle'] ?? 'Procedure collective en cours';
        }

        // Dirigeants et autres entreprises rattachees a chacun.
        $donnees['dirigeants_detail'] = $this->dirigeantsEtEntreprises($donnees);

        // Score de solvabilite detaille.
        $donnees['solvabilite'] = $this->solvabilite->calculer($donnees, $encours);

        return $donnees;
    }

    /**
     * Indique si une procedure collective est en cours d'apres la derniere
     * annonce BODACC (hors cloture / radiation / fin de procedure).
     *
     * @param array<int,array<string,mixed>> $annonces
     * @return array<string,mixed>|null
     */
    private function procedureEnCours(array $annonces): ?array
    {
        foreach ($annonces as $a) {
            $type = mb_strtolower((string) ($a['type'] ?? ''));
            if ($type === '' || str_contains($type, 'cloture') || str_contains($type, 'fin de')
                || str_contains($type, 'radiation')) {
                continue;
            }
            return $a;
        }
        return null;
    }

    /**
     * Pour chaque dirigeant, recherche les autres entreprises (SIREN distinct)
     * auxquelles il est rattache, via l'API Recherche d'entreprises.
     *
     * @param array<string,mixed> $donnees
     * @return array<int,array<string,mixed>>
     */
    public function dirigeantsEtEntreprises(array $donnees): array
    {
        $dirigeants = $donnees['dirigeants'] ?? [];
        if (!is_array($dirigeants) || $dirigeants === []) {
            return [];
        }

        $siren = (string) ($donnees['siren'] ?? '');
        $out   = [];
        foreach ($dirigeants as $d) {
            if (!is_array($d)) {
                continue;
            }
            $entree = $d;
            $entree['entreprises'] = $this->entreprisesDuDirigeant($d, $siren);
            $out[] = $entree;
        }
        return $out;
    }

    /**
     * Autres entreprises liees a un dirigeant (personne physique).
     *
     * @param array<string,mixed> $dirigeant
     * @return array<int,array<string,mixed>>
     */
    private function entreprisesDuDirigeant(array $dirigeant, string $sirenExclu): array
    {
        $nom     = trim((string) ($dirigeant['nom'] ?? ''));
        $prenoms = trim((string) ($dirigeant['prenoms'] ?? ''));
        // Dirigeant personne morale : on ne cherche pas par nom de famille.
        if ($nom === '' || (($dirigeant['type'] ?? '') === 'personne morale' && $prenoms === '')) {
            return [];
        }

        $cible = self::normaliserNom($prenoms . ' ' . $nom);
        if ($cible === '') {
            return [];
        }

        $resultats = $this->etat->search(trim($prenoms . ' ' . $nom), 25);
        $out = [];
        foreach ($resultats as $r) {
            $siren = (string) ($r['siren'] ?? '');
            if ($siren === '' || $siren === $sirenExclu) {
                continue;
            }
            foreach (($r['dirigeants'] ?? []) as $d) {
                if (!is_array($d)) {
                    continue;
                }
                $nomComplet = self::normaliserNom(((string) ($d['prenoms'] ?? '')) . ' ' . ((string) ($d['nom'] ?? '')));
                if ($nomComplet !== '' && $nomComplet === $cible) {
                    $out[$siren] = [
                        'siren'           => $siren,
                        'raison_sociale'  => $r['raison_sociale'] ?? '',
                        'ville'           => $r['ville'] ?? '',
                        'statut'          => $r['statut_entreprise'] ?? '',
                        'forme_juridique' => $r['forme_juridique'] ?? null,
                        'libelle_ape'     => $r['libelle_ape'] ?? null,
                        'qualite'         => $d['qualite'] ?? null,
                    ];
                    break;
                }
            }
        }

        return array_slice(array_values($out), 0, 15);
    }

    /** Normalise un nom (minuscules, sans accents, espaces compresses). */
    private static function normaliserNom(string $nom): string
    {
        $nom = mb_strtolower(trim($nom));
        $nom = strtr($nom, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ÿ' => 'y',
            'œ' => 'oe', 'æ' => 'ae',
        ]);
        $nom = preg_replace('/[^a-z0-9 ]/', ' ', $nom) ?? $nom;
        $nom = preg_replace('/\s+/', ' ', $nom) ?? $nom;
        return trim($nom);
    }

    /**
     * Extrait et normalise les dirigeants stockes dans raw_data (JSON), pour
     * l'affichage sur la fiche client sans requete API supplementaire.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function dirigeantsFromRaw(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        $dirigeants = $data['dirigeants'] ?? ($data['entreprise']['dirigeants'] ?? []);
        return RechercheEntreprisesApi::normaliserDirigeants($dirigeants);
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

        return $this->enrichir($this->etat->normalize($response['json']['results'][0]));
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
