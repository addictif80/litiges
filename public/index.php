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
use App\Controllers\AgendaController;
use App\Controllers\ApiV1Controller;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\ClientController;
use App\Controllers\CompanyController;
use App\Controllers\ContactController;
use App\Controllers\CourrierController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentController;
use App\Controllers\FiltreController;
use App\Controllers\ImportController;
use App\Controllers\InspectionController;
use App\Controllers\IntegrationController;
use App\Controllers\LitigeController;
use App\Controllers\MigrationController;
use App\Controllers\ModeleCourrierController;
use App\Controllers\NoteController;
use App\Controllers\NotificationController;
use App\Controllers\PartageController;
use App\Controllers\PresentationController;
use App\Controllers\ProfileController;
use App\Controllers\RecouvrementController;
use App\Controllers\RelanceController;
use App\Controllers\ReportController;
use App\Controllers\RgpdController;
use App\Controllers\SettingController;
use App\Controllers\ShareController;
use App\Controllers\TacheController;
use App\Controllers\TwoFactorController;
use App\Controllers\UserController;
use App\Core\ApiAuth;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;

// --- Verification CSRF globale sur les requetes POST -------------------
// (l'API REST s'authentifie par jeton Bearer : pas de CSRF, pas de cookie)
if (Request::isPost() && !ApiAuth::estRequeteApi() && !Csrf::check($_POST['_token'] ?? null)) {
    http_response_code(419);
    echo View::render('errors/error', [
        'code'    => 419,
        'message' => "Votre session a expiré ou le formulaire est invalide. Merci de recharger la page.",
    ], Auth::check() ? 'layouts/app' : 'layouts/blank');
    exit;
}

// --- Forcer le changement de mot de passe ------------------------------
$chemin = Request::path();
if (Auth::check() && (int) (Auth::user()['must_change_password'] ?? 0) === 1) {
    $autorises = ['/profil/mot-de-passe', '/logout'];
    if (!in_array($chemin, $autorises, true)) {
        \App\Core\Flash::add('warning', 'Vous devez définir un nouveau mot de passe avant de continuer.');
        redirect('/profil/mot-de-passe');
    }
}

// --- Forcer l'activation de la double authentification ------------------
if (Auth::check() && Auth::a2faObligatoire() && !Auth::a2fa()) {
    $autorises = ['/profil/2fa', '/profil/2fa/activer', '/logout'];
    if (!in_array($chemin, $autorises, true)) {
        \App\Core\Flash::add('warning', 'La double authentification est obligatoire : activez-la pour continuer.');
        redirect('/profil/2fa');
    }
}

// --- Routes ------------------------------------------------------------
$router = new Router();

// Authentification
$router->get('/login',  [AuthController::class, 'showLogin'], 'guest');
$router->post('/login', [AuthController::class, 'login'], 'guest');
$router->get('/login/2fa',  [TwoFactorController::class, 'showChallenge'], 'guest');
$router->post('/login/2fa', [TwoFactorController::class, 'verifyChallenge'], 'guest');
$router->post('/logout', [AuthController::class, 'logout'], 'auth');
$router->get('/forgot', [AuthController::class, 'showForgot'], 'guest');
$router->post('/forgot', [AuthController::class, 'sendReset'], 'guest');
$router->get('/reset/{token}',  [AuthController::class, 'showReset'], 'guest');
$router->post('/reset/{token}', [AuthController::class, 'reset'], 'guest');

// Tableau de bord
$router->get('/', [DashboardController::class, 'index'], 'auth');

// Presentation des fonctionnalites (diaporama)
$router->get('/presentation', [PresentationController::class, 'index'], 'auth');

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
$router->get('/profil/2fa', [TwoFactorController::class, 'show'], 'auth');
$router->post('/profil/2fa/activer', [TwoFactorController::class, 'activate'], 'auth');
$router->post('/profil/2fa/desactiver', [TwoFactorController::class, 'disable'], 'auth');
$router->post('/profil/2fa/codes', [TwoFactorController::class, 'regenerateCodes'], 'auth');
$router->post('/profil/agenda', [AgendaController::class, 'regenerer'], 'auth');
$router->post('/profil/agenda/supprimer', [AgendaController::class, 'supprimer'], 'auth');

// Agenda iCal (jeton secret dans l'URL, pas de session)
$router->get('/agenda.ics', [AgendaController::class, 'flux']);

// Notifications
$router->get('/notifications', [NotificationController::class, 'index'], 'auth');
$router->post('/notifications/lu', [NotificationController::class, 'markAll'], 'auth');
$router->post('/notifications/{id}/lu', [NotificationController::class, 'mark'], 'auth');

// Litiges (les routes specifiques doivent preceder /litiges/{id})
$router->get('/litiges', [LitigeController::class, 'index'], 'module:litiges');
$router->get('/litiges/nouveau', [LitigeController::class, 'create'], 'modulewrite:litiges');
$router->post('/litiges', [LitigeController::class, 'store'], 'modulewrite:litiges');
$router->post('/litiges/actions-groupees', [LitigeController::class, 'actionGroupee'], 'modulewrite:litiges');
$router->get('/litiges/{id}', [LitigeController::class, 'show'], 'module:litiges');
$router->get('/litiges/{id}/modifier', [LitigeController::class, 'edit'], 'modulewrite:litiges');
$router->post('/litiges/{id}/modifier', [LitigeController::class, 'update'], 'modulewrite:litiges');
$router->post('/litiges/{id}/statut', [LitigeController::class, 'changerStatut'], 'modulewrite:litiges');
$router->post('/litiges/{id}/supprimer', [LitigeController::class, 'destroy'], 'admin');

// Filtres enregistres
$router->post('/filtres', [FiltreController::class, 'store'], 'module:litiges');
$router->post('/filtres/{id}/supprimer', [FiltreController::class, 'destroy'], 'module:litiges');

// Paiements, echeancier, penalites, declaration de creance
$router->post('/litiges/{id}/paiements', [RecouvrementController::class, 'storePaiement'], 'modulewrite:litiges');
$router->post('/litiges/{id}/paiements/{paiementId}/supprimer', [RecouvrementController::class, 'destroyPaiement'], 'modulewrite:litiges');
$router->post('/litiges/{id}/echeancier', [RecouvrementController::class, 'storeEcheancier'], 'modulewrite:litiges');
$router->post('/litiges/{id}/echeancier/supprimer', [RecouvrementController::class, 'destroyEcheancier'], 'modulewrite:litiges');
$router->post('/litiges/{id}/penalites', [RecouvrementController::class, 'majPenalites'], 'modulewrite:litiges');
$router->post('/litiges/{id}/creance', [RecouvrementController::class, 'creanceDeclaree'], 'modulewrite:litiges');

// Courriers (modeles, generation, suivi d'envoi / accuse de reception)
$router->post('/litiges/{id}/courriers', [CourrierController::class, 'store'], 'modulewrite:litiges');
$router->get('/courriers/{id}', [CourrierController::class, 'show'], 'module:litiges');
$router->get('/courriers/{id}/telecharger', [CourrierController::class, 'download'], 'module:litiges');
$router->post('/courriers/{id}/suivi', [CourrierController::class, 'suivi'], 'modulewrite:litiges');
$router->post('/courriers/{id}/supprimer', [CourrierController::class, 'destroy'], 'modulewrite:litiges');

// Taches
$router->get('/taches', [TacheController::class, 'index'], 'module:litiges');
$router->post('/taches', [TacheController::class, 'store'], 'modulewrite:litiges');
$router->post('/taches/{id}/statut', [TacheController::class, 'statut'], 'modulewrite:litiges');
$router->post('/taches/{id}/supprimer', [TacheController::class, 'destroy'], 'modulewrite:litiges');

// Rapports et previsions
$router->get('/rapports', [ReportController::class, 'index'], 'module:litiges');
$router->get('/rapports/balance-agee', [ReportController::class, 'exportBalance'], 'module:litiges');

// Import de donnees (CSV / Excel, ERP BLG)
$router->get('/import', [ImportController::class, 'index'], 'write');
$router->post('/import/analyser', [ImportController::class, 'analyser'], 'write');
$router->get('/import/apercu', [ImportController::class, 'apercu'], 'write');
$router->post('/import/apercu', [ImportController::class, 'majApercu'], 'write');
$router->post('/import/executer', [ImportController::class, 'executer'], 'write');
$router->post('/import/annuler', [ImportController::class, 'annuler'], 'write');
$router->post('/import/profil', [ImportController::class, 'enregistrerProfil'], 'admin');

// Relances
$router->post('/litiges/{id}/relances', [RelanceController::class, 'store'], 'modulewrite:relances');
$router->post('/litiges/{id}/relances/{relanceId}/supprimer', [RelanceController::class, 'destroy'], 'modulewrite:relances');

// Notes
$router->post('/litiges/{id}/notes', [NoteController::class, 'store'], 'modulewrite:litiges');
$router->post('/litiges/{id}/notes/{noteId}', [NoteController::class, 'update'], 'modulewrite:litiges');
$router->post('/litiges/{id}/notes/{noteId}/supprimer', [NoteController::class, 'destroy'], 'modulewrite:litiges');

// Documents (les droits dependent du module du document : voir DocumentController)
$router->post('/litiges/{id}/documents', [DocumentController::class, 'storeLitige'], 'modulewrite:litiges');
$router->post('/clients/{id}/documents', [DocumentController::class, 'storeClient'], 'modulewrite:clients');
$router->get('/documents/{id}/telecharger', [DocumentController::class, 'download'], 'auth');
$router->post('/documents/{id}/supprimer', [DocumentController::class, 'destroy'], 'write');
$router->post('/documents/{id}/partage-public', [DocumentController::class, 'basculerPartagePublic'], 'write');

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

// Contacts (+ droits RGPD : export et anonymisation)
$router->post('/clients/{id}/contacts', [ContactController::class, 'store'], 'modulewrite:clients');
$router->post('/clients/{id}/contacts/{contactId}', [ContactController::class, 'update'], 'modulewrite:clients');
$router->post('/clients/{id}/contacts/{contactId}/supprimer', [ContactController::class, 'destroy'], 'modulewrite:clients');
$router->get('/clients/{id}/contacts/{contactId}/export', [ContactController::class, 'exporter'], 'modulewrite:clients');
$router->post('/clients/{id}/contacts/{contactId}/anonymiser', [ContactController::class, 'anonymiser'], 'modulewrite:clients');

// API interne (JSON, session)
$router->get('/api/clients/suggestions', [ClientController::class, 'suggestions'], 'module:clients');
$router->get('/api/entreprises', [CompanyController::class, 'search'], 'auth');
$router->get('/api/entreprises/{identifiant}', [CompanyController::class, 'detail'], 'auth');

// API REST v1 (JSON, jeton Bearer) — voir docs/api.md
$router->get('/api/v1/litiges', [ApiV1Controller::class, 'litiges'], 'api:read');
$router->post('/api/v1/litiges', [ApiV1Controller::class, 'creerLitige'], 'api:write');
$router->get('/api/v1/litiges/{id}', [ApiV1Controller::class, 'litige'], 'api:read');
$router->post('/api/v1/litiges/{id}/statut', [ApiV1Controller::class, 'statutLitige'], 'api:write');
$router->post('/api/v1/litiges/{id}/paiements', [ApiV1Controller::class, 'creerPaiement'], 'api:write');
$router->post('/api/v1/litiges/{id}/relances', [ApiV1Controller::class, 'creerRelance'], 'api:write');
$router->get('/api/v1/clients', [ApiV1Controller::class, 'clients'], 'api:read');
$router->get('/api/v1/clients/{id}', [ApiV1Controller::class, 'client'], 'api:read');

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
$router->post('/utilisateurs/{id}/2fa/reinitialiser', [UserController::class, 'reset2fa'], 'admin');
$router->post('/utilisateurs/{id}/supprimer', [UserController::class, 'destroy'], 'admin');

// Agences (admin)
$router->get('/agences', [AgenceController::class, 'index'], 'admin');
$router->post('/agences', [AgenceController::class, 'store'], 'admin');
$router->post('/agences/{id}', [AgenceController::class, 'update'], 'admin');
$router->post('/agences/{id}/supprimer', [AgenceController::class, 'destroy'], 'admin');

// Parametres (admin)
$router->get('/parametres', [SettingController::class, 'parametres'], 'admin');
$router->post('/parametres', [SettingController::class, 'updateParametres'], 'admin');
$router->post('/parametres/ia-test', [SettingController::class, 'testerIa'], 'admin');
$router->get('/parametres/statuts', [SettingController::class, 'statuts'], 'admin');
$router->post('/parametres/statuts', [SettingController::class, 'storeStatut'], 'admin');
$router->post('/parametres/statuts/{id}', [SettingController::class, 'updateStatut'], 'admin');
$router->post('/parametres/statuts/{id}/workflow', [SettingController::class, 'updateWorkflow'], 'admin');
$router->post('/parametres/statuts/{id}/supprimer', [SettingController::class, 'destroyStatut'], 'admin');

// Modeles de courriers (admin)
$router->get('/parametres/modeles', [ModeleCourrierController::class, 'index'], 'admin');
$router->get('/parametres/modeles/nouveau', [ModeleCourrierController::class, 'create'], 'admin');
$router->post('/parametres/modeles', [ModeleCourrierController::class, 'store'], 'admin');
$router->get('/parametres/modeles/{id}/modifier', [ModeleCourrierController::class, 'edit'], 'admin');
$router->post('/parametres/modeles/{id}/modifier', [ModeleCourrierController::class, 'update'], 'admin');
$router->post('/parametres/modeles/{id}/supprimer', [ModeleCourrierController::class, 'destroy'], 'admin');

// API & webhooks (admin)
$router->get('/parametres/integrations', [IntegrationController::class, 'index'], 'admin');
$router->post('/parametres/integrations/jetons', [IntegrationController::class, 'creerJeton'], 'admin');
$router->post('/parametres/integrations/jetons/{id}/revoquer', [IntegrationController::class, 'revoquerJeton'], 'admin');
$router->post('/parametres/integrations/webhooks', [IntegrationController::class, 'creerWebhook'], 'admin');
$router->post('/parametres/integrations/webhooks/{id}/basculer', [IntegrationController::class, 'basculerWebhook'], 'admin');
$router->post('/parametres/integrations/webhooks/{id}/tester', [IntegrationController::class, 'testerWebhook'], 'admin');
$router->post('/parametres/integrations/webhooks/{id}/supprimer', [IntegrationController::class, 'supprimerWebhook'], 'admin');

// RGPD (admin)
$router->get('/rgpd', [RgpdController::class, 'index'], 'admin');
$router->post('/rgpd/parametres', [RgpdController::class, 'parametres'], 'admin');
$router->post('/rgpd/simuler', [RgpdController::class, 'simuler'], 'admin');
$router->post('/rgpd/purger', [RgpdController::class, 'purger'], 'admin');
$router->post('/rgpd/registre', [RgpdController::class, 'enregistrer'], 'admin');
$router->post('/rgpd/registre/{id}/supprimer', [RgpdController::class, 'supprimer'], 'admin');

// Base de donnees / migrations (admin)
$router->get('/parametres/migrations', [MigrationController::class, 'index'], 'admin');
$router->post('/parametres/migrations', [MigrationController::class, 'run'], 'admin');

// Journal d'audit (admin)
$router->get('/audit', [AuditController::class, 'index'], 'admin');

// Partages lecture seule
$router->get('/partages', [PartageController::class, 'index'], 'write');
$router->post('/partages', [PartageController::class, 'store'], 'write');
$router->post('/partages/{id}/basculer', [PartageController::class, 'toggle'], 'write');
$router->post('/partages/{id}/securiser', [PartageController::class, 'securiser'], 'write');
$router->post('/partages/{id}/supprimer', [PartageController::class, 'destroy'], 'write');
$router->post('/litiges/{id}/partage', [PartageController::class, 'storeLitige'], 'write');

// Consultation publique (code d'acces ou lien, sans compte)
$router->get('/partage', [ShareController::class, 'form']);
$router->post('/partage', [ShareController::class, 'access']);
$router->get('/partage/lien/{token}', [ShareController::class, 'viaLien']);
$router->get('/p/{token}', [ShareController::class, 'viaLien']);
$router->get('/partage/mot-de-passe', [ShareController::class, 'showMotDePasse']);
$router->post('/partage/mot-de-passe', [ShareController::class, 'verifierMotDePasse']);
$router->get('/partage/consultation', [ShareController::class, 'index']);
$router->get('/partage/consultation/{id}', [ShareController::class, 'show']);
$router->get('/partage/consultation/{id}/documents/{documentId}', [ShareController::class, 'document']);
$router->post('/partage/quitter', [ShareController::class, 'exitShare']);

// --- Dispatch ----------------------------------------------------------
$router->dispatch(Request::method(), Request::path());
