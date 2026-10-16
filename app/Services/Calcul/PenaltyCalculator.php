<?php
declare(strict_types=1);

namespace App\Services\Calcul;

/**
 * Calcul des penalites de retard (interets simples + indemnite forfaitaire).
 * Fonction pure, sans acces a la base : facile a tester.
 *
 * Methode : interets au taux annuel sur le capital restant du, jour par jour
 * (base 365), les paiements etant imputes sur le capital. L'indemnite
 * forfaitaire (40 EUR, art. D.441-5 du Code de commerce) est due des le premier
 * jour de retard.
 */
final class PenaltyCalculator
{
    /**
     * @param array<int,array{date:string,montant:float|int|string}> $paiements
     * @return array{interets:float, indemnite:float, total:float, jours:int, capital_restant:float}
     */
    public static function calculer(
        float $principal,
        string $depuis,
        string $jusquau,
        array $paiements,
        float $tauxAnnuel,
        bool $avecIndemnite,
        float $montantIndemnite = 40.0
    ): array {
        $debut = self::date($depuis);
        $fin   = self::date($jusquau);

        if ($principal <= 0 || $fin <= $debut) {
            return ['interets' => 0.0, 'indemnite' => 0.0, 'total' => 0.0, 'jours' => 0, 'capital_restant' => max(0.0, round($principal, 2))];
        }

        usort($paiements, static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));

        $capital  = $principal;
        $curseur  = $debut;
        $interets = 0.0;

        foreach ($paiements as $paiement) {
            $montant = (float) $paiement['montant'];
            if ($montant <= 0) {
                continue;
            }
            $date = self::date((string) $paiement['date']);
            if ($date > $fin) {
                break;
            }
            if ($date > $curseur) {
                $interets += self::interetsPeriode($capital, $tauxAnnuel, (int) $curseur->diff($date)->days);
                $curseur = $date;
            }
            $capital = max(0.0, $capital - $montant);
        }

        if ($fin > $curseur && $capital > 0) {
            $interets += self::interetsPeriode($capital, $tauxAnnuel, (int) $curseur->diff($fin)->days);
        }

        $jours     = (int) $debut->diff($fin)->days;
        $indemnite = ($avecIndemnite && $jours > 0) ? round($montantIndemnite, 2) : 0.0;
        $interets  = round($interets, 2);

        return [
            'interets'        => $interets,
            'indemnite'       => $indemnite,
            'total'           => round($interets + $indemnite, 2),
            'jours'           => $jours,
            'capital_restant' => round($capital, 2),
        ];
    }

    private static function interetsPeriode(float $capital, float $tauxAnnuel, int $jours): float
    {
        return $capital * ($tauxAnnuel / 100) * $jours / 365;
    }

    private static function date(string $valeur): \DateTimeImmutable
    {
        return new \DateTimeImmutable(substr($valeur, 0, 10));
    }
}
