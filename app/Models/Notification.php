<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Notification extends Model
{
    protected string $table = 'notifications';
    protected array $fillable = ['user_id', 'litige_id', 'type', 'message', 'lien', 'lu'];

    public function forUser(int $userId, bool $nonLuesSeules = false, int $limit = 50): array
    {
        $where = 'n.user_id = :id';
        if ($nonLuesSeules) {
            $where .= ' AND n.lu = 0';
        }
        return Database::fetchAll(
            "SELECT n.*, l.reference
             FROM notifications n
             LEFT JOIN litiges l ON l.id = n.litige_id
             WHERE {$where}
             ORDER BY n.created_at DESC
             LIMIT " . max(1, min(200, $limit)),
            ['id' => $userId]
        );
    }

    public function countNonLues(int $userId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :id AND lu = 0',
            ['id' => $userId],
            0
        );
    }

    public function marquerLues(int $userId): void
    {
        Database::run('UPDATE notifications SET lu = 1 WHERE user_id = :id', ['id' => $userId]);
    }

    public function marquerLue(int $id, int $userId): void
    {
        Database::run(
            'UPDATE notifications SET lu = 1 WHERE id = :id AND user_id = :u',
            ['id' => $id, 'u' => $userId]
        );
    }

    /** Evite de creer plusieurs fois la meme alerte le meme jour. */
    public function existeDeja(int $userId, int $litigeId, string $type, string $jour): bool
    {
        return Database::exists(
            'notifications',
            'user_id = :u AND litige_id = :l AND type = :t AND DATE(created_at) = :d',
            ['u' => $userId, 'l' => $litigeId, 't' => $type, 'd' => $jour]
        );
    }
}
