<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Client extends Model
{
    protected string $table = 'clients';
    protected array $fillable = [
        'raison_sociale', 'forme_juridique', 'siren', 'siret', 'tva_intra',
        'ape_naf', 'libelle_ape', 'adresse', 'complement_adresse', 'code_postal',
        'ville', 'pays', 'effectif', 'date_creation', 'capital', 'chiffre_affaires',
        'resultat_net', 'annee_finances', 'statut_entreprise', 'procedure_collective',
        'score_solvabilite', 'source_api', 'raw_data', 'data_synced_at', 'notes',
        'actif', 'created_by',
    ];

    /**
     * Recherche paginee de clients.
     * @return array{rows: array, total: int}
     */
    public function search(string $q, int $page = 1, int $perPage = 25): array
    {
        $where  = '1';
        $params = [];

        if ($q !== '') {
            $where = '(c.raison_sociale LIKE :q1 OR c.siren LIKE :q2 OR c.siret LIKE :q3 OR c.ville LIKE :q4)';
            $like  = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $total = (int) Database::value("SELECT COUNT(*) FROM clients c WHERE {$where}", $params, 0);
        $page  = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM litiges l WHERE l.client_id = c.id) AS nb_litiges,
                    (SELECT COALESCE(SUM(l.montant_principal + l.montant_penalites - l.montant_recupere),0)
                        FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
                        WHERE l.client_id = c.id AND s.cloturant = 0) AS encours
             FROM clients c
             WHERE {$where}
             ORDER BY c.raison_sociale ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function findBySiren(string $siren): ?array
    {
        return $this->findBy('siren', $siren);
    }

    /** Cree ou met a jour un client a partir des donnees d'une API entreprise. */
    public function upsertFromApi(array $mapped, int $userId): int
    {
        $siren = $mapped['siren'] ?? null;
        $existing = $siren ? $this->findBySiren((string) $siren) : null;

        $mapped['source_api']    = $mapped['source_api'] ?? null;
        $mapped['data_synced_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            $this->update((int) $existing['id'], $mapped);
            return (int) $existing['id'];
        }

        $mapped['created_by'] = $userId;
        return $this->create($mapped);
    }

    public function contacts(int $clientId): array
    {
        return Database::fetchAll(
            'SELECT * FROM contacts WHERE client_id = :id ORDER BY principal DESC, nom ASC',
            ['id' => $clientId]
        );
    }

    public function litiges(int $clientId): array
    {
        return Database::fetchAll(
            'SELECT l.*, s.libelle AS statut_libelle, s.couleur AS statut_couleur, s.cloturant AS statut_cloturant, f.libelle AS finalite_libelle
             FROM litiges l
             JOIN litige_statuts s ON s.id = l.statut_id
             LEFT JOIN litige_statuts f ON f.id = l.finalite_id
             WHERE l.client_id = :id
             ORDER BY l.date_ouverture DESC',
            ['id' => $clientId]
        );
    }
}
