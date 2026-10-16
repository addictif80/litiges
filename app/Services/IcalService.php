<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

/**
 * Flux d'agenda iCalendar (RFC 5545) a s'abonner depuis Outlook, Google Agenda,
 * Apple Calendrier... : relances a faire, echeances, promesses de paiement,
 * echeances de plans de paiement et taches de l'utilisateur.
 */
class IcalService
{
    /** Genere le calendrier d'un utilisateur. */
    public function calendrier(array $user): string
    {
        $evenements = array_merge(
            $this->relances($user),
            $this->echeancesLitiges($user),
            $this->echeancesPlan($user),
            $this->promesses($user),
            $this->taches($user)
        );

        $lignes = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Locavente//Gestion des litiges//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::echapper('Litiges — ' . ($user['full_name'] ?: $user['username'])),
            'X-WR-TIMEZONE:Europe/Paris',
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];
        foreach ($evenements as $e) {
            $lignes = array_merge($lignes, self::evenement($e));
        }
        $lignes[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'plier'], $lignes)) . "\r\n";
    }

    /**
     * Evenement "journee entiere".
     *
     * @param array{uid:string, date:string, titre:string, description?:string, url?:string} $e
     * @return string[]
     */
    public static function evenement(array $e): array
    {
        $debut = new \DateTimeImmutable(substr($e['date'], 0, 10));
        $lignes = [
            'BEGIN:VEVENT',
            'UID:' . $e['uid'],
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:' . $debut->format('Ymd'),
            'DTEND;VALUE=DATE:' . $debut->modify('+1 day')->format('Ymd'),
            'SUMMARY:' . self::echapper($e['titre']),
        ];
        if (!empty($e['description'])) {
            $lignes[] = 'DESCRIPTION:' . self::echapper($e['description']);
        }
        if (!empty($e['url'])) {
            $lignes[] = 'URL:' . $e['url'];
        }
        $lignes[] = 'TRANSP:TRANSPARENT';
        $lignes[] = 'END:VEVENT';
        return $lignes;
    }

    /** Echappement RFC 5545 des valeurs de type TEXT. */
    public static function echapper(string $texte): string
    {
        $texte = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $texte);
        return str_replace(["\r\n", "\r", "\n"], '\\n', $texte);
    }

    /** Pliage des lignes a 75 octets maximum (sans couper un caractere UTF-8). */
    public static function plier(string $ligne): string
    {
        if (strlen($ligne) <= 75) {
            return $ligne;
        }
        $sortie = '';
        $courant = '';
        $limite = 75;
        foreach (preg_split('//u', $ligne, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $caractere) {
            if (strlen($courant) + strlen($caractere) > $limite) {
                $sortie .= $courant . "\r\n ";
                $courant = '';
                $limite = 74; // l'espace de continuation compte dans les 75 octets
            }
            $courant .= $caractere;
        }
        return $sortie . $courant;
    }

    // ------------------------------------------------------------------

    /** Litiges visibles pour l'utilisateur : les siens, et les non affectes pour un admin. */
    private function filtreResponsable(array $user, array &$params): string
    {
        $params['u'] = (int) $user['id'];
        return $user['role'] === 'admin' ? '(l.responsable_id = :u OR l.responsable_id IS NULL)' : 'l.responsable_id = :u';
    }

    private function hote(): string
    {
        return Request::host() ?: 'litiges.local';
    }

    private function relances(array $user): array
    {
        $params = [];
        $filtre = $this->filtreResponsable($user, $params);
        $lignes = Database::fetchAll(
            "SELECT l.id, l.reference, l.prochaine_relance_at AS d, c.raison_sociale
             FROM litiges l JOIN clients c ON c.id = l.client_id JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND l.prochaine_relance_at IS NOT NULL AND {$filtre}
               AND l.prochaine_relance_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            $params
        );
        return array_map(fn (array $l): array => [
            'uid' => 'relance-' . $l['id'] . '@' . $this->hote(), 'date' => $l['d'],
            'titre' => 'Relance — ' . $l['reference'] . ' (' . $l['raison_sociale'] . ')',
            'url' => url('/litiges/' . (int) $l['id']),
        ], $lignes);
    }

    private function echeancesLitiges(array $user): array
    {
        $params = [];
        $filtre = $this->filtreResponsable($user, $params);
        $lignes = Database::fetchAll(
            "SELECT l.id, l.reference, l.date_echeance AS d, c.raison_sociale
             FROM litiges l JOIN clients c ON c.id = l.client_id JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND l.date_echeance IS NOT NULL AND {$filtre}
               AND l.date_echeance >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            $params
        );
        return array_map(fn (array $l): array => [
            'uid' => 'echeance-' . $l['id'] . '@' . $this->hote(), 'date' => $l['d'],
            'titre' => 'Échéance du litige — ' . $l['reference'] . ' (' . $l['raison_sociale'] . ')',
            'url' => url('/litiges/' . (int) $l['id']),
        ], $lignes);
    }

    private function echeancesPlan(array $user): array
    {
        $params = [];
        $filtre = $this->filtreResponsable($user, $params);
        $lignes = Database::fetchAll(
            "SELECT e.id, e.numero, e.date_echeance AS d, (e.montant - e.montant_regle) AS reste, l.id AS litige_id, l.reference, c.raison_sociale
             FROM echeances e JOIN litiges l ON l.id = e.litige_id JOIN clients c ON c.id = l.client_id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND e.montant_regle + 0.005 < e.montant AND {$filtre}
               AND e.date_echeance >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            $params
        );
        return array_map(fn (array $l): array => [
            'uid' => 'plan-' . $l['id'] . '@' . $this->hote(), 'date' => $l['d'],
            'titre' => 'Paiement attendu ' . money($l['reste']) . ' — ' . $l['raison_sociale'],
            'description' => 'Échéance n°' . $l['numero'] . ' du litige ' . $l['reference'],
            'url' => url('/litiges/' . (int) $l['litige_id']),
        ], $lignes);
    }

    private function promesses(array $user): array
    {
        $params = [];
        $filtre = $this->filtreResponsable($user, $params);
        $lignes = Database::fetchAll(
            "SELECT r.id, r.date_promesse AS d, r.montant_promis, l.id AS litige_id, l.reference, c.raison_sociale
             FROM relances r JOIN litiges l ON l.id = r.litige_id JOIN clients c ON c.id = l.client_id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND r.date_promesse IS NOT NULL AND {$filtre}
               AND r.date_promesse >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            $params
        );
        return array_map(fn (array $l): array => [
            'uid' => 'promesse-' . $l['id'] . '@' . $this->hote(), 'date' => $l['d'],
            'titre' => 'Promesse de paiement — ' . $l['raison_sociale'] . ($l['montant_promis'] ? ' (' . money($l['montant_promis']) . ')' : ''),
            'description' => 'Litige ' . $l['reference'],
            'url' => url('/litiges/' . (int) $l['litige_id']),
        ], $lignes);
    }

    private function taches(array $user): array
    {
        $lignes = Database::fetchAll(
            "SELECT t.id, t.titre, t.echeance AS d, t.litige_id
             FROM taches t
             WHERE t.assigne_a = :u AND t.statut <> 'faite' AND t.echeance IS NOT NULL",
            ['u' => (int) $user['id']]
        );
        return array_map(fn (array $t): array => [
            'uid' => 'tache-' . $t['id'] . '@' . $this->hote(), 'date' => $t['d'],
            'titre' => 'Tâche — ' . $t['titre'],
            'url' => $t['litige_id'] ? url('/litiges/' . (int) $t['litige_id']) : url('/taches'),
        ], $lignes);
    }
}
