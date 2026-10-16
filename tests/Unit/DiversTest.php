<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Crypto;
use App\Core\Validator;
use App\Enums\Priorite;
use App\Enums\Role;
use App\Enums\TypeAction;
use App\Enums\TypeLitige;
use App\Models\Litige;
use App\Services\IcalService;
use App\Services\MentionService;
use App\Services\Import\XlsxReader;
use App\Services\XlsxWriter;
use PHPUnit\Framework\TestCase;

final class DiversTest extends TestCase
{
    // --- Validator ---------------------------------------------------------

    public function testValidator(): void
    {
        $v = new Validator(['objet' => 'ab', 'date' => '2025-13-40x', 'email' => 'pas-un-mail', 'prio' => 'zzz'], ['objet' => "L'objet"]);
        $v->required('objet')->length('objet', 3, 190)->date('date')->email('email')->in('prio', ['basse', 'haute']);
        $this->assertTrue($v->fails());
        $this->assertSame(['objet', 'date', 'email', 'prio'], array_keys($v->errors()));

        $ok = new Validator(['objet' => 'Impayé facture 12', 'date' => '2025-03-07', 'email' => 'a@b.fr', 'prio' => 'haute']);
        $ok->required('objet')->length('objet', 3, 190)->date('date')->email('email')->in('prio', ['basse', 'haute']);
        $this->assertFalse($ok->fails());
    }

    // --- Enums -------------------------------------------------------------

    public function testEnums(): void
    {
        $this->assertSame('Impayé', TypeLitige::options()['impaye']);
        $this->assertArrayHasKey('restriction', TypeLitige::options(), 'la valeur historique "restriction" (non-restitution) est conservee');
        $this->assertSame(['basse', 'normale', 'haute', 'critique'], Priorite::valeurs());
        $this->assertLessThan(Priorite::Basse->rang(), Priorite::Critique->rang());
        $this->assertSame('Mise en demeure', TypeAction::from('mise_en_demeure')->label());
        $this->assertNull(TypeAction::tryFrom('inconnu'));
        $this->assertTrue(Role::Gestionnaire->peutEcrire());
        $this->assertFalse(Role::Lecteur->peutEcrire());
        $this->assertSame(['admin', 'gestionnaire', 'consultant', 'lecteur'], Role::valeurs());
    }

    // --- Tri des litiges (liste blanche) ---------------------------------------

    public function testTriMultiCriteres(): void
    {
        $sql = Litige::orderBy(['tri' => 'priorite,echeance']);
        $this->assertStringContainsString('FIELD(l.priorite', $sql);
        $this->assertStringContainsString('l.date_echeance', $sql);
        $this->assertLessThan(strpos($sql, 'l.date_echeance'), strpos($sql, 'FIELD'));
    }

    public function testTriRefuseToutCeQuiNEstPasEnListeBlanche(): void
    {
        $defaut = Litige::orderBy(['tri' => 'recent']);
        $this->assertSame($defaut, Litige::orderBy(['tri' => 'recent; DROP TABLE litiges']));
        $this->assertSame($defaut, Litige::orderBy(['tri' => 'x,y,(SELECT 1)']));
        $this->assertSame($defaut, Litige::orderBy([]));
    }

    // --- Mentions ---------------------------------------------------------------

    public function testExtractionDesMentions(): void
    {
        $this->assertSame(['marie.d', 'paul'], MentionService::extraire('Voir avec @Marie.D et @paul. Contact: jean@exemple.fr'));
        $this->assertSame([], MentionService::extraire('mail a jean@exemple.fr'));
        $this->assertSame(['paul'], MentionService::extraire("@paul @paul\n(@paul)"));
    }

    public function testMentionsHtmlEchappeAvantDeMettreEnForme(): void
    {
        $html = mentions_html('Salut @paul <script>x</script>');
        $this->assertStringContainsString('<span class="mention">@paul</span>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    // --- iCalendar --------------------------------------------------------------

    public function testEchappementIcal(): void
    {
        $this->assertSame('a\\, b\\; c\\\\d\\nligne', IcalService::echapper("a, b; c\\d\nligne"));
    }

    public function testPliageDesLignesIcal(): void
    {
        $longue = 'SUMMARY:' . str_repeat('é', 200);
        $pliee = IcalService::plier($longue);
        foreach (explode("\r\n", $pliee) as $segment) {
            $this->assertLessThanOrEqual(75, strlen($segment));
        }
        $this->assertSame($longue, str_replace("\r\n ", '', $pliee));
        $this->assertSame('COURT', IcalService::plier('COURT'));
    }

    public function testEvenementJourneeEntiere(): void
    {
        $lignes = IcalService::evenement(['uid' => 'relance-1@x', 'date' => '2025-12-31', 'titre' => 'Relance, urgente', 'url' => 'https://exemple.fr/l/1']);
        $this->assertContains('DTSTART;VALUE=DATE:20251231', $lignes);
        $this->assertContains('DTEND;VALUE=DATE:20260101', $lignes);
        $this->assertContains('SUMMARY:Relance\\, urgente', $lignes);
        $this->assertSame('BEGIN:VEVENT', $lignes[0]);
        $this->assertSame('END:VEVENT', end($lignes));
    }

    // --- Chiffrement --------------------------------------------------------------

    public function testChiffrementAllerRetourEtIntegrite(): void
    {
        $chiffre = Crypto::chiffrer('JBSWY3DPEHPK3PXP');
        $this->assertStringStartsWith('v1:', $chiffre);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $chiffre);
        $this->assertSame('JBSWY3DPEHPK3PXP', Crypto::dechiffrer($chiffre));

        // Deux chiffrements du meme texte different (IV aleatoire).
        $this->assertNotSame($chiffre, Crypto::chiffrer('JBSWY3DPEHPK3PXP'));

        // Une alteration est detectee (GCM).
        $altere = substr($chiffre, 0, -3) . (substr($chiffre, -3) === 'AAA' ? 'BBB' : 'AAA');
        $this->assertNull(Crypto::dechiffrer($altere));
        $this->assertNull(Crypto::dechiffrer('texte en clair'));
        $this->assertNull(Crypto::dechiffrer(null));
    }

    public function testEmpreinteStable(): void
    {
        $this->assertSame(Crypto::empreinte('ABC'), Crypto::empreinte('ABC'));
        $this->assertNotSame(Crypto::empreinte('ABC'), Crypto::empreinte('ABD'));
        $this->assertSame(64, strlen(Crypto::empreinte('ABC')));
    }

    // --- Excel --------------------------------------------------------------------

    public function testReferencesDeCellules(): void
    {
        $this->assertSame('A1', XlsxWriter::ref(0, 1));
        $this->assertSame('Z9', XlsxWriter::ref(25, 9));
        $this->assertSame('AA2', XlsxWriter::ref(26, 2));
        $this->assertSame('AB3', XlsxWriter::ref(27, 3));
        $this->assertSame(0, XlsxReader::indexColonne('A12'));
        $this->assertSame(27, XlsxReader::indexColonne('AB3'));
    }

    public function testSerieExcel(): void
    {
        $this->assertSame(45658, XlsxWriter::serieExcel('2025-01-01'));
        $this->assertNull(XlsxWriter::serieExcel('2025-02-30'));
        $this->assertNull(XlsxWriter::serieExcel('abc'));
    }

    public function testClasseurExcelAllerRetour(): void
    {
        if (!XlsxWriter::disponible()) {
            $this->markTestSkipped('Extension zip absente.');
        }
        $binaire = XlsxWriter::generer(
            ['nom' => 'Client', 'montant' => 'Montant', 'date' => 'Date'],
            [
                ['nom' => 'Dupont & Fils', 'montant' => '1234.5', 'date' => '2025-03-07'],
                ['nom' => '=CMD()', 'montant' => '10', 'date' => ''],
            ],
            ['montant' => 'nombre', 'date' => 'date']
        );
        $this->assertStringStartsWith('PK', $binaire);

        $fichier = tempnam(sys_get_temp_dir(), 'xl');
        file_put_contents($fichier, $binaire);
        $lignes = XlsxReader::lire($fichier);
        @unlink($fichier);

        $this->assertSame(['Client', 'Montant', 'Date'], $lignes[0]);
        $this->assertSame('Dupont & Fils', $lignes[1][0]);
        $this->assertSame('1234.5', $lignes[1][1]);
        $this->assertSame('45723', $lignes[1][2]); // 2025-03-07
        $this->assertSame("'=CMD()", $lignes[2][0], 'les formules sont neutralisees');
    }
}
