<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class LitigeStatut extends Model
{
    protected string $table = 'litige_statuts';
    protected array $fillable = ['categorie', 'libelle', 'code', 'couleur', 'ordre', 'cloturant', 'systeme', 'actif'];

    public function statuts(bool $actifsSeuls = true): array
    {
        $where = "categorie = 'statut'";
        if ($actifsSeuls) {
            $where .= ' AND actif = 1';
        }
        return $this->all($where, [], 'ordre ASC, libelle ASC');
    }

    public function finalites(bool $actifsSeuls = true): array
    {
        $where = "categorie = 'finalite'";
        if ($actifsSeuls) {
            $where .= ' AND actif = 1';
        }
        return $this->all($where, [], 'ordre ASC, libelle ASC');
    }

    public function allOrdered(): array
    {
        return $this->all('1', [], "categorie ASC, ordre ASC, libelle ASC");
    }

    public function defaultStatutId(): int
    {
        $id = Database::value(
            "SELECT id FROM litige_statuts WHERE categorie = 'statut' AND code = 'nouveau' LIMIT 1"
        );
        if ($id) {
            return (int) $id;
        }
        return (int) Database::value(
            "SELECT id FROM litige_statuts WHERE categorie = 'statut' AND actif = 1 ORDER BY ordre ASC LIMIT 1",
            [],
            0
        );
    }

    public function isCloturant(int $statutId): bool
    {
        return (bool) Database::value(
            'SELECT cloturant FROM litige_statuts WHERE id = :id',
            ['id' => $statutId],
            0
        );
    }

    public function nextOrdre(string $categorie): int
    {
        return (int) Database::value(
            'SELECT COALESCE(MAX(ordre),0) + 10 FROM litige_statuts WHERE categorie = :c',
            ['c' => $categorie],
            10
        );
    }
}
