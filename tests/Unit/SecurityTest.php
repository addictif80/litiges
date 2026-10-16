<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\SecurityHeaders;
use App\Services\ClamAv;
use App\Services\ExportService;
use App\Services\UploadService;
use App\Services\UrlGuard;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function testIpIgnoreXForwardedForSansProxyDeConfiance(): void
    {
        $serveur = ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'];
        $this->assertSame('203.0.113.9', Request::resoudreIp($serveur, []));
        $this->assertSame('203.0.113.9', Request::resoudreIp($serveur, ['10.0.0.1']));
    }

    public function testIpUtiliseXForwardedForDeLaPartDunProxyDeConfiance(): void
    {
        $serveur = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.0.0.1'];
        $this->assertSame('198.51.100.7', Request::resoudreIp($serveur, ['10.0.0.1']));
    }

    public function testIpInvalideOuAbsente(): void
    {
        $this->assertSame('0.0.0.0', Request::resoudreIp([], []));
        $serveur = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'pas-une-ip'];
        $this->assertSame('10.0.0.1', Request::resoudreIp($serveur, ['10.0.0.1']));
    }

    public function testCspContientLeNonceEtInterditLesObjets(): void
    {
        $csp = SecurityHeaders::construireCsp('abc123');
        $this->assertStringContainsString("script-src 'self' 'nonce-abc123'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*unsafe-inline/", $csp);
    }

    public function testNonceUniqueParRequeteEtNonVide(): void
    {
        $nonce = SecurityHeaders::nonce();
        $this->assertGreaterThanOrEqual(20, strlen($nonce));
        $this->assertSame($nonce, SecurityHeaders::nonce());
    }

    public function testHotesLocaux(): void
    {
        $this->assertTrue(SecurityHeaders::estLocal('localhost'));
        $this->assertTrue(SecurityHeaders::estLocal('app.test'));
        $this->assertFalse(SecurityHeaders::estLocal('litiges.exemple.fr'));
    }

    /** @dataProvider adresses */
    public function testIpEstPublique(string $ip, bool $attendu): void
    {
        $this->assertSame($attendu, UrlGuard::ipEstPublique($ip));
    }

    public static function adresses(): array
    {
        return [
            'google dns' => ['8.8.8.8', true],
            'ipv6 publique' => ['2606:4700:4700::1111', true],
            'loopback' => ['127.0.0.1', false],
            'prive 10' => ['10.1.2.3', false],
            'prive 192' => ['192.168.0.12', false],
            'prive 172' => ['172.16.5.5', false],
            'metadonnees cloud' => ['169.254.169.254', false],
            'ipv6 loopback' => ['::1', false],
            'ipv6 ula' => ['fd12:3456:789a::1', false],
            'ipv6 lien local' => ['fe80::1', false],
            'invalide' => ['pas-une-ip', false],
        ];
    }

    /** @dataProvider mimes */
    public function testCoherenceExtensionEtContenu(string $ext, string $mime, bool $attendu): void
    {
        $this->assertSame($attendu, UploadService::mimeCompatible($ext, $mime));
    }

    public static function mimes(): array
    {
        return [
            'pdf ok' => ['pdf', 'application/pdf', true],
            'pdf deguise en html' => ['pdf', 'text/html', false],
            'jpg ok' => ['JPG', 'image/jpeg', true],
            'png avec contenu php' => ['png', 'text/x-php', false],
            'docx zip' => ['docx', 'application/zip', true],
            'xlsx officiel' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true],
            'csv texte' => ['csv', 'text/plain', true],
            'executable' => ['exe', 'application/x-dosexec', false],
            'php' => ['php', 'text/x-php', false],
        ];
    }

    public function testExtensionsExecutablesJamaisAutorisees(): void
    {
        $autorisees = UploadService::extensionsAutorisees();
        foreach (['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'js'] as $interdite) {
            $this->assertNotContains($interdite, $autorisees);
        }
        $this->assertContains('pdf', $autorisees);
    }

    public function testInjectionDeFormulesNeutralisee(): void
    {
        $this->assertSame("'=SOMME(A1:A9)", ExportService::nettoyerCellule('=SOMME(A1:A9)'));
        $this->assertSame("'+33123456789x", ExportService::nettoyerCellule('+33123456789x'));
        $this->assertSame("'@cmd", ExportService::nettoyerCellule('@cmd'));
        $this->assertSame('-12.5', ExportService::nettoyerCellule('-12.5'));
        $this->assertSame('Dupont', ExportService::nettoyerCellule('Dupont'));
        $this->assertSame(12.5, ExportService::nettoyerCellule(12.5));
    }

    public function testInterpretationReponseClamAv(): void
    {
        $this->assertSame(['ok' => true, 'infecte' => false, 'message' => 'Aucune menace détectée.'], ClamAv::interpreter("stream: OK\0"));
        $infecte = ClamAv::interpreter('stream: Win.Test.EICAR_HDB-1 FOUND');
        $this->assertTrue($infecte['ok']);
        $this->assertTrue($infecte['infecte']);
        $inattendu = ClamAv::interpreter('INSTREAM size limit exceeded. ERROR');
        $this->assertFalse($inattendu['ok']);
        $this->assertFalse($inattendu['infecte']);
    }
}
