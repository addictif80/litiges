<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Settings;
use App\Models\User;
use App\Services\TwoFactorService;

/**
 * Double authentification : defi a la connexion + gestion depuis le profil.
 */
class TwoFactorController extends Controller
{
    // ---------------------------------------------------------------
    // Defi a la connexion
    // ---------------------------------------------------------------

    public function showChallenge(): void
    {
        if (Auth::utilisateurEnAttente2fa() === null) {
            $this->redirect('/login');
        }
        $this->view('auth/twofa', [], 'layouts/blank');
    }

    public function verifyChallenge(): void
    {
        $user = Auth::utilisateurEnAttente2fa();
        if ($user === null) {
            flash('warning', 'Votre tentative de connexion a expiré. Merci de recommencer.');
            $this->redirect('/login');
        }

        $cle = '2fa:' . (int) $user['id'];
        $attente = RateLimiter::bloque($cle, 5, 600);
        if ($attente !== null) {
            Auth::annulerDoubleAuth();
            Audit::log('2fa_bloque', 'auth', (int) $user['id']);
            flash('error', RateLimiter::message($attente));
            $this->redirect('/login');
        }

        $code = (string) $this->post('code', '');
        if ((new TwoFactorService())->verifierConnexion($user, $code)) {
            RateLimiter::reinitialiser($cle);
            Auth::terminerDoubleAuth($user);

            $intended = $_SESSION['_intended'] ?? '/';
            unset($_SESSION['_intended']);
            flash('success', 'Bienvenue ' . e($user['full_name'] ?: $user['username']) . ' !');
            $this->redirect(is_string($intended) ? $intended : '/');
        }

        RateLimiter::hit($cle, 5, 600);
        Audit::log('2fa_echec', 'auth', (int) $user['id']);
        $this->backWithErrors('/login/2fa', ['code' => 'Code incorrect ou déjà utilisé.']);
    }

    // ---------------------------------------------------------------
    // Gestion depuis le profil
    // ---------------------------------------------------------------

    public function show(): void
    {
        $user    = Auth::user();
        $service = new TwoFactorService();
        $donnees = [
            'titre'      => 'Double authentification',
            'user'       => $user,
            'active'     => (int) $user['totp_enabled'] === 1,
            'obligatoire' => Auth::a2faObligatoire(),
            'restants'   => $service->codesRestants($user),
            'nouveauxCodes' => $_SESSION['_2fa_codes'] ?? null,
        ];
        unset($_SESSION['_2fa_codes']);

        if (!$donnees['active']) {
            if (empty($_SESSION['_2fa_secret'])) {
                $_SESSION['_2fa_secret'] = $service->preparerActivation($user, $this->emetteur())['secret'];
            }
            $secret = (string) $_SESSION['_2fa_secret'];
            $donnees['secret'] = $secret;
            $donnees['uri']    = \App\Core\Totp::uri((string) ($user['email'] ?: $user['username']), $this->emetteur(), $secret);
        }

        $this->view('profile/twofa', $donnees);
    }

    public function activate(): void
    {
        $user   = Auth::user();
        $secret = (string) ($_SESSION['_2fa_secret'] ?? '');
        if ($secret === '') {
            $this->redirect('/profil/2fa');
        }

        $codes = (new TwoFactorService())->activer((int) $user['id'], $secret, (string) $this->post('code', ''));
        if ($codes === null) {
            $this->backWithErrors('/profil/2fa', ['code' => 'Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.']);
        }

        unset($_SESSION['_2fa_secret']);
        $_SESSION['_2fa_codes'] = $codes;
        Auth::refresh();
        flash('success', 'Double authentification activée. Conservez vos codes de secours.');
        $this->redirect('/profil/2fa');
    }

    public function disable(): void
    {
        $user = Auth::user();
        if (!$this->motDePasseValide((string) $this->post('password', ''))) {
            $this->backWithErrors('/profil/2fa', ['password' => 'Mot de passe incorrect.']);
        }
        (new TwoFactorService())->desactiver((int) $user['id']);
        Auth::refresh();
        flash('success', 'Double authentification désactivée.');
        $this->redirect('/profil/2fa');
    }

    public function regenerateCodes(): void
    {
        $user = Auth::user();
        if (!$this->motDePasseValide((string) $this->post('password', ''))) {
            $this->backWithErrors('/profil/2fa', ['password' => 'Mot de passe incorrect.']);
        }
        $_SESSION['_2fa_codes'] = (new TwoFactorService())->regenererCodes((int) $user['id']);
        flash('success', 'De nouveaux codes de secours ont été générés. Les anciens ne fonctionnent plus.');
        $this->redirect('/profil/2fa');
    }

    private function motDePasseValide(string $motDePasse): bool
    {
        $attente = RateLimiter::hit('2fa_mdp:' . (int) Auth::id(), 5, 600);
        if ($attente !== null) {
            return false;
        }
        $user = (new User())->find((int) Auth::id());
        return $user !== null && password_verify($motDePasse, (string) $user['password_hash']);
    }

    private function emetteur(): string
    {
        return (string) Settings::get('app_nom', Config::get('app.name', 'Litiges'));
    }
}
