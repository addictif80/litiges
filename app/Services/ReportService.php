<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Calcul\BalanceAgee;

/**
 * Indicateurs de pilotage : balance agee, recouvrement par agence et par mois,
 * previsions d'encaissement.
 */
class ReportService
{
    /**
     * Balance agee des litiges ouverts. L'anciennete se mesure depuis la date
     * d'echeance, a defaut la date de facture, a defaut la date d'ouverture.
     *
     * @return array{
     *   total:array<string,float>, nb:int, encours:float, anciennete_moyenne:float,
     *   par_agence:array<string,array<string,float>>, par_client:array<int,array<string,mixed>>
     * }
     */
    public function balanceAgee(?string $aujourdhui = null, int $limiteClients = 25): array
    {
        $aujourdhui ??= date('Y-m-d');
        $lignes = Database::fetchAll(
            "SELECT l.id, l.reference, l.client_id, c.raison_sociale,
                    COALESCE(a.nom, 'Non affectée') AS agence,
                    (l.montant_principal + l.montant_penalites - l.montant_recupere) AS encours,
                    COALESCE(l.date_echeance, l.date_facture, l.date_ouverture) AS date_ref
             FROM litiges l
             JOIN clients c ON c.id = l.client_id
             JOIN litige_statuts s ON s.id = l.statut_id
             LEFT JOIN agences a ON a.id = l.agence_id
             WHERE s.cloturant = 0
               AND (l.montant_principal + l.montant_penalites - l.montant_recupere) > 0.005"
        );

        $total     = BalanceAgee::vide();
        $parAgence = [];
        $parClient = [];
        $sommeJoursPonderee = 0.0;
        $encoursTotal = 0.0;

        foreach ($lignes as $l) {
            $jours  = BalanceAgee::joursDeRetard((string) $l['date_ref'], $aujourdhui);
            $tranche = BalanceAgee::tranche($jours);
            $montant = (float) $l['encours'];

            $total[$tranche] += $montant;
            $parAgence[$l['agence']] ??= BalanceAgee::vide();
            $parAgence[$l['agence']][$tranche] += $montant;

            $cid = (int) $l['client_id'];
            $parClient[$cid] ??= ['client_id' => $cid, 'raison_sociale' => $l['raison_sociale']] + BalanceAgee::vide() + ['total' => 0.0, 'nb' => 0];
            $parClient[$cid][$tranche] += $montant;
            $parClient[$cid]['total'] += $montant;
            $parClient[$cid]['nb']++;

            if ($jours > 0) {
                $sommeJoursPonderee += $jours * $montant;
            }
            $encoursTotal += $montant;
        }

        ksort($parAgence);
        usort($parClient, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'total'              => $total,
            'nb'                 => count($lignes),
            'encours'            => round($encoursTotal, 2),
            'anciennete_moyenne' => $encoursTotal > 0 ? round($sommeJoursPonderee / $encoursTotal, 1) : 0.0,
            'par_agence'         => $parAgence,
            'par_client'         => array_slice($parClient, 0, max(1, $limiteClients)),
        ];
    }

    /**
     * Par mois et par agence : montants mis en recouvrement (ouverture des litiges)
     * et montants encaisses (paiements).
     *
     * @return array{mois:string[], agences:array<string,array<string,array{ouvert:float,recupere:float}>>, total:array<string,array{ouvert:float,recupere:float}>}
     */
    public function recouvrementParAgence(int $nbMois = 12): array
    {
        $nbMois = max(1, min(36, $nbMois));
        $debut  = date('Y-m-01', strtotime('-' . ($nbMois - 1) . ' months'));

        $mois = [];
        for ($i = $nbMois - 1; $i >= 0; $i--) {
            $mois[] = date('Y-m', strtotime("first day of -{$i} months"));
        }

        $ouverts = Database::fetchAll(
            "SELECT COALESCE(a.nom, 'Non affectée') AS agence, DATE_FORMAT(l.date_ouverture, '%Y-%m') AS mois,
                    SUM(l.montant_principal + l.montant_penalites) AS montant
             FROM litiges l LEFT JOIN agences a ON a.id = l.agence_id
             WHERE l.date_ouverture >= :debut
             GROUP BY agence, mois",
            ['debut' => $debut]
        );
        $encaisses = Database::fetchAll(
            "SELECT COALESCE(a.nom, 'Non affectée') AS agence, DATE_FORMAT(p.date_paiement, '%Y-%m') AS mois,
                    SUM(p.montant) AS montant
             FROM paiements p JOIN litiges l ON l.id = p.litige_id LEFT JOIN agences a ON a.id = l.agence_id
             WHERE p.date_paiement >= :debut
             GROUP BY agence, mois",
            ['debut' => $debut]
        );

        $modele = array_fill_keys($mois, ['ouvert' => 0.0, 'recupere' => 0.0]);
        $agences = [];
        $total = $modele;

        foreach ($ouverts as $r) {
            if (!isset($modele[$r['mois']])) {
                continue;
            }
            $agences[$r['agence']] ??= $modele;
            $agences[$r['agence']][$r['mois']]['ouvert'] += (float) $r['montant'];
            $total[$r['mois']]['ouvert'] += (float) $r['montant'];
        }
        foreach ($encaisses as $r) {
            if (!isset($modele[$r['mois']])) {
                continue;
            }
            $agences[$r['agence']] ??= $modele;
            $agences[$r['agence']][$r['mois']]['recupere'] += (float) $r['montant'];
            $total[$r['mois']]['recupere'] += (float) $r['montant'];
        }

        ksort($agences);
        return ['mois' => $mois, 'agences' => $agences, 'total' => $total];
    }

    /**
     * Previsions d'encaissement : echeances de plans de paiement non soldees et
     * promesses de paiement a venir (pour les litiges sans echeancier).
     *
     * @return array{semaines:array<int,array<string,mixed>>, total_echeances:float, total_promesses:float, en_retard:float}
     */
    public function previsions(int $nbSemaines = 8): array
    {
        $nbSemaines = max(1, min(26, $nbSemaines));
        $aujourdhui = new \DateTimeImmutable('today');
        $fin        = $aujourdhui->modify('+' . ($nbSemaines * 7) . ' days')->format('Y-m-d');

        $echeances = Database::fetchAll(
            "SELECT e.date_echeance AS date, (e.montant - e.montant_regle) AS reste
             FROM echeances e
             JOIN litiges l ON l.id = e.litige_id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND e.montant_regle + 0.005 < e.montant
               AND e.date_echeance BETWEEN CURDATE() AND :fin",
            ['fin' => $fin]
        );

        // Promesse = derniere promesse non echue de chaque litige sans echeancier impaye.
        $promesses = Database::fetchAll(
            "SELECT r.date_promesse AS date, r.montant_promis AS reste
             FROM relances r
             JOIN litiges l ON l.id = r.litige_id
             JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND r.date_promesse BETWEEN CURDATE() AND :fin AND r.montant_promis > 0
               AND NOT EXISTS (SELECT 1 FROM echeances e WHERE e.litige_id = l.id AND e.montant_regle + 0.005 < e.montant)
               AND r.id = (SELECT MAX(r2.id) FROM relances r2 WHERE r2.litige_id = l.id AND r2.date_promesse IS NOT NULL)",
            ['fin' => $fin]
        );

        $enRetard = (float) Database::value(
            "SELECT COALESCE(SUM(e.montant - e.montant_regle), 0)
             FROM echeances e JOIN litiges l ON l.id = e.litige_id JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND e.date_echeance < CURDATE() AND e.montant_regle + 0.005 < e.montant",
            [],
            0
        );

        $semaines = [];
        for ($i = 0; $i < $nbSemaines; $i++) {
            $debutSemaine = $aujourdhui->modify('+' . ($i * 7) . ' days');
            $semaines[$i] = [
                'debut'     => $debutSemaine->format('Y-m-d'),
                'fin'       => $debutSemaine->modify('+6 days')->format('Y-m-d'),
                'echeances' => 0.0,
                'promesses' => 0.0,
            ];
        }
        $placer = static function (array $lignes, string $cle) use (&$semaines, $aujourdhui): void {
            foreach ($lignes as $l) {
                $jours = (int) $aujourdhui->diff(new \DateTimeImmutable((string) $l['date']))->days;
                $index = intdiv($jours, 7);
                if (isset($semaines[$index])) {
                    $semaines[$index][$cle] += (float) $l['reste'];
                }
            }
        };
        $placer($echeances, 'echeances');
        $placer($promesses, 'promesses');

        return [
            'semaines'        => $semaines,
            'total_echeances' => round(array_sum(array_column($semaines, 'echeances')), 2),
            'total_promesses' => round(array_sum(array_column($semaines, 'promesses')), 2),
            'en_retard'       => round($enRetard, 2),
        ];
    }
}
