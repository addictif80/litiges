<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modele de base : operations CRUD generiques sur une table.
 * Les noms de tables et de colonnes sont internes (jamais issus de l'utilisateur).
 */
abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    /** @var string[] Colonnes autorisees a l'ecriture. */
    protected array $fillable = [];

    public function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1",
            ['id' => $id]
        );
    }

    public function findBy(string $column, mixed $value): ?array
    {
        return Database::fetch(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = :value LIMIT 1",
            ['value' => $value]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $where = '1', array $params = [], string $orderBy = 'id DESC', ?int $limit = null): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE {$where} ORDER BY {$orderBy}";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(0, $limit);
        }
        return Database::fetchAll($sql, $params);
    }

    public function create(array $data): int
    {
        return Database::insert($this->table, $this->filter($data));
    }

    public function update(int $id, array $data): int
    {
        $data = $this->filter($data);
        if ($data === []) {
            return 0;
        }
        return Database::update($this->table, $data, "`{$this->primaryKey}` = :__id", ['__id' => $id]);
    }

    public function delete(int $id): int
    {
        return Database::delete($this->table, "`{$this->primaryKey}` = :id", ['id' => $id]);
    }

    public function count(string $where = '1', array $params = []): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM `{$this->table}` WHERE {$where}", $params, 0);
    }

    public function exists(int $id): bool
    {
        return Database::exists($this->table, "`{$this->primaryKey}` = :id", ['id' => $id]);
    }

    protected function filter(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function table(): string
    {
        return $this->table;
    }
}
