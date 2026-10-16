<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Calcul\PenaltyCalculator;
use PHPUnit\Framework\TestCase;

final class PenaltyCalculatorTest extends TestCase
{
    public function testInteretsSimplesSansPaiement(): void
    {
        // 1000 EUR a 10 % pendant 73 jours = 1000 * 0,10 * 73 / 365 = 20,00 EUR
        $r = PenaltyCalculator::calculer(1000.0, '2025-01-01', '2025-03-15', [], 10.0, false);
        $this->assertSame(73, $r['jours']);
        $this->assertEqualsWithDelta(20.00, $r['interets'], 0.001);
        $this->assertSame(0.0, $r['indemnite']);
        $this->assertEqualsWithDelta(20.00, $r['total'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $r['capital_restant'], 0.001);
    }

    public function testIndemniteForfaitaire(): void
    {
        $r = PenaltyCalculator::calculer(1000.0, '2025-01-01', '2025-03-15', [], 10.0, true, 40.0);
        $this->assertEqualsWithDelta(40.0, $r['indemnite'], 0.001);
        $this->assertEqualsWithDelta(60.0, $r['total'], 0.001);
    }

    public function testLePaiementReduitLeCapitalPourLaSuite(): void
    {
        // 36 jours sur 1000 puis 37 jours sur 500 :
        // 1000*0,1*36/365 + 500*0,1*37/365 = 9,8630 + 5,0685 = 14,93
        $r = PenaltyCalculator::calculer(1000.0, '2025-01-01', '2025-03-15', [['date' => '2025-02-06', 'montant' => 500]], 10.0, false);
        $this->assertEqualsWithDelta(14.93, $r['interets'], 0.005);
        $this->assertEqualsWithDelta(500.0, $r['capital_restant'], 0.001);
    }

    public function testPasDeRetardPasDePenalite(): void
    {
        $r = PenaltyCalculator::calculer(1000.0, '2025-03-15', '2025-03-15', [], 10.0, true);
        $this->assertSame(0.0, $r['total']);
        $r = PenaltyCalculator::calculer(1000.0, '2025-04-01', '2025-03-15', [], 10.0, true);
        $this->assertSame(0.0, $r['total']);
    }

    public function testCapitalEntierementRegleArreteLesInterets(): void
    {
        $r = PenaltyCalculator::calculer(1000.0, '2025-01-01', '2025-12-31', [['date' => '2025-01-11', 'montant' => 1000]], 10.0, false);
        // Uniquement 10 jours d'interets : 1000 * 0,1 * 10 / 365 = 2,74
        $this->assertEqualsWithDelta(2.74, $r['interets'], 0.005);
        $this->assertSame(0.0, $r['capital_restant']);
    }

    public function testPaiementAnterieurAuDepartReduitLeCapitalInitial(): void
    {
        $r = PenaltyCalculator::calculer(1000.0, '2025-02-01', '2025-03-03', [['date' => '2025-01-15', 'montant' => 400]], 10.0, false);
        // Le paiement du 15/01 precede le point de depart : 600 EUR courent pendant 30 jours.
        $this->assertEqualsWithDelta(600 * 0.10 * 30 / 365, $r['interets'], 0.01);
    }

    public function testPaiementsDansLeDesordreSontTries(): void
    {
        $a = PenaltyCalculator::calculer(1000.0, '2025-01-01', '2025-03-15', [
            ['date' => '2025-02-20', 'montant' => 200], ['date' => '2025-02-06', 'montant' => 300],
        ], 10.0, false);
        $b = PenaltyCalculator::calculer(1000.0, '2025-01-01', '2025-03-15', [
            ['date' => '2025-02-06', 'montant' => 300], ['date' => '2025-02-20', 'montant' => 200],
        ], 10.0, false);
        $this->assertSame($a['interets'], $b['interets']);
    }
}
