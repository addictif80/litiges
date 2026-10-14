<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Authentification, autorisations et gestion du compte connecte.
 */
class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    /** Tente une connexion. Renvoie null si OK, sinon un message d'erreur. */
    public static function attempt(string $username, string $password): ?string
    {
        $userModel = new User();
        $user      = $userModel->findByLogin($username);

        // Message generique pour ne pas reveler l'existence du compte.
        $genericError = "Identifiant ou mot de passe incorrect.";

        if (!$user) {
            // Comparaison factice pour egaliser le temps de reponse.
            password_verify($password, '$2y$10$usesomesillystringforsalt000000000000000000000000000000');
            Audit::log('login_echec', 'auth', null, ['username' => $username, 'raison' => 'utilisateur_inconnu']);
            return $genericError;
        }

        if ((int) $user['actif'] !== 1) {
            Audit::log('login_echec', 'auth', (int) $user['id'], ['raison' => 'compte_desactive']);
            return "Ce compte est desactive. Contactez l'administrateur.";
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            $minutes = max(1, (int) ceil((strtotime((string) $user['locked_until']) - time()) / 60));
            Audit::log('login_echec', 'auth', (int) $user['id'], ['raison' => 'compte_verrouille']);
            return "Compte temporairement verrouille. Reessayez dans {$minutes} minute(s).";
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $userModel->registerFailedAttempt((int) $user['id']);
            Audit::log('login_echec', 'auth', (int) $user['id'], ['raison' => 'mot_de_passe_invalide']);
            $max = (int) Settings::get('verrouillage_tentatives', Config::get('security.max_login_attempts', 5));
            $restant = max(0, $max - (int) $user['failed_attempts'] - 1);
            return $restant > 0
                ? "Identifiant ou mot de passe incorrect. Il reste {$restant} tentative(s)."
                : "Identifiant ou mot de passe incorrect. Compte verrouille.";
        }

        // Succes
        $userModel->registerSuccessfulLogin((int) $user['id']);
        self::login($user);
        Audit::log('login', 'auth', (int) $user['id']);

        return null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        self::$user          = $user;
        self::$resolved      = true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('logout', 'auth', self::id());
        }
        self::$user     = null;
        self::$resolved = false;
        Session::destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            self::$user = null;
            return null;
        }

        $user = (new User())->find($id);
        if (!$user || (int) $user['actif'] !== 1) {
            self::$user = null;
            return null;
        }
        self::$user = $user;
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): string
    {
        return (string) (self::user()['role'] ?? 'invite');
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isLecteur(): bool
    {
        return self::role() === 'lecteur';
    }

    /** L'utilisateur peut-il modifier les donnees (admin ou gestionnaire) ? */
    public static function canWrite(): bool
    {
        return in_array(self::role(), ['admin', 'gestionnaire'], true);
    }

    /** Modules de l'application soumis aux permissions par role. */
    public const MODULES = [
        'litiges'  => 'Litiges',
        'clients'  => 'Clients',
        'relances' => 'Relances',
        'chat'     => 'Messagerie',
    ];

    /** Roles disposant de reglages de permissions (l'admin a toujours tout). */
    public const ROLES_CONFIGURABLES = ['gestionnaire', 'consultant', 'lecteur'];

    /** @var array<string,array<string,bool>> Permissions par defaut. */
    private const PERMISSIONS_DEFAUT = [
        'gestionnaire' => ['litiges' => true,  'clients' => true,  'relances' => true,  'chat' => true],
        'consultant'   => ['litiges' => true,  'clients' => true,  'relances' => false, 'chat' => true],
        'lecteur'      => ['litiges' => true,  'clients' => true,  'relances' => false, 'chat' => true],
    ];

    /** L'utilisateur connecte a-t-il acces a ce module ? */
    public static function peut(string $module): bool
    {
        return self::peutPourRole(self::role(), $module);
    }

    /**
     * Le role donne a-t-il acces a ce module ? Utilise notamment pour cibler
     * les destinataires des notifications (un utilisateur ne recoit une
     * notification de module que s'il peut acceder a ce module).
     */
    public static function peutPourRole(string $role, string $module): bool
    {
        if ($role === 'admin') {
            return true;
        }
        $defaut = self::PERMISSIONS_DEFAUT[$role][$module] ?? false;
        return Settings::bool('perm_' . $role . '_' . $module, $defaut);
    }

    /** Libelle lisible d'un role. */
    public static function roleLabel(?string $role = null): string
    {
        $role = $role ?? self::role();
        return [
            'admin'        => 'Administrateur',
            'gestionnaire' => 'Gestionnaire',
            'consultant'   => 'Consultant',
            'lecteur'      => 'Lecteur (consultation)',
            'invite'       => 'Invite',
        ][$role] ?? ucfirst($role);
    }

    public static function agenceId(): ?int
    {
        $user = self::user();
        return isset($user['agence_id']) && $user['agence_id'] !== null ? (int) $user['agence_id'] : null;
    }

    /** Rafraichit l'utilisateur memorise (apres modification de son profil). */
    public static function refresh(): void
    {
        self::$resolved = false;
        self::$user     = null;
        self::user();
    }
}
