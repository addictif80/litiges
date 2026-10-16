<?php
declare(strict_types=1);

namespace App\Enums;

enum Priorite: string
{
    case Basse    = 'basse';
    case Normale  = 'normale';
    case Haute    = 'haute';
    case Critique = 'critique';

    public function label(): string
    {
        return match ($this) {
            self::Basse    => 'Basse',
            self::Normale  => 'Normale',
            self::Haute    => 'Haute',
            self::Critique => 'Critique',
        };
    }

    /** Rang pour les tris (0 = plus urgent). */
    public function rang(): int
    {
        return match ($this) {
            self::Critique => 0,
            self::Haute    => 1,
            self::Normale  => 2,
            self::Basse    => 3,
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

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
