<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Tache extends Model
{
    protected string $table = 'taches';
    protected array $fillable = [
        'litige_id', 'client_id', 'titre', 'description', 'assigne_a', 'echeance',
        'priorite', 'statut', 'done_at', 'created_by',
    ];

    public const STATUTS = [
        'a_faire'  => 'À faire',
        'en_cours' => 'En cours',
        'faite'    => 'Faite',
    ];

    private const SELECT = "
        SELECT t.*,
               l.reference AS litige_reference,
               c.raison_sociale AS client_nom,
               ua.full_name AS assigne_nom, ua.username AS assigne_username,
               uc.full_name AS createur_nom
        FROM taches t
        LEFT JOIN litiges l ON l.id = t.litige_id
        LEFT JOIN clients c ON c.id = COALESCE(t.client_id, l.client_id)
        LEFT JOIN users ua ON ua.id = t.assigne_a
        LEFT JOIN users uc ON uc.id = t.created_by
    ";

    public function forLitige(int $litigeId): array
    {
        return Database::fetchAll(
            self::SELECT . ' WHERE t.litige_id = :id ORDER BY (t.statut = \'faite\') ASC, t.echeance IS NULL, t.echeance ASC, t.id DESC',
            ['id' => $litigeId]
        );
    }

    /**
     * @param array{assigne_a?:int, statut?:string, en_retard?:bool, litige_id?:int} $f
     */
    public function liste(array $f = [], int $limite = 200): array
    {
        $conds  = ['1'];
        $params = [];
        if (!empty($f['assigne_a'])) {
            $conds[] = 't.assigne_a = :assigne';
            $params['assigne'] = (int) $f['assigne_a'];
        }
        if (($f['statut'] ?? '') === 'ouvertes') {
            $conds[] = "t.statut <> 'faite'";
        } elseif (!empty($f['statut']) && isset(self::STATUTS[$f['statut']])) {
            $conds[] = 't.statut = :statut';
            $params['statut'] = $f['statut'];
        }
        if (!empty($f['en_retard'])) {
            $conds[] = "t.statut <> 'faite' AND t.echeance IS NOT NULL AND t.echeance < CURDATE()";
        }
        if (!empty($f['litige_id'])) {
            $conds[] = 't.litige_id = :litige';
            $params['litige'] = (int) $f['litige_id'];
        }

        return Database::fetchAll(
            self::SELECT . ' WHERE ' . implode(' AND ', $conds)
            . " ORDER BY (t.statut = 'faite') ASC, t.echeance IS NULL, t.echeance ASC, t.id DESC LIMIT " . max(1, min(500, $limite)),
            $params
        );
    }

    public function compterOuvertes(int $userId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM taches WHERE assigne_a = :u AND statut <> 'faite'",
            ['u' => $userId],
            0
        );
    }

    public function compterEnRetard(int $userId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM taches WHERE assigne_a = :u AND statut <> 'faite' AND echeance IS NOT NULL AND echeance < CURDATE()",
            ['u' => $userId],
            0
        );
    }

    /** Taches ouvertes dont l'echeance est depassee (pour les rappels du cron). */
    public function echues(): array
    {
        return Database::fetchAll(
            "SELECT t.*, l.reference AS litige_reference
             FROM taches t LEFT JOIN litiges l ON l.id = t.litige_id
             WHERE t.statut <> 'faite' AND t.echeance IS NOT NULL AND t.echeance < CURDATE() AND t.assigne_a IS NOT NULL"
        );
    }
}
