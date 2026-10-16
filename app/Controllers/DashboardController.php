<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Litige;
use App\Models\LitigeStatut;
use App\Models\Notification;
use App\Models\Relance;
use App\Models\Tache;
use App\Services\ReportService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $litiges = new Litige();

        $stats          = $litiges->statsGlobal();
        $parStatut      = $litiges->statsParStatut();
        $parAgence      = $litiges->statsParAgence();
        $parResponsable = $litiges->statsParResponsable();
        $topClients     = $litiges->topClients(5);
        $evolution      = $litiges->evolutionMensuelle(12);

        $recherche = [
            'etat'         => 'ouverts',
            'relance_due'  => 1,
            'tri'          => 'echeance',
        ];
        $relancesDues = $litiges->search($recherche, 1, 8);

        $recentes = $litiges->search(['etat' => 'ouverts', 'tri' => 'recent'], 1, 8);

        $notifications = new Notification();
        $nonLues = Auth::id() ? $notifications->countNonLues((int) Auth::id()) : 0;

        $mesTaches = [];
        $previsions = null;
        $creancesUrgentes = [];
        try {
            if (Auth::peut('litiges')) {
                $mesTaches = (new Tache())->liste(['assigne_a' => (int) Auth::id(), 'statut' => 'ouvertes'], 6);
                $previsions = (new ReportService())->previsions(4);
                $creancesUrgentes = \App\Core\Database::fetchAll(
                    "SELECT l.id, l.reference, c.raison_sociale, c.procedure_collective,
                            DATE_ADD(c.procedure_date_parution, INTERVAL :m MONTH) AS limite
                     FROM litiges l
                     JOIN clients c ON c.id = l.client_id
                     JOIN litige_statuts s ON s.id = l.statut_id
                     WHERE s.cloturant = 0 AND l.creance_declaree_le IS NULL AND c.procedure_date_parution IS NOT NULL
                       AND DATE_ADD(c.procedure_date_parution, INTERVAL :m2 MONTH) >= CURDATE()
                     ORDER BY limite ASC LIMIT 5",
                    ['m' => max(1, (int) \App\Core\Settings::get('creance_delai_mois', 2)), 'm2' => max(1, (int) \App\Core\Settings::get('creance_delai_mois', 2))]
                );
            }
        } catch (\Throwable $e) {
            // Les nouvelles tables peuvent ne pas encore exister (migrations en attente).
            \App\Core\Logger::exception($e, 'Tableau de bord : widgets de recouvrement');
        }

        $this->view('dashboard/index', [
            'mesTaches'        => $mesTaches,
            'previsions'       => $previsions,
            'creancesUrgentes' => $creancesUrgentes,
            'titre'          => 'Tableau de bord',
            'stats'          => $stats,
            'parStatut'      => $parStatut,
            'parAgence'      => $parAgence,
            'parResponsable' => $parResponsable,
            'topClients'     => $topClients,
            'evolution'      => $evolution,
            'relancesDues'   => $relancesDues['rows'],
            'nbRelancesDues' => $relancesDues['total'],
            'derniers'       => $recentes['rows'],
            'relancesRecentes' => (new Relance())->recentes(6),
            'nbNotifications' => $nonLues,
        ]);
    }
}
