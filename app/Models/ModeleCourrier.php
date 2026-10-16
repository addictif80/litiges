<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ModeleCourrier extends Model
{
    protected string $table = 'modeles_courrier';
    protected array $fillable = ['code', 'nom', 'type', 'sujet', 'contenu', 'actif', 'systeme'];

    public const TYPES = [
        'relance'          => 'Relance',
        'mise_en_demeure'  => 'Mise en demeure',
        'echeancier'       => 'Échéancier',
        'creance'          => 'Déclaration de créance',
        'autre'            => 'Autre',
    ];

    public function actifs(): array
    {
        return $this->all('actif = 1', [], 'nom ASC');
    }

    public function findByCode(string $code): ?array
    {
        return $this->findBy('code', $code);
    }

    public function codeExiste(string $code, ?int $sauf = null): bool
    {
        return Database::exists(
            'modeles_courrier',
            'code = :c' . ($sauf !== null ? ' AND id <> :id' : ''),
            $sauf !== null ? ['c' => $code, 'id' => $sauf] : ['c' => $code]
        );
    }
}
