<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ApiToken extends Model
{
    protected string $table = 'api_tokens';
    protected array $fillable = [
        'nom', 'prefixe', 'token_hash', 'user_id', 'ecriture', 'expires_at',
        'last_used_at', 'actif', 'created_by',
    ];

    public function liste(): array
    {
        return Database::fetchAll(
            'SELECT t.*, u.username, u.full_name
             FROM api_tokens t JOIN users u ON u.id = t.user_id
             ORDER BY t.created_at DESC'
        );
    }

    /** Retrouve un jeton valide a partir de son empreinte SHA-256. */
    public function parEmpreinte(string $empreinte): ?array
    {
        return Database::fetch(
            'SELECT t.*, u.actif AS user_actif, u.role AS user_role
             FROM api_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :h AND t.actif = 1
               AND (t.expires_at IS NULL OR t.expires_at > NOW())
             LIMIT 1',
            ['h' => $empreinte]
        );
    }

    public function marquerUtilise(int $id): void
    {
        Database::run('UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id', ['id' => $id]);
    }
}
