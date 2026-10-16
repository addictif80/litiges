#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Traitements quotidiens (cron) : a lancer une fois par jour, tot le matin.
 *
 *   php cron/quotidien.php                 # tout executer
 *   php cron/quotidien.php --dry-run       # simulation (aucune ecriture quand c'est possible)
 *   php cron/quotidien.php --quiet         # aucune sortie console
 *   php cron/quotidien.php --only=rappels,penalites
 *   php cron/quotidien.php --help
 *
 * Taches (dans l'ordre) :
 *   migrations   applique les migrations de base de donnees en attente
 *   blg          importe les fichiers deposes par l'ERP BLG (factures, reglements)
 *   penalites    recalcule les penalites de retard des litiges en mode automatique
 *   rappels      genere les notifications et l'e-mail recapitulatif
 *   bodacc       surveille les procedures collectives des clients
 *   rapport      envoie le rapport hebdomadaire (au jour configure)
 *   rgpd         anonymisation / purge selon les durees de conservation
 *   entretien    nettoyage (compteurs de limitation, fichiers d'import temporaires)
 *
 * Exemple de crontab (7 h tous les jours) :
 *   0 7 * * * /usr/bin/php /chemin/vers/litiges/cron/quotidien.php --quiet
 */

use App\Core\Logger;
use App\Core\Migrator;
use App\Core\RateLimiter;
use App\Services\BodaccWatchService;
use App\Services\Import\BlgService;
use App\Services\PenaltyService;
use App\Services\PeriodicReportService;
use App\Services\ReminderService;
use App\Services\RgpdService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Ce script doit etre execute en ligne de commande (cron).';
    exit(1);
}

@ini_set('session.use_cookies', '0');
@ini_set('session.cache_limiter', '');
define('APP_ROOT', dirname(__DIR__));

$options = getopt('', ['dry-run', 'quiet', 'help', 'only::']) ?: [];
if (array_key_exists('help', $options)) {
    fwrite(STDOUT, "Voir l'en-tete de ce fichier pour la liste des taches et options.\n");
    exit(0);
}
$simulation = array_key_exists('dry-run', $options);
$quiet      = array_key_exists('quiet', $options);
$seulement  = isset($options['only']) && is_string($options['only']) && $options['only'] !== ''
    ? array_map('trim', explode(',', $options['only']))
    : null;

$sortie = static function (string $message) use ($quiet): void {
    if (!$quiet) {
        fwrite(STDOUT, $message . PHP_EOL);
    }
};

if (!is_file(APP_ROOT . '/config/config.php')) {
    fwrite(STDERR, "Configuration introuvable (config/config.php). L'application n'est pas installee." . PHP_EOL);
    exit(1);
}
require APP_ROOT . '/app/bootstrap.php';

/** @var array<string,callable():string> $taches */
$taches = [
    'migrations' => static function () use ($simulation): string {
        if ($simulation) {
            return 'en attente : ' . (new Migrator())->pendingCount();
        }
        $rapport = (new Migrator())->force();
        return count($rapport) . ' migration(s) traitée(s)';
    },
    'blg' => static function () use ($simulation): string {
        if (!BlgService::actif()) {
            return 'désactivé';
        }
        $s = (new BlgService())->synchroniser($simulation);
        return sprintf('%d fichier(s), %d créé(s), %d mis à jour, %d ignoré(s), %d erreur(s)', $s['fichiers'], $s['crees'], $s['mis_a_jour'], $s['ignores'], $s['erreurs'])
            . ($s['details'] ? ' | ' . implode(' | ', $s['details']) : '');
    },
    'penalites' => static function () use ($simulation): string {
        if (!\App\Core\Settings::bool('penalite_activer_cron', true)) {
            return 'désactivé';
        }
        $s = (new PenaltyService())->recalculerTous($simulation);
        return $s['traites'] . ' litige(s) examiné(s), ' . $s['modifies'] . ' modifié(s)';
    },
    'rappels' => static function () use ($simulation): string {
        $service = new ReminderService();
        $stats = $service->run($simulation);
        if (!$simulation) {
            $stats['emails'] = $service->envoyerRecapitulatif($stats);
        }
        $morceaux = [];
        foreach ($stats as $cle => $valeur) {
            $morceaux[] = $cle . '=' . (int) $valeur;
        }
        return implode(' ', $morceaux);
    },
    'bodacc' => static function () use ($simulation): string {
        $s = (new BodaccWatchService())->surveiller(null, $simulation);
        return $s['controles'] . ' client(s) contrôlé(s), ' . $s['nouveautes'] . ' nouveauté(s), ' . $s['erreurs'] . ' erreur(s)';
    },
    'rapport' => static function () use ($simulation): string {
        if ($simulation) {
            return 'ignoré en simulation';
        }
        $r = (new PeriodicReportService())->envoyer(false);
        return $r['envoye'] ? $r['destinataires'] . ' destinataire(s)' : (string) $r['raison'];
    },
    'rgpd' => static function () use ($simulation): string {
        $s = (new RgpdService())->purger($simulation);
        return $s['contacts_anonymises'] . ' contact(s) anonymisé(s), ' . $s['litiges_supprimes'] . ' litige(s) supprimé(s), ' . $s['audit_purge'] . ' ligne(s) d\'audit';
    },
    'entretien' => static function () use ($simulation): string {
        $n = $simulation ? 0 : RateLimiter::purger(86400);
        $fichiers = 0;
        foreach (glob(APP_ROOT . '/storage/imports/*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - 86400 && !$simulation) {
                @unlink($f);
                $fichiers++;
            }
        }
        return $n . ' compteur(s) purgé(s), ' . $fichiers . ' fichier(s) temporaire(s) supprimé(s)';
    },
];

$codeSortie = 0;
foreach ($taches as $nom => $tache) {
    if ($seulement !== null && !in_array($nom, $seulement, true)) {
        continue;
    }
    try {
        $resultat = $tache();
        $ligne = sprintf('[%s] %s%s : %s', date('Y-m-d H:i:s'), $simulation ? 'DRY-RUN ' : '', $nom, $resultat);
    } catch (\Throwable $e) {
        $codeSortie = 1;
        Logger::exception($e, 'Cron quotidien : tache ' . $nom);
        $ligne = sprintf('[%s] %s : ERREUR %s', date('Y-m-d H:i:s'), $nom, $e->getMessage());
    }
    $sortie($ligne);
    @file_put_contents(APP_ROOT . '/storage/logs/cron-quotidien.log', $ligne . PHP_EOL, FILE_APPEND);
}

exit($codeSortie);
