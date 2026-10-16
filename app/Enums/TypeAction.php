<?php
declare(strict_types=1);

namespace App\Enums;

/** Nature d'une action de suivi / relance. */
enum TypeAction: string
{
    case Relance          = 'relance';
    case Appel            = 'appel';
    case Email            = 'email';
    case Courrier         = 'courrier';
    case MiseEnDemeure    = 'mise_en_demeure';
    case PromessePaiement = 'promesse_paiement';
    case Echelonnement    = 'echelonnement';
    case Visite           = 'visite';
    case Autre            = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Relance          => 'Relance',
            self::Appel            => 'Appel téléphonique',
            self::Email            => 'E-mail',
            self::Courrier         => 'Courrier',
            self::MiseEnDemeure    => 'Mise en demeure',
            self::PromessePaiement => 'Promesse de paiement',
            self::Echelonnement    => 'Échelonnement',
            self::Visite           => 'Visite / rendez-vous',
            self::Autre            => 'Autre',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $cas) {
            $options[$cas->value] = $cas->label();
        }
        return $options;
    }
}
