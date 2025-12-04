<?php
/**
 * Base Test Case
 * Step 151: Základní třída pro všechny testy
 *
 * Poskytuje společné utility a setup/teardown metody.
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected ?PDO $pdo = null;

    /**
     * Setup před každým testem
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset session
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /**
     * Teardown po každém testu
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Vyčistit globální proměnné
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    /**
     * Získat testovací DB připojení
     */
    protected function getTestDb(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = getDbConnection();
            loadTestSchema($this->pdo);
        }

        return $this->pdo;
    }

    /**
     * Simulovat POST request
     */
    protected function simulatePost(array $data): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $data;
    }

    /**
     * Simulovat GET request
     */
    protected function simulateGet(array $params): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $params;
    }

    /**
     * Simulovat přihlášeného uživatele
     */
    protected function loginAsUser(int $userId = 1, string $email = 'test@test.cz'): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['is_logged_in'] = true;
    }

    /**
     * Simulovat přihlášeného admina
     */
    protected function loginAsAdmin(): void
    {
        $this->loginAsUser(1, 'admin@wgs-service.cz');
        $_SESSION['is_admin'] = true;
    }

    /**
     * Odhlásit uživatele
     */
    protected function logout(): void
    {
        $_SESSION = [];
    }

    /**
     * Assert že pole obsahuje klíč s danou hodnotou
     */
    protected function assertArrayHasKeyWithValue(string $key, mixed $expected, array $array): void
    {
        $this->assertArrayHasKey($key, $array);
        $this->assertSame($expected, $array[$key]);
    }

    /**
     * Assert že string obsahuje podřetězec (case-insensitive)
     */
    protected function assertStringContainsIgnoringCase(string $needle, string $haystack): void
    {
        $this->assertTrue(
            stripos($haystack, $needle) !== false,
            "Failed asserting that '$haystack' contains '$needle' (case-insensitive)"
        );
    }
}
