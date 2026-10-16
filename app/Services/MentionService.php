<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Mentions @identifiant dans les notes : notifie les personnes citees
 * (si elles ont acces au module concerne).
 */
class MentionService
{
    /**
     * Identifiants cites dans un texte (sans doublon, en minuscules).
     *
     * @return string[]
     */
    public static function extraire(string $texte): array
    {
        if (!preg_match_all('/(?:^|[\s(])@([A-Za-z0-9_.-]{2,60})/u', $texte, $m)) {
            return [];
        }
        return array_values(array_unique(array_map('strtolower', array_map(static fn (string $s): string => rtrim($s, '.-'), $m[1]))));
    }

    /**
     * @param string $module  litiges | clients : module que le destinataire doit pouvoir consulter
     * @return int nombre de personnes notifiees
     */
    public function notifier(string $contenu, int $auteurId, string $module, string $contexte, string $lien, ?int $litigeId = null): int
    {
        $identifiants = self::extraire($contenu);
        if ($identifiants === []) {
            return 0;
        }

        $marqueurs = implode(',', array_fill(0, count($identifiants), '?'));
        $stmt = Database::run(
            "SELECT id, role FROM users WHERE actif = 1 AND LOWER(username) IN ({$marqueurs})",
            $identifiants
        );

        $notif = new NotificationService();
        $n = 0;
        foreach ($stmt->fetchAll() as $u) {
            if ((int) $u['id'] === $auteurId || !Auth::peutPourRole((string) $u['role'], $module)) {
                continue;
            }
            $notif->mention((int) $u['id'], $contexte, $lien, $auteurId, $litigeId);
            $n++;
        }
        return $n;
    }
}
