<?php
declare(strict_types=1);

namespace App\Enums;

enum TypeLitige: string
{
    case Impaye         = 'impaye';
    case RetardPaiement = 'retard_paiement';
    case Degradation    = 'degradation';
    case NonRestitution = 'restriction';
    case Vol            = 'vol';
    case Sinistre       = 'sinistre';
    case Penalites      = 'penalites';
    case Carburant      = 'carburant';
    case Assurance      = 'assurance';
    case Autre          = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Impaye         => 'Impayé',
            self::RetardPaiement => 'Retard de paiement',
            self::Degradation    => 'Dégradation matériel',
            self::NonRestitution => 'Non-restitution',
            self::Vol            => 'Vol / disparition',
            self::Sinistre       => 'Sinistre / accident',
            self::Penalites      => 'Pénalités de retard',
            self::Carburant      => 'Carburant',
            self::Assurance      => 'Assurance / franchise',
            self::Autre          => 'Autre',
        };
    }

    /** @return array<string,string> valeur => libelle */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $cas) {
            $options[$cas->value] = $cas->label();
        }
        return $options;
    }
}
