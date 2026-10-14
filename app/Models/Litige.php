<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Litige extends Model
{
    protected string $table = 'litiges';
    protected array $fillable = [
        'reference', 'client_id', 'agence_id', 'objet', 'description', 'type_litige',
        'montant_principal', 'montant_penalites', 'montant_recupere', 'devise',
        'date_ouverture', 'date_echeance', 'date_cloture', 'statut_id', 'finalite_id',
        'responsable_id', 'priorite', 'numero_facture', 'date_facture', 'numero_contrat',
        'materiel', 'reference_externe', 'prochaine_relance_at', 'delai_relance_jours',
        'derniere_relance_at', 'created_by',
    ];

    private const SELECT_BASE = "
        SELECT l.*,
               c.raison_sociale,
               c.siren AS client_siren,
               c.ville AS client_ville,
               s.libelle  AS statut_libelle,
               s.couleur  AS statut_couleur,
               s.cloturant AS statut_cloturant,
               f.libelle  AS finalite_libelle,
               f.couleur  AS finalite_couleur,
               a.nom      AS agence_nom,
               u.full_name AS responsable_nom,
               u.username  AS responsable_username,
               (l.montant_principal + l.montant_penalites - l.montant_recupere) AS encours
        FROM litiges l
        JOIN clients c        ON c.id = l.client_id
        JOIN litige_statuts s ON s.id = l.statut_id
        LEFT JOIN litige_statuts f ON f.id = l.finalite_id
        LEFT JOIN agences a   ON a.id = l.agence_id
        LEFT JOIN users u     ON u.id = l.responsable_id
    ";

    public function findFull(int $id): ?array
    {
        return Database::fetch(self::SELECT_BASE . ' WHERE l.id = :id LIMIT 1', ['id' => $id]);
    }

    /**
     * Recherche paginee des litiges.
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public function search(array $f, int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = $this->buildWhere($f);

        $enable = [];

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM litiges l
             JOIN clients c ON c.id = l.client_id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE {$where}",
            $params,
            0
        );

        $page   = max(1, $page);
        $perPage = min(200, max(5, $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            self::SELECT_BASE . " WHERE {$where} ORDER BY {$this->orderBy($f)} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows'  => $rows,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    private function orderBy(array $f): string
    {
        return match ($f['tri'] ?? 'recent') {
            'ancien'      => 'l.date_ouverture ASC',
            'montant'     => 'encours DESC',
            'montant_asc' => 'encours ASC',
            'client'      => 'c.raison_sociale ASC',
            'echeance'    => 'l.date_echeance ASC',
            'priorite'    => "FIELD(l.priorite,'critique','haute','normale','basse') ASC",
            default       => 'l.date_ouverture DESC, l.id DESC',
        };
    }

    /** @return array{0:string,1:array} */
    private function buildWhere(array $f): array
    {
        $conds  = [];
        $params = [];

        if (!empty($f['q'])) {
            $conds[] = '(l.reference LIKE :q OR l.objet LIKE :q OR c.raison_sociale LIKE :q OR l.numero_facture LIKE :q OR l.reference_externe LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['client_id'])) {
            $conds[] = 'l.client_id = :client_id';
            $params['client_id'] = (int) $f['client_id'];
        }
        if (!empty($f['only_id'])) {
            $conds[] = 'l.id = :only_id';
            $params['only_id'] = (int) $f['only_id'];
        }
        if (!empty($f['agence_id'])) {
            $conds[] = 'l.agence_id = :agence_id';
            $params['agence_id'] = (int) $f['agence_id'];
        }
        if (!empty($f['statut_id'])) {
            $conds[] = 'l.statut_id = :statut_id';
            $params['statut_id'] = (int) $f['statut_id'];
        }
        if (!empty($f['finalite_id'])) {
            $conds[] = 'l.finalite_id = :finalite_id';
            $params['finalite_id'] = (int) $f['finalite_id'];
        }
        if (!empty($f['responsable_id'])) {
            $conds[] = 'l.responsable_id = :responsable_id';
            $params['responsable_id'] = (int) $f['responsable_id'];
        }
        if (!empty($f['priorite'])) {
            $conds[] = 'l.priorite = :priorite';
            $params['priorite'] = $f['priorite'];
        }
        if (!empty($f['type_litige'])) {
            $conds[] = 'l.type_litige = :type_litige';
            $params['type_litige'] = $f['type_litige'];
        }
        if (!empty($f['date_from'])) {
            $conds[] = 'l.date_ouverture >= :date_from';
            $params['date_from'] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $conds[] = 'l.date_ouverture <= :date_to';
            $params['date_to'] = $f['date_to'];
        }
        if (($f['etat'] ?? '') === 'ouverts') {
            $conds[] = 's.cloturant = 0';
        } elseif (($f['etat'] ?? '') === 'clotures') {
            $conds[] = 's.cloturant = 1';
        }
        if (!empty($f['relance_due'])) {
            $conds[] = 's.cloturant = 0 AND l.prochaine_relance_at IS NOT NULL AND l.prochaine_relance_at <= CURDATE()';
        }
        if (!empty($f['promesse_depassee'])) {
            $conds[] = 'EXISTS (SELECT 1 FROM relances r WHERE r.litige_id = l.id AND r.date_promesse IS NOT NULL AND r.date_promesse < CURDATE())';
        }

        return [$conds === [] ? '1' : implode(' AND ', $conds), $params];
    }

    /**
     * Genere une reference unique du type LIT-2026-0042.
     */
    public function generateReference(): string
    {
        $annee = date('Y');
        for ($i = 0; $i < 5; $i++) {
            $max = Database::value(
                'SELECT MAX(CAST(SUBSTRING_INDEX(reference, \'-\', -1) AS UNSIGNED))
                 FROM litiges WHERE reference LIKE :prefix',
                ['prefix' => "LIT-{$annee}-%"],
                0
            );
            $reference = sprintf('LIT-%s-%04d', $annee, ((int) $max) + 1 + $i);
            if (!Database::exists('litiges', 'reference = :r', ['r' => $reference])) {
                return $reference;
            }
        }
        return 'LIT-' . $annee . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    // ------------------------------------------------------------------
    //  Statistiques
    // ------------------------------------------------------------------

    public function statsGlobal(): array
    {
        $row = Database::fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN s.cloturant = 0 THEN 1 ELSE 0 END) AS ouverts,
                SUM(CASE WHEN s.cloturant = 1 THEN 1 ELSE 0 END) AS clotures,
                COALESCE(SUM(l.montant_principal + l.montant_penalites), 0) AS montant_total,
                COALESCE(SUM(l.montant_recupere), 0) AS montant_recupere,
                COALESCE(SUM(CASE WHEN s.cloturant = 0 THEN l.montant_principal + l.montant_penalites - l.montant_recupere ELSE 0 END), 0) AS encours,
                AVG(CASE WHEN s.cloturant = 1 AND l.date_cloture IS NOT NULL
                         THEN DATEDIFF(l.date_cloture, l.date_ouverture) END) AS delai_moyen
             FROM litiges l
             JOIN litige_statuts s ON s.id = l.statut_id"
        ) ?? [];

        $row['taux_recouvrement'] = ((float) ($row['montant_total'] ?? 0)) > 0
            ? round(((float) $row['montant_recupere'] / (float) $row['montant_total']) * 100, 1)
            : 0.0;

        return $row;
    }

    public function statsParStatut(): array
    {
        return Database::fetchAll(
            "SELECT s.libelle, s.couleur, COUNT(l.id) AS nb,
                    COALESCE(SUM(l.montant_principal + l.montant_penalites - l.montant_recupere), 0) AS encours
             FROM litige_statuts s
             LEFT JOIN litiges l ON l.statut_id = s.id
             WHERE s.categorie = 'statut'
             GROUP BY s.id, s.libelle, s.couleur, s.ordre
             HAVING nb > 0
             ORDER BY s.ordre ASC"
        );
    }

    public function statsParAgence(): array
    {
        return Database::fetchAll(
            "SELECT COALESCE(a.nom, 'Non affectee') AS agence,
                    COUNT(l.id) AS nb,
                    COALESCE(SUM(CASE WHEN s.cloturant = 0 THEN l.montant_principal + l.montant_penalites - l.montant_recupere ELSE 0 END), 0) AS encours
             FROM litiges l
             JOIN litige_statuts s ON s.id = l.statut_id
             LEFT JOIN agences a ON a.id = l.agence_id
             GROUP BY agence
             ORDER BY encours DESC"
        );
    }

    public function statsParResponsable(): array
    {
        return Database::fetchAll(
            "SELECT COALESCE(u.full_name, u.username, 'Non affecte') AS responsable,
                    COUNT(l.id) AS nb,
                    COALESCE(SUM(CASE WHEN s.cloturant = 0 THEN l.montant_principal + l.montant_penalites - l.montant_recupere ELSE 0 END), 0) AS encours
             FROM litiges l
             JOIN litige_statuts s ON s.id = l.statut_id
             LEFT JOIN users u ON u.id = l.responsable_id
             GROUP BY responsable
             ORDER BY encours DESC"
        );
    }

    public function topClients(int $limit = 5): array
    {
        return Database::fetchAll(
            "SELECT c.raison_sociale, c.id,
                    COUNT(l.id) AS nb,
                    COALESCE(SUM(l.montant_principal + l.montant_penalites - l.montant_recupere), 0) AS encours
             FROM clients c
             JOIN litiges l ON l.client_id = c.id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0
             GROUP BY c.id, c.raison_sociale
             ORDER BY encours DESC
             LIMIT " . max(1, min(50, $limit))
        );
    }

    public function evolutionMensuelle(int $mois = 12): array
    {
        return Database::fetchAll(
            "SELECT DATE_FORMAT(l.date_ouverture, '%Y-%m') AS periode,
                    COUNT(*) AS nb,
                    COALESCE(SUM(l.montant_principal + l.montant_penalites), 0) AS montant
             FROM litiges l
             WHERE l.date_ouverture >= DATE_SUB(CURDATE(), INTERVAL :m MONTH)
             GROUP BY periode
             ORDER BY periode ASC",
            ['m' => $mois]
        );
    }

    public function listeRelanceDue(): array
    {
        return Database::fetchAll(
            self::SELECT_BASE . "
             WHERE s.cloturant = 0
               AND l.prochaine_relance_at IS NOT NULL
               AND l.prochaine_relance_at <= CURDATE()
             ORDER BY l.prochaine_relance_at ASC"
        );
    }

    public function listePromessesDepassees(): array
    {
        return Database::fetchAll(
            self::SELECT_BASE . "
             WHERE s.cloturant = 0
               AND EXISTS (
                    SELECT 1 FROM relances r
                    WHERE r.litige_id = l.id
                      AND r.date_promesse IS NOT NULL
                      AND r.date_promesse < CURDATE()
               )
             ORDER BY l.id DESC"
        );
    }
}
