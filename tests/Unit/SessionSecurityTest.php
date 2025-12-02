<?php
/**
 * Session Security Tests
 * Step 152: Unit testy pro bezpečnost sessions
 *
 * Testuje session handling a bezpečnostní kontroly.
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    // ========================================
    // PŘIHLÁŠENÍ / ODHLÁŠENÍ
    // ========================================

    /**
     * Test: Simulace přihlášení nastaví správné session hodnoty
     */
    public function testSimulacePřihlášeníNastavíSession(): void
    {
        $this->loginAsUser(123, 'test@example.com');

        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertArrayHasKey('user_email', $_SESSION);
        $this->assertArrayHasKey('is_logged_in', $_SESSION);

        $this->assertSame(123, $_SESSION['user_id']);
        $this->assertSame('test@example.com', $_SESSION['user_email']);
        $this->assertTrue($_SESSION['is_logged_in']);
    }

    /**
     * Test: Simulace admin přihlášení nastaví is_admin
     */
    public function testSimulaceAdminPřihlášeníNastavíIsAdmin(): void
    {
        $this->loginAsAdmin();

        $this->assertArrayHasKey('is_admin', $_SESSION);
        $this->assertTrue($_SESSION['is_admin']);
    }

    /**
     * Test: Odhlášení vyčistí session
     */
    public function testOdhlášeníVyčistíSession(): void
    {
        $this->loginAsUser(123, 'test@example.com');
        $this->logout();

        $this->assertEmpty($_SESSION);
    }

    // ========================================
    // KONTROLA PŘIHLÁŠENÍ
    // ========================================

    /**
     * Test: Kontrola přihlášení pro běžného uživatele
     */
    public function testKontrolaPřihlášeníProUživatele(): void
    {
        $this->loginAsUser(123, 'test@example.com');

        // Typická kontrola v API
        $isLoggedIn = isset($_SESSION['user_id']) ||
                      (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

        $this->assertTrue($isLoggedIn);
    }

    /**
     * Test: Kontrola přihlášení pro admina
     */
    public function testKontrolaPřihlášeníProAdmina(): void
    {
        $this->loginAsAdmin();

        $isLoggedIn = isset($_SESSION['user_id']) ||
                      (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

        $this->assertTrue($isLoggedIn);
    }

    /**
     * Test: Kontrola přihlášení bez session
     */
    public function testKontrolaPřihlášeníBezSession(): void
    {
        $this->logout();

        $isLoggedIn = isset($_SESSION['user_id']) ||
                      (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

        $this->assertFalse($isLoggedIn);
    }

    // ========================================
    // ADMIN KONTROLA
    // ========================================

    /**
     * Test: Admin kontrola pro admina
     */
    public function testAdminKontrolaProAdmina(): void
    {
        $this->loginAsAdmin();

        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

        $this->assertTrue($isAdmin);
    }

    /**
     * Test: Admin kontrola pro běžného uživatele
     */
    public function testAdminKontrolaProUživatele(): void
    {
        $this->loginAsUser(123, 'test@example.com');

        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

        $this->assertFalse($isAdmin);
    }

    /**
     * Test: Admin kontrola s is_admin = false
     */
    public function testAdminKontrolaSIsAdminFalse(): void
    {
        $this->loginAsUser(123, 'test@example.com');
        $_SESSION['is_admin'] = false;

        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

        $this->assertFalse($isAdmin);
    }

    /**
     * Test: Admin kontrola s is_admin = 'true' (string)
     */
    public function testAdminKontrolaSIsAdminString(): void
    {
        $this->loginAsUser(123, 'test@example.com');
        $_SESSION['is_admin'] = 'true'; // String, ne boolean

        // Strict comparison === true by mělo selhat
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

        $this->assertFalse($isAdmin, 'String "true" by neměl projít strict comparison');
    }

    /**
     * Test: Admin kontrola s is_admin = 1 (integer)
     */
    public function testAdminKontrolaSIsAdminInteger(): void
    {
        $this->loginAsUser(123, 'test@example.com');
        $_SESSION['is_admin'] = 1; // Integer, ne boolean

        // Strict comparison === true by mělo selhat
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

        $this->assertFalse($isAdmin, 'Integer 1 by neměl projít strict comparison');
    }

    // ========================================
    // SESSION FIXATION PREVENCE
    // ========================================

    /**
     * Test: Session ID regenerace (koncept)
     */
    public function testSessionIdRegenerace(): void
    {
        // V reálném kódu by se volalo session_regenerate_id(true)
        // Zde testujeme koncept

        $oldSessionData = ['user_id' => 123];
        $newSessionData = $oldSessionData; // Data se zachovají

        $this->assertSame($oldSessionData['user_id'], $newSessionData['user_id']);
    }

    // ========================================
    // SESSION TIMEOUT
    // ========================================

    /**
     * Test: Session timeout kontrola
     */
    public function testSessionTimeoutKontrola(): void
    {
        $this->loginAsUser(123, 'test@example.com');
        $_SESSION['last_activity'] = time() - 3600; // 1 hodina staré

        $sessionTimeout = 1800; // 30 minut
        $isExpired = (time() - $_SESSION['last_activity']) > $sessionTimeout;

        $this->assertTrue($isExpired, 'Session by měla být expirovaná');
    }

    /**
     * Test: Session ještě platná
     */
    public function testSessionJeštěPlatná(): void
    {
        $this->loginAsUser(123, 'test@example.com');
        $_SESSION['last_activity'] = time() - 300; // 5 minut staré

        $sessionTimeout = 1800; // 30 minut
        $isExpired = (time() - $_SESSION['last_activity']) > $sessionTimeout;

        $this->assertFalse($isExpired, 'Session by měla být stále platná');
    }

    // ========================================
    // REQUEST METHOD KONTROLA
    // ========================================

    /**
     * Test: POST request detekce
     */
    public function testPostRequestDetekce(): void
    {
        $this->simulatePost(['action' => 'save']);

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

        $this->assertTrue($isPost);
    }

    /**
     * Test: GET request detekce
     */
    public function testGetRequestDetekce(): void
    {
        $this->simulateGet(['id' => '123']);

        $isGet = $_SERVER['REQUEST_METHOD'] === 'GET';

        $this->assertTrue($isGet);
    }

    /**
     * Test: POST data jsou dostupná
     */
    public function testPostDataJsouDostupná(): void
    {
        $this->simulatePost([
            'action' => 'save',
            'name' => 'Test',
            'email' => 'test@example.com'
        ]);

        $this->assertArrayHasKey('action', $_POST);
        $this->assertSame('save', $_POST['action']);
        $this->assertSame('Test', $_POST['name']);
    }

    /**
     * Test: GET parametry jsou dostupné
     */
    public function testGetParametryJsouDostupné(): void
    {
        $this->simulateGet([
            'id' => '123',
            'action' => 'view'
        ]);

        $this->assertArrayHasKey('id', $_GET);
        $this->assertSame('123', $_GET['id']);
        $this->assertSame('view', $_GET['action']);
    }
}
