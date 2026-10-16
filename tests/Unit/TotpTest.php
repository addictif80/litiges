<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    /** Secret ASCII "12345678901234567890" des vecteurs de test RFC 6238 (SHA-1). */
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testVecteursRfc6238(): void
    {
        // Valeurs a 8 chiffres de la RFC tronquees a 6 chiffres.
        $this->assertSame('287082', Totp::code(self::SECRET, intdiv(59, 30)));
        $this->assertSame('081804', Totp::code(self::SECRET, intdiv(1111111109, 30)));
        $this->assertSame('050471', Totp::code(self::SECRET, intdiv(1111111111, 30)));
        $this->assertSame('005924', Totp::code(self::SECRET, intdiv(1234567890, 30)));
        $this->assertSame('279037', Totp::code(self::SECRET, intdiv(2000000000, 30)));
    }

    public function testVerificationRetourneLePasValide(): void
    {
        $this->assertSame(1, Totp::verifier(self::SECRET, '287082', 59));
        // Tolerance d'un pas (decalage d'horloge) :
        $this->assertSame(1, Totp::verifier(self::SECRET, '287082', 89));
        $this->assertNull(Totp::verifier(self::SECRET, '287082', 59 + 30 * 5));
        $this->assertNull(Totp::verifier(self::SECRET, '000000', 59));
        $this->assertNull(Totp::verifier(self::SECRET, 'abcdef', 59));
    }

    public function testEspacesDansLeCodeSontAcceptes(): void
    {
        $this->assertSame(1, Totp::verifier(self::SECRET, '287 082', 59));
    }

    public function testBase32AllerRetour(): void
    {
        $binaire = random_bytes(20);
        $this->assertSame($binaire, Totp::base32Decoder(Totp::base32Encoder($binaire)));
        $this->assertSame('12345678901234567890', Totp::base32Decoder(self::SECRET));
    }

    public function testSecretGenere(): void
    {
        $secret = Totp::genererSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertNotSame($secret, Totp::genererSecret());
    }

    public function testUriOtpauth(): void
    {
        $uri = Totp::uri('jean@exemple.fr', 'Locavente', 'ABC234');
        $this->assertStringStartsWith('otpauth://totp/Locavente%3Ajean%40exemple.fr?secret=ABC234', $uri);
        $this->assertStringContainsString('issuer=Locavente', $uri);
    }

    public function testCodesDeSecours(): void
    {
        $codes = Totp::codesSecours(8);
        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9A-F]{6}-[0-9A-F]{6}$/', $code);
        }
    }
}
