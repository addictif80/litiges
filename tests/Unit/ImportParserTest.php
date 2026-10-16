<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Import\ImportParser;
use PHPUnit\Framework\TestCase;

final class ImportParserTest extends TestCase
{
    /** @dataProvider nombres */
    public function testNombre(mixed $entree, ?float $attendu): void
    {
        $r = ImportParser::nombre($entree);
        if ($attendu === null) {
            $this->assertNull($r);
        } else {
            $this->assertEqualsWithDelta($attendu, $r, 0.0001);
        }
    }

    public static function nombres(): array
    {
        return [
            'virgule decimale' => ['1234,56', 1234.56],
            'milliers espaces' => ['1 234,56', 1234.56],
            'milliers points' => ['1.234,56', 1234.56],
            'format anglais' => ['1,234.56', 1234.56],
            'point decimal' => ['1234.56', 1234.56],
            'avec euro' => ['12,5 €', 12.5],
            'negatif' => ['-45,00', -45.0],
            'parentheses' => ['(45,00)', -45.0],
            'entier' => ['500', 500.0],
            'vide' => ['', null],
            'texte' => ['abc', null],
            'null' => [null, null],
        ];
    }

    /** @dataProvider dates */
    public function testDate(mixed $entree, ?string $attendu): void
    {
        $this->assertSame($attendu, ImportParser::date($entree));
    }

    public static function dates(): array
    {
        return [
            'iso' => ['2025-03-07', '2025-03-07'],
            'francais' => ['07/03/2025', '2025-03-07'],
            'tirets' => ['7-3-2025', '2025-03-07'],
            'annee courte' => ['07/03/25', '2025-03-07'],
            'serie excel' => ['45658', '2025-01-01'],
            'date invalide' => ['31/02/2025', null],
            'texte' => ['bientot', null],
            'vide' => ['', null],
        ];
    }

    public function testNormaliserEntete(): void
    {
        $this->assertSame('n facture', ImportParser::normaliserEntete(' N° Facture '));
        $this->assertSame('echeance', ImportParser::normaliserEntete('Échéance'));
        $this->assertSame('raison sociale', ImportParser::normaliserEntete('Raison_Sociale'));
    }

    public function testCsvPointVirguleAvecAccents(): void
    {
        $csv = "Client;Montant\n\"Dupont & Fils\";\"1 234,50\"\nSARL Été;99,00\n";
        $r = ImportParser::lireCsvTexte($csv);
        $this->assertSame(';', $r['separateur']);
        $this->assertSame(['Client', 'Montant'], $r['entetes']);
        $this->assertSame('Dupont & Fils', $r['lignes'][0][0]);
        $this->assertSame('SARL Été', $r['lignes'][1][0]);
    }

    public function testCsvWindows1252EtBom(): void
    {
        $contenu = mb_convert_encoding("Société;Montant\nÉtablissements Martin;10,00\n", 'Windows-1252', 'UTF-8');
        $r = ImportParser::lireCsvTexte($contenu);
        $this->assertSame('Société', $r['entetes'][0]);
        $this->assertSame('Établissements Martin', $r['lignes'][0][0]);

        $avecBom = "\xEF\xBB\xBFa,b\n1,2\n";
        $r = ImportParser::lireCsvTexte($avecBom);
        $this->assertSame(',', $r['separateur']);
        $this->assertSame(['a', 'b'], $r['entetes']);
    }

    public function testCsvIgnoreLesLignesVides(): void
    {
        $r = ImportParser::lireCsvTexte("a;b\n\n1;2\n;\n3;4\n");
        $this->assertCount(2, $r['lignes']);
    }

    public function testSiren(): void
    {
        $this->assertSame('123456789', ImportParser::siren('123 456 789'));
        $this->assertSame('123456789', ImportParser::siren('12345678901234'));
        $this->assertNull(ImportParser::siren('12345'));
        $this->assertNull(ImportParser::siren(''));
    }
}
