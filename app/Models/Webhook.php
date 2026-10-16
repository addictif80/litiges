<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Webhook extends Model
{
    protected string $table = 'webhooks';
    protected array $fillable = [
        'nom', 'url', 'secret', 'evenements', 'actif', 'dernier_statut',
        'dernier_envoi_at', 'echecs_consecutifs', 'created_by',
    ];

    /** Evenements publies par l'application. */
    public const EVENEMENTS = [
        'litige.cree'                 => 'Litige créé',
        'litige.modifie'              => 'Litige modifié',
        'litige.statut_change'        => 'Changement de statut',
        'litige.cloture'              => 'Litige clôturé',
        'paiement.enregistre'         => 'Paiement enregistré',
        'relance.ajoutee'             => 'Relance ajoutée',
        'echeance.manquee'            => 'Échéance de paiement manquée',
        'procedure_collective.detectee' => 'Procédure collective détectée',
    ];

    public function actifsPourEvenement(string $evenement): array
    {
        $tous = $this->all('actif = 1', [], 'id ASC');
        return array_values(array_filter($tous, static function (array $w) use ($evenement): bool {
            $liste = json_decode((string) $w['evenements'], true);
            return is_array($liste) && (in_array($evenement, $liste, true) || in_array('*', $liste, true));
        }));
    }

    public function livraisonsRecentes(int $webhookId, int $limite = 20): array
    {
        return Database::fetchAll(
            'SELECT id, evenement, statut, tentatives, code_http, erreur, created_at
             FROM webhook_livraisons WHERE webhook_id = :id ORDER BY id DESC LIMIT ' . max(1, min(100, $limite)),
            ['id' => $webhookId]
        );
    }
}
