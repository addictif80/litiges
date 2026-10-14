<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class AuditLog extends Model
{
    protected string $table = 'audit_log';
    protected array $fillable = ['user_id', 'username', 'action', 'entite', 'entite_id', 'ip', 'details'];

    /**
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public function search(array $f, int $page = 1, int $perPage = 50): array
    {
        $conds  = [];
        $params = [];

        if (!empty($f['q'])) {
            $conds[] = '(username LIKE :q OR action LIKE :q OR entite LIKE :q OR ip LIKE :q OR details LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['action'])) {
            $conds[] = 'action = :action';
            $params['action'] = $f['action'];
        }
        if (!empty($f['user_id'])) {
            $conds[] = 'user_id = :user_id';
            $params['user_id'] = (int) $f['user_id'];
        }
        if (!empty($f['date_from'])) {
            $conds[] = 'created_at >= :date_from';
            $params['date_from'] = $f['date_from'] . ' 00:00:00';
        }
        if (!empty($f['date_to'])) {
            $conds[] = 'created_at <= :date_to';
            $params['date_to'] = $f['date_to'] . ' 23:59:59';
        }

        $where = $conds === [] ? '1' : implode(' AND ', $conds);

        $total = (int) Database::value("SELECT COUNT(*) FROM audit_log WHERE {$where}", $params, 0);
        $page  = max(1, $page);
        $perPage = min(200, max(10, $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT * FROM audit_log WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows'  => $rows,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    public function actionsDistinctes(): array
    {
        return Database::fetchAll('SELECT DISTINCT action FROM audit_log ORDER BY action ASC');
    }
}
