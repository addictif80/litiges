<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CourrierService;
use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class CourrierEtHtmlTest extends TestCase
{
    public function testFusionDesVariablesEchappeLesValeurs(): void
    {
        $r = CourrierService::rendre(
            '<p>Bonjour {{ client.raison_sociale }}, solde {{litige.encours}}.</p>',
            ['client.raison_sociale' => 'Dupont <script>alert(1)</script> & Fils', 'litige.encours' => '1 200,00 €']
        );
        $this->assertStringContainsString('Dupont &lt;script&gt;alert(1)&lt;/script&gt; &amp; Fils', $r);
        $this->assertStringNotContainsString('<script>', $r);
        $this->assertStringContainsString('1 200,00 €', $r);
    }

    public function testVariablesHtmlFabriqueesParLApplicationNeSontPasEchappees(): void
    {
        $r = CourrierService::rendre('{{echeancier.tableau}}', [], ['echeancier.tableau' => '<table><tr><td>1</td></tr></table>']);
        $this->assertSame('<table><tr><td>1</td></tr></table>', $r);
    }

    public function testVariableInconnueDevientVideEtCleInsensibleALaCasse(): void
    {
        $this->assertSame('[]', CourrierService::rendre('[{{inconnue.cle}}]', []));
        $this->assertSame('X', CourrierService::rendre('{{LITIGE.Reference}}', ['litige.reference' => 'X']));
    }

    public function testUneValeurNeReinjecteJamaisDeNouvelleVariable(): void
    {
        $r = CourrierService::rendre('{{a}}', ['a' => '{{b}}', 'b' => 'SECRET']);
        $this->assertStringNotContainsString('SECRET', $r);
    }

    public function testPageHtmlEchappeLeTitre(): void
    {
        $page = CourrierService::pageHtml('<b>x</b>', '<p>corps</p>', 'COUR-2025-0001');
        $this->assertStringContainsString('<title>&lt;b&gt;x&lt;/b&gt;</title>', $page);
        $this->assertStringContainsString('<p>corps</p>', $page);
        $this->assertStringContainsString('COUR-2025-0001', $page);
    }

    public function testSanitizerSupprimeScriptsEvenementsEtLiensDangereux(): void
    {
        $html = '<p onclick="x()">Bonjour <strong>monde</strong></p>'
            . '<script>alert(1)</script>'
            . '<a href="javascript:alert(1)" onmouseover="y()">lien</a>'
            . '<a href="https://exemple.fr/page">ok</a>'
            . '<img src="x" onerror="z()">'
            . '<iframe src="https://evil.example"></iframe>';
        $propre = HtmlSanitizer::nettoyer($html);

        $this->assertStringContainsString('<strong>monde</strong>', $propre);
        $this->assertStringContainsString('href="https://exemple.fr/page"', $propre);
        $this->assertStringNotContainsString('script', $propre);
        $this->assertStringNotContainsString('onclick', $propre);
        $this->assertStringNotContainsString('onmouseover', $propre);
        $this->assertStringNotContainsString('javascript:', $propre);
        $this->assertStringNotContainsString('<img', $propre);
        $this->assertStringNotContainsString('iframe', $propre);
    }

    public function testSanitizerConserveLesVariablesEtLaMiseEnForme(): void
    {
        $propre = HtmlSanitizer::nettoyer('<p style="text-align:right">{{client.raison_sociale}}</p><table><tr><td colspan="2">{{echeancier.tableau}}</td></tr></table>');
        $this->assertStringContainsString('{{client.raison_sociale}}', $propre);
        $this->assertStringContainsString('style="text-align:right"', $propre);
        $this->assertStringContainsString('colspan="2"', $propre);
        $this->assertStringContainsString('{{echeancier.tableau}}', $propre);
    }

    public function testSanitizerRefuseLesStylesDangereux(): void
    {
        $propre = HtmlSanitizer::nettoyer('<p style="background:url(javascript:alert(1))">a</p><p style="behavior:url(x.htc)">b</p>');
        $this->assertStringNotContainsString('style=', $propre);
    }

    public function testSanitizerVide(): void
    {
        $this->assertSame('', HtmlSanitizer::nettoyer('   '));
    }
}
