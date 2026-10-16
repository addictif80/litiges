<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Partage extends Model
{
    protected string $table = 'partages';
    protected array $fillable = [
        'code', 'token', 'libelle', 'scope', 'scope_id', 'expires_at', 'actif', 'created_by', 'last_used_at', 'vues',
        'mot_de_passe_hash', 'afficher_documents',
    ];

    /** Partage de type "litige" le plus recent pour ce litige. */
    public function dernierPourLitige(int $litigeId): ?array
    {
        return Database::fetch(
            "SELECT * FROM partages WHERE scope = 'litige' AND scope_id = :id ORDER BY id DESC LIMIT 1",
            ['id' => $litigeId]
        );
    }

    /** Trace une consultation publique (acces, litige consulte, document telecharge...). */
    public function journaliser(int $partageId, string $action, ?int $litigeId = null): void
    {
        try {
            Database::insert('partage_consultations', [
                'partage_id' => $partageId,
                'litige_id'  => $litigeId,
                'action'     => mb_substr($action, 0, 30),
                'ip'         => \App\Core\Request::ip(),
                'user_agent' => \App\Core\Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e, 'Journal des consultations');
        }
    }

    /**
     * Dernieres consultations de chaque partage.
     *
     * @param int[] $ids
     * @return array<int,array<int,array<string,mixed>>> partage_id => lignes (les plus recentes d'abord)
     */
    public function journalPar(array $ids, int $parPartage = 8): array
    {
        if ($ids === []) {
            return [];
        }
        $marqueurs = implode(',', array_fill(0, count($ids), '?'));
        $lignes = Database::run(
            "SELECT c.*, l.reference
             FROM partage_consultations c LEFT JOIN litiges l ON l.id = c.litige_id
             WHERE c.partage_id IN ({$marqueurs})
             ORDER BY c.id DESC LIMIT 600",
            array_values(array_map('intval', $ids))
        )->fetchAll();

        $groupes = [];
        foreach ($lignes as $l) {
            $pid = (int) $l['partage_id'];
            if (count($groupes[$pid] ?? []) < $parPartage) {
                $groupes[$pid][] = $l;
            }
        }
        return $groupes;
    }

    /** Expiration par defaut des nouveaux partages (parametre, en jours ; 0 = jamais). */
    public static function expirationParDefaut(): ?string
    {
        $jours = (int) \App\Core\Settings::get('partage_expiration_defaut_jours', 30);
        return $jours > 0 ? date('Y-m-d 23:59:59', strtotime("+{$jours} days")) : null;
    }

    public const SCOPES = [
        'global' => 'Tous les litiges',
        'agence' => 'Une agence',
        'client' => 'Un client',
        'litige' => 'Un litige',
    ];

    public function listAll(): array
    {
        return Database::fetchAll(
            'SELECT p.*, u.full_name AS user_nom
             FROM partages p
             LEFT JOIN users u ON u.id = p.created_by
             ORDER BY p.created_at DESC'
        );
    }

    public function actifs(): array
    {
        return $this->all('actif = 1', [], 'created_at DESC');
    }

    public function findByCode(string $code): ?array
    {
        return Database::fetch(
            'SELECT * FROM partages
             WHERE code = :c AND actif = 1
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1',
            ['c' => $code]
        );
    }

    /** Retrouve un partage a partir de son jeton de lien public. */
    public function findByToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT * FROM partages
             WHERE token = :t AND actif = 1
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1',
            ['t' => $token]
        );
    }

    public function codeExists(string $code): bool
    {
        return Database::exists('partages', 'code = :c', ['c' => $code]);
    }

    public function generateCode(): string
    {
        do {
            // 14 caracteres alphanumeriques (~80 bits) : non devinable meme sans limitation.
            $code = '';
            while (strlen($code) < 14) {
                $code .= strtoupper(str_replace(['+', '/', '=', 'O', '0', 'I', '1', 'l'], '', base64_encode(random_bytes(12))));
            }
            $code = substr($code, 0, 14);
        } while ($this->codeExists($code));
        return $code;
    }

    public function tokenExists(string $token): bool
    {
        return Database::exists('partages', 'token = :t', ['t' => $token]);
    }

    /** Jeton opaque pour un lien public direct (sans saisie de code). */
    public function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while ($this->tokenExists($token));
        return $token;
    }

    public function enregistrerVue(int $id): void
    {
        Database::run(
            'UPDATE partages SET vues = vues + 1, last_used_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }

    /** Libelle du perimetre pour l'affichage. */
    public function scopeLabel(array $partage): string
    {
        $type = self::SCOPES[$partage['scope']] ?? $partage['scope'];
        $id   = (int) ($partage['scope_id'] ?? 0);
        if ($id <= 0) {
            return $type;
        }

        $nom = match ($partage['scope']) {
            'agence' => (string) Database::value('SELECT nom FROM agences WHERE id = :id', ['id' => $id], ''),
            'client' => (string) Database::value('SELECT raison_sociale FROM clients WHERE id = :id', ['id' => $id], ''),
            'litige' => (string) Database::value('SELECT reference FROM litiges WHERE id = :id', ['id' => $id], ''),
            default  => '',
        };

        return $nom !== '' ? "{$type} : {$nom}" : $type;
    }
}
