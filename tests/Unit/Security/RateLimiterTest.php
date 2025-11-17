<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use RateLimiter;

/**
 * Testy pro Rate Limiter
 *
 * Testuje:
 * - Vytvoření tabulky
 * - Rate limiting enforcement
 * - Transaction handling (race condition protection)
 * - Cleanup mechanismus
 * - Blocking mechanismus
 * - Reset funkci
 */
class RateLimiterTest extends TestCase
{
    private $pdo;
    private $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        // Vytvořit in-memory SQLite databázi pro testy
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Načíst RateLimiter třídu
        require_once __DIR__ . '/../../../includes/rate_limiter.php';

        // Vytvořit instanci RateLimiteru (automaticky vytvoří tabulku)
        $this->rateLimiter = new RateLimiter($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->rateLimiter = null;
        parent::tearDown();
    }

    /**
     * Test: Tabulka wgs_rate_limits je vytvořena
     */
    public function testVytvoriTabulku(): void
    {
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='wgs_rate_limits'");
        $table = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($table, 'Tabulka wgs_rate_limits musí existovat');
    }

    /**
     * Test: První pokus je povolen
     */
    public function testPrvniPokusJePovolen(): void
    {
        $result = $this->rateLimiter->checkLimit('test_user', 'test_action', [
            'max_attempts' => 5,
            'window_minutes' => 10,
            'block_minutes' => 60
        ]);

        $this->assertTrue($result['allowed'], 'První pokus musí být povolen');
        $this->assertEquals(4, $result['remaining'], 'Musí zbývat 4 pokusy');
    }

    /**
     * Test: Limity jsou správně vynucovány
     */
    public function testLimityJsouVynucovany(): void
    {
        $limits = [
            'max_attempts' => 3,
            'window_minutes' => 10,
            'block_minutes' => 60
        ];

        // První 3 pokusy musí projít
        for ($i = 0; $i < 3; $i++) {
            $result = $this->rateLimiter->checkLimit('test_user', 'login', $limits);
            $this->assertTrue($result['allowed'], "Pokus #{$i} musí být povolen");
        }

        // 4. pokus musí být zablokován
        $result = $this->rateLimiter->checkLimit('test_user', 'login', $limits);
        $this->assertFalse($result['allowed'], '4. pokus musí být zablokován');
        $this->assertEquals(0, $result['remaining'], 'Nesmí zbývat žádné pokusy');
    }

    /**
     * Test: Různé action_type mají odděléné limity
     */
    public function testRuzneAkceJsouOddelene(): void
    {
        $limits = ['max_attempts' => 2, 'window_minutes' => 10, 'block_minutes' => 60];

        // Vyčerpat limit pro 'login'
        $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->rateLimiter->checkLimit('user1', 'login', $limits);

        // 'email' akce musí stále fungovat
        $result = $this->rateLimiter->checkLimit('user1', 'email', $limits);
        $this->assertTrue($result['allowed'], 'Jiná akce musí mít vlastní limit');
    }

    /**
     * Test: Různí uživatelé mají odděléné limity
     */
    public function testRuzniUzivateleJsouOddeleni(): void
    {
        $limits = ['max_attempts' => 2, 'window_minutes' => 10, 'block_minutes' => 60];

        // Vyčerpat limit pro user1
        $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->rateLimiter->checkLimit('user1', 'login', $limits);

        // user2 musí mít vlastní limity
        $result = $this->rateLimiter->checkLimit('user2', 'login', $limits);
        $this->assertTrue($result['allowed'], 'Jiný uživatel musí mít vlastní limit');
    }

    /**
     * Test: Reset funkce vymaže limity
     */
    public function testResetVymazeLimity(): void
    {
        $limits = ['max_attempts' => 2, 'window_minutes' => 10, 'block_minutes' => 60];

        // Vyčerpat limit
        $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->rateLimiter->checkLimit('user1', 'login', $limits);

        // Limit by měl být vyčerpán
        $result = $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->assertFalse($result['allowed']);

        // Reset
        $this->rateLimiter->reset('user1', 'login');

        // Po resetu musí být povolen
        $result = $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->assertTrue($result['allowed'], 'Po resetu musí být pokusy povoleny');
    }

    /**
     * Test: Reset všech akcí pro uživatele
     */
    public function testResetVsechAkci(): void
    {
        $limits = ['max_attempts' => 1, 'window_minutes' => 10, 'block_minutes' => 60];

        // Vyčerpat více akcí
        $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->rateLimiter->checkLimit('user1', 'email', $limits);

        // Reset všech akcí
        $this->rateLimiter->reset('user1'); // Bez action_type = všechny akce

        // Obě akce musí být resetovány
        $loginResult = $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $emailResult = $this->rateLimiter->checkLimit('user1', 'email', $limits);

        $this->assertTrue($loginResult['allowed'], 'Login musí být resetován');
        $this->assertTrue($emailResult['allowed'], 'Email musí být resetován');
    }

    /**
     * Test: Blokování funguje správně
     */
    public function testBlokovaniJeVynuceno(): void
    {
        $limits = ['max_attempts' => 2, 'window_minutes' => 10, 'block_minutes' => 60];

        // Vyčerpat limit
        $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->rateLimiter->checkLimit('user1', 'login', $limits);

        // Tento pokus způsobí blokování
        $result = $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->assertFalse($result['allowed']);

        // Další pokusy musí být stále blokovány
        $result2 = $this->rateLimiter->checkLimit('user1', 'login', $limits);
        $this->assertFalse($result2['allowed'], 'Zablokovaný uživatel musí zůstat zablokován');
    }

    /**
     * Test: Výchozí limity fungují
     */
    public function testVychoziLimityFunguji(): void
    {
        // Bez specifikace limitů - použijí se výchozí (5, 10, 60)
        $result = $this->rateLimiter->checkLimit('user1', 'default_test');

        $this->assertTrue($result['allowed']);
        $this->assertEquals(4, $result['remaining'], 'Výchozí max_attempts je 5');
    }

    /**
     * Test: Returning informace o zbývajícím čase
     */
    public function testVraciInformaceOZbyvajicimCase(): void
    {
        $limits = ['max_attempts' => 2, 'window_minutes' => 10, 'block_minutes' => 60];

        // Vyčerpat limit
        $this->rateLimiter->checkLimit('user1', 'action', $limits);
        $this->rateLimiter->checkLimit('user1', 'action', $limits);
        $result = $this->rateLimiter->checkLimit('user1', 'action', $limits);

        $this->assertArrayHasKey('reset_at', $result, 'Musí vrátit reset_at');
        $this->assertNotNull($result['reset_at'], 'reset_at nesmí být null');
    }

    /**
     * Test: Transaction safety - attempt count increment je atomický
     *
     * Tento test ověřuje že při současném volání checkLimit()
     * nedojde k race condition díky FOR UPDATE lock.
     */
    public function testTransakceChrani(): void
    {
        $limits = ['max_attempts' => 5, 'window_minutes' => 10, 'block_minutes' => 60];

        // Simulace několika současných pokusů
        // (V reálném světě by to byly paralelní requesty)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->rateLimiter->checkLimit('concurrent_user', 'test', $limits);
            $this->assertTrue($result['allowed'], "Pokus #{$i} musí být povolen");
        }

        // Ověřit že počet pokusů je přesný
        $stmt = $this->pdo->prepare("
            SELECT attempt_count FROM wgs_rate_limits
            WHERE identifier = ? AND action_type = ?
        ");
        $stmt->execute(['concurrent_user', 'test']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(3, $row['attempt_count'], 'Počet pokusů musí být přesně 3 (ne víc kvůli race condition)');
    }

    /**
     * Test: Fail-open behavior při chybě databáze
     *
     * Pokud selže databáze, rate limiter by měl povolit požadavek
     * místo úplného zamrznutí aplikace.
     */
    public function testFailOpenPriChybeDb(): void
    {
        // Vytvořit neplatné PDO připojení
        $badPdo = new \PDO('sqlite::memory:');
        $badPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // Nemá tabulku wgs_rate_limits

        $limiter = new RateLimiter($badPdo);

        // Drop tabulku aby selhal SELECT
        $badPdo->exec("DROP TABLE IF EXISTS wgs_rate_limits");

        $result = $limiter->checkLimit('user', 'action');

        $this->assertTrue($result['allowed'], 'Při chybě DB musí povolit request (fail-open)');
        $this->assertNull($result['remaining'], 'remaining musí být null při chybě');
    }

    /**
     * Test: Message obsahuje užitečné informace
     */
    public function testMessageObsahujeInformace(): void
    {
        $limits = ['max_attempts' => 3, 'window_minutes' => 10, 'block_minutes' => 60];

        $result = $this->rateLimiter->checkLimit('user1', 'action', $limits);

        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }
}
