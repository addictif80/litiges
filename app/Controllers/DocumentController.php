<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Client;
use App\Models\Document;
use App\Models\Litige;
use App\Services\UploadService;

class DocumentController extends Controller
{
    /** Televersement a la volee sur un litige. */
    public function storeLitige(string $litigeId): void
    {
        $id     = (int) $litigeId;
        $litige = (new Litige())->find($id);
        if (!$litige) {
            $this->notFound('Litige introuvable.');
        }

        $file = Request::file('fichier');
        if (!$file) {
            flash('error', 'Aucun fichier selectionne.');
            $this->redirect('/litiges/' . $id . '#documents');
        }

        $resultat = (new UploadService())->store($file, $id, null, (string) $this->post('categorie', ''));
        if (!$resultat['ok']) {
            flash('error', $resultat['error'] ?? 'Erreur de televersement.');
        } else {
            Audit::log('document_ajout', 'litige', $id, ['document_id' => $resultat['id'], 'nom' => $file['name']]);
            flash('success', 'Piece jointe ajoutee.');
        }

        $this->redirect('/litiges/' . $id . '#documents');
    }

    /** Televersement sur une fiche client. */
    public function storeClient(string $clientId): void
    {
        $id     = (int) $clientId;
        $client = (new Client())->find($id);
        if (!$client) {
            $this->notFound('Client introuvable.');
        }

        $file = Request::file('fichier');
        if (!$file) {
            flash('error', 'Aucun fichier selectionne.');
            $this->redirect('/clients/' . $id);
        }

        $resultat = (new UploadService())->store($file, null, $id, (string) $this->post('categorie', ''));
        if (!$resultat['ok']) {
            flash('error', $resultat['error'] ?? 'Erreur de televersement.');
        } else {
            Audit::log('document_ajout', 'client', $id, ['document_id' => $resultat['id']]);
            flash('success', 'Piece jointe ajoutee.');
        }

        $this->redirect('/clients/' . $id . '#documents');
    }

    public function download(string $id): void
    {
        $document = (new Document())->findFull((int) $id);
        if (!$document) {
            $this->notFound('Document introuvable.');
        }

        $path = (new UploadService())->pathFor($document);
        if ($path === null) {
            $this->notFound('Fichier absent du stockage.');
        }

        Audit::log('document_telechargement', 'document', (int) $id);

        header('Content-Type: ' . ($document['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . rawurlencode((string) $document['nom_original']) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function destroy(string $id): void
    {
        $document = (new Document())->find((int) $id);
        if (!$document) {
            $this->notFound('Document introuvable.');
        }

        if (!Auth::isAdmin() && (int) $document['uploaded_by'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres documents.');
        }

        (new UploadService())->delete((int) $id);
        Audit::log('document_suppression', 'document', (int) $id, ['nom' => $document['nom_original']]);

        flash('success', 'Document supprime.');

        if ($document['litige_id']) {
            $this->redirect('/litiges/' . (int) $document['litige_id'] . '#documents');
        }
        $this->redirect('/clients/' . (int) $document['client_id'] . '#documents');
    }
}
