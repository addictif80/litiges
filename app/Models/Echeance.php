<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Echeance extends Model
{
    protected string $table = 'echeances';
    protected array $fillable = [
        'litige_id', 'numero', 'date_echeance', 'montant', 'montant_regle', 'created_by',
    ];

    public function forLitige(int $litigeId): array
    {
        return Database::fetchAll(
            'SELECT * FROM echeances WHERE litige_id = :id ORDER BY numero ASC',
            ['id' => $litigeId]
        );
    }

    public function supprimerPlan(int $litigeId): void
    {
        Database::delete('echeances', 'litige_id = :id', ['id' => $litigeId]);
    }

    /** Echeances en retard sur des litiges non clotures (pour les rappels). */
    public function enRetard(): array
    {
        return Database::fetchAll(
            'SELECT e.*, l.reference, l.responsable_id, c.raison_sociale
             FROM echeances e
             JOIN litiges l ON l.id = e.litige_id
             JOIN clients c ON c.id = l.client_id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0
               AND e.date_echeance < CURDATE()
               AND e.montant_regle + 0.005 < e.montant
             ORDER BY e.date_echeance ASC'
        );
    }

    /** Echeances impayees a venir sur une periode (previsions d'encaissement). */
    public function aVenir(string $du, string $au): array
    {
        return Database::fetchAll(
            'SELECT e.date_echeance, (e.montant - e.montant_regle) AS reste, l.agence_id
             FROM echeances e
             JOIN litiges l ON l.id = e.litige_id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0
               AND e.montant_regle + 0.005 < e.montant
               AND e.date_echeance BETWEEN :du AND :au
             ORDER BY e.date_echeance ASC',
            ['du' => $du, 'au' => $au]
        );
    }
}
