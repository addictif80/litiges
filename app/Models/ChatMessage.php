<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ChatMessage extends Model
{
    protected string $table = 'chat_messages';
    protected array $fillable = ['chat_id', 'user_id', 'contenu'];

    /**
     * Messages d'une conversation, eventuellement a partir d'un id donne
     * (utilise par le rafraichissement automatique).
     *
     * @return array<int,array<string,mixed>>
     */
    public function pourChat(int $chatId, int $sinceId = 0): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.full_name AS user_nom, u.username
             FROM chat_messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.chat_id = :c AND m.id > :since
             ORDER BY m.id ASC',
            ['c' => $chatId, 'since' => $sinceId]
        );
    }
}
