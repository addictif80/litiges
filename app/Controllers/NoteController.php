<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\Note;
use App\Services\NotificationService;

class NoteController extends Controller
{
    public function store(string $litigeId): void
    {
        $id     = (int) $litigeId;
        $litige = (new Litige())->find($id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }

        $contenu = trim((string) $this->post('contenu', ''));
        if ($contenu === '') {
            flash('error', 'La note ne peut pas etre vide.');
            $this->redirect('/litiges/' . $id . '#notes');
        }

        (new Note())->create([
            'litige_id' => $id,
            'user_id'   => (int) Auth::id(),
            'contenu'   => mb_substr($contenu, 0, 5000),
        ]);

        (new LitigeHistorique())->log($id, (int) Auth::id(), 'note', 'Note interne', null, null, mb_substr($contenu, 0, 250));
        Audit::log('note_ajout', 'litige', $id);

        (new NotificationService())->noteLitige($litige, (int) Auth::id());

        flash('success', 'Note ajoutee.');
        $this->redirect('/litiges/' . $id . '#notes');
    }

    public function update(string $litigeId, string $noteId): void
    {
        $notes = new Note();
        if (!$notes->belongsTo((int) $noteId, (int) $litigeId)) {
            $this->notFound('Note introuvable.');
        }

        $note = $notes->find((int) $noteId);
        if (!Auth::isAdmin() && (int) $note['user_id'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez modifier que vos propres notes.');
        }

        $contenu = trim((string) $this->post('contenu', ''));
        if ($contenu === '') {
            flash('error', 'La note ne peut pas etre vide.');
            $this->redirect('/litiges/' . $litigeId . '#notes');
        }

        $notes->update((int) $noteId, ['contenu' => mb_substr($contenu, 0, 5000)]);
        Audit::log('note_modification', 'litige', (int) $litigeId, ['note_id' => (int) $noteId]);

        flash('success', 'Note mise a jour.');
        $this->redirect('/litiges/' . $litigeId . '#notes');
    }

    public function destroy(string $litigeId, string $noteId): void
    {
        $notes = new Note();
        if (!$notes->belongsTo((int) $noteId, (int) $litigeId)) {
            $this->notFound('Note introuvable.');
        }

        $note = $notes->find((int) $noteId);
        if (!Auth::isAdmin() && (int) $note['user_id'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres notes.');
        }

        $notes->delete((int) $noteId);
        Audit::log('note_suppression', 'litige', (int) $litigeId, ['note_id' => (int) $noteId]);

        flash('success', 'Note supprimee.');
        $this->redirect('/litiges/' . $litigeId . '#notes');
    }
}
