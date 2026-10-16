<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Models\User;
use App\Services\IcalService;

/**
 * Flux iCal personnel. L'URL contient un jeton secret propre a l'utilisateur
 * (aucune session : les logiciels d'agenda ne peuvent pas se connecter).
 */
class AgendaController extends Controller
{
    /** GET /agenda.ics?token=... */
    public function flux(): void
    {
        $attente = RateLimiter::hit('ical:' . Request::ip(), 120, 3600);
        if ($attente !== null) {
            http_response_code(429);
            header('Retry-After: ' . $attente);
            exit('Trop de requêtes.');
        }

        $jeton = (string) $this->input('token', '');
        $user = ($jeton !== '' && preg_match('/^[a-f0-9]{48}$/', $jeton))
            ? Database::fetch('SELECT * FROM users WHERE ical_token = :t AND actif = 1 LIMIT 1', ['t' => $jeton])
            : null;
        if (!$user) {
            http_response_code(404);
            exit('Agenda introuvable.');
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="litiges.ics"');
        header('Cache-Control: private, max-age=900');
        echo (new IcalService())->calendrier($user);
        exit;
    }

    /** POST /profil/agenda : (re)genere le jeton personnel. */
    public function regenerer(): void
    {
        $jeton = bin2hex(random_bytes(24));
        (new User())->update((int) Auth::id(), ['ical_token' => $jeton]);
        Audit::log('agenda_jeton_genere', 'user', (int) Auth::id());
        Auth::refresh();
        flash('success', "Nouvelle adresse d'agenda générée. L'ancienne ne fonctionne plus.");
        $this->redirect('/profil');
    }

    public function supprimer(): void
    {
        (new User())->update((int) Auth::id(), ['ical_token' => null]);
        Audit::log('agenda_jeton_supprime', 'user', (int) Auth::id());
        Auth::refresh();
        flash('success', 'Abonnement agenda désactivé.');
        $this->redirect('/profil');
    }
}
