<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class LitigeHistorique extends Model
{
    protected string $table = 'litige_historique';
    protected array $fillable = [
        'litige_id', 'user_id', 'action', 'champ', 'ancienne_valeur', 'nouvelle_valeur', 'commentaire',
    ];

    public function forLitige(int $litigeId): array
    {
        return Database::fetchAll(
            'SELECT h.*, u.full_name AS user_nom, u.username
             FROM litige_historique h
             LEFT JOIN users u ON u.id = h.user_id
             WHERE h.litige_id = :id
             ORDER BY h.created_at DESC, h.id DESC',
            ['id' => $litigeId]
        );
    }

    public function log(int $litigeId, ?int $userId, string $action, ?string $champ = null, ?string $ancienne = null, ?string $nouvelle = null, ?string $commentaire = null): void
    {
        $this->create([
            'litige_id'       => $litigeId,
            'user_id'         => $userId,
            'action'          => $action,
            'champ'           => $champ,
            'ancienne_valeur' => $ancienne !== null ? mb_substr($ancienne, 0, 255) : null,
            'nouvelle_valeur' => $nouvelle !== null ? mb_substr($nouvelle, 0, 255) : null,
            'commentaire'     => $commentaire,
        ]);
    }

    /**
     * Compare deux versions d'un litige et journalise les changements.
     */
    public function diff(int $litigeId, ?int $userId, array $avant, array $apres): void
    {
        $labels = [
            'objet'                => 'Objet',
            'description'          => 'Description',
            'type_litige'          => 'Type',
            'montant_principal'    => 'Montant principal',
            'montant_penalites'    => 'Penalites',
            'montant_recupere'     => 'Montant recupere',
            'date_ouverture'       => 'Date ouverture',
            'date_echeance'        => 'Date echeance',
            'date_cloture'         => 'Date cloture',
            'statut_id'            => 'Statut',
            'finalite_id'          => 'Finalite',
            'responsable_id'       => 'Responsable',
            'priorite'             => 'Priorite',
            'numero_facture'       => 'N. facture',
            'date_facture'         => 'Date facture',
            'numero_contrat'       => 'N. contrat',
            'materiel'             => 'Materiel',
            'reference_externe'    => 'Reference externe',
            'delai_relance_jours'  => 'Delai relance (j)',
            'agence_id'            => 'Agence',
        ];

        foreach ($labels as $champ => $label) {
            $old = $avant[$champ] ?? null;
            $new = $apres[$champ] ?? null;
            if ((string) $old !== (string) $new) {
                $this->log($litigeId, $userId, 'modification', $label, (string) $old, (string) $new);
            }
        }
    }
}
