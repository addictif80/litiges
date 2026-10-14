<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modeles de conversation de la messagerie interne.
 *
 * - direct : discussion 1-a-1 entre deux utilisateurs ;
 * - groupe : discussion nommee avec plusieurs utilisateurs ;
 * - agence : canal partage par tous les utilisateurs d'une agence.
 */
class Chat extends Model
{
    protected string $table = 'chats';
    protected array $fillable = ['type', 'titre', 'agence_id', 'created_by'];

    /**
     * Conversations visibles par un utilisateur, avec dernier message et
     * compteur de messages non lus.
     *
     * @return array<int,array<string,mixed>>
     */
    public function conversations(int $userId, ?int $agenceId, bool $admin = false): array
    {
        return Database::fetchAll(
            "SELECT c.*,
                    (SELECT m.contenu FROM chat_messages m WHERE m.chat_id = c.id ORDER BY m.id DESC LIMIT 1) AS dernier_message,
                    (SELECT m.created_at FROM chat_messages m WHERE m.chat_id = c.id ORDER BY m.id DESC LIMIT 1) AS dernier_at,
                    cm.last_read_at,
                    (SELECT COUNT(*) FROM chat_messages m2
                        WHERE m2.chat_id = c.id AND m2.user_id <> :uid
                          AND (cm.last_read_at IS NULL OR m2.created_at > cm.last_read_at)) AS non_lus
             FROM chats c
             LEFT JOIN chat_membres cm ON cm.chat_id = c.id AND cm.user_id = :uid2
             WHERE (c.type IN ('direct','groupe') AND cm.user_id IS NOT NULL)
                OR (c.type = 'agence' AND (c.agence_id = :agence OR :admin = 1))
             ORDER BY COALESCE(dernier_at, c.created_at) DESC",
            [
                'uid'    => $userId,
                'uid2'   => $userId,
                'agence' => $agenceId ?? 0,
                'admin'  => $admin ? 1 : 0,
            ]
        );
    }

    /** Nombre total de messages non lus pour la barre de navigation. */
    public function nonLus(int $userId, ?int $agenceId, bool $admin = false): int
    {
        return (int) Database::value(
            "SELECT COUNT(*)
             FROM chat_messages m
             JOIN chats c ON c.id = m.chat_id
             LEFT JOIN chat_membres cm ON cm.chat_id = c.id AND cm.user_id = :uid2
             WHERE m.user_id <> :uid
               AND (cm.last_read_at IS NULL OR m.created_at > cm.last_read_at)
               AND (
                    (c.type IN ('direct','groupe') AND cm.user_id IS NOT NULL)
                    OR (c.type = 'agence' AND (c.agence_id = :agence OR :admin = 1))
               )",
            [
                'uid'    => $userId,
                'uid2'   => $userId,
                'agence' => $agenceId ?? 0,
                'admin'  => $admin ? 1 : 0,
            ]
        );
    }

    public function estMembre(int $chatId, int $userId): bool
    {
        return Database::exists(
            'chat_membres',
            'chat_id = :c AND user_id = :u',
            ['c' => $chatId, 'u' => $userId]
        );
    }

    /** L'utilisateur a-t-il le droit de lire/ecrire dans cette conversation ? */
    public function acces(array $chat, int $userId, ?int $agenceId, bool $admin = false): bool
    {
        if (($chat['type'] ?? '') === 'agence') {
            return $admin || ($agenceId !== null && (int) $chat['agence_id'] === $agenceId);
        }
        return $this->estMembre((int) $chat['id'], $userId);
    }

    /** @return array<int,array<string,mixed>> */
    public function membres(int $chatId): array
    {
        return Database::fetchAll(
            'SELECT cm.*, u.full_name, u.username
             FROM chat_membres cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.chat_id = :c
             ORDER BY u.full_name ASC, u.username ASC',
            ['c' => $chatId]
        );
    }

    public function ajouterMembre(int $chatId, int $userId): void
    {
        Database::run(
            'INSERT IGNORE INTO chat_membres (chat_id, user_id) VALUES (:c, :u)',
            ['c' => $chatId, 'u' => $userId]
        );
    }

    /** Marque la conversation comme lue (cree la ligne de suivi si besoin). */
    public function marquerLu(int $chatId, int $userId): void
    {
        Database::run(
            'INSERT INTO chat_membres (chat_id, user_id, last_read_at)
             VALUES (:c, :u, NOW())
             ON DUPLICATE KEY UPDATE last_read_at = NOW()',
            ['c' => $chatId, 'u' => $userId]
        );
    }

    /** Recherche une discussion 1-a-1 existante entre deux utilisateurs. */
    public function trouverDirect(int $userA, int $userB): ?array
    {
        return Database::fetch(
            "SELECT c.* FROM chats c
             WHERE c.type = 'direct'
               AND EXISTS (SELECT 1 FROM chat_membres m WHERE m.chat_id = c.id AND m.user_id = :a)
               AND EXISTS (SELECT 1 FROM chat_membres m WHERE m.chat_id = c.id AND m.user_id = :b)
               AND (SELECT COUNT(*) FROM chat_membres m WHERE m.chat_id = c.id) = 2
             LIMIT 1",
            ['a' => $userA, 'b' => $userB]
        );
    }

    /** Canal d'agence associe a une agence (ou null). */
    public function canalAgence(int $agenceId): ?array
    {
        return Database::fetch(
            "SELECT * FROM chats WHERE type = 'agence' AND agence_id = :a LIMIT 1",
            ['a' => $agenceId]
        );
    }

    /**
     * Cree une conversation et ses participants.
     * @param int[] $membres
     */
    public function creer(string $type, ?string $titre, ?int $agenceId, int $createdBy, array $membres = []): int
    {
        $id = $this->create([
            'type'       => $type,
            'titre'      => $titre,
            'agence_id'  => $agenceId,
            'created_by' => $createdBy,
        ]);

        $membres[] = $createdBy;
        foreach (array_unique(array_map('intval', $membres)) as $membre) {
            if ($membre > 0) {
                $this->ajouterMembre($id, $membre);
            }
        }

        return $id;
    }
}
