<?php
/**
 * Testy klasifikačního enginu souborů
 *
 * Testuje pravidla R01–R09 a výsledné statusy klasifikace.
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class SouboryKlasifikaceTest extends TestCase
{
    private string $korenAdresar;
    private bool $funkceNacteny = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Načíst funkce z API souboru (pouze jednou)
        if (!$this->funkceNacteny) {
            $this->nacistKlasifikacniFunkce();
            $this->funkceNacteny = true;
        }

        // Dočasný adresář jako kořen projektu pro testy
        $this->korenAdresar = sys_get_temp_dir() . '/wgs_test_' . getmypid();
        if (!is_dir($this->korenAdresar)) {
            mkdir($this->korenAdresar, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Vyčistit dočasný adresář
        if (is_dir($this->korenAdresar)) {
            $this->smazatAdresar($this->korenAdresar);
        }
    }

    /**
     * Načte klasifikační funkce z API souboru bez spuštění HTTP logiky
     */
    private function nacistKlasifikacniFunkce(): void
    {
        if (function_exists('klasifikovatSoubor')) {
            return;
        }

        $apiSoubor = WGS_ROOT . '/api/soubory_api.php';
        if (!file_exists($apiSoubor)) {
            $this->markTestSkipped('Soubor api/soubory_api.php neexistuje');
            return;
        }

        // Extrahovat pouze definice konstant a funkcí
        $obsah = file_get_contents($apiSoubor);

        // Najít blok KLASIFIKAČNÍ ENGINE a pomocné funkce
        $bloky = [];

        // Konstanty
        if (preg_match_all("/define\s*\(\s*'KL_[^;]+;/s", $obsah, $shody)) {
            $bloky = array_merge($bloky, $shody[0]);
        }

        // Funkce klasifikace
        $funkcePouzite = [
            'nactiRuntimeAudit',
            'zkontrolujKonfigReference',
            'jeArtefakt',
            'sestavVysledekKlasifikace',
            'klasifikovatSoubor',
        ];

        foreach ($funkcePouzite as $nazevFunkce) {
            if (function_exists($nazevFunkce)) {
                continue;
            }
            // Extrahovat celou funkci – najít function název( a pak { } balancování
            $pozice = strpos($obsah, 'function ' . $nazevFunkce . '(');
            if ($pozice === false) {
                continue;
            }
            // Najít první { po signatuře
            $zacatekTela = strpos($obsah, '{', $pozice);
            if ($zacatekTela === false) {
                continue;
            }
            // Balancovat závorky
            $hloubka = 0;
            $konec = $zacatekTela;
            for ($i = $zacatekTela; $i < strlen($obsah); $i++) {
                if ($obsah[$i] === '{') {
                    $hloubka++;
                } elseif ($obsah[$i] === '}') {
                    $hloubka--;
                    if ($hloubka === 0) {
                        $konec = $i;
                        break;
                    }
                }
            }
            $bloky[] = substr($obsah, $pozice, $konec - $pozice + 1);
        }

        if (!empty($bloky)) {
            $kod = implode("\n\n", $bloky);
            eval($kod);
        }
    }

    /**
     * Rekurzivní smazání adresáře
     */
    private function smazatAdresar(string $cesta): void
    {
        if (!is_dir($cesta)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cesta, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $polozka) {
            if ($polozka->isDir()) {
                rmdir($polozka->getPathname());
            } else {
                unlink($polozka->getPathname());
            }
        }
        rmdir($cesta);
    }

    /**
     * Vytvoří testovací soubor
     */
    private function vytvorSoubor(string $relativniCesta, string $obsah = '', int $stariDni = 60): string
    {
        $absolutniCesta = $this->korenAdresar . '/' . $relativniCesta;
        $adresar = dirname($absolutniCesta);
        if (!is_dir($adresar)) {
            mkdir($adresar, 0755, true);
        }
        file_put_contents($absolutniCesta, $obsah);

        // Nastavit mtime na minulost
        $cas = time() - ($stariDni * 86400);
        touch($absolutniCesta, $cas);

        return $absolutniCesta;
    }

    // ================================================================
    // TESTY: Kontrola existence funkcí
    // ================================================================

    public function testKlasifikacniFunkceExistuji(): void
    {
        $this->assertTrue(function_exists('klasifikovatSoubor'), 'Funkce klasifikovatSoubor neexistuje');
        $this->assertTrue(function_exists('jeArtefakt'), 'Funkce jeArtefakt neexistuje');
        $this->assertTrue(function_exists('sestavVysledekKlasifikace'), 'Funkce sestavVysledekKlasifikace neexistuje');
        $this->assertTrue(function_exists('nactiRuntimeAudit'), 'Funkce nactiRuntimeAudit neexistuje');
    }

    public function testKonstantyDefinovany(): void
    {
        $this->assertTrue(defined('KL_POUZIVANO'), 'Konstanta KL_POUZIVANO');
        $this->assertTrue(defined('KL_BEZ_REFERENCI'), 'Konstanta KL_BEZ_REFERENCI');
        $this->assertTrue(defined('KL_NEJISTE'), 'Konstanta KL_NEJISTE');
        $this->assertTrue(defined('KL_BEZPECNE_SMAZAT'), 'Konstanta KL_BEZPECNE_SMAZAT');
        $this->assertSame('USED', KL_POUZIVANO);
        $this->assertSame('NO_REFS_STATIC', KL_BEZ_REFERENCI);
        $this->assertSame('UNCERTAIN', KL_NEJISTE);
        $this->assertSame('SAFE_TO_DELETE', KL_BEZPECNE_SMAZAT);
    }

    // ================================================================
    // TESTY: R01 – Statické reference
    // ================================================================

    public function testR01SouborSReferencemiJeUsed(): void
    {
        $vysledek = klasifikovatSoubor(
            'test.php', 'test.php', 'php', time() - 86400 * 60,
            3, ['index.php', 'admin.php', 'app/controllers/save.php'],
            $this->korenAdresar, []
        );

        $this->assertSame(KL_POUZIVANO, $vysledek['status']);
        $this->assertNotEmpty($vysledek['reasons']);
        $this->assertSame('R01', $vysledek['reasons'][0]['rule_id']);
        $this->assertFalse($vysledek['reasons'][0]['passed']);
    }

    // ================================================================
    // TESTY: R02 – Kritický soubor
    // ================================================================

    public function testR02KritickySouborJeUsed(): void
    {
        $kriticke = ['robots.txt', 'sitemap.xml', 'init.php', 'index.php', '.htaccess'];

        foreach ($kriticke as $nazev) {
            $vysledek = klasifikovatSoubor(
                $nazev, $nazev, 'php', time() - 86400 * 60,
                0, [], $this->korenAdresar, []
            );
            $this->assertSame(
                KL_POUZIVANO,
                $vysledek['status'],
                "Kritický soubor '$nazev' by měl být USED"
            );
        }
    }

    // ================================================================
    // TESTY: R04 – Chráněný adresář
    // ================================================================

    public function testR04SouborVChranemAdresariJeUncertain(): void
    {
        $adresare = ['includes', 'config', 'app', 'api'];

        foreach ($adresare as $adr) {
            $vysledek = klasifikovatSoubor(
                $adr . '/helper.php', 'helper.php', 'php', time() - 86400 * 60,
                0, [], $this->korenAdresar, []
            );
            $this->assertSame(
                KL_NEJISTE,
                $vysledek['status'],
                "Soubor v chráněném adresáři '$adr' by měl být UNCERTAIN"
            );
        }
    }

    // ================================================================
    // TESTY: R05 – Root PHP entrypoint
    // ================================================================

    public function testR05RootPhpJeUncertain(): void
    {
        $vysledek = klasifikovatSoubor(
            'stranka.php', 'stranka.php', 'php', time() - 86400 * 60,
            0, [], $this->korenAdresar, []
        );

        $this->assertSame(KL_NEJISTE, $vysledek['status']);
    }

    // ================================================================
    // TESTY: R06 – Veřejný asset
    // ================================================================

    public function testR06VeřejnýAssetJeUncertain(): void
    {
        $vysledek = klasifikovatSoubor(
            'assets/js/utils.js', 'utils.js', 'js', time() - 86400 * 60,
            0, [], $this->korenAdresar, []
        );

        $this->assertSame(KL_NEJISTE, $vysledek['status']);
    }

    // ================================================================
    // TESTY: R07 – Artefakt
    // ================================================================

    public function testR07ArtefaktDetekce(): void
    {
        $artefakty = ['soubor.bak', 'test.old', 'data.tmp', 'file.backup', 'script~'];

        foreach ($artefakty as $nazev) {
            $this->assertTrue(
                jeArtefakt($nazev),
                "'$nazev' by měl být rozpoznán jako artefakt"
            );
        }

        $neArtefakty = ['utils.js', 'save.php', 'styles.css', 'data.json'];
        foreach ($neArtefakty as $nazev) {
            $this->assertFalse(
                jeArtefakt($nazev),
                "'$nazev' by NEMĚL být rozpoznán jako artefakt"
            );
        }
    }

    // ================================================================
    // TESTY: R08 – Runtime audit
    // ================================================================

    public function testR08BezRuntimeVraciBezReferenci(): void
    {
        // Soubor mimo chráněné adresáře/root PHP/assety, bez referencí, bez runtime
        $vysledek = klasifikovatSoubor(
            'temp_skript.txt', 'temp_skript.txt', 'text', time() - 86400 * 60,
            0, [], $this->korenAdresar, []  // prázdná runtime data = audit neaktivní
        );

        $this->assertSame(KL_BEZ_REFERENCI, $vysledek['status']);
    }

    public function testR08RuntimeSHityVraciUsed(): void
    {
        $runtimeData = [
            ['ts' => time() - 3600, 'cesta' => 'temp_skript.txt', 'status' => 200],
        ];

        $vysledek = klasifikovatSoubor(
            'temp_skript.txt', 'temp_skript.txt', 'text', time() - 86400 * 60,
            0, [], $this->korenAdresar, $runtimeData
        );

        $this->assertSame(KL_POUZIVANO, $vysledek['status']);
    }

    // ================================================================
    // TESTY: R09 – Stáří souboru
    // ================================================================

    public function testR09PrilisNovySouborNeniSafeToDelete(): void
    {
        // Soubor mladší 30 dní, s runtime auditem, bez hitů
        $runtimeData = [
            ['ts' => time() - 3600, 'cesta' => 'jiny_soubor.txt', 'status' => 200],
        ];

        $vysledek = klasifikovatSoubor(
            'novy_soubor.txt', 'novy_soubor.txt', 'text',
            time() - 86400 * 5,  // 5 dní starý
            0, [], $this->korenAdresar, $runtimeData
        );

        // Příliš nový, ale runtime aktivní a bez hitů → UNCERTAIN (ne SAFE_TO_DELETE)
        $this->assertNotSame(KL_BEZPECNE_SMAZAT, $vysledek['status']);
    }

    // ================================================================
    // TESTY: Finální rozhodnutí – SAFE_TO_DELETE
    // ================================================================

    public function testSafeToDeletePouzeSeVsemiPodminkami(): void
    {
        // Soubor: ne-PHP, ne v chráněném adresáři, ne asset, starý, runtime aktivní bez hitů
        $runtimeData = [
            ['ts' => time() - 3600, 'cesta' => 'jiny_soubor.txt', 'status' => 200],
        ];

        $vysledek = klasifikovatSoubor(
            'stary_nepotrebny.txt', 'stary_nepotrebny.txt', 'text',
            time() - 86400 * 60,  // 60 dní starý
            0, [], $this->korenAdresar, $runtimeData
        );

        $this->assertSame(KL_BEZPECNE_SMAZAT, $vysledek['status']);
    }

    public function testArtefaktSRuntimeJeSafeToDelete(): void
    {
        // Artefakt (.bak) s runtime auditem, starý, bez hitů
        $runtimeData = [
            ['ts' => time() - 3600, 'cesta' => 'jiny.txt', 'status' => 200],
        ];

        $vysledek = klasifikovatSoubor(
            'zalozni.bak', 'zalozni.bak', 'ostatni',
            time() - 86400 * 5,  // 5 dní starý – ale artefakt, tak stáří prominuto
            0, [], $this->korenAdresar, $runtimeData
        );

        $this->assertSame(KL_BEZPECNE_SMAZAT, $vysledek['status']);
    }

    // ================================================================
    // TESTY: Struktura výsledku
    // ================================================================

    public function testVysledekMaSprávnouStrukturu(): void
    {
        $vysledek = klasifikovatSoubor(
            'test.txt', 'test.txt', 'text', time() - 86400 * 60,
            0, [], $this->korenAdresar, []
        );

        $this->assertArrayHasKey('status', $vysledek);
        $this->assertArrayHasKey('reasons', $vysledek);
        $this->assertArrayHasKey('evidence', $vysledek);

        // Evidence
        $evidence = $vysledek['evidence'];
        $this->assertArrayHasKey('staticke_reference_pocet', $evidence);
        $this->assertArrayHasKey('staticke_reference', $evidence);
        $this->assertArrayHasKey('runtime_dostupny', $evidence);
        $this->assertArrayHasKey('runtime_hity', $evidence);
        $this->assertArrayHasKey('runtime_okno_dni', $evidence);
        $this->assertArrayHasKey('stari_dni', $evidence);

        // Reasons
        $this->assertIsArray($vysledek['reasons']);
        foreach ($vysledek['reasons'] as $pravidlo) {
            $this->assertArrayHasKey('rule_id', $pravidlo);
            $this->assertArrayHasKey('nazev', $pravidlo);
            $this->assertArrayHasKey('passed', $pravidlo);
            $this->assertArrayHasKey('details', $pravidlo);
            $this->assertArrayHasKey('zdroj', $pravidlo);
        }
    }

    public function testVysledekMaPlatnyStatus(): void
    {
        $platneStatusy = [KL_POUZIVANO, KL_BEZ_REFERENCI, KL_NEJISTE, KL_BEZPECNE_SMAZAT];

        $vysledek = klasifikovatSoubor(
            'test.txt', 'test.txt', 'text', time() - 86400 * 60,
            0, [], $this->korenAdresar, []
        );

        $this->assertContains($vysledek['status'], $platneStatusy);
    }

    // ================================================================
    // TESTY: nactiRuntimeAudit
    // ================================================================

    public function testNactiRuntimeAuditPrazdnyBezSouboru(): void
    {
        $vysledek = nactiRuntimeAudit($this->korenAdresar);
        $this->assertIsArray($vysledek);
        $this->assertEmpty($vysledek);
    }

    public function testNactiRuntimeAuditCteSoubor(): void
    {
        // Vytvořit JSONL soubor s testovacími daty
        $logDir = $this->korenAdresar . '/logs';
        mkdir($logDir, 0755, true);

        $zaznamy = [
            json_encode(['ts' => time() - 3600, 'cesta' => '/test.php', 'status' => 200]),
            json_encode(['ts' => time() - 7200, 'cesta' => '/api/data.php', 'status' => 200]),
            json_encode(['ts' => time() - 86400 * 30, 'cesta' => '/stary.php', 'status' => 200]),  // starý – za hranicí
        ];

        file_put_contents($logDir . '/runtime_audit.jsonl', implode("\n", $zaznamy) . "\n");

        $vysledek = nactiRuntimeAudit($this->korenAdresar);

        // Měly by být pouze 2 záznamy (třetí je za hranicí 14 dní)
        $this->assertCount(2, $vysledek);
        $this->assertSame('test.php', $vysledek[0]['cesta']);
        $this->assertSame(200, $vysledek[0]['status']);
    }

    // ================================================================
    // TESTY: jeArtefakt
    // ================================================================

    public function testJeArtefaktRuzneVzory(): void
    {
        $this->assertTrue(jeArtefakt('backup.bak'));
        $this->assertTrue(jeArtefakt('config.old'));
        $this->assertTrue(jeArtefakt('temp.tmp'));
        $this->assertTrue(jeArtefakt('data.backup'));
        $this->assertTrue(jeArtefakt('file.orig'));
        $this->assertTrue(jeArtefakt('script~'));
        $this->assertTrue(jeArtefakt('config.bak_20240101'));
        $this->assertTrue(jeArtefakt('styles_old.css'));

        $this->assertFalse(jeArtefakt('index.php'));
        $this->assertFalse(jeArtefakt('styles.css'));
        $this->assertFalse(jeArtefakt('app.js'));
        $this->assertFalse(jeArtefakt('readme.md'));
    }

    // ================================================================
    // TESTY: Kombinované scénáře
    // ================================================================

    public function testSouborSReferencemiIVChranemAdresari(): void
    {
        // R01 má prioritu – pokud má reference, je USED bez ohledu na adresář
        $vysledek = klasifikovatSoubor(
            'includes/helper.php', 'helper.php', 'php', time() - 86400 * 60,
            2, ['index.php', 'admin.php'],
            $this->korenAdresar, []
        );

        $this->assertSame(KL_POUZIVANO, $vysledek['status']);
    }

    public function testKritickySouborBezReferenci(): void
    {
        // R02 – i bez statických referencí je USED protože je kritický
        $vysledek = klasifikovatSoubor(
            'robots.txt', 'robots.txt', 'text', time() - 86400 * 60,
            0, [], $this->korenAdresar, []
        );

        $this->assertSame(KL_POUZIVANO, $vysledek['status']);
    }
}
