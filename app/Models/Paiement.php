<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Paiement extends Model
{
    protected string $table = 'paiements';
    protected array $fillable = [
        'litige_id', 'date_paiement', 'montant', 'mode', 'reference', 'commentaire',
        'source', 'source_ref', 'created_by',
    ];

    public function forLitige(int $litigeId): array
    {
        return Database::fetchAll(
            'SELECT p.*, u.full_name AS user_nom, u.username
             FROM paiements p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.litige_id = :id
             ORDER BY p.date_paiement DESC, p.id DESC',
            ['id' => $litigeId]
        );
    }

    /** @return array<int,array{date:string,montant:float}> pour le calcul des penalites */
    public function chronologie(int $litigeId): array
    {
        $lignes = Database::fetchAll(
            'SELECT date_paiement, montant FROM paiements WHERE litige_id = :id ORDER BY date_paiement ASC, id ASC',
            ['id' => $litigeId]
        );
        return array_map(
            static fn (array $l): array => ['date' => (string) $l['date_paiement'], 'montant' => (float) $l['montant']],
            $lignes
        );
    }

    public function total(int $litigeId): float
    {
        return (float) Database::value(
            'SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE litige_id = :id',
            ['id' => $litigeId],
            0
        );
    }

    public function existeSource(int $litigeId, string $source, string $ref): bool
    {
        return Database::exists(
            'paiements',
            'litige_id = :l AND source = :s AND source_ref = :r',
            ['l' => $litigeId, 's' => $source, 'r' => $ref]
        );
    }
}
