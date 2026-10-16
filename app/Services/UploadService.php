<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Logger;
use App\Core\Settings;
use App\Models\Document;

/**
 * Gestion des pieces jointes (stockees dans /storage/uploads, hors racine web).
 *
 * Controles : extension autorisee, taille, type MIME reel (finfo) coherent avec
 * l'extension et analyse antivirus ClamAV facultative.
 */
class UploadService
{
    public const EXTENSIONS_PAR_DEFAUT = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'eml', 'msg',
    ];

    /** Types MIME reels acceptes pour chaque extension. */
    private const MIMES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'txt'  => ['text/plain'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'eml'  => ['message/rfc822', 'text/plain', 'text/html'],
        'msg'  => ['application/vnd.ms-outlook', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
    ];

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

    /** Le type MIME detecte est-il coherent avec l'extension annoncee ? */
    public static function mimeCompatible(string $extension, string $detecte): bool
    {
        $extension = strtolower($extension);
        if (!isset(self::MIMES[$extension])) {
            return false;
        }
        return in_array($detecte, self::MIMES[$extension], true);
    }

    /** @return string[] */
    public static function extensionsAutorisees(): array
    {
        $configurees = (array) Config::get('uploads.extensions', []);
        $liste = $configurees !== [] ? $configurees : self::EXTENSIONS_PAR_DEFAUT;
        // Jamais d'extension executable, quoi que dise la configuration.
        return array_values(array_diff(
            array_map('strtolower', $liste),
            ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'phps', 'exe', 'sh', 'bat', 'cmd', 'js', 'htaccess']
        ));
    }

    public static function tailleMaxOctets(): int
    {
        $mo = (int) Settings::get('max_upload_mo', 0);
        if ($mo > 0) {
            return $mo * 1048576;
        }
        return (int) Config::get('uploads.max_size', 10 * 1024 * 1024);
    }

    /**
     * @return array{ok:bool, id:?int, error:?string}
     */
    public function store(array $file, ?int $litigeId, ?int $clientId, ?string $categorie = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'id' => null, 'error' => 'Aucun fichier reçu.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'id' => null, 'error' => $this->uploadErrorMessage((int) $file['error'])];
        }

        $maxSize = self::tailleMaxOctets();
        if (($file['size'] ?? 0) > $maxSize) {
            return ['ok' => false, 'id' => null, 'error' => 'Fichier trop volumineux (max ' . round($maxSize / 1048576) . ' Mo).'];
        }

        $original  = (string) ($file['name'] ?? 'fichier');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, self::extensionsAutorisees(), true)) {
            return ['ok' => false, 'id' => null, 'error' => 'Type de fichier non autorisé.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'id' => null, 'error' => 'Fichier invalide.'];
        }

        $detecte = $this->detecterMime($tmp);
        if (!self::mimeCompatible($extension, $detecte)) {
            Logger::warning('Upload refuse : type incoherent', ['nom' => $original, 'ext' => $extension, 'mime' => $detecte]);
            return ['ok' => false, 'id' => null, 'error' => "Le contenu du fichier ne correspond pas à son extension (.{$extension})."];
        }

        $verdict = $this->analyserVirus($tmp);
        if ($verdict !== null) {
            return ['ok' => false, 'id' => null, 'error' => $verdict];
        }

        $nomStocke = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $cible     = $this->uploadsDir . '/' . $nomStocke;

        if (!move_uploaded_file($tmp, $cible)) {
            return ['ok' => false, 'id' => null, 'error' => "Impossible d'enregistrer le fichier."];
        }
        @chmod($cible, 0664);

        $id = $this->documents->create([
            'litige_id'    => $litigeId,
            'client_id'    => $clientId,
            'categorie'    => $categorie,
            'nom_original' => mb_substr($original, 0, 255),
            'nom_stocke'   => $nomStocke,
            'mime'         => mb_substr($detecte, 0, 120),
            'taille'       => (int) $file['size'],
            'uploaded_by'  => Auth::id(),
        ]);

        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    /**
     * Enregistre un contenu genere par l'application (courrier, export...)
     * comme piece jointe. L'extension et le type sont fixes par l'appelant.
     *
     * @return array{ok:bool, id:?int, error:?string}
     */
    public function storeContenu(string $contenu, string $nomOriginal, string $extension, string $mime, ?int $litigeId, ?int $clientId, ?string $categorie = null): array
    {
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?? '');
        if ($extension === '' || in_array($extension, ['php', 'phtml', 'phar', 'exe', 'sh', 'bat'], true)) {
            return ['ok' => false, 'id' => null, 'error' => 'Extension interdite.'];
        }

        $nomStocke = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $cible     = $this->uploadsDir . '/' . $nomStocke;
        if (@file_put_contents($cible, $contenu) === false) {
            return ['ok' => false, 'id' => null, 'error' => "Impossible d'enregistrer le fichier."];
        }
        @chmod($cible, 0664);

        $id = $this->documents->create([
            'litige_id'    => $litigeId,
            'client_id'    => $clientId,
            'categorie'    => $categorie,
            'nom_original' => mb_substr($nomOriginal, 0, 255),
            'nom_stocke'   => $nomStocke,
            'mime'         => mb_substr($mime, 0, 120),
            'taille'       => strlen($contenu),
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
        // basename() : le nom stocke est genere par l'application, mais on
        // refuse tout separateur de chemin par precaution.
        $path = $this->uploadsDir . '/' . basename((string) $document['nom_stocke']);
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

    private function detecterMime(string $chemin): string
    {
        if (!function_exists('finfo_open')) {
            return 'application/octet-stream';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (string) finfo_file($finfo, $chemin) : '';
        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    /** Renvoie un message d'erreur si le fichier doit etre refuse, null sinon. */
    private function analyserVirus(string $chemin): ?string
    {
        if (!ClamAv::actif()) {
            return null;
        }
        $resultat = ClamAv::analyser($chemin);
        if ($resultat['infecte']) {
            Logger::warning('Upload refuse : virus detecte', ['detail' => $resultat['message']]);
            return 'Le fichier a été refusé par l\'antivirus.';
        }
        if (!$resultat['ok'] && Settings::bool('clamav_bloquer_si_indisponible', true)) {
            return 'Analyse antivirus indisponible : fichier refusé par précaution.';
        }
        return null;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux.',
            UPLOAD_ERR_PARTIAL                        => 'Transfert interrompu.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Répertoire temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE                     => 'Écriture impossible sur le disque.',
            UPLOAD_ERR_EXTENSION                      => 'Extension PHP bloquante.',
            default                                   => 'Erreur inconnue lors du transfert.',
        };
    }
}
