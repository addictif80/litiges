<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Contact extends Model
{
    protected string $table = 'contacts';
    protected array $fillable = [
        'client_id', 'civilite', 'nom', 'prenom', 'fonction', 'email',
        'telephone', 'mobile', 'type', 'principal', 'notes',
    ];

    public function forClient(int $clientId): array
    {
        return Database::fetchAll(
            'SELECT * FROM contacts WHERE client_id = :id ORDER BY principal DESC, nom ASC',
            ['id' => $clientId]
        );
    }

    /** Un seul contact principal par client. */
    public function clearPrincipal(int $clientId, ?int $exceptId = null): void
    {
        $sql    = 'UPDATE contacts SET principal = 0 WHERE client_id = :c';
        $params = ['c' => $clientId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        Database::run($sql, $params);
    }

    public function nomComplet(?array $contact): string
    {
        if (!$contact) {
            return '';
        }
        return trim(implode(' ', array_filter([
            $contact['prenom'] ?? '',
            $contact['nom'] ?? '',
        ])));
    }
}
