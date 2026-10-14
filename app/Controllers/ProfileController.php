<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Validator;
use App\Models\User;

class ProfileController extends Controller
{
    public function show(): void
    {
        $this->view('profile/show', [
            'titre' => 'Mon profil',
            'user'  => Auth::user(),
        ]);
    }

    public function update(): void
    {
        $id   = (int) Auth::id();
        $data = [
            'full_name' => (string) $this->post('full_name', ''),
            'email'     => (string) $this->post('email', ''),
        ];

        $validator = new Validator($data, ['full_name' => 'Le nom complet', 'email' => "L'email"]);
        $validator->required('full_name')->length('full_name', 2, 150)->email('email');

        $users = new User();
        if ($data['email'] !== '' && $users->emailExists($data['email'], $id)) {
            $validator->custom('email', false, 'Cette adresse email est deja utilisee.');
        }

        if ($validator->fails()) {
            $this->backWithErrors('/profil', $validator->errors(), $data);
        }

        $users->update($id, $data);
        Audit::log('profil_modifie', 'user', $id, $data);
        Auth::refresh();

        flash('success', 'Profil mis a jour.');
        $this->redirect('/profil');
    }

    public function showPassword(): void
    {
        $this->view('profile/password', [
            'titre'  => 'Changer mon mot de passe',
            'force'  => (bool) (Auth::user()['must_change_password'] ?? false),
        ]);
    }

    public function changePassword(): void
    {
        $current = (string) $this->post('current_password', '');
        $new     = (string) $this->post('password', '');
        $confirm = (string) $this->post('password_confirm', '');
        $id      = (int) Auth::id();
        $users   = new User();
        $user    = $users->find($id);

        $errors    = [];
        $minLength = (int) Config::get('security.password_min_length', 10);

        // Le mot de passe actuel n'est exige que s'il ne s'agit pas d'un changement force.
        if (!(bool) ($user['must_change_password'] ?? false)) {
            if (!password_verify($current, (string) $user['password_hash'])) {
                $errors['current_password'] = 'Mot de passe actuel incorrect.';
            }
        }
        if (mb_strlen($new) < $minLength) {
            $errors['password'] = "Le nouveau mot de passe doit contenir au moins {$minLength} caracteres.";
        }
        if ($new !== $confirm) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if ($errors !== []) {
            $this->backWithErrors('/profil/mot-de-passe', $errors);
        }

        $users->setPassword($id, $new, false);
        Audit::log('mot_de_passe_change', 'user', $id);
        Auth::refresh();

        flash('success', 'Mot de passe modifie avec succes.');
        $this->redirect('/');
    }
}
