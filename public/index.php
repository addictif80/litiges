<?php
declare(strict_types=1);

/**
 * Point d'entree unique de l'application (front controller).
 */

$root = dirname(__DIR__);

// Si l'application n'est pas encore installee, on redirige vers l'installeur.
if (!is_file($root . '/config/config.php')) {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    header('Location: ' . $base . '/install.php');
    exit;
}

require $root . '/app/bootstrap.php';

use App\Controllers\AgenceController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\CompanyController;
use App\Controllers\ContactController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentController;
use App\Controllers\InspectionController;
use App\Controllers\LitigeController;
use App\Controllers\MigrationController;
use App\Controllers\NoteController;
use App\Controllers\NotificationController;
use App\Controllers\PartageController;
use App\Controllers\ProfileController;
use App\Controllers\RelanceController;
use App\Controllers\SettingController;
use App\Controllers\ShareController;
use App\Controllers\UserController;
use App\Controllers\ChatController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;

// --- Verification CSRF globale sur les requetes POST -------------------
if (Request::isPost() && !Csrf::check($_POST['_token'] ?? null)) {
    http_response_code(419);
    echo View::render('errors/error', [
        'code'    => 419,
        'message' => "Votre session a expire ou le formulaire est invalide. Merci de recharger la page.",
    ], Auth::check() ? 'layouts/app' : 'layouts/blank');
    exit;
}

// --- Forcer le changement de mot de passe ------------------------------
$chemin = Request::path();
if (Auth::check() && (int) (Auth::user()['must_change_password'] ?? 0) === 1) {
    $autorises = ['/profil/mot-de-passe', '/logout'];
    if (!in_array($chemin, $autorises, true)) {
        \App\Core\Flash::add('warning', 'Vous devez definir un nouveau mot de passe avant de continuer.');
        redirect('/profil/mot-de-passe');
    }
}

// --- Routes ------------------------------------------------------------
$router = new Router();

// Authentification
$router->get('/login',  [AuthController::class, 'showLogin'], 'guest');
$router->post('/login', [AuthController::class, 'login'], 'guest');
$router->post('/logout', [AuthController::class, 'logout'], 'auth');
$router->get('/forgot', [AuthController::class, 'showForgot'], 'guest');
$router->post('/forgot', [AuthController::class, 'sendReset'], 'guest');
$router->get('/reset/{token}',  [AuthController::class, 'showReset'], 'guest');
$router->post('/reset/{token}', [AuthController::class, 'reset'], 'guest');

// Tableau de bord
$router->get('/', [DashboardController::class, 'index'], 'auth');

// Messagerie
$router->get('/chat', [ChatController::class, 'index'], 'module:chat');
$router->post('/chat', [ChatController::class, 'store'], 'module:chat');
$router->get('/chat/{id}', [ChatController::class, 'show'], 'module:chat');
$router->post('/chat/{id}/messages', [ChatController::class, 'storeMessage'], 'module:chat');
$router->get('/chat/{id}/flux', [ChatController::class, 'flux'], 'module:chat');

// Profil
$router->get('/profil', [ProfileController::class, 'show'], 'auth');
$router->post('/profil', [ProfileController::class, 'update'], 'auth');
$router->get('/profil/mot-de-passe', [ProfileController::class, 'showPassword'], 'auth');
$router->post('/profil/mot-de-passe', [ProfileController::class, 'changePassword'], 'auth');

// Notifications
$router->get('/notifications', [NotificationController::class, 'index'], 'auth');
$router->post('/notifications/lu', [NotificationController::class, 'markAll'], 'auth');
$router->post('/notifications/{id}/lu', [NotificationController::class, 'mark'], 'auth');

// Litiges (les routes specifiques doivent preceder /litiges/{id})
$router->get('/litiges', [LitigeController::class, 'index'], 'module:litiges');
$router->get('/litiges/nouveau', [LitigeController::class, 'create'], 'modulewrite:litiges');
$router->post('/litiges', [LitigeController::class, 'store'], 'modulewrite:litiges');
$router->get('/litiges/{id}', [LitigeController::class, 'show'], 'module:litiges');
$router->get('/litiges/{id}/modifier', [LitigeController::class, 'edit'], 'modulewrite:litiges');
$router->post('/litiges/{id}/modifier', [LitigeController::class, 'update'], 'modulewrite:litiges');
$router->post('/litiges/{id}/statut', [LitigeController::class, 'changerStatut'], 'modulewrite:litiges');
$router->post('/litiges/{id}/supprimer', [LitigeController::class, 'destroy'], 'admin');

// Relances
$router->post('/litiges/{id}/relances', [RelanceController::class, 'store'], 'modulewrite:relances');
$router->post('/litiges/{id}/relances/{relanceId}/supprimer', [RelanceController::class, 'destroy'], 'modulewrite:relances');

// Notes
$router->post('/litiges/{id}/notes', [NoteController::class, 'store'], 'modulewrite:litiges');
$router->post('/litiges/{id}/notes/{noteId}', [NoteController::class, 'update'], 'modulewrite:litiges');
$router->post('/litiges/{id}/notes/{noteId}/supprimer', [NoteController::class, 'destroy'], 'modulewrite:litiges');

// Documents
$router->post('/litiges/{id}/documents', [DocumentController::class, 'storeLitige'], 'write');
$router->post('/clients/{id}/documents', [DocumentController::class, 'storeClient'], 'write');
$router->get('/documents/{id}/telecharger', [DocumentController::class, 'download'], 'auth');
$router->post('/documents/{id}/supprimer', [DocumentController::class, 'destroy'], 'write');

// Clients
$router->get('/clients', [ClientController::class, 'index'], 'module:clients');
$router->get('/clients/nouveau', [ClientController::class, 'create'], 'modulewrite:clients');
$router->post('/clients', [ClientController::class, 'store'], 'modulewrite:clients');
$router->get('/clients/{id}', [ClientController::class, 'show'], 'module:clients');
$router->get('/clients/{id}/modifier', [ClientController::class, 'edit'], 'modulewrite:clients');
$router->post('/clients/{id}/modifier', [ClientController::class, 'update'], 'modulewrite:clients');
$router->post('/clients/{id}/rafraichir', [ClientController::class, 'rafraichir'], 'modulewrite:clients');
$router->post('/clients/{id}/analyser-ia', [ClientController::class, 'analyserIa'], 'modulewrite:clients');
$router->post('/clients/{id}/notes', [ClientController::class, 'storeNote'], 'modulewrite:clients');
$router->post('/clients/{id}/notes/{noteId}/supprimer', [ClientController::class, 'destroyNote'], 'modulewrite:clients');
$router->post('/clients/{id}/supprimer', [ClientController::class, 'destroy'], 'admin');

// Contacts
$router->post('/clients/{id}/contacts', [ContactController::class, 'store'], 'modulewrite:clients');
$router->post('/clients/{id}/contacts/{contactId}', [ContactController::class, 'update'], 'modulewrite:clients');
$router->post('/clients/{id}/contacts/{contactId}/supprimer', [ContactController::class, 'destroy'], 'modulewrite:clients');

// API entreprises (JSON)
$router->get('/api/entreprises', [CompanyController::class, 'search'], 'auth');
$router->get('/api/entreprises/{identifiant}', [CompanyController::class, 'detail'], 'auth');

// Inspection d'une entreprise sans creation de client (HTML)
$router->get('/entreprise', [InspectionController::class, 'index'], 'module:clients');
$router->get('/entreprise/{identifiant}', [InspectionController::class, 'show'], 'module:clients');

// Utilisateurs (admin)
$router->get('/utilisateurs', [UserController::class, 'index'], 'admin');
$router->get('/utilisateurs/nouveau', [UserController::class, 'create'], 'admin');
$router->post('/utilisateurs', [UserController::class, 'store'], 'admin');
$router->get('/utilisateurs/{id}/modifier', [UserController::class, 'edit'], 'admin');
$router->post('/utilisateurs/{id}/modifier', [UserController::class, 'update'], 'admin');
$router->post('/utilisateurs/{id}/mot-de-passe', [UserController::class, 'resetPassword'], 'admin');
$router->post('/utilisateurs/{id}/supprimer', [UserController::class, 'destroy'], 'admin');

// Agences (admin)
$router->get('/agences', [AgenceController::class, 'index'], 'admin');
$router->post('/agences', [AgenceController::class, 'store'], 'admin');
$router->post('/agences/{id}', [AgenceController::class, 'update'], 'admin');
$router->post('/agences/{id}/supprimer', [AgenceController::class, 'destroy'], 'admin');

// Parametres (admin)
$router->get('/parametres', [SettingController::class, 'parametres'], 'admin');
$router->post('/parametres', [SettingController::class, 'updateParametres'], 'admin');
$router->get('/parametres/statuts', [SettingController::class, 'statuts'], 'admin');
$router->post('/parametres/statuts', [SettingController::class, 'storeStatut'], 'admin');
$router->post('/parametres/statuts/{id}', [SettingController::class, 'updateStatut'], 'admin');
$router->post('/parametres/statuts/{id}/supprimer', [SettingController::class, 'destroyStatut'], 'admin');

// Base de donnees / migrations (admin)
$router->get('/parametres/migrations', [MigrationController::class, 'index'], 'admin');
$router->post('/parametres/migrations', [MigrationController::class, 'run'], 'admin');

// Journal d'audit (admin)
$router->get('/audit', [AuditController::class, 'index'], 'admin');

// Partages lecture seule
$router->get('/partages', [PartageController::class, 'index'], 'write');
$router->post('/partages', [PartageController::class, 'store'], 'write');
$router->post('/partages/{id}/basculer', [PartageController::class, 'toggle'], 'write');
$router->post('/partages/{id}/supprimer', [PartageController::class, 'destroy'], 'write');
$router->post('/litiges/{id}/partage', [PartageController::class, 'storeLitige'], 'write');

// Consultation publique (code d'acces ou lien, sans compte)
$router->get('/partage', [ShareController::class, 'form']);
$router->post('/partage', [ShareController::class, 'access']);
$router->get('/partage/lien/{token}', [ShareController::class, 'viaLien']);
$router->get('/p/{token}', [ShareController::class, 'viaLien']);
$router->get('/partage/consultation', [ShareController::class, 'index']);
$router->get('/partage/consultation/{id}', [ShareController::class, 'show']);
$router->post('/partage/quitter', [ShareController::class, 'exitShare']);

// --- Dispatch ----------------------------------------------------------
$router->dispatch(Request::method(), Request::path());
