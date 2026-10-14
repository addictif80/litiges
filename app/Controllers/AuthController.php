<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
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

        $error = Auth::attempt($username, $password);
        if ($error !== null) {
            $this->backWithErrors('/login', ['global' => $error], ['username' => $username]);
        }

        $user = Auth::user();
        if ($user && (int) $user['must_change_password'] === 1) {
            flash('warning', 'Vous devez definir un nouveau mot de passe avant de continuer.');
            $this->redirect('/profil/mot-de-passe');
        }

        $intended = $_SESSION['_intended'] ?? '/';
        unset($_SESSION['_intended']);

        flash('success', 'Bienvenue ' . ($user['full_name'] ?: $user['username']) . ' !');
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
            $corps  = '<p>Une reinitialisation de mot de passe a ete demandee pour votre compte.</p>'
                . '<p><a href="' . e($lien) . '">Definir un nouveau mot de passe</a> (valable 1 heure).</p>'
                . '<p>Si vous n\'etes pas a l\'origine de cette demande, ignorez ce message.</p>';

            $envoye = $mailer->isEnabled() && $mailer->send($email, 'Reinitialisation de votre mot de passe', $mailer->gabarit('Reinitialisation du mot de passe', $corps));

            Audit::log('reset_demande', 'auth', (int) $user['id'], ['email_ok' => $envoye]);

            if ($envoye) {
                flash('success', 'Un email de reinitialisation vous a ete envoye.');
            } else {
                // Sans SMTP configure, on affiche le lien a l'administrateur/utilisateur.
                flash('warning', 'Envoi email indisponible. Lien de reinitialisation : ' . $lien);
            }
        } else {
            Audit::log('reset_demande', 'auth', null, ['email' => $email, 'trouve' => false]);
            flash('success', 'Si un compte correspond a cette adresse, un email de reinitialisation a ete envoye.');
        }

        $this->redirect('/login');
    }

    public function showReset(string $token): void
    {
        $user = (new User())->findByResetToken($token);
        if (!$user) {
            flash('error', 'Lien de reinitialisation invalide ou expire.');
            $this->redirect('/login');
        }
        $this->view('auth/reset', ['token' => $token], 'layouts/blank');
    }

    public function reset(string $token): void
    {
        $user = (new User())->findByResetToken($token);
        if (!$user) {
            flash('error', 'Lien de reinitialisation invalide ou expire.');
            $this->redirect('/login');
        }

        $password = (string) $this->post('password', '');
        $confirm  = (string) $this->post('password_confirm', '');
        $minLength = (int) \App\Core\Config::get('security.password_min_length', 10);

        $errors = [];
        if (mb_strlen($password) < $minLength) {
            $errors['password'] = "Le mot de passe doit contenir au moins {$minLength} caracteres.";
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if ($errors !== []) {
            $this->backWithErrors('/reset/' . $token, $errors);
        }

        (new User())->setPassword((int) $user['id'], $password);
        Audit::log('reset_effectue', 'auth', (int) $user['id']);

        flash('success', 'Votre mot de passe a ete modifie. Vous pouvez vous connecter.');
        $this->redirect('/login');
    }
}
