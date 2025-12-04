<?php
/**
 * Reklamace ID Validator Tests
 * Step 151: Unit testy pro validaci ID reklamace
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Exception;

class ReklamaceIdValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Načíst validator
        if (!function_exists('sanitizeReklamaceId')) {
            require_once WGS_ROOT . '/includes/reklamace_id_validator.php';
        }
    }

    // ========================================
    // TESTY PRO sanitizeReklamaceId()
    // ========================================

    /**
     * Test: Validní jednoduché ID
     */
    public function testValidníJednoduchéId(): void
    {
        $result = sanitizeReklamaceId('12345');

        $this->assertSame('12345', $result);
    }

    /**
     * Test: Validní ID s pomlčkou
     */
    public function testValidníIdSPomlčkou(): void
    {
        $result = sanitizeReklamaceId('ABC-123-DEF');

        $this->assertSame('ABC-123-DEF', $result);
    }

    /**
     * Test: Validní ID s lomítkem
     */
    public function testValidníIdSLomítkem(): void
    {
        $result = sanitizeReklamaceId('2024/001/CZ');

        $this->assertSame('2024/001/CZ', $result);
    }

    /**
     * Test: Validní ID s tečkou
     */
    public function testValidníIdSTečkou(): void
    {
        $result = sanitizeReklamaceId('REK.2024.001');

        $this->assertSame('REK.2024.001', $result);
    }

    /**
     * Test: Validní ID s podtržítkem
     */
    public function testValidníIdSPodtržítkem(): void
    {
        $result = sanitizeReklamaceId('REK_2024_001');

        $this->assertSame('REK_2024_001', $result);
    }

    /**
     * Test: ID s mezerami na začátku/konci je trimováno
     */
    public function testIdSMezeramiJeTriomováno(): void
    {
        $result = sanitizeReklamaceId('  12345  ');

        $this->assertSame('12345', $result);
    }

    /**
     * Test: Null hodnota vyhodí výjimku
     */
    public function testNullHodnotaVyhodíVýjimku(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Chybí ID reklamace');

        sanitizeReklamaceId(null);
    }

    /**
     * Test: Prázdný string vyhodí výjimku
     */
    public function testPrázdnýStringVyhodíVýjimku(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Chybí ID reklamace');

        sanitizeReklamaceId('');
    }

    /**
     * Test: Jen mezery vyhodí výjimku
     */
    public function testJenMezeryVyhodíVýjimku(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Chybí ID reklamace');

        sanitizeReklamaceId('   ');
    }

    /**
     * Test: Pole vyhodí výjimku
     */
    public function testPoleVyhodíVýjimku(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Neplatné ID reklamace');

        sanitizeReklamaceId(['id' => '123']);
    }

    /**
     * Test: ID s neplatnými znaky vyhodí výjimku
     */
    public function testIdSNeplatnýmiZnakyVyhodíVýjimku(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Neplatné ID reklamace');

        // SQL injection pokus
        sanitizeReklamaceId("123'; DROP TABLE--");
    }

    /**
     * Test: ID s mezerou uprostřed vyhodí výjimku
     */
    public function testIdSMezorouUprostředVyhodíVýjimku(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Neplatné ID reklamace');

        sanitizeReklamaceId('ABC 123');
    }

    /**
     * Test: ID se speciálními znaky vyhodí výjimku
     */
    public function testIdSeSpeciálnímiZnakyVyhodíVýjimku(): void
    {
        $neplatneZnaky = ['@', '#', '$', '%', '&', '*', '!', '?', '<', '>', '|'];

        foreach ($neplatneZnaky as $znak) {
            try {
                sanitizeReklamaceId("ABC{$znak}123");
                $this->fail("Očekávána výjimka pro znak: {$znak}");
            } catch (Exception $e) {
                $this->assertStringContainsString('Neplatné ID reklamace', $e->getMessage());
            }
        }
    }

    /**
     * Test: ID je zkráceno na max 120 znaků
     */
    public function testIdJeZkrácenoNaMax120Znaků(): void
    {
        $dlouhéId = str_repeat('A', 150);

        $result = sanitizeReklamaceId($dlouhéId);

        $this->assertSame(120, strlen($result));
    }

    /**
     * Test: Vlastní label v chybové zprávě
     */
    public function testVlastníLabelVChybovéZprávě(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Chybí číslo objednávky');

        sanitizeReklamaceId(null, 'číslo objednávky');
    }

    // ========================================
    // TESTY PRO reklamaceStorageKey()
    // ========================================

    /**
     * Test: Jednoduché ID bez změny
     */
    public function testStorageKeyJednoduchéId(): void
    {
        $result = reklamaceStorageKey('ABC123');

        $this->assertSame('ABC123', $result);
    }

    /**
     * Test: Lomítka jsou nahrazena pomlčkami
     */
    public function testStorageKeyNahrazujeLomítka(): void
    {
        $result = reklamaceStorageKey('2024/001/CZ');

        $this->assertSame('2024-001-CZ', $result);
    }

    /**
     * Test: Zpětná lomítka jsou nahrazena pomlčkami
     */
    public function testStorageKeyNahrazujeZpětnáLomítka(): void
    {
        $result = reklamaceStorageKey('2024\\001\\CZ');

        $this->assertSame('2024-001-CZ', $result);
    }

    /**
     * Test: Vícenásobné pomlčky jsou zredukovány
     */
    public function testStorageKeyRedukujePomlčky(): void
    {
        $result = reklamaceStorageKey('ABC---DEF');

        $this->assertSame('ABC-DEF', $result);
    }

    /**
     * Test: Pomlčky na začátku/konci jsou odstraněny
     */
    public function testStorageKeyOdstraňujePomlčkyNaKrajích(): void
    {
        $result = reklamaceStorageKey('/ABC/DEF/');

        $this->assertSame('ABC-DEF', $result);
    }

    /**
     * Test: Prázdný string vrací fallback
     */
    public function testStorageKeyPrázdnýStringVracíFallback(): void
    {
        $result = reklamaceStorageKey('');

        $this->assertSame('reklamace', $result);
    }

    /**
     * Test: Samé lomítka vrací fallback
     */
    public function testStorageKeySaméLomítkaVracíFallback(): void
    {
        $result = reklamaceStorageKey('///');

        $this->assertSame('reklamace', $result);
    }
}
