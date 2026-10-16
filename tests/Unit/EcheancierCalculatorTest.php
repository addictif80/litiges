<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Calcul\EcheancierCalculator;
use PHPUnit\Framework\TestCase;

final class EcheancierCalculatorTest extends TestCase
{
    public function testRepartitionEgaleAvecCentimesSurLaDerniere(): void
    {
        $plan = EcheancierCalculator::generer(100.0, 3, '2025-01-31', 'mensuelle');
        $this->assertSame([33.33, 33.33, 33.34], array_column($plan, 'montant'));
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($plan, 'montant')), 0.001);
    }

    public function testFinDeMoisRespectee(): void
    {
        $plan = EcheancierCalculator::generer(300.0, 3, '2025-01-31', 'mensuelle');
        $this->assertSame(['2025-01-31', '2025-02-28', '2025-03-31'], array_column($plan, 'date'));
    }

    public function testPeriodicites(): void
    {
        $hebdo = EcheancierCalculator::generer(300.0, 3, '2025-01-01', 'hebdomadaire');
        $this->assertSame(['2025-01-01', '2025-01-08', '2025-01-15'], array_column($hebdo, 'date'));
        $quinzaine = EcheancierCalculator::generer(300.0, 3, '2025-01-01', 'quinzaine');
        $this->assertSame(['2025-01-01', '2025-01-16', '2025-01-31'], array_column($quinzaine, 'date'));
    }

    public function testPremierVersement(): void
    {
        $plan = EcheancierCalculator::generer(1000.0, 4, '2025-01-01', 'mensuelle', 400.0);
        $this->assertSame([400.0, 200.0, 200.0, 200.0], array_column($plan, 'montant'));
    }

    public function testTroncatureSansErreurBinaire(): void
    {
        // 2,90 / 10 = 0,29 : ne doit pas devenir 0,28 a cause de 0,29 * 100 = 28,999...
        $plan = EcheancierCalculator::generer(2.90, 10, '2025-01-01', 'mensuelle');
        $this->assertSame(0.29, $plan[0]['montant']);
        $this->assertEqualsWithDelta(2.90, array_sum(array_column($plan, 'montant')), 0.001);
    }

    public function testMontantNulOuNegatif(): void
    {
        $this->assertSame([], EcheancierCalculator::generer(0.0, 3, '2025-01-01'));
    }

    public function testNombreBorne(): void
    {
        $this->assertCount(120, EcheancierCalculator::generer(1200.0, 999, '2025-01-01'));
        $this->assertCount(1, EcheancierCalculator::generer(100.0, 0, '2025-01-01'));
    }

    public function testImputationDesPaiementsDansLOrdre(): void
    {
        $echeances = [['numero' => 1, 'montant' => 100], ['numero' => 2, 'montant' => 100], ['numero' => 3, 'montant' => 100]];
        $this->assertSame([100.0, 50.0, 0.0], EcheancierCalculator::repartir($echeances, 150.0));
        $this->assertSame([0.0, 0.0, 0.0], EcheancierCalculator::repartir($echeances, 0.0));
        $this->assertSame([100.0, 100.0, 100.0], EcheancierCalculator::repartir($echeances, 999.0));
    }

    public function testStatuts(): void
    {
        $this->assertSame('payee', EcheancierCalculator::statut(100.0, 100.0, '2025-01-01', '2025-06-01'));
        $this->assertSame('en_retard', EcheancierCalculator::statut(100.0, 40.0, '2025-01-01', '2025-06-01'));
        $this->assertSame('en_retard', EcheancierCalculator::statut(100.0, 0.0, '2025-01-01', '2025-06-01'));
        $this->assertSame('partielle', EcheancierCalculator::statut(100.0, 40.0, '2025-09-01', '2025-06-01'));
        $this->assertSame('a_venir', EcheancierCalculator::statut(100.0, 0.0, '2025-09-01', '2025-06-01'));
        // Le jour de l'echeance, le paiement n'est pas encore en retard.
        $this->assertSame('a_venir', EcheancierCalculator::statut(100.0, 0.0, '2025-06-01', '2025-06-01'));
    }
}
