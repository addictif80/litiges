<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Notification;

/**
 * Gestion des notifications internes : determine les destinataires et
 * enregistre les notifications dans la table `notifications`.
 */
class NotificationService
{
    /** @return array<int> */
    public function destinatairesModule(string $module, int $exclure = 0): array
    {
        $rows = Database::fetchAll(
            'SELECT id, role FROM users WHERE actif = 1' . ($exclure > 0 ? ' AND id <> :id' : ''),
            $exclure > 0 ? ['id' => $exclure] : []
        );

        $ids = [];
        foreach ($rows as $r) {
            $role = (string) ($r['role'] ?? '');
            if ($role === '') {
                continue;
            }
            if (Auth::peutPourRole($role, $module)) {
                $id = (int) ($r['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        return $ids;
    }

    /** @return array<int> */
    public function destinatairesChat(array $chat, int $auteurId): array
    {
        $type = (string) ($chat['type'] ?? '');
        if ($type === 'agence') {
            $agenceId = (int) ($chat['agence_id'] ?? 0);
            if ($agenceId <= 0) {
                return [];
            }
            $rows = Database::fetchAll(
                'SELECT id FROM users WHERE actif = 1 AND agence_id = :a' . ($auteurId > 0 ? ' AND id <> :u' : ''),
                $auteurId > 0 ? ['a' => $agenceId, 'u' => $auteurId] : ['a' => $agenceId]
            );
            $ids = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
            return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        }

        $chatId = (int) ($chat['id'] ?? 0);
        if ($chatId <= 0) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT user_id FROM chat_membres WHERE chat_id = :c' . ($auteurId > 0 ? ' AND user_id <> :u' : ''),
            $auteurId > 0 ? ['c' => $chatId, 'u' => $auteurId] : ['c' => $chatId]
        );
        $ids = array_map(static fn (array $r): int => (int) ($r['user_id'] ?? 0), $rows);
        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }

    /** Cree une notification pour chaque destinataire. */
    public function creer(int|array $destinataires, string $type, string $message, ?string $lien = null, ?int $litigeId = null): void
    {
        if (empty($destinataires)) {
            return;
        }

        $ids = is_array($destinataires) ? $destinataires : [$destinataires];
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, static fn ($v): bool => is_numeric($v) && (int) $v > 0))));

        if ($ids === []) {
            return;
        }

        $model  = new Notification();
        $message = mb_substr(trim($message), 0, 255);
        $lien    = $lien !== null ? mb_substr(trim($lien), 0, 255) : null;
        $litigeId = $litigeId !== null ? (int) $litigeId : null;

        foreach ($ids as $uid) {
            $model->create([
                'user_id'  => $uid,
                'litige_id' => $litigeId,
                'type'      => $type,
                'message'   => $message,
                'lien'      => $lien,
                'lu'        => 0,
            ]);
        }
    }

    // ------------------------------------------------------------------

    public function clientCree(array $client, int $auteurId): void
    {
        $nom = (string) ($client['raison_sociale'] ?? '');
        $dest = $this->destinatairesModule('clients', $auteurId);
        if ($dest === []) {
            return;
        }
        $this->creer($dest, 'client_cree', "Nouveau client cree : {$nom}", url('/clients/' . (int) ($client['id'] ?? 0)), null);
    }

    public function litigeCree(array $litige, int $auteurId): void
    {
        $ref = (string) ($litige['reference'] ?? '');
        $dest = $this->destinatairesModule('litiges', $auteurId);
        if ($dest === []) {
            return;
        }
        $this->creer($dest, 'litige_cree', "Nouveau litige : {$ref}", url('/litiges/' . (int) ($litige['id'] ?? 0)), (int) ($litige['id'] ?? 0));
    }

    public function litigeModifie(array $litige, int $auteurId): void
    {
        $ref = (string) ($litige['reference'] ?? '');
        $dest = $this->destinatairesModule('litiges', $auteurId);
        if ($dest === []) {
            return;
        }
        $this->creer($dest, 'litige_modif', "Litige modifie : {$ref}", url('/litiges/' . (int) ($litige['id'] ?? 0)), (int) ($litige['id'] ?? 0));
    }

    public function litigeResolu(array $litige, int $auteurId, ?string $statutLibelle): void
    {
        $ref = (string) ($litige['reference'] ?? '');
        $message = $statutLibelle ? "Litige resolu ({$statutLibelle}) : {$ref}" : "Litige resolu : {$ref}";
        $dest = $this->destinatairesModule('litiges', $auteurId);
        if ($dest === []) {
            return;
        }
        $this->creer($dest, 'litige_resolu', $message, url('/litiges/' . (int) ($litige['id'] ?? 0)), (int) ($litige['id'] ?? 0));
    }

    public function noteLitige(array $litige, int $auteurId): void
    {
        $ref = (string) ($litige['reference'] ?? '');
        $dest = $this->destinatairesModule('litiges', $auteurId);
        if ($dest === []) {
            return;
        }
        $this->creer($dest, 'litige_note', "Nouvelle note sur le litige : {$ref}", url('/litiges/' . (int) ($litige['id'] ?? 0) . '#notes'), (int) ($litige['id'] ?? 0));
    }

    /** Le litige est entierement regle : proposer la cloture au responsable (ou aux administrateurs). */
    public function litigeSolde(array $litige): void
    {
        $dest = $this->responsableOuAdmins($litige);
        $this->creer(
            $dest,
            'litige_solde',
            'Litige soldé, à clôturer : ' . (string) ($litige['reference'] ?? ''),
            url('/litiges/' . (int) ($litige['id'] ?? 0)),
            (int) ($litige['id'] ?? 0)
        );
    }

    /** Une personne est citee (@identifiant) dans une note de litige ou de client. */
    public function mention(int $destinataireId, string $contexte, string $lien, int $auteurId, ?int $litigeId = null): void
    {
        if ($destinataireId === $auteurId) {
            return;
        }
        $this->creer($destinataireId, 'mention', 'Vous avez été cité(e) : ' . $contexte, url($lien), $litigeId);
    }

    public function tacheAssignee(array $tache, int $auteurId): void
    {
        $dest = (int) ($tache['assigne_a'] ?? 0);
        if ($dest <= 0 || $dest === $auteurId) {
            return;
        }
        $this->creer(
            $dest,
            'tache_assignee',
            'Nouvelle tâche : ' . mb_substr((string) ($tache['titre'] ?? ''), 0, 120),
            url('/taches'),
            !empty($tache['litige_id']) ? (int) $tache['litige_id'] : null
        );
    }

    /** Un client suivi vient de faire l'objet d'une procedure collective. */
    public function procedureCollective(array $client, array $litiges, string $libelle): void
    {
        $destinataires = [];
        foreach ($litiges as $l) {
            if (!empty($l['responsable_id'])) {
                $destinataires[] = (int) $l['responsable_id'];
            }
        }
        $destinataires = array_merge($destinataires, $this->administrateurs());
        $this->creer(
            array_values(array_unique($destinataires)),
            'procedure_collective',
            sprintf('Procédure collective : %s (%s). Déclarez votre créance dans les délais.', (string) $client['raison_sociale'], $libelle),
            url('/clients/' . (int) $client['id'])
        );
    }

    /** @return int[] */
    public function administrateurs(): array
    {
        $rows = Database::fetchAll("SELECT id FROM users WHERE role = 'admin' AND actif = 1");
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    /** @return int[] */
    private function responsableOuAdmins(array $litige): array
    {
        $resp = (int) ($litige['responsable_id'] ?? 0);
        return $resp > 0 ? [$resp] : $this->administrateurs();
    }

    public function chatMessage(array $chat, string $titre, int $auteurId): void
    {
        $dest = $this->destinatairesChat($chat, $auteurId);
        if ($dest === []) {
            return;
        }
        $titreAffiche = mb_substr(trim($titre), 0, 80);
        $this->creer($dest, 'chat_message', "Nouveau message dans « {$titreAffiche} »", url('/chat/' . (int) ($chat['id'] ?? 0)), null);
    }
}
