<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;
use App\Models\Courrier;
use App\Models\Echeance;
use App\Models\Litige;
use App\Models\Notification;
use App\Models\Tache;
use App\Models\User;

/**
 * Genere les rappels automatiques (execute par le cron quotidien).
 *
 * Types d'alertes :
 *   - relance_due          : la date de prochaine relance est atteinte
 *   - promesse_depassee    : une promesse de paiement n'a pas ete honoree
 *   - echeance_proche      : l'echeance du litige approche
 *   - echeance_plan_manquee: une echeance d'un plan de paiement n'est pas reglee
 *   - tache_echue          : des taches assignees sont en retard
 *   - creance_delai        : le delai de declaration de creance approche
 *   - courrier_ar_attente  : un courrier envoye n'a pas d'accuse de reception
 */
class ReminderService
{
    /** Jours restants avant la fin du delai de declaration de creance qui declenchent un rappel. */
    private const JALONS_CREANCE = [30, 15, 7, 3, 1, 0];

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
     * @return array<string,int>
     * @param bool $dryRun Ne rien ecrire, seulement compter.
     */
    public function run(bool $dryRun = false): array
    {
        $stats = [
            'relance_due' => 0, 'promesse_depassee' => 0, 'echeance_proche' => 0,
            'echeance_plan_manquee' => 0, 'tache_echue' => 0, 'creance_delai' => 0,
            'courrier_ar_attente' => 0, 'emails' => 0,
        ];
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
            $sql = "SELECT l.id, l.reference, l.date_echeance, l.responsable_id, c.raison_sociale
                    FROM litiges l
                    JOIN clients c ON c.id = l.client_id
                    JOIN litige_statuts s ON s.id = l.statut_id
                    WHERE s.cloturant = 0
                      AND l.date_echeance IS NOT NULL
                      AND l.date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :seuil DAY)";
            foreach (Database::fetchAll($sql, ['seuil' => $seuil]) as $litige) {
                $destinataires = $this->destinataires($litige, $admins);
                if ($destinataires === []) {
                    continue;
                }
                $stats['echeance_proche']++;
                $message = sprintf('Echeance le %s pour %s (%s)', date('d/m/Y', strtotime((string) $litige['date_echeance'])), $litige['reference'], $litige['raison_sociale']);
                $this->notifier($destinataires, (int) $litige['id'], 'echeance_proche', $message, $jour, $dryRun);
            }
        }

        // --- 4. Echeances de plans de paiement non reglees ---
        $dejaSignale = [];
        foreach ((new Echeance())->enRetard() as $e) {
            $litigeId = (int) $e['litige_id'];
            if (isset($dejaSignale[$litigeId])) {
                continue; // une alerte par litige et par jour
            }
            $dejaSignale[$litigeId] = true;
            $destinataires = $this->destinataires($e, $admins);
            if ($destinataires === []) {
                continue;
            }
            $stats['echeance_plan_manquee']++;
            $reste = (float) $e['montant'] - (float) $e['montant_regle'];
            $message = sprintf('Echeance de paiement du %s non reglee (%s) : %s (%s)',
                date('d/m/Y', strtotime((string) $e['date_echeance'])), number_format($reste, 2, ',', ' ') . ' EUR', $e['reference'], $e['raison_sociale']);
            $cree = $this->notifier($destinataires, $litigeId, 'echeance_plan_manquee', $message, $jour, $dryRun);
            if ($cree && !$dryRun) {
                WebhookService::emettre('echeance.manquee', [
                    'litige_id' => $litigeId, 'reference' => $e['reference'], 'client' => $e['raison_sociale'],
                    'echeance' => $e['date_echeance'], 'reste_a_payer' => round($reste, 2),
                ]);
            }
        }

        // --- 5. Taches en retard (une notification par personne et par jour) ---
        $parPersonne = [];
        foreach ((new Tache())->echues() as $t) {
            $parPersonne[(int) $t['assigne_a']] = ($parPersonne[(int) $t['assigne_a']] ?? 0) + 1;
        }
        foreach ($parPersonne as $userId => $nb) {
            $stats['tache_echue']++;
            if ($dryRun || $this->notifications->existeDeja($userId, null, 'tache_echue', $jour)) {
                continue;
            }
            $this->notifications->create([
                'user_id' => $userId, 'litige_id' => null, 'type' => 'tache_echue', 'lien' => url('/taches?vue=moi'),
                'message' => sprintf('Vous avez %d tache(s) en retard', $nb), 'lu' => 0,
            ]);
        }

        // --- 6. Delai de declaration de creance ---
        $mois = max(1, (int) Settings::get('creance_delai_mois', 2));
        $creances = Database::fetchAll(
            "SELECT l.id, l.reference, l.responsable_id, c.raison_sociale, c.procedure_date_parution
             FROM litiges l
             JOIN clients c ON c.id = l.client_id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND l.creance_declaree_le IS NULL
               AND c.procedure_date_parution IS NOT NULL
               AND DATE_ADD(c.procedure_date_parution, INTERVAL :m MONTH) >= CURDATE()",
            ['m' => $mois]
        );
        foreach ($creances as $c) {
            $limite = (new \DateTimeImmutable((string) $c['procedure_date_parution']))->modify("+{$mois} months")->format('Y-m-d');
            $jours = days_until($limite);
            if ($jours === null || !in_array($jours, self::JALONS_CREANCE, true)) {
                continue;
            }
            $destinataires = $this->destinataires($c, $admins);
            if ($destinataires === []) {
                continue;
            }
            $stats['creance_delai']++;
            $message = sprintf('Creance a declarer avant le %s (%d j) : %s (%s)', date('d/m/Y', strtotime($limite)), $jours, $c['reference'], $c['raison_sociale']);
            $this->notifier($destinataires, (int) $c['id'], 'creance_delai', $message, $jour, $dryRun);
        }

        // --- 7. Courriers sans accuse de reception ---
        foreach ((new Courrier())->arEnAttente(10) as $c) {
            $destinataires = $this->destinataires($c, $admins);
            if ($destinataires === []) {
                continue;
            }
            $stats['courrier_ar_attente']++;
            $message = sprintf('Pas d\'accuse de reception pour le courrier %s (%s)', $c['numero'], $c['reference']);
            $this->notifier($destinataires, (int) $c['litige_id'], 'courrier_ar_attente', $message, $jour, $dryRun);
        }

        return $stats;
    }

    /** Libelles des compteurs pour les recapitulatifs. */
    public const LIBELLES = [
        'relance_due'           => 'Relances à effectuer',
        'promesse_depassee'     => 'Promesses non honorées',
        'echeance_proche'       => 'Échéances proches',
        'echeance_plan_manquee' => 'Échéances de plans de paiement impayées',
        'tache_echue'           => 'Personnes avec des tâches en retard',
        'creance_delai'         => 'Délais de déclaration de créance à surveiller',
        'courrier_ar_attente'   => "Courriers sans accusé de réception",
    ];

    /**
     * Envoie le recapitulatif aux administrateurs (si le SMTP est configure).
     *
     * @param array<string,int> $stats
     * @return int nombre d'e-mails envoyes
     */
    public function envoyerRecapitulatif(array $stats): int
    {
        $mailer = new Mailer();
        $total = 0;
        foreach (array_keys(self::LIBELLES) as $cle) {
            $total += (int) ($stats[$cle] ?? 0);
        }
        if ($total === 0 || !$mailer->isEnabled()) {
            return 0;
        }

        $admins = Database::fetchAll(
            "SELECT email FROM users WHERE role = 'admin' AND actif = 1 AND email IS NOT NULL AND email <> ''"
        );
        if ($admins === []) {
            return 0;
        }

        $contenu = '<p>Rappels générés le ' . htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') . ' :</p><ul>';
        foreach (self::LIBELLES as $cle => $libelle) {
            if ((int) ($stats[$cle] ?? 0) > 0) {
                $contenu .= '<li>' . htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') . ' : <strong>' . (int) $stats[$cle] . '</strong></li>';
            }
        }
        $contenu .= '</ul><p>Connectez-vous à l\'application pour traiter ces points.</p>';

        $html = $mailer->gabarit('Rappels du ' . date('d/m/Y'), $contenu);
        $envoyes = 0;
        foreach ($admins as $admin) {
            if ($mailer->send((string) $admin['email'], '[Litiges] Rappels du ' . date('d/m/Y'), $html)) {
                $envoyes++;
            }
        }
        return $envoyes;
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

    /** @return bool true si au moins une notification a ete (ou serait) creee */
    private function notifier(array $userIds, int $litigeId, string $type, string $message, string $jour, bool $dryRun): bool
    {
        $cree = false;
        foreach ($userIds as $userId) {
            if ($this->notifications->existeDeja($userId, $litigeId, $type, $jour)) {
                continue;
            }
            $cree = true;
            if ($dryRun) {
                continue;
            }
            $this->notifications->create([
                'user_id'   => $userId,
                'litige_id' => $litigeId,
                'type'      => $type,
                'message'   => mb_substr($message, 0, 255),
                'lien'      => url('/litiges/' . $litigeId),
                'lu'        => 0,
            ]);
        }
        return $cree;
    }
}
