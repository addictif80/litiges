<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ExportService;

class AuditController extends Controller
{
    public function index(): void
    {
        $filtres = [
            'q'         => (string) $this->input('q', ''),
            'action'    => (string) $this->input('action', ''),
            'user_id'   => $this->int('user_id'),
            'date_from' => (string) $this->input('date_from', ''),
            'date_to'   => (string) $this->input('date_to', ''),
        ];

        $model = new AuditLog();
        $page  = max(1, $this->int('page', 1));
        $resultat = $model->search($filtres, $page, 50);

        if ($this->input('format') === 'csv') {
            $colonnes = [
                'created_at' => 'Date', 'username' => 'Utilisateur', 'action' => 'Action',
                'entite' => 'Entite', 'entite_id' => 'ID entite', 'ip' => 'IP', 'details' => 'Details',
            ];
            Audit::log('export_audit', 'audit');
            (new ExportService())->csv('journal_audit_' . date('Ymd_His'), $colonnes, $resultat['rows']);
        }

        $this->view('audit/index', [
            'titre'    => "Journal d'audit",
            'lignes'   => $resultat['rows'],
            'total'    => $resultat['total'],
            'page'     => $resultat['page'],
            'pages'    => $resultat['pages'],
            'filtres'  => $filtres,
            'actions'  => $model->actionsDistinctes(),
            'users'    => (new User())->withAgence(),
        ]);
    }
}
