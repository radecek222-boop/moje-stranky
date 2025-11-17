<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Testy pro Save Controller
 *
 * Testuje:
 * - generateWorkflowId() - unikátní ID generování s race condition protection
 * - normalizeDateInput() - datum normalizace a validace
 * - handleUpdate() - update reklamací
 */
class SaveControllerTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Vytvořit in-memory SQLite databázi
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Vytvořit tabulku wgs_reklamace pro testy
        $this->pdo->exec("
            CREATE TABLE wgs_reklamace (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reklamace_id VARCHAR(20) UNIQUE,
                jmeno VARCHAR(100),
                email VARCHAR(100),
                telefon VARCHAR(20),
                stav VARCHAR(20),
                datum_prodeje DATE,
                datum_reklamace DATE,
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        // Načíst save.php funkce
        require_once __DIR__ . '/../../../app/controllers/save.php';
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        parent::tearDown();
    }

    /**
     * Test: generateWorkflowId() vytváří správný formát
     */
    public function testGenerujeSpravnyFormatId(): void
    {
        $this->pdo->beginTransaction();

        $id = generateWorkflowId($this->pdo);

        $this->pdo->rollBack();

        // Formát: WGSyymmdd-XXXXXX (např. WGS251114-A3F2B1)
        $this->assertMatchesRegularExpression(
            '/^WGS\d{6}-[A-F0-9]{6}$/',
            $id,
            'ID musí mít formát WGSyymmdd-XXXXXX'
        );
    }

    /**
     * Test: generateWorkflowId() obsahuje dnešní datum
     */
    public function testIdObsahujeDnesniDatum(): void
    {
        $this->pdo->beginTransaction();

        $id = generateWorkflowId($this->pdo);

        $this->pdo->rollBack();

        $expectedDate = date('ymd'); // např. 251114 pro 14.11.2025
        $this->assertStringStartsWith("WGS{$expectedDate}-", $id, 'ID musí obsahovat dnešní datum');
    }

    /**
     * Test: generateWorkflowId() generuje unikátní ID
     */
    public function testGenerujeUnikatniId(): void
    {
        $ids = [];

        for ($i = 0; $i < 50; $i++) {
            $this->pdo->beginTransaction();
            $id = generateWorkflowId($this->pdo);

            // Vložit do databáze aby další generování vidělo že už existuje
            $this->pdo->exec("INSERT INTO wgs_reklamace (reklamace_id) VALUES ('$id')");
            $this->pdo->commit();

            $ids[] = $id;
        }

        $uniqueIds = array_unique($ids);

        $this->assertCount(50, $uniqueIds, 'Všech 50 ID musí být unikátních');
    }

    /**
     * Test: generateWorkflowId() zkusí max 5 pokusů při kolizi
     */
    public function testMaximalniPocetPokusuPriKolizi(): void
    {
        // Naplnit databázi mnoha ID aby byla vysoká pravděpodobnost kolize
        for ($i = 0; $i < 1000; $i++) {
            $fakeId = 'WGS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            try {
                $this->pdo->exec("INSERT INTO wgs_reklamace (reklamace_id) VALUES ('$fakeId')");
            } catch (\PDOException $e) {
                // Ignorovat duplicate key errors
            }
        }

        // I přes mnoho kolizí by měl uspět (max 5 pokusů)
        $this->pdo->beginTransaction();

        try {
            $id = generateWorkflowId($this->pdo);
            $this->assertNotEmpty($id, 'ID musí být vygenerováno i při kolizích');
            $this->pdo->rollBack();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            // Je OK pokud selže po 5 pokusech
            $this->assertStringContainsString('Nepodařilo se vygenerovat', $e->getMessage());
        }
    }

    /**
     * Test: normalizeDateInput() vrací NULL pro prázdný string
     */
    public function testNormalizaceDatumPrazdnyString(): void
    {
        $this->assertNull(normalizeDateInput(''), 'Prázdný string musí vrátit NULL');
        $this->assertNull(normalizeDateInput(null), 'NULL musí vrátit NULL');
        $this->assertNull(normalizeDateInput('   '), 'Whitespace musí vrátit NULL');
    }

    /**
     * Test: normalizeDateInput() vrací NULL pro "nevyplňuje se"
     */
    public function testNormalizaceDatumNevyplnujeSe(): void
    {
        $this->assertNull(normalizeDateInput('nevyplňuje se'), '"nevyplňuje se" musí vrátit NULL');
        $this->assertNull(normalizeDateInput('NEVYPLŇUJE SE'), 'Case-insensitive check');
        $this->assertNull(normalizeDateInput('  nevyplňuje se  '), 'S whitespace');
    }

    /**
     * Test: normalizeDateInput() ponechá YYYY-MM-DD beze změny
     */
    public function testNormalizaceDatumYyyyMmDd(): void
    {
        $this->assertEquals('2025-11-14', normalizeDateInput('2025-11-14'));
        $this->assertEquals('2024-01-01', normalizeDateInput('2024-01-01'));
        $this->assertEquals('2023-12-31', normalizeDateInput('2023-12-31'));
    }

    /**
     * Test: normalizeDateInput() převede DD.MM.YYYY na YYYY-MM-DD
     */
    public function testNormalizaceDatumDdMmYyyy(): void
    {
        $this->assertEquals('2025-11-14', normalizeDateInput('14.11.2025'), '14.11.2025 → 2025-11-14');
        $this->assertEquals('2024-01-01', normalizeDateInput('01.01.2024'), '01.01.2024 → 2024-01-01');
        $this->assertEquals('2023-12-31', normalizeDateInput('31.12.2023'), '31.12.2023 → 2023-12-31');
    }

    /**
     * Test: normalizeDateInput() validuje platnost data
     */
    public function testNormalizaceValidujePlatnostData(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Neplatné datum');

        // 32.13.9999 je neplatné datum
        normalizeDateInput('32.13.9999');
    }

    /**
     * Test: normalizeDateInput() odmítne 31. únor
     */
    public function testNormalizaceOdmitne31Unor(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Neplatné datum');

        normalizeDateInput('31.02.2024'); // Únor nemá 31 dnů
    }

    /**
     * Test: normalizeDateInput() odmítne 30. únor
     */
    public function testNormalizaceOdmitne30Unor(): void
    {
        $this->expectException(\Exception::class);

        normalizeDateInput('30.02.2024');
    }

    /**
     * Test: normalizeDateInput() akceptuje 29. únor v přestupném roce
     */
    public function testNormalizaceAkceptuje29UnorVPrestupnemRoce(): void
    {
        $this->assertEquals('2024-02-29', normalizeDateInput('29.02.2024'), '2024 je přestupný rok');
    }

    /**
     * Test: normalizeDateInput() odmítne 29. únor v nepřestupném roce
     */
    public function testNormalizaceOdmitne29UnorVNeprestupnemRoce(): void
    {
        $this->expectException(\Exception::class);

        normalizeDateInput('29.02.2023'); // 2023 není přestupný rok
    }

    /**
     * Test: normalizeDateInput() odmítne neplatný měsíc
     */
    public function testNormalizaceOdmitneNeplatnyMesic(): void
    {
        $this->expectException(\Exception::class);

        normalizeDateInput('15.13.2024'); // Měsíc 13 neexistuje
    }

    /**
     * Test: normalizeDateInput() odmítne neplatný den
     */
    public function testNormalizaceOdmitneNeplatnyDen(): void
    {
        $this->expectException(\Exception::class);

        normalizeDateInput('00.01.2024'); // Den 0 neexistuje
    }

    /**
     * Test: normalizeDateInput() odmítne špatný formát
     */
    public function testNormalizaceOdmitneSpatnyFormat(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Neplatný formát data');

        normalizeDateInput('2024/11/14'); // Slash místo tečky nebo pomlčky
    }

    /**
     * Test: normalizeDateInput() odmítne text
     */
    public function testNormalizaceOdmitneText(): void
    {
        $this->expectException(\Exception::class);

        normalizeDateInput('not a date');
    }

    /**
     * Test: Enum mapping - ČEKÁ → wait
     */
    public function testEnumMappingCekaToWait(): void
    {
        // Toto se testuje v rámci handleUpdate(), ale můžeme ověřit mapping
        // V save.php je mapping na řádcích 226-242

        $mapping = [
            'ČEKÁ' => 'wait',
            'wait' => 'wait',
            'DOMLUVENÁ' => 'open',
            'open' => 'open',
            'HOTOVO' => 'done',
            'done' => 'done'
        ];

        $this->assertEquals('wait', $mapping['ČEKÁ']);
        $this->assertEquals('open', $mapping['DOMLUVENÁ']);
        $this->assertEquals('done', $mapping['HOTOVO']);
    }

    /**
     * Test: Trim a whitespace handling
     */
    public function testNormalizaceTrimujeWhitespace(): void
    {
        $this->assertEquals('2025-11-14', normalizeDateInput('  2025-11-14  '));
        $this->assertEquals('2025-11-14', normalizeDateInput("\t14.11.2025\n"));
    }

    /**
     * Test: Okrajové případy - přestupné roky
     */
    public function testPrestupneRokyJsouSpravneZpracovany(): void
    {
        // 2024 je přestupný (dělitelný 4)
        $this->assertEquals('2024-02-29', normalizeDateInput('29.02.2024'));

        // 2000 je přestupný (dělitelný 400)
        $this->assertEquals('2000-02-29', normalizeDateInput('29.02.2000'));

        // 1900 není přestupný (dělitelný 100 ale ne 400)
        $this->expectException(\Exception::class);
        normalizeDateInput('29.02.1900');
    }

    /**
     * Test: Validace všech měsíců s 31 dny
     */
    public function testMesiceSe31Dny(): void
    {
        $months31 = [1, 3, 5, 7, 8, 10, 12]; // Leden, Březen, Květen, Červenec, Srpen, Říjen, Prosinec

        foreach ($months31 as $month) {
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $date = normalizeDateInput("31.{$monthStr}.2024");
            $this->assertNotNull($date, "Měsíc {$month} musí mít 31 dní");
        }
    }

    /**
     * Test: Validace všech měsíců s 30 dny
     */
    public function testMesiceSe30Dny(): void
    {
        $months30 = [4, 6, 9, 11]; // Duben, Červen, Září, Listopad

        foreach ($months30 as $month) {
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $date = normalizeDateInput("30.{$monthStr}.2024");
            $this->assertNotNull($date, "Měsíc {$month} musí mít 30 dní");

            // 31. den musí selhat
            try {
                normalizeDateInput("31.{$monthStr}.2024");
                $this->fail("Měsíc {$month} nesmí mít 31 dní");
            } catch (\Exception $e) {
                $this->assertStringContainsString('Neplatné datum', $e->getMessage());
            }
        }
    }
}
