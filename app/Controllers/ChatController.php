<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Agence;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\NotificationService;

/**
 * Messagerie interne : discussions 1-a-1, groupes et canaux d'agence.
 */
class ChatController extends Controller
{
    public function index(): void
    {
        $uid   = (int) Auth::id();
        $chats = new Chat();

        $conversations = $chats->conversations($uid, Auth::agenceId(), Auth::isAdmin());
        foreach ($conversations as &$c) {
            $c = array_merge($c, $this->presentation($c, $uid, $chats));
        }
        unset($c);

        $this->view('chat/index', [
            'titre'         => 'Messagerie',
            'conversations' => $conversations,
            'users'         => (new User())->withAgence('u.actif = 1'),
            'agences'       => (new Agence())->listActives(),
            'estAdmin'      => Auth::isAdmin(),
        ]);
    }

    public function show(string $id): void
    {
        $uid   = (int) Auth::id();
        $chats = new Chat();
        $chat  = $this->charger((int) $id, $chats);

        $chats->marquerLu((int) $chat['id'], $uid);

        $entete = $this->presentation($chat, $uid, $chats);

        $this->view('chat/show', [
            'titre'     => $entete['titre'],
            'chat'      => $chat,
            'entete'    => $entete,
            'titreChat' => $entete['titre'],
            'messages'  => (new ChatMessage())->pourChat((int) $chat['id']),
            'membres'   => $chats->membres((int) $chat['id']),
            'authUser'  => Auth::user(),
        ]);
    }

    /** Cree une conversation (direct, groupe ou canal d'agence). */
    public function store(): void
    {
        $uid   = (int) Auth::id();
        $type  = (string) $this->post('type', 'direct');
        $chats = new Chat();

        if ($type === 'agence') {
            if (!Auth::isAdmin()) {
                $this->forbidden("Seul un administrateur peut créer un canal d'agence.");
            }
            $agenceId = $this->int('agence_id');
            if ($agenceId <= 0) {
                flash('error', 'Sélectionnez une agence.');
                $this->redirect('/chat');
            }

            $existant = $chats->canalAgence($agenceId);
            if ($existant) {
                $this->redirect('/chat/' . (int) $existant['id']);
            }

            $nom = (string) Database::value('SELECT nom FROM agences WHERE id = :id', ['id' => $agenceId], 'Agence');
            $id  = $chats->creer('agence', 'Canal ' . $nom, $agenceId, $uid, []);
            Audit::log('chat_creation', 'chat', $id, ['type' => 'agence', 'agence_id' => $agenceId]);

            flash('success', "Canal d'agence créé.");
            $this->redirect('/chat/' . $id);
        }

        if ($type === 'groupe') {
            $titre = trim((string) $this->post('titre', ''));
            if ($titre === '') {
                flash('error', 'Donnez un nom au groupe.');
                $this->redirect('/chat');
            }
            $membres = $this->post('membres', []);
            $membres = is_array($membres) ? array_map('intval', $membres) : [];

            $id = $chats->creer('groupe', mb_substr($titre, 0, 150), null, $uid, $membres);
            Audit::log('chat_creation', 'chat', $id, ['type' => 'groupe']);

            flash('success', 'Groupe créé.');
            $this->redirect('/chat/' . $id);
        }

        // Discussion 1-a-1
        $dest = $this->int('destinataire_id');
        if ($dest <= 0 || $dest === $uid) {
            flash('error', 'Sélectionnez un destinataire.');
            $this->redirect('/chat');
        }

        $existant = $chats->trouverDirect($uid, $dest);
        if ($existant) {
            $this->redirect('/chat/' . (int) $existant['id']);
        }

        $id = $chats->creer('direct', null, null, $uid, [$dest]);
        Audit::log('chat_creation', 'chat', $id, ['type' => 'direct', 'destinataire' => $dest]);

        $this->redirect('/chat/' . $id);
    }

    public function storeMessage(string $id): void
    {
        $uid   = (int) Auth::id();
        $chats = new Chat();
        $chat  = $this->charger((int) $id, $chats);

        $contenu = trim((string) $this->post('contenu', ''));
        if ($contenu === '') {
            $this->redirect('/chat/' . (int) $chat['id'] . '#bas');
        }

        (new ChatMessage())->create([
            'chat_id' => (int) $chat['id'],
            'user_id' => $uid,
            'contenu' => mb_substr($contenu, 0, 4000),
        ]);

        Database::run('UPDATE chats SET updated_at = NOW() WHERE id = :id', ['id' => (int) $chat['id']]);
        $chats->marquerLu((int) $chat['id'], $uid);
        Audit::log('chat_message', 'chat', (int) $chat['id']);

        (new NotificationService())->chatMessage($chat, $this->titre($chat, $uid), $uid);

        $this->redirect('/chat/' . (int) $chat['id'] . '#bas');
    }

    /** Flux JSON des nouveaux messages (rafraichissement automatique). */
    public function flux(string $id): void
    {
        $uid   = (int) Auth::id();
        $chats = new Chat();
        $chat  = $this->charger((int) $id, $chats);

        $after    = $this->int('after');
        $messages = (new ChatMessage())->pourChat((int) $chat['id'], $after);
        $chats->marquerLu((int) $chat['id'], $uid);

        $donnees = array_map(static fn (array $m): array => [
            'id'       => (int) $m['id'],
            'user_id'  => (int) ($m['user_id'] ?? 0),
            'auteur'   => nom_utilisateur($m),
            'agence'   => (string) ($m['agence_nom'] ?? ''),
            'contenu'  => (string) $m['contenu'],
            'quand'    => datetime_fr($m['created_at']),
        ], $messages);

        $this->json(['ok' => true, 'messages' => $donnees]);
    }

    // ------------------------------------------------------------------

    private function charger(int $id, Chat $chats): array
    {
        $chat = $chats->find($id);
        if (!$chat || !$chats->acces($chat, (int) Auth::id(), Auth::agenceId(), Auth::isAdmin())) {
            $this->notFound('Conversation introuvable.');
        }
        return $chat;
    }

    /**
     * Informations d'affichage d'une conversation : titre, agence rattachee,
     * initiales (avatar) et libelle du type.
     *
     * @return array{type:string,type_libelle:string,titre:string,agence_nom:?string,initiales:string}
     */
    private function presentation(array $chat, int $uid, ?Chat $chats = null): array
    {
        $chats ??= new Chat();
        $type = (string) ($chat['type'] ?? 'direct');

        if ($type === 'agence') {
            $nom = trim((string) Database::value(
                'SELECT nom FROM agences WHERE id = :id',
                ['id' => (int) ($chat['agence_id'] ?? 0)],
                ''
            ));
            return [
                'type'         => 'agence',
                'type_libelle' => "Canal d'agence",
                'titre'        => 'Canal ' . ($nom !== '' ? $nom : 'agence'),
                'agence_nom'   => $nom !== '' ? $nom : null,
                'initiales'    => initials($nom !== '' ? $nom : 'Agence'),
            ];
        }

        if ($type === 'groupe') {
            $titre = trim((string) ($chat['titre'] ?? ''));
            $titre = $titre !== '' ? $titre : 'Groupe';
            return [
                'type'         => 'groupe',
                'type_libelle' => 'Groupe',
                'titre'        => $titre,
                'agence_nom'   => null,
                'initiales'    => initials($titre),
            ];
        }

        $autre = $chats->autreMembre((int) $chat['id'], $uid);
        $titre = $autre ? nom_utilisateur($autre) : 'Discussion';

        return [
            'type'         => 'direct',
            'type_libelle' => 'Discussion privée',
            'titre'        => $titre,
            'agence_nom'   => $autre !== null && !empty($autre['agence_nom']) ? (string) $autre['agence_nom'] : null,
            'initiales'    => initials($titre),
        ];
    }

    private function titre(array $chat, int $uid): string
    {
        return $this->presentation($chat, $uid)['titre'];
    }
}
