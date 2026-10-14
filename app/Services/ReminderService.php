<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Models\Litige;
use App\Models\Notification;
use App\Models\User;

/**
 * Genere les rappels automatiques (execute par le cron quotidien).
 *
 * Types d'alertes :
 *   - relance_due        : la date de prochaine relance est atteinte
 *   - promesse_depassee  : une promesse de paiement n'a pas ete honoree
 *   - echeance_proche    : l'echeance du litige approche
 */
class ReminderService
{
    private Litige $litiges;
    private Notification $notifications;
    private User $users;

    public function __construct()
    {
        $this->litiges       = new Litige();
        $this->notifications = new Notification();
        $this->users         = new User();
    }

    /**
     * @return array{relance_due:int, promesse_depassee:int, echeance_proche:int, emails:int}
     * @param bool $dryRun Ne rien ecrire, seulement compter.
     */
    public function run(bool $dryRun = false): array
    {
        $stats = ['relance_due' => 0, 'promesse_depassee' => 0, 'echeance_proche' => 0, 'emails' => 0];
        $jour  = date('Y-m-d');

        $seuil  = (int) Settings::get('seuil_alerte_retard_jours', 7);
        $admins = array_map(
            static fn (array $u) => (int) $u['id'],
            $this->users->all("role = 'admin' AND actif = 1")
        );

        // --- 1. Relances dues ---
        foreach ($this->litiges->listeRelanceDue() as $litige) {
            $destinataires = $this->destinataires($litige, $admins);
            if ($destinataires === []) {
                continue;
            }
            $stats['relance_due']++;
            $message = sprintf('Relance a effectuer pour %s (%s)', $litige['reference'], $litige['raison_sociale']);
            $this->notifier($destinataires, (int) $litige['id'], 'relance_due', $message, $jour, $dryRun);
        }

        // --- 2. Promesses de paiement depassees ---
        foreach ($this->litiges->listePromessesDepassees() as $litige) {
            $destinataires = $this->destinataires($litige, $admins);
            if ($destinataires === []) {
                continue;
            }
            $stats['promesse_depassee']++;
            $message = sprintf('Promesse de paiement non honoree pour %s (%s)', $litige['reference'], $litige['raison_sociale']);
            $this->notifier($destinataires, (int) $litige['id'], 'promesse_depassee', $message, $jour, $dryRun);
        }

        // --- 3. Echeances proches ---
        if ($seuil > 0) {
            $sql = "SELECT l.id, l.reference, l.date_echeance, c.raison_sociale
                    FROM litiges l
                    JOIN clients c ON c.id = l.client_id
                    JOIN litige_statuts s ON s.id = l.statut_id
                    WHERE s.cloturant = 0
                      AND l.date_echeance IS NOT NULL
                      AND l.date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :seuil DAY)";
            foreach (\App\Core\Database::fetchAll($sql, ['seuil' => $seuil]) as $litige) {
                $destinataires = $this->destinataires($litige, $admins);
                if ($destinataires === []) {
                    continue;
                }
                $stats['echeance_proche']++;
                $message = sprintf('Echeance le %s pour %s (%s)', date('d/m/Y', strtotime((string) $litige['date_echeance'])), $litige['reference'], $litige['raison_sociale']);
                $this->notifier($destinataires, (int) $litige['id'], 'echeance_proche', $message, $jour, $dryRun);
            }
        }

        return $stats;
    }

    /** @return int[] */
    private function destinataires(array $litige, array $admins): array
    {
        $responsable = (int) ($litige['responsable_id'] ?? 0);
        if ($responsable > 0) {
            return [$responsable];
        }
        return $admins;
    }

    private function notifier(array $userIds, int $litigeId, string $type, string $message, string $jour, bool $dryRun): void
    {
        foreach ($userIds as $userId) {
            if ($this->notifications->existeDeja($userId, $litigeId, $type, $jour)) {
                continue;
            }
            if ($dryRun) {
                continue;
            }
            $this->notifications->create([
                'user_id'   => $userId,
                'litige_id' => $litigeId,
                'type'      => $type,
                'message'   => $message,
                'lu'        => 0,
            ]);
        }
    }
}
