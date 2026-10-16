<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Courrier extends Model
{
    protected string $table = 'courriers';
    protected array $fillable = [
        'numero', 'litige_id', 'modele_id', 'type', 'sujet', 'contenu', 'document_id',
        'mode_envoi', 'numero_suivi', 'date_envoi', 'date_ar', 'statut_ar', 'created_by',
    ];

    public const MODES_ENVOI = [
        'lrar'      => 'Lettre recommandée avec AR (papier)',
        'lre'       => 'Lettre recommandée électronique (LRE)',
        'huissier'  => 'Huissier / commissaire de justice',
        'email'     => 'E-mail',
        'remise'    => 'Remise en main propre',
    ];

    public const STATUTS_AR = [
        'non_envoye'  => 'Non envoyé',
        'en_attente'  => 'Envoyé - AR en attente',
        'recu'        => 'AR reçu',
        'refuse'      => 'Refusé par le destinataire',
        'non_reclame' => 'Non réclamé',
    ];

    public function forLitige(int $litigeId): array
    {
        return Database::fetchAll(
            'SELECT c.*, u.full_name AS user_nom, u.username
             FROM courriers c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.litige_id = :id
             ORDER BY c.created_at DESC, c.id DESC',
            ['id' => $litigeId]
        );
    }

    /** Prochain numero : COUR-AAAA-NNNN. */
    public function prochainNumero(): string
    {
        $annee = date('Y');
        $max = (int) Database::value(
            "SELECT MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)) FROM courriers WHERE numero LIKE :p",
            ['p' => "COUR-{$annee}-%"],
            0
        );
        return sprintf('COUR-%s-%04d', $annee, $max + 1);
    }

    /** Courriers envoyes dont l'accusé de réception n'est pas encore revenu. */
    public function arEnAttente(int $joursMin = 10): array
    {
        return Database::fetchAll(
            "SELECT c.*, l.reference, l.responsable_id
             FROM courriers c JOIN litiges l ON l.id = c.litige_id
             WHERE c.statut_ar = 'en_attente' AND c.date_envoi IS NOT NULL
               AND c.date_envoi <= DATE_SUB(CURDATE(), INTERVAL :j DAY)",
            ['j' => $joursMin]
        );
    }
}
