<?php
declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin        = 'admin';
    case Gestionnaire = 'gestionnaire';
    case Consultant   = 'consultant';
    case Lecteur      = 'lecteur';

    public function label(): string
    {
        return match ($this) {
            self::Admin        => 'Administrateur',
            self::Gestionnaire => 'Gestionnaire',
            self::Consultant   => 'Consultant',
            self::Lecteur      => 'Lecteur (consultation)',
        };
    }

    /** Ce role peut-il modifier des donnees ? */
    public function peutEcrire(): bool
    {
        return $this === self::Admin || $this === self::Gestionnaire;
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
