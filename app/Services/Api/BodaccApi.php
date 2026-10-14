<?php
declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Settings;
use App\Services\HttpClient;

/**
 * API BODACC (Bulletin officiel des annonces civiles et commerciales).
 *
 * Donnees ouvertes, gratuites et sans cle (DILA, exposees via Opendatasoft).
 * Sert a detecter les procedures collectives (redressement, liquidation,
 * sauvegarde, conciliation, retablissement professionnel) et les radiations.
 *
 * Jeu de donnees : "annonces-commerciales".
 */
class BodaccApi
{
    /** URL complete de l'endpoint "records" du jeu de donnees. */
    private string $recordsUrl;
    private int $timeout;

    public function __construct()
    {
        $host    = rtrim((string) Config::get('apis.bodacc.base_url', 'https://bodacc-datadila.opendatasoft.com'), '/');
        $dataset = (string) Config::get('apis.bodacc.dataset', 'annonces-commerciales');
        $this->recordsUrl = $host . '/api/explore/v2.1/catalog/datasets/' . $dataset . '/records';
        $this->timeout    = (int) Config::get('apis.bodacc.timeout', 8);
    }

    public function isConfigured(): bool
    {
        return Settings::bool('bodacc_actif', (bool) Config::get('apis.bodacc.actif', true));
    }

    /**
     * Derniere procedure collective/conciliation connue pour un SIREN.
     * @return array<string,mixed>|null
     */
    public function procedureCollective(string $siren): ?array
    {
        $annonces = $this->annonces($siren, 'Procédures%', 10);
        if ($annonces === []) {
            return null;
        }
        // Les annonces sont triees de la plus recente a la plus ancienne.
        return $annonces[0];
    }

    /**
     * Annonces BODACC publiees pour un SIREN.
     *
     * @param string $familleLike Filtre SQL "like" sur familleavis_lib (ex. "Procédures%").
     * @return array<int,array<string,mixed>>
     */
    public function annonces(string $siren, string $familleLike = '', int $limit = 10): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $siren = preg_replace('/\D/', '', $siren) ?? '';
        if (strlen($siren) !== 9) {
            return [];
        }

        $where = 'registre="' . $siren . '"';
        if ($familleLike !== '') {
            $where .= ' AND familleavis_lib like "' . $familleLike . '"';
        }

        $response = HttpClient::get(
            HttpClient::urlWithQuery($this->recordsUrl, [
                'where'    => $where,
                'order_by' => 'dateparution desc',
                'limit'    => max(1, min(100, $limit)),
                'select'   => 'registre,commercant,familleavis_lib,typeavis_lib,dateparution,'
                              . 'numeroannonce,jugement,acte,depot,radiationaurcs,tribunal,ville,url_complete',
            ]),
            [],
            $this->timeout
        );

        if ($response['error'] !== null || $response['status'] >= 400 || !is_array($response['json'])) {
            return [];
        }

        $results = $response['json']['results'] ?? [];
        if (!is_array($results)) {
            return [];
        }

        return array_map(fn (array $item): array => $this->normalize($item), $results);
    }

    /** Transforme une annonce brute BODACC en structure commune a l'application. */
    public function normalize(array $item): array
    {
        $famille  = (string) ($item['familleavis_lib'] ?? '');
        $jugement = $this->decodeJugement($item['jugement'] ?? null);
        $type     = $this->typeProcedure($famille, $jugement, $item);

        return [
            'famille'       => $famille,
            'type'          => $type,
            'nature'        => $jugement['nature'] ?? ($item['typeavis_lib'] ?? null),
            'date'          => $jugement['date'] ?? null,
            'date_parution' => $item['dateparution'] ?? null,
            'numero'        => $item['numeroannonce'] ?? null,
            'tribunal'      => $item['tribunal'] ?? null,
            'commercant'    => $item['commercant'] ?? null,
            'texte'         => $jugement['complementJugement'] ?? null,
            'url'           => $item['url_complete'] ?? null,
            'libelle'       => $this->libelle($type, $jugement, $item),
        ];
    }

    private function decodeJugement(mixed $jugement): array
    {
        if (is_array($jugement)) {
            return $jugement;
        }
        if (is_string($jugement) && $jugement !== '') {
            $decoded = json_decode($jugement, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    /** Devine le type de procedure d'apres le texte du jugement. */
    private function typeProcedure(string $famille, array $jugement, array $item): ?string
    {
        $texte = mb_strtolower(
            ($jugement['complementJugement'] ?? '') . ' '
            . ($jugement['nature'] ?? '') . ' '
            . $famille . ' '
            . ($item['typeavis_lib'] ?? '')
        );

        $motifs = [
            'liquidation judiciaire'       => 'Liquidation judiciaire',
            'redressement judiciaire'      => 'Redressement judiciaire',
            'sauvegarde'                   => 'Sauvegarde',
            'conciliation'                 => 'Conciliation',
            'rétablissement professionnel' => 'Rétablissement professionnel',
            'retablissement professionnel' => 'Rétablissement professionnel',
            'radiation'                    => 'Radiation',
            'clôture'                      => 'Clôture procédure',
            'cloture'                      => 'Clôture procédure',
            'fin de procédure'             => 'Fin de procédure',
            'fin de la procédure'          => 'Fin de procédure',
        ];
        foreach ($motifs as $motif => $libelle) {
            if (str_contains($texte, $motif)) {
                return $libelle;
            }
        }

        return $famille !== '' ? $famille : null;
    }

    private function libelle(?string $type, array $jugement, array $item): string
    {
        $type = $type ?: 'Procedure collective';
        // On prefere la date du jugement, sinon la date de parution.
        $date = $jugement['date'] ?? ($item['dateparution'] ?? null);

        return $date ? ($type . ' (BODACC ' . $date . ')') : $type;
    }
}
