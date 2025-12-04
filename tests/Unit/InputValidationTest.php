<?php
/**
 * Input Validation Tests
 * Step 152: Unit testy pro validaci vstupů
 *
 * Testuje běžné validační vzory používané v API.
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class InputValidationTest extends TestCase
{
    // ========================================
    // EMAIL VALIDACE
    // ========================================

    /**
     * @dataProvider platnéEmailyProvider
     */
    public function testPlatnéEmaily(string $email): void
    {
        $result = filter_var($email, FILTER_VALIDATE_EMAIL);
        $this->assertNotFalse($result, "Email '$email' by měl být platný");
    }

    public static function platnéEmailyProvider(): array
    {
        return [
            'standardní email' => ['test@example.com'],
            'email s tečkou' => ['jmeno.prijmeni@example.com'],
            'email s plus' => ['user+tag@example.com'],
            'email s pomlčkou v doméně' => ['user@my-domain.com'],
            'email s čísly' => ['user123@example123.com'],
            'email se subdoménou' => ['user@mail.example.com'],
        ];
    }

    /**
     * @dataProvider neplatnéEmailyProvider
     */
    public function testNeplatnéEmaily(string $email): void
    {
        $result = filter_var($email, FILTER_VALIDATE_EMAIL);
        $this->assertFalse($result, "Email '$email' by měl být neplatný");
    }

    public static function neplatnéEmailyProvider(): array
    {
        return [
            'bez zavináče' => ['testexample.com'],
            'bez domény' => ['test@'],
            'bez lokální části' => ['@example.com'],
            'mezery' => ['test @example.com'],
            'dvojitý zavináč' => ['test@@example.com'],
            'prázdný string' => [''],
            'jen mezery' => ['   '],
        ];
    }

    // ========================================
    // TELEFON VALIDACE
    // ========================================

    /**
     * @dataProvider platnéTelefonyProvider
     */
    public function testPlatnéTelefony(string $telefon): void
    {
        // Očistit na čísla a validovat
        $cisla = preg_replace('/[^0-9+]/', '', $telefon);
        $delka = strlen(preg_replace('/[^0-9]/', '', $cisla));

        $this->assertGreaterThanOrEqual(9, $delka, "Telefon '$telefon' by měl mít min 9 číslic");
        $this->assertLessThanOrEqual(15, $delka, "Telefon '$telefon' by měl mít max 15 číslic");
    }

    public static function platnéTelefonyProvider(): array
    {
        return [
            'CZ mobil' => ['+420 777 123 456'],
            'CZ pevná' => ['+420 2 1234 5678'],
            'SK mobil' => ['+421 905 123 456'],
            'IT mobil' => ['+39 333 1234567'],
            'bez předvolby' => ['777123456'],
            's pomlčkami' => ['777-123-456'],
        ];
    }

    // ========================================
    // SANITIZACE HTML
    // ========================================

    /**
     * Test: htmlspecialchars escapuje nebezpečné znaky
     */
    public function testHtmlspecialcharsEscapujeNebezpečnéZnaky(): void
    {
        $vstup = '<script>alert("XSS")</script>';
        $výstup = htmlspecialchars($vstup, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script>', $výstup);
        $this->assertStringContainsString('&lt;script&gt;', $výstup);
    }

    /**
     * Test: htmlspecialchars zachovává bezpečný text
     */
    public function testHtmlspecialcharsZachováváBezpečnýText(): void
    {
        $vstup = 'Běžný text bez HTML';
        $výstup = htmlspecialchars($vstup, ENT_QUOTES, 'UTF-8');

        $this->assertSame($vstup, $výstup);
    }

    /**
     * Test: htmlspecialchars escapuje uvozovky
     */
    public function testHtmlspecialcharsEscapujeUvozovky(): void
    {
        $vstup = 'onclick="evil()"';
        $výstup = htmlspecialchars($vstup, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('"', $výstup);
        $this->assertStringContainsString('&quot;', $výstup);
    }

    // ========================================
    // SQL INJECTION PREVENCE
    // ========================================

    /**
     * Test: Prepared statements chrání před SQL injection
     */
    public function testPreparedStatementsChrání(): void
    {
        $pdo = $this->getTestDb();

        // Simulace SQL injection pokusu
        $nebezpečnýVstup = "'; DROP TABLE wgs_users; --";

        $stmt = $pdo->prepare("SELECT * FROM wgs_users WHERE email = :email");
        $stmt->execute(['email' => $nebezpečnýVstup]);

        // Tabulka by měla stále existovat
        $result = $pdo->query("SELECT COUNT(*) FROM wgs_users");
        $this->assertNotFalse($result);
    }

    // ========================================
    // CSRF TOKEN VALIDACE
    // ========================================

    /**
     * Test: Pole jako CSRF token je odmítnuto
     */
    public function testPoleJakoCsrfTokenJeOdmítnuto(): void
    {
        $token = ['malicious' => 'array'];

        // Typická ochrana v API
        if (is_array($token)) {
            $token = '';
        }

        $this->assertSame('', $token);
    }

    /**
     * Test: CSRF token musí být string
     */
    public function testCsrfTokenMusíBýtString(): void
    {
        $validníTokeny = ['abc123', 'a1b2c3d4e5f6', str_repeat('a', 64)];
        $nevalidníTokeny = [null, [], new \stdClass(), 123, 45.67];

        foreach ($validníTokeny as $token) {
            $this->assertTrue(is_string($token), "Token by měl být string");
        }

        foreach ($nevalidníTokeny as $token) {
            $this->assertFalse(is_string($token), "Token by neměl být string");
        }
    }

    // ========================================
    // JSON VALIDACE
    // ========================================

    /**
     * Test: Validní JSON je správně parsován
     */
    public function testValidníJsonJeSpravněParsován(): void
    {
        $json = '{"action": "save", "data": {"name": "Test"}}';

        $data = json_decode($json, true);

        $this->assertNotNull($data);
        $this->assertArrayHasKey('action', $data);
        $this->assertSame('save', $data['action']);
    }

    /**
     * Test: Nevalidní JSON vrací null
     */
    public function testNevalidníJsonVracíNull(): void
    {
        $nevalidníJsony = [
            '{invalid}',
            '{"missing": "closing brace"',
            '',
            'not json at all',
            '{"key": undefined}',
        ];

        foreach ($nevalidníJsony as $json) {
            $result = json_decode($json, true);
            $this->assertNull($result, "JSON '$json' by měl vrátit null");
        }
    }

    // ========================================
    // INTEGER VALIDACE
    // ========================================

    /**
     * Test: Pozitivní celé číslo
     */
    public function testPozitivníCeléČíslo(): void
    {
        $validníIds = [1, 100, 999999];
        $nevalidníIds = [0, -1, -100, 'abc', '', null, 1.5];

        foreach ($validníIds as $id) {
            $result = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $this->assertNotFalse($result, "ID $id by mělo být validní");
        }

        foreach ($nevalidníIds as $id) {
            $result = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $this->assertFalse($result, "ID " . var_export($id, true) . " by mělo být nevalidní");
        }
    }

    // ========================================
    // URL VALIDACE
    // ========================================

    /**
     * @dataProvider platnéUrlProvider
     */
    public function testPlatnéUrl(string $url): void
    {
        $result = filter_var($url, FILTER_VALIDATE_URL);
        $this->assertNotFalse($result, "URL '$url' by měla být platná");
    }

    public static function platnéUrlProvider(): array
    {
        return [
            'http' => ['http://example.com'],
            'https' => ['https://example.com'],
            's cestou' => ['https://example.com/path/to/page'],
            's query' => ['https://example.com?param=value'],
            's portem' => ['https://example.com:8080'],
        ];
    }

    /**
     * @dataProvider neplatnéUrlProvider
     */
    public function testNeplatnéUrl(string $url): void
    {
        $result = filter_var($url, FILTER_VALIDATE_URL);
        $this->assertFalse($result, "URL '$url' by měla být neplatná");
    }

    public static function neplatnéUrlProvider(): array
    {
        return [
            'bez protokolu' => ['example.com'],
            'javascript' => ['javascript:alert(1)'],
            'prázdná' => [''],
            'jen mezery' => ['   '],
        ];
    }
}
