<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Calcul\BalanceAgee;
use PHPUnit\Framework\TestCase;

final class BalanceAgeeTest extends TestCase
{
    /** @dataProvider tranches */
    public function testTranches(int $jours, string $attendu): void
    {
        $this->assertSame($attendu, BalanceAgee::tranche($jours));
    }

    public static function tranches(): array
    {
        return [
            'non echu' => [-5, 'courant'],
            'jour meme' => [0, 'courant'],
            '1 jour' => [1, 't0_30'],
            '30 jours' => [30, 't0_30'],
            '31 jours' => [31, 't31_60'],
            '60 jours' => [60, 't31_60'],
            '61 jours' => [61, 't61_90'],
            '90 jours' => [90, 't61_90'],
            '91 jours' => [91, 't90p'],
        ];
    }

    public function testJoursDeRetard(): void
    {
        $this->assertSame(10, BalanceAgee::joursDeRetard('2025-01-01', '2025-01-11'));
        $this->assertSame(-5, BalanceAgee::joursDeRetard('2025-01-11', '2025-01-06'));
        $this->assertSame(0, BalanceAgee::joursDeRetard('2025-01-01', '2025-01-01'));
    }

    public function testVide(): void
    {
        $vide = BalanceAgee::vide();
        $this->assertSame(array_keys(BalanceAgee::TRANCHES), array_keys($vide));
        $this->assertSame(0.0, array_sum($vide));
    }
}
