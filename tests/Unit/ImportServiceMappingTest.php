<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Import\ImportService;
use PHPUnit\Framework\TestCase;

final class ImportServiceMappingTest extends TestCase
{
    public function testMappingAutomatiqueDesFacturesImpayees(): void
    {
        $entetes = ['Code client', 'Raison sociale', 'N° Facture', 'Date facture', 'Échéance', 'Reste à payer', 'Agence'];
        $mapping = ImportService::proposerMapping('litiges', $entetes);

        $this->assertSame(0, $mapping['client_code_erp']);
        $this->assertSame(1, $mapping['client_raison_sociale']);
        $this->assertSame(2, $mapping['numero_facture']);
        $this->assertSame(3, $mapping['date_facture']);
        $this->assertSame(4, $mapping['date_echeance']);
        $this->assertSame(5, $mapping['montant_principal']);
        $this->assertSame(6, $mapping['agence']);
    }

    public function testUneColonneNeServQueUneFois(): void
    {
        $mapping = ImportService::proposerMapping('litiges', ['Facture', 'Montant']);
        $utilises = array_filter($mapping, static fn ($i): bool => $i !== null);
        $this->assertSame(count($utilises), count(array_unique($utilises)));
    }

    public function testProfilEnregistrePrioriteSurLaDetectionAuto(): void
    {
        $entetes = ['Tiers', 'Pièce', 'Solde dû', 'Libellé'];
        $profil = ['client_raison_sociale' => 'Tiers', 'numero_facture' => 'Pièce', 'montant_principal' => 'Solde dû'];
        $mapping = ImportService::mappingDepuisProfil('litiges', $entetes, $profil);

        $this->assertSame(0, $mapping['client_raison_sociale']);
        $this->assertSame(1, $mapping['numero_facture']);
        $this->assertSame(2, $mapping['montant_principal']);
    }

    public function testReglementsEtChampsObligatoires(): void
    {
        $champs = ImportService::champs('reglements');
        $obligatoires = array_keys(array_filter($champs, static fn (array $c): bool => $c[1]));
        $this->assertEqualsCanonicalizing(['numero_facture', 'date_paiement', 'montant'], $obligatoires);
        $this->assertSame([], ImportService::champs('inconnu'));
    }
}
