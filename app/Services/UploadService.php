<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Models\Document;

/**
 * Gestion des pieces jointes (stockees dans /storage/uploads, hors racine web).
 */
class UploadService
{
    private Document $documents;
    private string $uploadsDir;

    public function __construct()
    {
        $this->documents  = new Document();
        $this->uploadsDir = (string) Config::get('paths.uploads', APP_ROOT . '/storage/uploads');
        if (!is_dir($this->uploadsDir)) {
            @mkdir($this->uploadsDir, 0775, true);
        }
    }

    /**
     * @return array{ok:bool, id:?int, error:?string}
     */
    public function store(array $file, ?int $litigeId, ?int $clientId, ?string $categorie = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'id' => null, 'error' => 'Aucun fichier recu.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'id' => null, 'error' => $this->uploadErrorMessage((int) $file['error'])];
        }

        $maxSize = (int) Config::get('uploads.max_size', 10 * 1024 * 1024);
        if (($file['size'] ?? 0) > $maxSize) {
            return ['ok' => false, 'id' => null, 'error' => 'Fichier trop volumineux (max ' . round($maxSize / 1048576) . ' Mo).'];
        }

        $original = (string) ($file['name'] ?? 'fichier');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed  = (array) Config::get('uploads.extensions', []);
        if ($allowed !== [] && !in_array($extension, $allowed, true)) {
            return ['ok' => false, 'id' => null, 'error' => 'Type de fichier non autorise.'];
        }

        $nomStocke = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . ($extension !== '' ? '.' . $extension : '');
        $cible     = $this->uploadsDir . '/' . $nomStocke;

        if (!move_uploaded_file($file['tmp_name'], $cible)) {
            return ['ok' => false, 'id' => null, 'error' => "Impossible d'enregistrer le fichier."];
        }

        @chmod($cible, 0664);

        $id = $this->documents->create([
            'litige_id'    => $litigeId,
            'client_id'    => $clientId,
            'categorie'    => $categorie,
            'nom_original' => mb_substr($original, 0, 255),
            'nom_stocke'   => $nomStocke,
            'mime'         => mb_substr((string) ($file['type'] ?? 'application/octet-stream'), 0, 120),
            'taille'       => (int) $file['size'],
            'uploaded_by'  => Auth::id(),
        ]);

        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    public function delete(int $documentId): bool
    {
        $document = $this->documents->find($documentId);
        if (!$document) {
            return false;
        }

        $path = $this->uploadsDir . '/' . $document['nom_stocke'];
        if (is_file($path)) {
            @unlink($path);
        }
        $this->documents->delete($documentId);
        return true;
    }

    public function pathFor(array $document): ?string
    {
        $path = $this->uploadsDir . '/' . $document['nom_stocke'];
        return is_file($path) ? $path : null;
    }

    public function tailleLisible(int $octets): string
    {
        $unites = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        $valeur = (float) $octets;
        while ($valeur >= 1024 && $i < count($unites) - 1) {
            $valeur /= 1024;
            $i++;
        }
        return round($valeur, $i === 0 ? 0 : 1) . ' ' . $unites[$i];
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux.',
            UPLOAD_ERR_PARTIAL                        => 'Transfert interrompu.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Repertoire temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE                     => 'Ecriture impossible sur le disque.',
            UPLOAD_ERR_EXTENSION                      => 'Extension PHP bloquante.',
            default                                   => 'Erreur inconnue lors du transfert.',
        };
    }
}
