<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Settings;

/**
 * Analyse antivirus optionnelle via le demon ClamAV (clamd), protocole INSTREAM.
 *
 * Parametres : clamav_actif (0/1), clamav_adresse
 *   - socket unix : unix:///var/run/clamav/clamd.ctl
 *   - reseau      : tcp://127.0.0.1:3310
 */
class ClamAv
{
    public static function actif(): bool
    {
        return Settings::bool('clamav_actif', false) && (string) Settings::get('clamav_adresse', '') !== '';
    }

    /**
     * @return array{ok:bool, infecte:bool, message:string}
     *   ok=false et infecte=false => scanner indisponible.
     */
    public static function analyser(string $chemin): array
    {
        $adresse = (string) Settings::get('clamav_adresse', '');
        $fichier = @fopen($chemin, 'rb');
        if ($adresse === '' || $fichier === false) {
            return ['ok' => false, 'infecte' => false, 'message' => 'Analyse antivirus impossible (configuration ou fichier illisible).'];
        }

        $erreurNo  = 0;
        $erreurMsg = '';
        $socket = @stream_socket_client($adresse, $erreurNo, $erreurMsg, 5);
        if ($socket === false) {
            fclose($fichier);
            Logger::warning('ClamAV indisponible', ['adresse' => $adresse, 'erreur' => $erreurMsg]);
            return ['ok' => false, 'infecte' => false, 'message' => 'Service antivirus indisponible.'];
        }

        stream_set_timeout($socket, 15);
        fwrite($socket, "zINSTREAM\0");
        while (!feof($fichier)) {
            $morceau = fread($fichier, 8192);
            if ($morceau === false || $morceau === '') {
                break;
            }
            fwrite($socket, pack('N', strlen($morceau)) . $morceau);
        }
        fwrite($socket, pack('N', 0));
        $reponse = trim((string) stream_get_contents($socket));
        fclose($socket);
        fclose($fichier);

        return self::interpreter($reponse);
    }

    /** @return array{ok:bool, infecte:bool, message:string} */
    public static function interpreter(string $reponse): array
    {
        $reponse = trim(str_replace("\0", '', $reponse));
        if (str_ends_with($reponse, 'OK')) {
            return ['ok' => true, 'infecte' => false, 'message' => 'Aucune menace détectée.'];
        }
        if (str_contains($reponse, 'FOUND')) {
            return ['ok' => true, 'infecte' => true, 'message' => 'Fichier infecté : ' . $reponse];
        }
        Logger::warning('Reponse ClamAV inattendue', ['reponse' => $reponse]);
        return ['ok' => false, 'infecte' => false, 'message' => 'Réponse inattendue du service antivirus.'];
    }
}
