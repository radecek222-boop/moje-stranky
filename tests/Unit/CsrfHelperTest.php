<?php
/**
 * CSRF Helper Tests
 * Step 151: Unit testy pro CSRF ochranu
 *
 * Testuje generování a validaci CSRF tokenů.
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class CsrfHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Načíst CSRF helper - musíme definovat funkce pokud neexistují
        if (!function_exists('generateCSRFToken')) {
            require_once WGS_ROOT . '/includes/csrf_helper.php';
        }
    }

    /**
     * Test: Generování tokenu vytvoří 64-znakový hex string
     */
    public function testGenerujTokenVytvaří64ZnakovýHex(): void
    {
        $_SESSION = [];

        $token = generateCSRFToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Test: Opakované volání vrací stejný token
     */
    public function testOpakovanéVoláníVracíStejnýToken(): void
    {
        $_SESSION = [];

        $token1 = generateCSRFToken();
        $token2 = generateCSRFToken();

        $this->assertSame($token1, $token2);
    }

    /**
     * Test: Token je uložen v session
     */
    public function testTokenJeUloženVSession(): void
    {
        $_SESSION = [];

        $token = generateCSRFToken();

        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    /**
     * Test: Validace platného tokenu
     */
    public function testValidacePlatnéhoTokenu(): void
    {
        $_SESSION = [];

        $token = generateCSRFToken();
        $jeValidní = validateCSRFToken($token);

        $this->assertTrue($jeValidní);
    }

    /**
     * Test: Validace neplatného tokenu
     */
    public function testValidaceNeplatnéhoTokenu(): void
    {
        $_SESSION = [];

        generateCSRFToken();
        $jeValidní = validateCSRFToken('neplatny_token_12345');

        $this->assertFalse($jeValidní);
    }

    /**
     * Test: Validace prázdného tokenu
     */
    public function testValidacePrázdnéhoTokenu(): void
    {
        $_SESSION = [];

        generateCSRFToken();
        $jeValidní = validateCSRFToken('');

        $this->assertFalse($jeValidní);
    }

    /**
     * Test: Validace bez session tokenu
     */
    public function testValidaceBezSessionTokenu(): void
    {
        $_SESSION = [];

        $jeValidní = validateCSRFToken('jakykoli_token');

        $this->assertFalse($jeValidní);
    }

    /**
     * Test: Různé sessions mají různé tokeny
     */
    public function testRůznéSessionsMajíRůznéTokeny(): void
    {
        // Session 1
        $_SESSION = [];
        $token1 = generateCSRFToken();

        // Session 2 (simulace)
        $_SESSION = [];
        $token2 = generateCSRFToken();

        $this->assertNotSame($token1, $token2);
    }

    /**
     * Test: Token je kryptograficky bezpečný (dostatečná entropie)
     */
    public function testTokenMáDostatečnouEntropii(): void
    {
        $_SESSION = [];

        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $_SESSION = [];
            $tokens[] = generateCSRFToken();
        }

        // Všechny tokeny by měly být unikátní
        $unikátní = array_unique($tokens);
        $this->assertCount(100, $unikátní, 'Tokeny by měly být unikátní');
    }

    /**
     * Test: Validace je case-sensitive
     */
    public function testValidaceJeCaseSensitive(): void
    {
        $_SESSION = [];

        $token = generateCSRFToken();
        $uppercaseToken = strtoupper($token);

        // Pokud token obsahuje lowercase písmena, uppercase verze by měla být neplatná
        if ($token !== $uppercaseToken) {
            $jeValidní = validateCSRFToken($uppercaseToken);
            $this->assertFalse($jeValidní);
        } else {
            // Token je celý numerický (velmi nepravděpodobné)
            $this->assertTrue(true);
        }
    }

    /**
     * Test: Validace podobného tokenu (off-by-one)
     */
    public function testValidacePodobnéhoTokenu(): void
    {
        $_SESSION = [];

        $token = generateCSRFToken();
        // Změnit poslední znak
        $podobnýToken = substr($token, 0, -1) . 'x';

        $jeValidní = validateCSRFToken($podobnýToken);

        $this->assertFalse($jeValidní);
    }
}
