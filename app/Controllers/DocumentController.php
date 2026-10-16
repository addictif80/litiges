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
            flash('error', 'Aucun fichier sélectionné.');
            $this->redirect('/litiges/' . $id . '#documents');
        }

        $resultat = (new UploadService())->store($file, $id, null, (string) $this->post('categorie', ''));
        if (!$resultat['ok']) {
            flash('error', e($resultat['error'] ?? 'Erreur de téléversement.'));
        } else {
            Audit::log('document_ajout', 'litige', $id, ['document_id' => $resultat['id'], 'nom' => $file['name']]);
            flash('success', 'Pièce jointe ajoutée.');
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
            flash('error', 'Aucun fichier sélectionné.');
            $this->redirect('/clients/' . $id);
        }

        $resultat = (new UploadService())->store($file, null, $id, (string) $this->post('categorie', ''));
        if (!$resultat['ok']) {
            flash('error', e($resultat['error'] ?? 'Erreur de téléversement.'));
        } else {
            Audit::log('document_ajout', 'client', $id, ['document_id' => $resultat['id']]);
            flash('success', 'Pièce jointe ajoutée.');
        }

        $this->redirect('/clients/' . $id . '#documents');
    }

    public function download(string $id): void
    {
        $document = (new Document())->findFull((int) $id);
        if (!$document) {
            $this->notFound('Document introuvable.');
        }
        $this->exigerAcces($document, false);

        $path = (new UploadService())->pathFor($document);
        if ($path === null) {
            $this->notFound('Fichier absent du stockage.');
        }

        Audit::log('document_telechargement', 'document', (int) $id);

        $nom   = (string) $document['nom_original'];
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $nom) ?: 'document';

        header('Content-Type: ' . ($document['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nom));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        readfile($path);
        exit;
    }

    /** Rend un document d'un litige visible (ou non) dans le partage public. */
    public function basculerPartagePublic(string $id): void
    {
        $documents = new Document();
        $document  = $documents->findFull((int) $id);
        if (!$document || empty($document['litige_id'])) {
            $this->notFound('Document introuvable.');
        }
        $this->exigerAcces($document, true);

        $nouveau = (int) $document['partage_public'] === 1 ? 0 : 1;
        $documents->update((int) $id, ['partage_public' => $nouveau]);
        Audit::log('document_partage_public', 'document', (int) $id, ['public' => $nouveau]);

        flash('success', $nouveau === 1
            ? 'Document visible dans les partages publics qui affichent les documents.'
            : 'Document retiré des partages publics.');
        $this->redirect('/litiges/' . (int) $document['litige_id'] . '#documents');
    }

    public function destroy(string $id): void
    {
        $document = (new Document())->findFull((int) $id);
        if (!$document) {
            $this->notFound('Document introuvable.');
        }
        $this->exigerAcces($document, true);

        if (!Auth::isAdmin() && (int) $document['uploaded_by'] !== (int) Auth::id()) {
            $this->forbidden('Vous ne pouvez supprimer que vos propres documents.');
        }

        (new UploadService())->delete((int) $id);
        Audit::log('document_suppression', 'document', (int) $id, ['nom' => $document['nom_original']]);

        flash('success', 'Document supprimé.');

        if ($document['litige_id']) {
            $this->redirect('/litiges/' . (int) $document['litige_id'] . '#documents');
        }
        $this->redirect('/clients/' . (int) $document['client_id'] . '#documents');
    }

    /**
     * Un document herite des droits du module auquel il se rattache :
     * "litiges" s'il est lie a un litige, "clients" sinon.
     */
    private function exigerAcces(array $document, bool $ecriture): void
    {
        $module = !empty($document['litige_id']) ? 'litiges' : 'clients';
        if (!Auth::peut($module) || ($ecriture && !Auth::canWrite())) {
            $this->forbidden('Accès non autorisé à ce document.');
        }
    }
}
