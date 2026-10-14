<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Litige;
use App\Models\LitigeStatut;
use App\Models\Notification;
use App\Models\Relance;

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

        $this->view('dashboard/index', [
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
