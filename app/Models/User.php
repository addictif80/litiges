<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Config;
use App\Core\Settings;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = [
        'username', 'email', 'password_hash', 'full_name', 'role',
        'agence_id', 'actif', 'must_change_password', 'failed_attempts',
        'locked_until', 'last_login_at', 'reset_token', 'reset_expires',
        'totp_secret', 'totp_enabled', 'totp_recovery', 'totp_last_step', 'ical_token',
    ];

    public function findByLogin(string $login): ?array
    {
        return Database::fetch(
            'SELECT * FROM users WHERE username = :login OR email = :email LIMIT 1',
            ['login' => $login, 'email' => $login]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function withAgence(?string $where = null, array $params = []): array
    {
        $sql = 'SELECT u.*, a.nom AS agence_nom
                FROM users u
                LEFT JOIN agences a ON a.id = u.agence_id';
        if ($where !== null) {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY u.full_name ASC, u.username ASC';
        return Database::fetchAll($sql, $params);
    }

    public function listActifs(): array
    {
        return $this->all('actif = 1', [], 'full_name ASC');
    }

    public function findByResetToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT * FROM users WHERE reset_token = :t AND reset_expires > NOW() LIMIT 1',
            ['t' => $token]
        );
    }

    public function registerFailedAttempt(int $id): void
    {
        $max     = (int) Settings::get('verrouillage_tentatives', Config::get('security.max_login_attempts', 5));
        $minutes = (int) Settings::get('verrouillage_minutes', Config::get('security.lockout_minutes', 15));

        $user     = $this->find($id);
        $attempts = (int) ($user['failed_attempts'] ?? 0) + 1;

        $data = ['failed_attempts' => $attempts];
        if ($attempts >= $max) {
            $data['locked_until']    = date('Y-m-d H:i:s', time() + $minutes * 60);
            $data['failed_attempts'] = 0;
        }
        $this->update($id, $data);
    }

    public function registerSuccessfulLogin(int $id): void
    {
        $this->update($id, [
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function setPassword(int $id, string $plain, bool $mustChange = false): void
    {
        $this->update($id, [
            'password_hash'        => password_hash($plain, PASSWORD_BCRYPT),
            'must_change_password' => $mustChange ? 1 : 0,
            'reset_token'          => null,
            'reset_expires'        => null,
        ]);
    }

    public function createUser(array $data, string $plainPassword): int
    {
        $data['password_hash'] = password_hash($plainPassword, PASSWORD_BCRYPT);
        return $this->create($data);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM users WHERE email = :e';
        $params = ['e' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM users WHERE username = :u';
        $params = ['u' => $username];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params) > 0;
    }
}
