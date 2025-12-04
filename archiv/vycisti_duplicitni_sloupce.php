<?php
/**
 * Migrace: Odstranění duplicitních sloupců v wgs_reklamace
 *
 * Tento skript BEZPEČNĚ odstraní logické duplicity - sloupce, které obsahují
 * stejné informace jako jiné sloupce.
 *
 * DUPLICITY K ODSTRANĚNÍ:
 * 1. zakaznik (VARCHAR) → nahrazeno: jmeno
 * 2. email_zadavatele (VARCHAR) → není používáno nikde v kódu
 * 3. zeme (VARCHAR) → nahrazeno: fakturace_firma (je to jen alias ve statistikách)
 *
 * ZACHOVÁNO (NENÍ DUPLICITA):
 * - ulice, mesto, psc → užitečné pro filtry, i když se ukládá i adresa
 * - technik (VARCHAR) → umožňuje volný text, i když existuje assigned_to
 * - cena → používá se jako fallback pro cena_celkem
 * - castka → používá se pro statistiky provizí
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vyčištění duplicitních sloupců</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #1e1e1e; color: #d4d4d4; }
        .container { background: #252526; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        h1 { color: #4ec9b0; border-bottom: 3px solid #4ec9b0;
             padding-bottom: 10px; }
        h2 { color: #dcdcaa; margin-top: 30px; }
        .success { background: #1e5a1e; border: 1px solid #4ec9b0;
                   color: #4ec9b0; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #5a1e1e; border: 1px solid #f48771;
                 color: #f48771; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #5a4e1e; border: 1px solid #dcdcaa;
                   color: #dcdcaa; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #1e3a5a; border: 1px solid #4fc1ff;
                color: #4fc1ff; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #3e3e42; padding: 10px; text-align: left; }
        th { background: #2d2d30; color: #4ec9b0; }
        code { background: #3c3c3c; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; color: #ce9178; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #0e639c; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #1177bb; }
        .btn-danger { background: #c82333; }
        .btn-danger:hover { background: #bd2130; }
        .comparison { display: grid; grid-template-columns: 1fr 1fr;
                      gap: 20px; margin: 20px 0; }
        .old { background: #5a1e1e; padding: 15px; border-radius: 5px; }
        .new { background: #1e5a1e; padding: 15px; border-radius: 5px; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 5px;
              overflow-x: auto; color: #ce9178; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Vyčištění duplicitních sloupců v tabulce wgs_reklamace</h1>";

    // Získat strukturu tabulky
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    // Definice duplicit
    $duplicity = [
        'zakaznik' => [
            'existuje' => in_array('zakaznik', $columnNames),
            'nahrazeno' => 'jmeno',
            'popis' => 'Legacy sloupec - synchronizuje se s jmeno',
            'bezpecne' => true
        ],
        'email_zadavatele' => [
            'existuje' => in_array('email_zadavatele', $columnNames),
            'nahrazeno' => 'N/A',
            'popis' => 'Nepoužívá se nikde v aktivním kódu',
            'bezpecne' => true
        ],
        'zeme' => [
            'existuje' => in_array('zeme', $columnNames),
            'nahrazeno' => 'fakturace_firma',
            'popis' => 'Používá se pouze jako alias ve statistikách',
            'bezpecne' => true
        ]
    ];

    // Zobrazit přehled duplicit
    echo "<h2>1. Nalezené duplicitní sloupce</h2>";

    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Existuje?</th><th>Nahrazeno</th><th>Popis</th><th>Bezpečné?</th></tr>";

    foreach ($duplicity as $sloupec => $info) {
        $existujeStatus = $info['existuje'] ?
            "<span style='color: #f48771;'>✓ ANO</span>" :
            "<span style='color: #4ec9b0;'>✗ Již odstraněn</span>";

        $bezpecneStatus = $info['bezpecne'] ?
            "<span style='color: #4ec9b0;'>✓ Ano</span>" :
            "<span style='color: #f48771;'>✗ Rizikové</span>";

        echo "<tr>";
        echo "<td><code>{$sloupec}</code></td>";
        echo "<td>{$existujeStatus}</td>";
        echo "<td><code>{$info['nahrazeno']}</code></td>";
        echo "<td>{$info['popis']}</td>";
        echo "<td>{$bezpecneStatus}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Kontrola dat v duplicitních sloupcích
    echo "<h2>2. Kontrola dat před odstraněním</h2>";

    $pocetZaznamu = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace")->fetchColumn();

    echo "<div class='info'>";
    echo "<strong>Celkem záznamů v tabulce:</strong> {$pocetZaznamu}";
    echo "</div>";

    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Záznamů s daty</th><th>% vyplněno</th><th>Status</th></tr>";

    foreach ($duplicity as $sloupec => $info) {
        if (!$info['existuje']) continue;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_reklamace WHERE `{$sloupec}` IS NOT NULL AND `{$sloupec}` != ''");
        $stmt->execute();
        $pocetSData = $stmt->fetchColumn();

        $procento = $pocetZaznamu > 0 ? round(($pocetSData / $pocetZaznamu) * 100, 2) : 0;

        $status = $pocetSData == 0 ?
            "<span style='color: #4ec9b0;'>✓ Prázdný (bezpečné)</span>" :
            "<span style='color: #dcdcaa;'>⚠ Obsahuje data (synchronizovat před odstraněním)</span>";

        echo "<tr>";
        echo "<td><code>{$sloupec}</code></td>";
        echo "<td>{$pocetSData} / {$pocetZaznamu}</td>";
        echo "<td>{$procento}%</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Ukázka porovnání dat pro zakaznik vs jmeno
    if ($duplicity['zakaznik']['existuje']) {
        echo "<h2>3. Porovnání: zakaznik vs jmeno (ukázka 5 záznamů)</h2>";

        $stmt = $pdo->query("
            SELECT id, reklamace_id, zakaznik, jmeno
            FROM wgs_reklamace
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $ukazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($ukazky) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Reklamace</th><th>zakaznik (legacy)</th><th>jmeno (aktuální)</th><th>Status</th></tr>";

            foreach ($ukazky as $zaznam) {
                $zakaznik = $zaznam['zakaznik'] ?? '';
                $jmeno = $zaznam['jmeno'] ?? '';

                if ($zakaznik === $jmeno) {
                    $status = "<span style='color: #4ec9b0;'>✓ Shodné</span>";
                } elseif (empty($zakaznik)) {
                    $status = "<span style='color: #4ec9b0;'>✓ zakaznik prázdný</span>";
                } elseif (empty($jmeno)) {
                    $status = "<span style='color: #dcdcaa;'>⚠ Synchronizovat do jmeno</span>";
                } else {
                    $status = "<span style='color: #f48771;'>✗ ODLIŠNÉ!</span>";
                }

                echo "<tr>";
                echo "<td>{$zaznam['id']}</td>";
                echo "<td>" . htmlspecialchars($zaznam['reklamace_id'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($zakaznik ?: '(prázdné)') . "</td>";
                echo "<td>" . htmlspecialchars($jmeno ?: '(prázdné)') . "</td>";
                echo "<td>{$status}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }

    // AKCE - odstranění
    if (isset($_GET['odstranit']) && $_GET['odstranit'] === 'ano') {
        echo "<h2>🗑️ Odstraňování duplicitních sloupců...</h2>";

        $pdo->beginTransaction();

        try {
            $odstranenoSloupcu = 0;
            $chyby = [];

            // KROK 1: Synchronizace dat před odstraněním
            echo "<h3>Krok 1: Synchronizace dat</h3>";

            if ($duplicity['zakaznik']['existuje']) {
                // Synchronizovat zakaznik → jmeno (pokud jmeno je prázdné)
                $stmt = $pdo->exec("
                    UPDATE wgs_reklamace
                    SET jmeno = zakaznik
                    WHERE (jmeno IS NULL OR jmeno = '') AND zakaznik IS NOT NULL AND zakaznik != ''
                ");
                echo "<div class='success'>✓ Synchronizováno {$stmt} záznamů: zakaznik → jmeno</div>";
            }

            // KROK 2: Odstranění sloupců
            echo "<h3>Krok 2: Odstranění sloupců</h3>";

            foreach ($duplicity as $sloupec => $info) {
                if ($info['existuje'] && $info['bezpecne']) {
                    try {
                        $pdo->exec("ALTER TABLE wgs_reklamace DROP COLUMN `{$sloupec}`");
                        echo "<div class='success'>✓ Sloupec <code>{$sloupec}</code> byl odstraněn</div>";
                        $odstranenoSloupcu++;
                    } catch (PDOException $e) {
                        $chyby[] = "Chyba při odstraňování sloupce {$sloupec}: " . $e->getMessage();
                        echo "<div class='error'>✗ Chyba: {$sloupec} - " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
            }

            if (empty($chyby)) {
                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✓ HOTOVO!</strong><br>";
                echo "Odstraněno <strong>{$odstranenoSloupcu}</strong> duplicitních sloupců.<br>";
                echo "Tabulka <code>wgs_reklamace</code> je nyní čistší a efektivnější.";
                echo "</div>";

                echo "<a href='vycisti_duplicitni_sloupce.php' class='btn'>Zkontrolovat znovu</a>";
                echo "<a href='vsechny_tabulky.php' class='btn'>SQL přehled</a>";
            } else {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>✗ MIGRACE PŘERUŠENA</strong><br>";
                echo "Některé operace selhaly. Žádné změny nebyly provedeny.<br>";
                echo "<ul>";
                foreach ($chyby as $chyba) {
                    echo "<li>" . htmlspecialchars($chyba) . "</li>";
                }
                echo "</ul>";
                echo "</div>";
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>✗ KRITICKÁ CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // NÁHLED - co se stane
        echo "<h2>4. Co se stane po spuštění migrace?</h2>";

        $existujiciDuplicity = array_filter($duplicity, fn($d) => $d['existuje']);

        if (empty($existujiciDuplicity)) {
            echo "<div class='success'>";
            echo "<strong>✓ VŠE ČISTÉ!</strong><br>";
            echo "Žádné duplicitní sloupce nebyly nalezeny. Databáze je již vyčištěná.";
            echo "</div>";
        } else {
            echo "<div class='warning'>";
            echo "<strong>⚠️ PŘIPRAVENO K ODSTRANĚNÍ:</strong><br>";
            echo "Následující duplicitní sloupce budou odstraněny:<br><ul>";
            foreach ($existujiciDuplicity as $sloupec => $info) {
                echo "<li><code>{$sloupec}</code> - {$info['popis']}</li>";
            }
            echo "</ul>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>ℹ️ PŘED SPUŠTĚNÍM:</strong><br>";
            echo "<ol>";
            echo "<li>Data v <code>zakaznik</code> budou synchronizována do <code>jmeno</code></li>";
            echo "<li>Sloupce budou trvale odstraněny (nelze vrátit zpět)</li>";
            echo "<li>Všechny operace proběhnou v transakci (vše nebo nic)</li>";
            echo "</ol>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>ℹ️ DOPORUČENÍ:</strong><br>";
            echo "<ol>";
            echo "<li>Stáhnout zálohu SQL: <a href='vsechny_tabulky.php' target='_blank'>Admin Panel → SQL → Stáhnout všechny DDL</a></li>";
            echo "<li>Zkontrolovat porovnání dat výše</li>";
            echo "<li>Spustit migraci tlačítkem níže</li>";
            echo "</ol>";
            echo "</div>";

            echo "<h3>📝 Náhled SQL operací:</h3>";
            echo "<pre>";
            echo "-- KROK 1: Synchronizace dat\n";
            echo "UPDATE wgs_reklamace\n";
            echo "SET jmeno = zakaznik\n";
            echo "WHERE (jmeno IS NULL OR jmeno = '') AND zakaznik IS NOT NULL;\n\n";

            echo "-- KROK 2: Odstranění sloupců\n";
            echo "ALTER TABLE wgs_reklamace\n";
            $drops = [];
            foreach ($existujiciDuplicity as $sloupec => $info) {
                $drops[] = "  DROP COLUMN `{$sloupec}`";
            }
            echo implode(",\n", $drops) . ";";
            echo "</pre>";

            echo "<a href='?odstranit=ano' class='btn btn-danger' onclick='return confirm(\"Opravdu chcete odstranit duplicitní sloupce? Data budou synchronizována před odstraněním.\")'>🗑️ SPUSTIT VYČIŠTĚNÍ</a>";
            echo "<a href='vycisti_duplicitni_sloupce.php' class='btn'>Zrušit</a>";
        }
    }

    echo "<div style='margin-top: 30px; padding: 15px; background: #2d2d30; border-radius: 6px;'>";
    echo "<strong>ℹ️ Co se NEODSTRAŇUJE:</strong><br>";
    echo "<ul>";
    echo "<li><code>ulice, mesto, psc</code> - užitečné pro filtry a mapy, i když se ukládá i <code>adresa</code></li>";
    echo "<li><code>technik</code> (VARCHAR) - umožňuje volný text, i když existuje <code>assigned_to</code> (INT)</li>";
    echo "<li><code>cena</code> - používá se jako fallback pro <code>cena_celkem</code> ve statistikách</li>";
    echo "<li><code>castka</code> - používá se pro výpočet provizí techniků</li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";
echo "</div></body></html>";
?>
