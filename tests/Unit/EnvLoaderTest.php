<?php
/**
 * Environment Loader Tests
 * Step 151: Unit testy pro načítání env proměnných
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class EnvLoaderTest extends TestCase
{
    private array $originalEnv;
    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();

        // Záloha původních hodnot
        $this->originalEnv = $_ENV;
        $this->originalServer = $_SERVER;

        // Načíst env_loader pokud funkce neexistují
        if (!function_exists('getEnvValue')) {
            // Definovat funkce lokálně pro testování
            $this->defineTestFunctions();
        }
    }

    protected function tearDown(): void
    {
        // Obnovit původní hodnoty
        $_ENV = $this->originalEnv;
        $_SERVER = $this->originalServer;

        parent::tearDown();
    }

    /**
     * Definovat testovací verze funkcí
     */
    private function defineTestFunctions(): void
    {
        // Lokální implementace pro izolované testy
    }

    /**
     * Test: getEnvValue vrací hodnotu z $_SERVER
     */
    public function testGetEnvValueVracíHodnotuZServer(): void
    {
        $_SERVER['TEST_VAR'] = 'server_value';
        $_ENV['TEST_VAR'] = 'env_value';

        $result = getEnvValue('TEST_VAR');

        // $_SERVER má prioritu
        $this->assertSame('server_value', $result);

        unset($_SERVER['TEST_VAR'], $_ENV['TEST_VAR']);
    }

    /**
     * Test: getEnvValue vrací hodnotu z $_ENV když $_SERVER je prázdné
     */
    public function testGetEnvValueVracíHodnotuZEnv(): void
    {
        unset($_SERVER['TEST_VAR_2']);
        $_ENV['TEST_VAR_2'] = 'env_value_2';

        $result = getEnvValue('TEST_VAR_2');

        $this->assertSame('env_value_2', $result);

        unset($_ENV['TEST_VAR_2']);
    }

    /**
     * Test: getEnvValue vrací default hodnotu
     */
    public function testGetEnvValueVracíDefault(): void
    {
        // Ujistit se že proměnná neexistuje
        unset($_SERVER['NEEXISTUJICI_VAR'], $_ENV['NEEXISTUJICI_VAR']);
        putenv('NEEXISTUJICI_VAR');

        $result = getEnvValue('NEEXISTUJICI_VAR', 'default_value');

        $this->assertSame('default_value', $result);
    }

    /**
     * Test: getEnvValue vrací null bez default hodnoty
     */
    public function testGetEnvValueVracíNullBezDefault(): void
    {
        unset($_SERVER['NEEXISTUJICI_VAR_2'], $_ENV['NEEXISTUJICI_VAR_2']);
        putenv('NEEXISTUJICI_VAR_2');

        $result = getEnvValue('NEEXISTUJICI_VAR_2');

        $this->assertNull($result);
    }

    /**
     * Test: getEnvValue ignoruje prázdné stringy v $_SERVER
     */
    public function testGetEnvValueIgnorujePrázdnéStringy(): void
    {
        $_SERVER['PRAZDNY_VAR'] = '';
        $_ENV['PRAZDNY_VAR'] = 'neprazdna_hodnota';

        $result = getEnvValue('PRAZDNY_VAR');

        // Prázdný string v $_SERVER by měl být ignorován
        $this->assertSame('neprazdna_hodnota', $result);

        unset($_SERVER['PRAZDNY_VAR'], $_ENV['PRAZDNY_VAR']);
    }

    /**
     * Test: getEnvValue s numerickou hodnotou
     */
    public function testGetEnvValueSNumerickouHodnotou(): void
    {
        $_SERVER['PORT'] = '8080';

        $result = getEnvValue('PORT');

        // Env hodnoty jsou vždy stringy
        $this->assertSame('8080', $result);

        unset($_SERVER['PORT']);
    }

    /**
     * Test: getEnvValue s boolean-like hodnotou
     */
    public function testGetEnvValueSBooleanHodnotou(): void
    {
        $_SERVER['DEBUG_MODE'] = 'true';

        $result = getEnvValue('DEBUG_MODE');

        // Env hodnoty jsou stringy, ne boolean
        $this->assertSame('true', $result);
        $this->assertNotSame(true, $result);

        unset($_SERVER['DEBUG_MODE']);
    }

    /**
     * Test: getEnvValue s různými default typy
     */
    public function testGetEnvValueSRůznýmiDefaultTypy(): void
    {
        unset($_SERVER['NEEXIST'], $_ENV['NEEXIST']);
        putenv('NEEXIST');

        // String default
        $this->assertSame('default', getEnvValue('NEEXIST', 'default'));

        // Integer default
        $this->assertSame(123, getEnvValue('NEEXIST', 123));

        // Boolean default
        $this->assertSame(false, getEnvValue('NEEXIST', false));

        // Array default
        $this->assertSame(['a', 'b'], getEnvValue('NEEXIST', ['a', 'b']));
    }
}
