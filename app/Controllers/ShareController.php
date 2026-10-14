<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Session;
use App\Models\ClientNote;
use App\Models\ClientNote;
use App\Models\LitigeStatut;
use App\Models\Partage;
use App\Models\Relance;

/**
 * Acces public en LECTURE SEULE via un code d'acces ou un lien public (pour les agences).
 * Aucune authentification utilisateur : la portee est definie par le partage.
 */
class ShareController extends Controller
{
    private const SESSION_KEY = '_partage';

    public function form(): void
    {
        $this->view('share/form', [
            'titre'   => 'Consultation en lecture seule',
            'partage' => $this->partageCourant(),
        ], 'layouts/share');
    }

    public function access(): void
    {
        $code = strtoupper(trim((string) $this->post('code', '')));
        $partage = (new Partage())->findByCode($code);

        if (!$partage) {
            Audit::log('partage_echec', 'partage', null, ['code' => $code]);
            $_SESSION['_errors'] = ['code' => "Code d'acces invalide ou expire."];
            $this->redirect('/partage');
        }

        Session::set(self::SESSION_KEY, (int) $partage['id']);
        Session::regenerate();
        (new Partage())->enregistrerVue((int) $partage['id']);
        Audit::log('partage_acces', 'partage', (int) $partage['id']);

        $this->redirect('/partage/consultation');
    }

    /** Acces direct via un lien public (/partage/lien/{token}). */
    public function viaLien(string $token): void
    {
        $partage = (new Partage())->findByToken($token);
        if (!$partage) {
            Audit::log('partage_echec', 'partage', null, ['token' => substr($token, 0, 8)]);
            $_SESSION['_errors'] = ['code' => "Lien d'acces invalide ou expire."];
            $this->redirect('/partage');
        }

        Session::set(self::SESSION_KEY, (int) $partage['id']);
        Session::regenerate();
        (new Partage())->enregistrerVue((int) $partage['id']);
        Audit::log('partage_acces', 'partage', (int) $partage['id'], ['via' => 'lien']);

        $this->redirect('/partage/consultation');
    }

    public function index(): void
    {
        $partage = $this->partageCourant();
        if (!$partage) {
            $this->redirect('/partage');
        }

        $filtres = array_merge($this->filtresPartage($partage), [
            'q'         => (string) $this->input('q', ''),
            'etat'      => (string) $this->input('etat', ''),
            'statut_id' => $this->int('statut_id'),
            'tri'       => 'recent',
        ]);

        $page     = max(1, $this->int('page', 1));
        $resultat = (new Litige())->search($filtres, $page, 25);

        $this->view('share/index', [
            'titre'   => 'Litiges — consultation',
            'partage' => $partage,
            'litiges' => $resultat['rows'],
            'total'   => $resultat['total'],
            'page'    => $resultat['page'],
            'pages'   => $resultat['pages'],
            'filtres' => $filtres,
            'statuts' => (new LitigeStatut())->statuts(),
        ], 'layouts/share');
    }

    public function show(string $id): void
    {
        $partage = $this->partageCourant();
        if (!$partage) {
            $this->redirect('/partage');
        }

        $litige = (new Litige())->findFull((int) $id);
        if (!$litige || !$this->dansPerimetre($partage, $litige)) {
            $this->notFound('Litige introuvable ou hors de votre perimetre de consultation.');
        }

        (new Partage())->enregistrerVue((int) $partage['id']);

        $this->view('share/show', [
            'titre'       => 'Litige ' . $litige['reference'],
            'partage'     => $partage,
            'litige'      => $litige,
            'relances'    => (new Relance())->forLitige((int) $id),
            'clientNotes' => (new ClientNote())->forClient((int) $litige['client_id']),
            'types'       => LitigeController::TYPES,
        ], 'layouts/share');
    }

    public function exitShare(): void
    {
        Session::forget(self::SESSION_KEY);
        flash('success', 'Vous avez quitte la consultation.');
        $this->redirect('/partage');
    }

    // ------------------------------------------------------------------

    private function partageCourant(): ?array
    {
        $id = (int) Session::get(self::SESSION_KEY, 0);
        if ($id <= 0) {
            return null;
        }
        $partage = (new Partage())->find($id);
        if (!$partage || (int) $partage['actif'] !== 1
            || ($partage['expires_at'] !== null && strtotime((string) $partage['expires_at']) < time())) {
            Session::forget(self::SESSION_KEY);
            return null;
        }
        return $partage;
    }

    private function filtresPartage(array $partage): array
    {
        return match ($partage['scope']) {
            'agence' => ['agence_id' => (int) $partage['scope_id']],
            'client' => ['client_id' => (int) $partage['scope_id']],
            'litige' => ['only_id'   => (int) $partage['scope_id']],
            default  => [],
        };
    }

    private function dansPerimetre(array $partage, array $litige): bool
    {
        return match ($partage['scope']) {
            'agence' => (int) $litige['agence_id'] === (int) $partage['scope_id'],
            'client' => (int) $litige['client_id'] === (int) $partage['scope_id'],
            'litige' => (int) $litige['id'] === (int) $partage['scope_id'],
            default  => true,
        };
    }
}
