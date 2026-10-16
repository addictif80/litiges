<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Aides pour ecrire des migrations PHP idempotentes (colonnes, index).
 * MySQL ne connait pas "ADD COLUMN IF NOT EXISTS" : on interroge information_schema.
 */
class Schema
{
    public static function colonneExiste(string $table, string $colonne): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
            ['t' => $table, 'c' => $colonne],
            0
        ) > 0;
    }

    public static function indexExiste(string $table, string $index): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i',
            ['t' => $table, 'i' => $index],
            0
        ) > 0;
    }

    public static function tableExiste(string $table): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
            ['t' => $table],
            0
        ) > 0;
    }

    /** Ajoute la colonne si elle est absente. $definition : "VARCHAR(60) DEFAULT NULL". */
    public static function ajouterColonne(string $table, string $colonne, string $definition): void
    {
        if (!self::colonneExiste($table, $colonne)) {
            Database::pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$colonne}` {$definition}");
        }
    }

    /** Ajoute un index (normal ou UNIQUE) s'il est absent. @param string[] $colonnes */
    public static function ajouterIndex(string $table, string $index, array $colonnes, bool $unique = false): void
    {
        if (self::indexExiste($table, $index)) {
            return;
        }
        $liste = implode('`, `', $colonnes);
        $type  = $unique ? 'UNIQUE KEY' : 'KEY';
        Database::pdo()->exec("ALTER TABLE `{$table}` ADD {$type} `{$index}` (`{$liste}`)");
    }

    /** Insere des parametres par defaut sans ecraser les valeurs existantes. @param array<string,string> $parametres */
    public static function parametresParDefaut(array $parametres): void
    {
        foreach ($parametres as $cle => $valeur) {
            Database::run(
                'INSERT INTO settings (cle, valeur) VALUES (:c, :v) ON DUPLICATE KEY UPDATE valeur = valeur',
                ['c' => $cle, 'v' => $valeur]
            );
        }
    }
}
