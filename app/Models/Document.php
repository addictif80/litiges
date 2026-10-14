<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Document extends Model
{
    protected string $table = 'documents';
    protected array $fillable = [
        'litige_id', 'client_id', 'categorie', 'nom_original', 'nom_stocke', 'mime', 'taille', 'uploaded_by',
    ];

    public function forLitige(int $litigeId): array
    {
        return Database::fetchAll(
            'SELECT d.*, u.full_name AS user_nom
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.litige_id = :id
             ORDER BY d.created_at DESC',
            ['id' => $litigeId]
        );
    }

    public function forClient(int $clientId): array
    {
        return Database::fetchAll(
            'SELECT d.*, u.full_name AS user_nom
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.client_id = :id
             ORDER BY d.created_at DESC',
            ['id' => $clientId]
        );
    }

    public function findFull(int $id): ?array
    {
        return Database::fetch(
            'SELECT d.*, l.reference, l.client_id AS litige_client_id
             FROM documents d
             LEFT JOIN litiges l ON l.id = d.litige_id
             WHERE d.id = :id LIMIT 1',
            ['id' => $id]
        );
    }
}
