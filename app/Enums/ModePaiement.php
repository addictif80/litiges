<?php
declare(strict_types=1);

namespace App\Enums;

enum ModePaiement: string
{
    case Virement    = 'virement';
    case Cheque      = 'cheque';
    case CarteBancaire = 'cb';
    case Prelevement = 'prelevement';
    case Especes     = 'especes';
    case Compensation = 'compensation';
    case Autre       = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Virement      => 'Virement',
            self::Cheque        => 'Chèque',
            self::CarteBancaire => 'Carte bancaire',
            self::Prelevement   => 'Prélèvement',
            self::Especes       => 'Espèces',
            self::Compensation  => 'Compensation / avoir',
            self::Autre         => 'Autre',
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
