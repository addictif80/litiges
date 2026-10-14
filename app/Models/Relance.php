<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Relance extends Model
{
    protected string $table = 'relances';
    protected array $fillable = [
        'litige_id', 'contact_id', 'type_action', 'canal', 'date_action', 'objet',
        'contenu', 'resultat', 'montant_promis', 'date_promesse', 'prochaine_action_at', 'created_by',
    ];

    public const TYPES = [
        'relance'           => 'Relance',
        'appel'             => 'Appel telephonique',
        'email'             => 'Email',
        'courrier'          => 'Courrier',
        'mise_en_demeure'   => 'Mise en demeure',
        'promesse_paiement' => 'Promesse de paiement',
        'echelonnement'     => 'Echelonnement',
        'visite'            => 'Visite / rendez-vous',
        'autre'             => 'Autre',
    ];

    public const CANAUX = [
        'telephone' => 'Telephone',
        'email'     => 'Email',
        'courrier'  => 'Courrier',
        'sms'       => 'SMS',
        'physique'  => 'En personne',
        'autre'     => 'Autre',
    ];

    public function forLitige(int $litigeId): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.full_name AS user_nom, u.username,
                    CONCAT_WS(" ", ct.prenom, ct.nom) AS contact_nom
             FROM relances r
             LEFT JOIN users u ON u.id = r.created_by
             LEFT JOIN contacts ct ON ct.id = r.contact_id
             WHERE r.litige_id = :id
             ORDER BY r.date_action DESC, r.id DESC',
            ['id' => $litigeId]
        );
    }

    public function derniere(int $litigeId): ?array
    {
        return Database::fetch(
            'SELECT * FROM relances WHERE litige_id = :id ORDER BY date_action DESC, id DESC LIMIT 1',
            ['id' => $litigeId]
        );
    }

    public function countForLitige(int $litigeId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM relances WHERE litige_id = :id', ['id' => $litigeId], 0);
    }

    /** Relances recentes tous litiges confondus (activite). */
    public function recentes(int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT r.*, l.reference, c.raison_sociale, u.full_name AS user_nom
             FROM relances r
             JOIN litiges l ON l.id = r.litige_id
             JOIN clients c ON c.id = l.client_id
             LEFT JOIN users u ON u.id = r.created_by
             ORDER BY r.created_at DESC
             LIMIT " . max(1, min(100, $limit))
        );
    }
}
