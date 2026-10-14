<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Notes libres rattachees a la fiche d'un client.
 * Elles sont visibles en lecture seule par les agences (comptes en
 * consultation et lien de partage public).
 */
class ClientNote extends Model
{
    protected string $table = 'client_notes';
    protected array $fillable = ['client_id', 'user_id', 'contenu'];

    /** @return array<int,array<string,mixed>> */
    public function forClient(int $clientId): array
    {
        return Database::fetchAll(
            'SELECT n.*, u.full_name AS user_nom, u.username
             FROM client_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.client_id = :id
             ORDER BY n.created_at DESC',
            ['id' => $clientId]
        );
    }

    public function belongsTo(int $noteId, int $clientId): bool
    {
        return Database::exists(
            'client_notes',
            'id = :id AND client_id = :c',
            ['id' => $noteId, 'c' => $clientId]
        );
    }
}
