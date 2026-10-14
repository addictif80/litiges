<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Couche d'acces a la base de donnees (PDO / MySQL).
 * Tous les appels utilisent des requetes preparees : aucune valeur n'est
 * injectee directement dans le SQL.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static array $cfg = [];

    public static function init(array $cfg): void
    {
        self::$cfg = $cfg;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = self::$cfg;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'] ?? '127.0.0.1',
            (int) ($cfg['port'] ?? 3306),
            $cfg['database'] ?? '',
            $cfg['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO(
            $dsn,
            (string) ($cfg['username'] ?? ''),
            (string) ($cfg['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]
        );

        return self::$pdo;
    }

    /** Teste la connexion : renvoie le message d'erreur ou null si OK. */
    public static function testConnection(): ?string
    {
        try {
            self::pdo()->query('SELECT 1');
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        self::run($sql, self::bindings($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if ($data === []) {
            return 0;
        }

        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "`{$column}` = :set_{$column}";
        }

        $params = [];
        foreach ($data as $column => $value) {
            $params['set_' . $column] = $value;
        }
        foreach ($whereParams as $k => $v) {
            $params[$k] = $v;
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);
        return self::run($sql, $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        return self::run($sql, $params)->rowCount();
    }

    public static function exists(string $table, string $where, array $params = []): bool
    {
        return self::value("SELECT 1 FROM `{$table}` WHERE {$where} LIMIT 1", $params) !== null;
    }

    public static function begin(): void
    {
        self::pdo()->beginTransaction();
    }

    public static function commit(): void
    {
        self::pdo()->commit();
    }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    private static function bindings(array $data): array
    {
        $bindings = [];
        foreach ($data as $column => $value) {
            $bindings[$column] = $value;
        }
        return $bindings;
    }
}
