<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

/**
 * Testy pro CSRF Protection Helper
 *
 * Testuje:
 * - Generování CSRF tokenů
 * - Validaci tokenů
 * - Ochranu proti timing attacks
 * - Ochranu proti array injection
 * - requireCSRF() funkci
 */
class CsrfHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Vyčistit session před každým testem
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        // Načíst csrf_helper.php
        require_once __DIR__ . '/../../../includes/csrf_helper.php';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }

    /**
     * Test: generateCSRFToken() vytvoří nový token pokud neexistuje
     */
    public function testGenerujeCsrfTokenPokudNeexistuje(): void
    {
        $token = generateCSRFToken();

        $this->assertNotEmpty($token, 'Token nesmí být prázdný');
        $this->assertEquals(64, strlen($token), 'Token musí mít 64 znaků (32 bytes hex)');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token, 'Token musí být hexadecimální');
    }

    /**
     * Test: generateCSRFToken() vrátí stejný token při opakovaném volání
     */
    public function testVraciStejnyTokenPriOpakovani(): void
    {
        $token1 = generateCSRFToken();
        $token2 = generateCSRFToken();

        $this->assertSame($token1, $token2, 'Opakované volání musí vrátit stejný token');
    }

    /**
     * Test: generateCSRFToken() ukládá token do session
     */
    public function testUkladaTokenDoSession(): void
    {
        $token = generateCSRFToken();

        $this->assertArrayHasKey('csrf_token', $_SESSION, 'Token musí být v $_SESSION');
        $this->assertSame($token, $_SESSION['csrf_token'], 'Token v session musí odpovídat vráceném');
    }

    /**
     * Test: validateCSRFToken() validuje správný token
     */
    public function testValidujeSprávnyToken(): void
    {
        $token = generateCSRFToken();

        $this->assertTrue(validateCSRFToken($token), 'Správný token musí být validní');
    }

    /**
     * Test: validateCSRFToken() odmítne nesprávný token
     */
    public function testOdmitneNespravnyToken(): void
    {
        generateCSRFToken(); // Vygeneruje token

        $this->assertFalse(validateCSRFToken('nespravny_token'), 'Nesprávný token musí být odmítnut');
    }

    /**
     * Test: validateCSRFToken() odmítne prázdný token
     */
    public function testOdmitnePrazdnyToken(): void
    {
        generateCSRFToken();

        $this->assertFalse(validateCSRFToken(''), 'Prázdný token musí být odmítnut');
    }

    /**
     * Test: validateCSRFToken() vrátí false pokud token není v session
     */
    public function testOdmitneTokenKdyzNeniVSession(): void
    {
        // Nevygenerovat token - session je prázdná
        $this->assertFalse(validateCSRFToken('jakykoli_token'), 'Token musí být odmítnut pokud není v session');
    }

    /**
     * Test: validateCSRFToken() používá hash_equals (timing attack protection)
     */
    public function testPouzivaHashEqualsProTimingProtection(): void
    {
        $token = generateCSRFToken();

        // Otestujeme že podobné tokeny jsou stále odmítnuté
        $similarToken = substr($token, 0, -1) . 'x'; // Liší se jen poslední znak

        $this->assertFalse(validateCSRFToken($similarToken), 'Podobný token musí být odmítnut');
    }

    /**
     * Test: ARRAY INJECTION PROTECTION
     * requireCSRF() musí odmítnout array jako token
     */
    public function testOdmitneArrayJakoToken(): void
    {
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_POST['csrf_token'] = ['hack', 'attempt']; // Array injection pokus

        $this->expectOutputRegex('/Neplatn.*CSRF token/');

        ob_start();
        requireCSRF();
        ob_end_clean();
    }

    /**
     * Test: requireCSRF() přijme validní token z $_POST
     */
    public function testRequireCsrfPrijmeValidniTokenZPost(): void
    {
        $token = generateCSRFToken();
        $_POST['csrf_token'] = $token;

        // Nemělo by hodit výjimku ani zastavit script
        try {
            ob_start();
            requireCSRF();
            $output = ob_get_clean();

            $this->assertEmpty($output, 'Validní token nesmí způsobit output');
        } catch (\Exception $e) {
            $this->fail('Validní token nesmí způsobit výjimku');
        }
    }

    /**
     * Test: requireCSRF() přijme validní token z $_GET
     */
    public function testRequireCsrfPrijmeValidniTokenZGet(): void
    {
        $token = generateCSRFToken();
        $_GET['csrf_token'] = $token;

        try {
            ob_start();
            requireCSRF();
            $output = ob_get_clean();

            $this->assertEmpty($output, 'Validní GET token nesmí způsobit output');
        } catch (\Exception $e) {
            $this->fail('Validní GET token nesmí způsobit výjimku');
        }
    }

    /**
     * Test: requireCSRF() přijme validní token z HTTP headeru
     */
    public function testRequireCsrfPrijmeValidniTokenZHeader(): void
    {
        $token = generateCSRFToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        try {
            ob_start();
            requireCSRF();
            $output = ob_get_clean();

            $this->assertEmpty($output, 'Validní HTTP header token nesmí způsobit output');
        } catch (\Exception $e) {
            $this->fail('Validní HTTP header token nesmí způsobit výjimku');
        }
    }

    /**
     * Test: Různé tokeny jsou unikátní
     */
    public function testRuzneTokenyJsouUnikatni(): void
    {
        $tokens = [];

        for ($i = 0; $i < 100; $i++) {
            $_SESSION = []; // Reset session
            $token = generateCSRFToken();
            $tokens[] = $token;
        }

        $uniqueTokens = array_unique($tokens);

        $this->assertCount(100, $uniqueTokens, 'Všech 100 tokenů musí být unikátních');
    }

    /**
     * Test: Token má dostatečnou entropii (náhodnost)
     */
    public function testTokenMaDostatecnouEntropii(): void
    {
        $_SESSION = [];
        $token = generateCSRFToken();

        // Token nesmí obsahovat předvídatelné vzory
        $this->assertStringNotContainsString('00000000', $token, 'Token nesmí obsahovat dlouhé sekvence nul');
        $this->assertStringNotContainsString('ffffffff', $token, 'Token nesmí obsahovat dlouhé sekvence F');
        $this->assertStringNotContainsString('12345678', $token, 'Token nesmí obsahovat sekvenční čísla');
    }

    /**
     * Test: Validace tokenu je case-sensitive
     */
    public function testValidaceJeCaseSensitive(): void
    {
        $token = generateCSRFToken();
        $uppercaseToken = strtoupper($token);

        if ($token !== $uppercaseToken) {
            $this->assertFalse(validateCSRFToken($uppercaseToken), 'Uppercase verze tokenu musí být odmítnuta');
        } else {
            $this->markTestSkipped('Token obsahuje pouze lowercase písmena');
        }
    }

    /**
     * Test: Bezpečnostní kontrola - admin bypass byl odstraněn
     * Dříve byl admin bypass security vulnerability - nyní i admin potřebuje CSRF token
     */
    public function testAdminNemaBypasProtection(): void
    {
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['is_admin'] = true; // Simulace admin session
        $_POST['csrf_token'] = 'spatny_token';

        // Admin s neplatným tokenem MUSÍ být odmítnut
        $this->expectOutputRegex('/Neplatn.*CSRF token/');

        ob_start();
        requireCSRF();
        ob_end_clean();
    }
}
