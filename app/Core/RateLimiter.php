<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitation de tentatives adossee a la base (table rate_limits).
 *
 * Fenetre fixe : le compteur repart a zero quand la fenetre est ecoulee.
 * En cas d'indisponibilite de la table, la limitation est ignoree (fail-open)
 * et l'incident est journalise.
 */
class RateLimiter
{
    /**
     * Enregistre une tentative et indique si la limite est depassee.
     *
     * @return int|null Nombre de secondes a attendre si bloque, null sinon.
     */
    public static function hit(string $cle, int $max, int $fenetreSecondes): ?int
    {
        $cle = self::normaliser($cle);
        try {
            Database::run(
                'INSERT INTO rate_limits (cle, hits, window_start) VALUES (:k, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                    hits = IF(window_start < DATE_SUB(NOW(), INTERVAL :w1 SECOND), 1, hits + 1),
                    window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL :w2 SECOND), NOW(), window_start)',
                ['k' => $cle, 'w1' => $fenetreSecondes, 'w2' => $fenetreSecondes]
            );
            return self::attente($cle, $max, $fenetreSecondes);
        } catch (\Throwable $e) {
            Logger::warning('RateLimiter indisponible', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verifie sans compter : renvoie les secondes d'attente si deja bloque.
     */
    public static function bloque(string $cle, int $max, int $fenetreSecondes): ?int
    {
        try {
            return self::attente(self::normaliser($cle), $max, $fenetreSecondes);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function reinitialiser(string $cle): void
    {
        try {
            Database::run('DELETE FROM rate_limits WHERE cle = :k', ['k' => self::normaliser($cle)]);
        } catch (\Throwable) {
            // ignore
        }
    }

    /** Purge les compteurs expires (appele par le cron). */
    public static function purger(int $ageSecondes = 86400): int
    {
        try {
            return Database::run(
                'DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL :s SECOND)',
                ['s' => $ageSecondes]
            )->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Message lisible pour l'utilisateur. */
    public static function message(int $secondes): string
    {
        $minutes = max(1, (int) ceil($secondes / 60));
        return "Trop de tentatives. Réessayez dans {$minutes} minute(s).";
    }

    private static function attente(string $cle, int $max, int $fenetreSecondes): ?int
    {
        $ligne = Database::fetch(
            'SELECT hits, TIMESTAMPDIFF(SECOND, window_start, NOW()) AS age FROM rate_limits WHERE cle = :k',
            ['k' => $cle]
        );
        if (!$ligne) {
            return null;
        }
        $age = (int) $ligne['age'];
        if ($age >= $fenetreSecondes) {
            return null;
        }
        return (int) $ligne['hits'] > $max ? max(1, $fenetreSecondes - $age) : null;
    }

    private static function normaliser(string $cle): string
    {
        return strlen($cle) <= 120 ? $cle : substr($cle, 0, 60) . sha1($cle);
    }
}
