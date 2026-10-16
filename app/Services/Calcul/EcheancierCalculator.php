<?php
declare(strict_types=1);

namespace App\Services\Calcul;

/**
 * Calculs d'echeanciers de paiement (fonctions pures).
 */
final class EcheancierCalculator
{
    public const PERIODICITES = [
        'hebdomadaire' => 'Hebdomadaire',
        'quinzaine'    => 'Tous les 15 jours',
        'mensuelle'    => 'Mensuelle',
    ];

    /**
     * Decoupe un montant en echeances regulieres. Les centimes d'arrondi sont
     * reportes sur la derniere echeance. Un acompte initial peut etre fixe.
     *
     * @return array<int,array{numero:int, date:string, montant:float}>
     */
    public static function generer(
        float $total,
        int $nombre,
        string $premiereDate,
        string $periodicite = 'mensuelle',
        ?float $premierMontant = null
    ): array {
        $nombre = max(1, min(120, $nombre));
        $total  = round($total, 2);
        if ($total <= 0) {
            return [];
        }

        $montants = [];
        if ($premierMontant !== null && $premierMontant > 0 && $nombre > 1 && $premierMontant < $total) {
            $montants[] = round($premierMontant, 2);
            $reste      = round($total - $premierMontant, 2);
            $part       = self::tronquer($reste / ($nombre - 1));
            for ($i = 2; $i <= $nombre; $i++) {
                $montants[] = $part;
            }
        } else {
            $part = self::tronquer($total / $nombre);
            for ($i = 1; $i <= $nombre; $i++) {
                $montants[] = $part;
            }
        }
        // Ajustement des centimes sur la derniere echeance.
        $montants[count($montants) - 1] = round($total - array_sum(array_slice($montants, 0, -1)), 2);

        $debut = new \DateTimeImmutable(substr($premiereDate, 0, 10));
        $plan  = [];
        foreach ($montants as $i => $montant) {
            $plan[] = [
                'numero'  => $i + 1,
                'date'    => self::dateN($debut, $i, $periodicite)->format('Y-m-d'),
                'montant' => $montant,
            ];
        }
        return $plan;
    }

    /** Tronque au centime en evitant les erreurs binaires (0,29 * 100 = 28,999...). */
    private static function tronquer(float $valeur): float
    {
        return floor(round($valeur * 100, 6)) / 100;
    }

    /** Date de la n-ieme echeance (index 0 = premiere), fin de mois respectee. */
    public static function dateN(\DateTimeImmutable $debut, int $index, string $periodicite): \DateTimeImmutable
    {
        return match ($periodicite) {
            'hebdomadaire' => $debut->modify('+' . (7 * $index) . ' days'),
            'quinzaine'    => $debut->modify('+' . (15 * $index) . ' days'),
            default        => self::ajouterMois($debut, $index),
        };
    }

    private static function ajouterMois(\DateTimeImmutable $date, int $mois): \DateTimeImmutable
    {
        if ($mois === 0) {
            return $date;
        }
        $jour    = (int) $date->format('j');
        $premier = $date->modify('first day of this month')->modify('+' . $mois . ' months');
        $max     = (int) $premier->format('t');
        return $premier->setDate((int) $premier->format('Y'), (int) $premier->format('n'), min($jour, $max));
    }

    /**
     * Impute un total paye sur les echeances dans l'ordre (premiere echue d'abord).
     *
     * @param array<int,array{numero:int|string, montant:float|string}> $echeances triees par numero
     * @return array<int,float> montants regles, dans le meme ordre
     */
    public static function repartir(array $echeances, float $totalPaye): array
    {
        $reste  = max(0.0, round($totalPaye, 2));
        $regles = [];
        foreach ($echeances as $echeance) {
            $du       = (float) $echeance['montant'];
            $regle    = min($du, $reste);
            $regles[] = round($regle, 2);
            $reste    = round($reste - $regle, 2);
        }
        return $regles;
    }

    /** Statut d'une echeance : payee | partielle | en_retard | a_venir. */
    public static function statut(float $montant, float $regle, string $dateEcheance, string $aujourdhui): string
    {
        if ($regle + 0.005 >= $montant) {
            return 'payee';
        }
        if (substr($dateEcheance, 0, 10) < substr($aujourdhui, 0, 10)) {
            return 'en_retard';
        }
        return $regle > 0 ? 'partielle' : 'a_venir';
    }
}
