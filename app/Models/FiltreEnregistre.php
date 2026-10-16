<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class FiltreEnregistre extends Model
{
    protected string $table = 'filtres_enregistres';
    protected array $fillable = ['user_id', 'module', 'nom', 'parametres', 'partage'];

    /** Filtres personnels + filtres partages par les autres utilisateurs. */
    public function visiblesPour(int $userId, string $module = 'litiges'): array
    {
        return Database::fetchAll(
            'SELECT f.*, u.full_name AS auteur
             FROM filtres_enregistres f
             LEFT JOIN users u ON u.id = f.user_id
             WHERE f.module = :m AND (f.user_id = :u OR f.partage = 1)
             ORDER BY f.nom ASC',
            ['m' => $module, 'u' => $userId]
        );
    }
}
