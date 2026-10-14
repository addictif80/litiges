<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Agence extends Model
{
    protected string $table = 'agences';
    protected array $fillable = ['nom', 'code', 'adresse', 'ville', 'telephone', 'email', 'actif'];

    public function listActives(): array
    {
        return $this->all('actif = 1', [], 'nom ASC');
    }

    public function allWithStats(): array
    {
        return Database::fetchAll(
            'SELECT a.*,
                    (SELECT COUNT(*) FROM litiges l WHERE l.agence_id = a.id) AS nb_litiges,
                    (SELECT COUNT(*) FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
                        WHERE l.agence_id = a.id AND s.cloturant = 1) AS nb_clotures
             FROM agences a
             ORDER BY a.nom ASC'
        );
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM agences WHERE code = :c';
        $params = ['c' => $code];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params) > 0;
    }
}
