<?php
declare(strict_types=1);

namespace App\Enums;

enum CanalAction: string
{
    case Telephone = 'telephone';
    case Email     = 'email';
    case Courrier  = 'courrier';
    case Sms       = 'sms';
    case Physique  = 'physique';
    case Autre     = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Telephone => 'Téléphone',
            self::Email     => 'E-mail',
            self::Courrier  => 'Courrier',
            self::Sms       => 'SMS',
            self::Physique  => 'En personne',
            self::Autre     => 'Autre',
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
