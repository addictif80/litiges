<?php
declare(strict_types=1);

namespace App\Services\Calcul;

/**
 * Classement des creances par anciennete (balance agee).
 */
final class BalanceAgee
{
    public const TRANCHES = [
        'courant' => 'Non échu',
        't0_30'   => '0 - 30 jours',
        't31_60'  => '31 - 60 jours',
        't61_90'  => '61 - 90 jours',
        't90p'    => 'Plus de 90 jours',
    ];

    /**
     * @param int $joursDeRetard Jours ecoules depuis la date d'echeance
     *                           (negatif ou nul = pas encore echu).
     */
    public static function tranche(int $joursDeRetard): string
    {
        if ($joursDeRetard <= 0) {
            return 'courant';
        }
        if ($joursDeRetard <= 30) {
            return 't0_30';
        }
        if ($joursDeRetard <= 60) {
            return 't31_60';
        }
        if ($joursDeRetard <= 90) {
            return 't61_90';
        }
        return 't90p';
    }

    /** Jours de retard entre une date d'echeance et aujourd'hui. */
    public static function joursDeRetard(string $dateEcheance, string $aujourdhui): int
    {
        $echeance = new \DateTimeImmutable(substr($dateEcheance, 0, 10));
        $jour     = new \DateTimeImmutable(substr($aujourdhui, 0, 10));
        $diff     = (int) $echeance->diff($jour)->days;
        return $jour > $echeance ? $diff : -$diff;
    }

    /** @return array<string,float> tranches a zero */
    public static function vide(): array
    {
        return array_fill_keys(array_keys(self::TRANCHES), 0.0);
    }
}
