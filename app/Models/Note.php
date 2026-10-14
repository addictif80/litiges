<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Note extends Model
{
    protected string $table = 'notes';
    protected array $fillable = ['litige_id', 'user_id', 'contenu'];

    public function forLitige(int $litigeId): array
    {
        return Database::fetchAll(
            'SELECT n.*, u.full_name AS user_nom, u.username
             FROM notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.litige_id = :id
             ORDER BY n.created_at DESC',
            ['id' => $litigeId]
        );
    }

    public function belongsTo(int $noteId, int $litigeId): bool
    {
        return Database::exists('notes', 'id = :id AND litige_id = :l', ['id' => $noteId, 'l' => $litigeId]);
    }
}
