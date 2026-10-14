<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Partage extends Model
{
    protected string $table = 'partages';
    protected array $fillable = [
        'code', 'libelle', 'scope', 'scope_id', 'expires_at', 'actif', 'created_by', 'last_used_at', 'vues',
    ];

    public const SCOPES = [
        'global' => 'Tous les litiges',
        'agence' => 'Une agence',
        'client' => 'Un client',
        'litige' => 'Un litige',
    ];

    public function listAll(): array
    {
        return Database::fetchAll(
            'SELECT p.*, u.full_name AS user_nom
             FROM partages p
             LEFT JOIN users u ON u.id = p.created_by
             ORDER BY p.created_at DESC'
        );
    }

    public function actifs(): array
    {
        return $this->all('actif = 1', [], 'created_at DESC');
    }

    public function findByCode(string $code): ?array
    {
        return Database::fetch(
            'SELECT * FROM partages
             WHERE code = :c AND actif = 1
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1',
            ['c' => $code]
        );
    }

    public function codeExists(string $code): bool
    {
        return Database::exists('partages', 'code = :c', ['c' => $code]);
    }

    public function generateCode(): string
    {
        do {
            $code = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10));
        } while ($this->codeExists($code));
        return $code;
    }

    public function enregistrerVue(int $id): void
    {
        Database::run(
            'UPDATE partages SET vues = vues + 1, last_used_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }

    /** Libelle du perimetre pour l'affichage. */
    public function scopeLabel(array $partage): string
    {
        $type = self::SCOPES[$partage['scope']] ?? $partage['scope'];
        $id   = (int) ($partage['scope_id'] ?? 0);
        if ($id <= 0) {
            return $type;
        }

        $nom = match ($partage['scope']) {
            'agence' => (string) Database::value('SELECT nom FROM agences WHERE id = :id', ['id' => $id], ''),
            'client' => (string) Database::value('SELECT raison_sociale FROM clients WHERE id = :id', ['id' => $id], ''),
            'litige' => (string) Database::value('SELECT reference FROM litiges WHERE id = :id', ['id' => $id], ''),
            default  => '',
        };

        return $nom !== '' ? "{$type} : {$nom}" : $type;
    }
}
