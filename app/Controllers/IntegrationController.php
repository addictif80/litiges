<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiAuth;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Crypto;
use App\Models\ApiToken;
use App\Models\User;
use App\Models\Webhook;
use App\Services\UrlGuard;
use App\Services\WebhookService;

/** Administration des integrations : jetons d'API et webhooks. */
class IntegrationController extends Controller
{
    public function index(): void
    {
        $webhooks = new Webhook();
        $liste = $webhooks->all('1', [], 'id DESC');
        $livraisons = [];
        foreach ($liste as $w) {
            $livraisons[(int) $w['id']] = $webhooks->livraisonsRecentes((int) $w['id'], 5);
        }

        $this->view('settings/integrations', [
            'titre'       => 'API & webhooks',
            'jetons'      => (new ApiToken())->liste(),
            'utilisateurs' => (new User())->listActifs(),
            'webhooks'    => $liste,
            'livraisons'  => $livraisons,
            'evenements'  => Webhook::EVENEMENTS,
            'jetonClair'  => $_SESSION['_api_jeton_clair'] ?? null,
            'secretClair' => $_SESSION['_webhook_secret_clair'] ?? null,
        ]);
        unset($_SESSION['_api_jeton_clair'], $_SESSION['_webhook_secret_clair']);
    }

    // --- Jetons ---------------------------------------------------------------

    public function creerJeton(): void
    {
        $nom = trim((string) $this->post('nom', ''));
        $userId = $this->int('user_id') ?: (int) Auth::id();
        if ($nom === '' || mb_strlen($nom) > 120 || !(new User())->find($userId)) {
            flash('error', 'Indiquez un nom (120 caractères max.) et un utilisateur valide.');
            $this->redirect('/parametres/integrations');
        }

        $jours = $this->int('expire_jours');
        $genere = ApiAuth::generer();
        $id = (new ApiToken())->create([
            'nom'        => $nom,
            'prefixe'    => $genere['prefixe'],
            'token_hash' => $genere['empreinte'],
            'user_id'    => $userId,
            'ecriture'   => $this->post('ecriture') !== null ? 1 : 0,
            'expires_at' => $jours > 0 ? date('Y-m-d H:i:s', strtotime("+{$jours} days")) : null,
            'actif'      => 1,
            'created_by' => (int) Auth::id(),
        ]);
        Audit::log('api_jeton_cree', 'api', $id, ['nom' => $nom, 'user_id' => $userId]);

        $_SESSION['_api_jeton_clair'] = $genere['clair'];
        flash('success', 'Jeton créé : copiez-le maintenant, il ne sera plus affiché.');
        $this->redirect('/parametres/integrations');
    }

    public function revoquerJeton(string $id): void
    {
        $jetons = new ApiToken();
        if (!$jetons->find((int) $id)) {
            $this->notFound('Jeton introuvable.');
        }
        $jetons->delete((int) $id);
        Audit::log('api_jeton_revoque', 'api', (int) $id);
        flash('success', 'Jeton révoqué.');
        $this->redirect('/parametres/integrations');
    }

    // --- Webhooks ---------------------------------------------------------------

    public function creerWebhook(): void
    {
        $nom = trim((string) $this->post('nom', ''));
        $url = trim((string) $this->post('url', ''));
        $evenements = array_values(array_intersect(array_keys(Webhook::EVENEMENTS), (array) ($_POST['evenements'] ?? [])));

        $verif = UrlGuard::valider($url);
        if ($nom === '' || !$verif['ok'] || $evenements === []) {
            flash('error', e($verif['ok'] ? 'Indiquez un nom et au moins un événement.' : (string) $verif['erreur']));
            $this->redirect('/parametres/integrations');
        }

        $secret = bin2hex(random_bytes(24));
        $id = (new Webhook())->create([
            'nom'        => mb_substr($nom, 0, 120),
            'url'        => mb_substr($url, 0, 500),
            'secret'     => Crypto::chiffrer($secret),
            'evenements' => json_encode($evenements),
            'actif'      => 1,
            'created_by' => (int) Auth::id(),
        ]);
        Audit::log('webhook_cree', 'webhook', $id, ['url' => $url, 'evenements' => $evenements]);

        $_SESSION['_webhook_secret_clair'] = $secret;
        flash('success', 'Webhook créé : conservez le secret de signature affiché ci-dessous.');
        $this->redirect('/parametres/integrations');
    }

    public function basculerWebhook(string $id): void
    {
        $webhooks = new Webhook();
        $w = $webhooks->find((int) $id);
        if (!$w) {
            $this->notFound('Webhook introuvable.');
        }
        $webhooks->update((int) $id, ['actif' => (int) $w['actif'] === 1 ? 0 : 1, 'echecs_consecutifs' => 0]);
        Audit::log('webhook_bascule', 'webhook', (int) $id);
        $this->redirect('/parametres/integrations');
    }

    public function testerWebhook(string $id): void
    {
        $w = (new Webhook())->find((int) $id);
        if (!$w) {
            $this->notFound('Webhook introuvable.');
        }
        $resultat = (new WebhookService())->tester($w);
        flash($resultat['ok'] ? 'success' : 'error', 'Test du webhook — ' . e($resultat['message']));
        $this->redirect('/parametres/integrations');
    }

    public function supprimerWebhook(string $id): void
    {
        $webhooks = new Webhook();
        if (!$webhooks->find((int) $id)) {
            $this->notFound('Webhook introuvable.');
        }
        $webhooks->delete((int) $id);
        Audit::log('webhook_supprime', 'webhook', (int) $id);
        flash('success', 'Webhook supprimé.');
        $this->redirect('/parametres/integrations');
    }
}
