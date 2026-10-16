<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Models\ClientNote;
use App\Models\Document;
use App\Models\Litige;
use App\Models\LitigeStatut;
use App\Models\Partage;
use App\Models\Relance;
use App\Services\UploadService;

/**
 * Acces public en LECTURE SEULE via un code d'acces ou un lien public (pour les agences).
 * Aucune authentification utilisateur : la portee est definie par le partage.
 * Un mot de passe facultatif peut proteger le partage ; chaque consultation est journalisee.
 */
class ShareController extends Controller
{
    private const SESSION_KEY = '_partage';
    private const SESSION_ATTENTE = '_partage_attente';

    /** Essais infructueux autorises par adresse IP sur la fenetre ci-dessous. */
    private const ESSAIS_MAX = 10;
    private const FENETRE    = 900; // 15 minutes

    private static function cleQuota(): string
    {
        return 'share:' . Request::ip();
    }

    /** Bloque l'IP qui accumule les codes / liens / mots de passe invalides. */
    private function exigerQuota(): void
    {
        $attente = RateLimiter::bloque(self::cleQuota(), self::ESSAIS_MAX, self::FENETRE);
        if ($attente !== null) {
            http_response_code(429);
            header('Retry-After: ' . $attente);
            $_SESSION['_errors'] = ['code' => RateLimiter::message($attente)];
            $this->view('share/form', [
                'titre'   => 'Consultation en lecture seule',
                'partage' => null,
            ], 'layouts/share');
            exit;
        }
    }

    public function form(): void
    {
        $this->view('share/form', [
            'titre'   => 'Consultation en lecture seule',
            'partage' => $this->partageCourant(),
        ], 'layouts/share');
    }

    public function access(): void
    {
        $this->exigerQuota();

        $code = strtoupper(trim((string) $this->post('code', '')));
        $partage = (new Partage())->findByCode($code);

        if (!$partage) {
            RateLimiter::hit(self::cleQuota(), self::ESSAIS_MAX, self::FENETRE);
            Audit::log('partage_echec', 'partage', null, ['code' => substr($code, 0, 3) . '***']);
            $_SESSION['_errors'] = ['code' => "Code d'accès invalide ou expiré."];
            $this->redirect('/partage');
        }

        $this->ouvrir($partage, 'code');
    }

    /** Acces direct via un lien public (/partage/lien/{token}). */
    public function viaLien(string $token): void
    {
        $this->exigerQuota();

        $partage = (new Partage())->findByToken($token);
        if (!$partage) {
            RateLimiter::hit(self::cleQuota(), self::ESSAIS_MAX, self::FENETRE);
            Audit::log('partage_echec', 'partage', null, ['token' => substr($token, 0, 4) . '***']);
            $_SESSION['_errors'] = ['code' => "Lien d'accès invalide ou expiré."];
            $this->redirect('/partage');
        }

        $this->ouvrir($partage, 'lien');
    }

    /** Ouvre la consultation, ou demande d'abord le mot de passe du partage. */
    private function ouvrir(array $partage, string $via): void
    {
        if (!empty($partage['mot_de_passe_hash'])) {
            Session::set(self::SESSION_ATTENTE, ['id' => (int) $partage['id'], 'via' => $via]);
            $this->redirect('/partage/mot-de-passe');
        }
        $this->accorder($partage, $via);
    }

    private function accorder(array $partage, string $via): never
    {
        Session::forget(self::SESSION_ATTENTE);
        Session::set(self::SESSION_KEY, (int) $partage['id']);
        Session::regenerate();

        $model = new Partage();
        $model->enregistrerVue((int) $partage['id']);
        $model->journaliser((int) $partage['id'], 'acces_' . $via);
        Audit::log('partage_acces', 'partage', (int) $partage['id'], ['via' => $via]);

        $this->redirect('/partage/consultation');
    }

    public function showMotDePasse(): void
    {
        if ($this->partageEnAttente() === null) {
            $this->redirect('/partage');
        }
        $this->view('share/mot_de_passe', ['titre' => 'Accès protégé'], 'layouts/share');
    }

    public function verifierMotDePasse(): void
    {
        $this->exigerQuota();
        $partage = $this->partageEnAttente();
        if ($partage === null) {
            $this->redirect('/partage');
        }

        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        if (!password_verify($motDePasse, (string) $partage['mot_de_passe_hash'])) {
            RateLimiter::hit(self::cleQuota(), self::ESSAIS_MAX, self::FENETRE);
            (new Partage())->journaliser((int) $partage['id'], 'echec_mot_de_passe');
            Audit::log('partage_echec', 'partage', (int) $partage['id'], ['raison' => 'mot_de_passe']);
            $_SESSION['_errors'] = ['mot_de_passe' => 'Mot de passe incorrect.'];
            $this->redirect('/partage/mot-de-passe');
        }

        $via = (string) (Session::get(self::SESSION_ATTENTE)['via'] ?? 'lien');
        $this->accorder($partage, $via);
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
            $this->notFound('Litige introuvable ou hors de votre périmètre de consultation.');
        }

        $model = new Partage();
        $model->enregistrerVue((int) $partage['id']);
        $model->journaliser((int) $partage['id'], 'vue_litige', (int) $id);

        $this->view('share/show', [
            'titre'       => 'Litige ' . $litige['reference'],
            'partage'     => $partage,
            'litige'      => $litige,
            'relances'    => (new Relance())->forLitige((int) $id),
            'clientNotes' => (new ClientNote())->forClient((int) $litige['client_id']),
            'documents'   => (int) $partage['afficher_documents'] === 1 ? (new Document())->publicsPourLitige((int) $id) : [],
            'types'       => \App\Enums\TypeLitige::options(),
        ], 'layouts/share');
    }

    /** Telechargement d'un document explicitement marque comme public sur ce litige. */
    public function document(string $id, string $documentId): void
    {
        $partage = $this->partageCourant();
        if (!$partage || (int) $partage['afficher_documents'] !== 1) {
            $this->redirect('/partage');
        }

        $litige = (new Litige())->findFull((int) $id);
        if (!$litige || !$this->dansPerimetre($partage, $litige)) {
            $this->notFound('Document introuvable.');
        }

        $document = (new Document())->find((int) $documentId);
        if (!$document || (int) $document['litige_id'] !== (int) $id || (int) $document['partage_public'] !== 1) {
            $this->notFound('Document introuvable.');
        }

        $chemin = (new UploadService())->pathFor($document);
        if ($chemin === null) {
            $this->notFound('Fichier absent du stockage.');
        }

        (new Partage())->journaliser((int) $partage['id'], 'document', (int) $id);
        Audit::log('partage_document', 'document', (int) $documentId, ['partage_id' => (int) $partage['id']]);

        $nom   = (string) $document['nom_original'];
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $nom) ?: 'document';
        header('Content-Type: ' . ($document['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nom));
        header('Content-Length: ' . filesize($chemin));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        readfile($chemin);
        exit;
    }

    public function exitShare(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::SESSION_ATTENTE);
        flash('success', 'Vous avez quitté la consultation.');
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

    /** Partage dont on attend le mot de passe (code ou lien deja valides). */
    private function partageEnAttente(): ?array
    {
        $attente = Session::get(self::SESSION_ATTENTE);
        if (!is_array($attente) || empty($attente['id'])) {
            return null;
        }
        $partage = (new Partage())->find((int) $attente['id']);
        if (!$partage || (int) $partage['actif'] !== 1 || empty($partage['mot_de_passe_hash'])
            || ($partage['expires_at'] !== null && strtotime((string) $partage['expires_at']) < time())) {
            Session::forget(self::SESSION_ATTENTE);
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
