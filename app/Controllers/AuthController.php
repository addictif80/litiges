<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Models\User;
use App\Services\Mailer;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', [], 'layouts/blank');
    }

    public function login(): void
    {
        $username = (string) $this->post('username', '');
        $password = (string) $this->post('password', '');

        if ($username === '' || $password === '') {
            $this->backWithErrors('/login', ['global' => 'Veuillez renseigner vos identifiants.'], ['username' => $username]);
        }

        // Limitation par adresse IP (en plus du verrouillage par compte).
        $cleIp = 'login:' . Request::ip();
        $attente = RateLimiter::bloque($cleIp, 20, 600);
        if ($attente !== null) {
            Audit::log('login_bloque', 'auth', null, ['username' => $username]);
            $this->backWithErrors('/login', ['global' => RateLimiter::message($attente)], ['username' => $username]);
        }

        $error = Auth::attempt($username, $password);
        if ($error === Auth::DOUBLE_AUTH_REQUISE) {
            $this->redirect('/login/2fa');
        }
        if ($error !== null) {
            RateLimiter::hit($cleIp, 20, 600);
            $this->backWithErrors('/login', ['global' => $error], ['username' => $username]);
        }
        RateLimiter::reinitialiser($cleIp);

        $user = Auth::user();
        if ($user && (int) $user['must_change_password'] === 1) {
            flash('warning', 'Vous devez définir un nouveau mot de passe avant de continuer.');
            $this->redirect('/profil/mot-de-passe');
        }

        $intended = $_SESSION['_intended'] ?? '/';
        unset($_SESSION['_intended']);

        flash('success', 'Bienvenue ' . e($user['full_name'] ?: $user['username']) . ' !');
        $this->redirect(is_string($intended) ? $intended : '/');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    public function showForgot(): void
    {
        $this->view('auth/forgot', [], 'layouts/blank');
    }

    public function sendReset(): void
    {
        $email = (string) $this->post('email', '');

        // Limitation par IP et par adresse : evite le spam d'e-mails et
        // l'enumeration de comptes. La reponse reste identique dans tous les cas.
        $bloqueIp   = RateLimiter::hit('forgot:' . Request::ip(), 5, 3600);
        $bloqueMail = RateLimiter::hit('forgot_mail:' . sha1(strtolower($email)), 3, 3600);
        if ($bloqueIp !== null || $bloqueMail !== null) {
            Audit::log('reset_limite', 'auth', null);
            flash('success', 'Si un compte correspond à cette adresse, un e-mail de réinitialisation a été envoyé.');
            $this->redirect('/login');
        }

        $user  = (new User())->findBy('email', $email);

        // Reponse identique que le compte existe ou non (anti-enumeration).
        if ($user && (int) $user['actif'] === 1) {
            $token = bin2hex(random_bytes(32));
            (new User())->update((int) $user['id'], [
                'reset_token'   => $token,
                'reset_expires' => date('Y-m-d H:i:s', time() + 3600),
            ]);

            $lien = url('reset/' . $token);
            $mailer = new Mailer();
            $corps  = '<p>Une réinitialisation de mot de passe a été demandée pour votre compte.</p>'
                . '<p><a href="' . e($lien) . '">Définir un nouveau mot de passe</a> (valable 1 heure).</p>'
                . '<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.</p>';

            $envoye = $mailer->isEnabled() && $mailer->send($email, 'Réinitialisation de votre mot de passe', $mailer->gabarit('Réinitialisation du mot de passe', $corps));

            Audit::log('reset_demande', 'auth', (int) $user['id'], ['email_ok' => $envoye]);

            if (!$envoye) {
                // Le lien n'est JAMAIS affiche a l'ecran : n'importe qui connaissant
                // l'adresse pourrait sinon prendre le controle du compte. Sans SMTP,
                // un administrateur reinitialise le mot de passe depuis l'administration.
                Logger::warning('Réinitialisation demandée mais e-mail non envoyé (SMTP indisponible)', [
                    'user_id' => (int) $user['id'],
                ]);
            }
        } else {
            Audit::log('reset_demande', 'auth', null, ['email' => $email, 'trouve' => false]);
        }

        flash('success', 'Si un compte correspond à cette adresse, un e-mail de réinitialisation a été envoyé.');
        $this->redirect('/login');
    }

    public function showReset(string $token): void
    {
        $user = (new User())->findByResetToken($token);
        if (!$user) {
            flash('error', 'Lien de réinitialisation invalide ou expiré.');
            $this->redirect('/login');
        }
        $this->view('auth/reset', ['token' => $token], 'layouts/blank');
    }

    public function reset(string $token): void
    {
        $user = (new User())->findByResetToken($token);
        if (!$user) {
            flash('error', 'Lien de réinitialisation invalide ou expiré.');
            $this->redirect('/login');
        }

        $password = (string) $this->post('password', '');
        $confirm  = (string) $this->post('password_confirm', '');
        $minLength = (int) \App\Core\Config::get('security.password_min_length', 10);

        $errors = [];
        if (mb_strlen($password) < $minLength) {
            $errors['password'] = "Le mot de passe doit contenir au moins {$minLength} caractères.";
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if ($errors !== []) {
            $this->backWithErrors('/reset/' . $token, $errors);
        }

        (new User())->setPassword((int) $user['id'], $password);
        Audit::log('reset_effectue', 'auth', (int) $user['id']);

        flash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
        $this->redirect('/login');
    }
}
