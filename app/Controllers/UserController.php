<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Validator;
use App\Models\Agence;
use App\Models\User;

class UserController extends Controller
{
    public function index(): void
    {
        $this->view('users/index', [
            'titre' => 'Utilisateurs',
            'users' => (new User())->withAgence(),
        ]);
    }

    public function create(): void
    {
        $this->view('users/form', [
            'titre'  => 'Nouvel utilisateur',
            'user'   => ['role' => 'gestionnaire', 'actif' => 1],
            'mode'   => 'creation',
            'agences' => (new Agence())->listActives(),
        ]);
    }

    public function store(): void
    {
        $data      = $this->collect();
        $password  = (string) $this->post('password', '');
        $users     = new User();
        $validator = $this->validate($data, $password, null);

        if ($validator->fails()) {
            $this->backWithErrors('/utilisateurs/nouveau', $validator->errors(), $_POST);
        }

        $data['must_change_password'] = $this->post('must_change_password') !== null ? 1 : 0;
        $id = $users->createUser($data, $password);

        Audit::log('user_creation', 'user', $id, ['username' => $data['username'], 'role' => $data['role']]);
        flash('success', 'Utilisateur cree.');
        $this->redirect('/utilisateurs');
    }

    public function edit(string $id): void
    {
        $user = (new User())->find((int) $id);
        if (!$user) {
            $this->notFound('Utilisateur introuvable.');
        }

        $this->view('users/form', [
            'titre'  => 'Modifier ' . ($user['full_name'] ?: $user['username']),
            'user'   => $user,
            'mode'   => 'edition',
            'agences' => (new Agence())->listActives(),
        ]);
    }

    public function update(string $id): void
    {
        $idUser = (int) $id;
        $users  = new User();
        $user   = $users->find($idUser);
        if (!$user) {
            $this->notFound('Utilisateur introuvable.');
        }

        $data     = $this->collect();
        $password = (string) $this->post('password', '');
        $validator = $this->validate($data, $password, $idUser);

        // Un admin ne peut pas se retrograder ni se desactiver lui-meme.
        if ($idUser === (int) Auth::id()) {
            if ($data['role'] !== 'admin') {
                $validator->custom('role', false, 'Vous ne pouvez pas retirer votre propre role administrateur.');
            }
            if ((int) $data['actif'] !== 1) {
                $validator->custom('actif', false, 'Vous ne pouvez pas desactiver votre propre compte.');
            }
        }

        if ($validator->fails()) {
            $this->backWithErrors('/utilisateurs/' . $idUser . '/modifier', $validator->errors(), $_POST);
        }

        $data['must_change_password'] = $this->post('must_change_password') !== null ? 1 : 0;
        $users->update($idUser, $data);

        if ($password !== '') {
            $users->setPassword($idUser, $password, (bool) $data['must_change_password']);
        }

        Audit::log('user_modification', 'user', $idUser, ['role' => $data['role']]);
        if ($idUser === (int) Auth::id()) {
            Auth::refresh();
        }

        flash('success', 'Utilisateur mis a jour.');
        $this->redirect('/utilisateurs');
    }

    /** Reinitialise le mot de passe avec un mot de passe temporaire. */
    public function resetPassword(string $id): void
    {
        $users = new User();
        $user  = $users->find((int) $id);
        if (!$user) {
            $this->notFound('Utilisateur introuvable.');
        }

        $temporaire = $this->motDePasseTemporaire();
        $users->setPassword((int) $id, $temporaire, true);

        Audit::log('user_reset_password', 'user', (int) $id, ['username' => $user['username']]);

        flash('warning', 'Mot de passe temporaire pour ' . $user['username'] . ' : ' . $temporaire
            . ' (a communiquer puis a changer a la premiere connexion).');
        $this->redirect('/utilisateurs');
    }

    public function destroy(string $id): void
    {
        $idUser = (int) $id;
        if ($idUser === (int) Auth::id()) {
            flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            $this->redirect('/utilisateurs');
        }

        $users = new User();
        $user  = $users->find($idUser);
        if (!$user) {
            $this->notFound('Utilisateur introuvable.');
        }

        $users->delete($idUser);
        Audit::log('user_suppression', 'user', $idUser, ['username' => $user['username']]);

        flash('success', 'Utilisateur supprime.');
        $this->redirect('/utilisateurs');
    }

    // ------------------------------------------------------------------

    private function collect(): array
    {
        return [
            'username'  => (string) $this->post('username', ''),
            'email'     => (string) $this->post('email', '') ?: null,
            'full_name' => (string) $this->post('full_name', '') ?: null,
            'role'      => (string) $this->post('role', 'gestionnaire'),
            'agence_id' => $this->int('agence_id') ?: null,
            'actif'     => $this->post('actif') !== null ? 1 : 0,
        ];
    }

    private function validate(array $data, string $password, ?int $exceptId): Validator
    {
        $minLength = (int) Config::get('security.password_min_length', 10);
        $users     = new User();

        $validator = new Validator($data, ['username' => "L'identifiant", 'role' => 'Le role']);
        $validator
            ->required('username')->length('username', 3, 60)
            ->email('email')
            ->in('role', ['admin', 'gestionnaire', 'lecteur']);

        if ($users->usernameExists($data['username'], $exceptId)) {
            $validator->custom('username', false, 'Cet identifiant est deja utilise.');
        }
        if (!empty($data['email']) && $users->emailExists($data['email'], $exceptId)) {
            $validator->custom('email', false, 'Cette adresse email est deja utilisee.');
        }
        if ($exceptId === null && mb_strlen($password) < $minLength) {
            $validator->custom('password', false, "Le mot de passe doit contenir au moins {$minLength} caracteres.");
        } elseif ($password !== '' && mb_strlen($password) < $minLength) {
            $validator->custom('password', false, "Le mot de passe doit contenir au moins {$minLength} caracteres.");
        }

        return $validator;
    }

    private function motDePasseTemporaire(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $mot = '';
        for ($i = 0; $i < 12; $i++) {
            $mot .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $mot . random_int(10, 99);
    }
}
