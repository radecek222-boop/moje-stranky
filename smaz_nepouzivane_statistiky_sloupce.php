<?php
/**
 * Migrace: Odstranění nepoužívaných sloupců pro statistiky
 *
 * Tento skript BEZPEČNĚ odstraní sloupce, které byly přidány
 * migrací add_statistics_columns.php ale NIKDE se nepoužívají:
 *
 * - zeme: Kopie fakturace_firma (statistiky používají fakturace_firma přímo)
 * - castka: Kopie cena (statistiky používají COALESCE(cena_celkem, cena, 0))
 *
 * PONECHANÉ sloupce:
 * - technik: Používá se v protokol.php, cron skriptech
 * - mesto: Formulářové pole z novareklamace
 * - cena: Fallback v COALESCE pro starší záznamy
 * - prodejce: Fallback v protokol_api.php
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Odstraneni nepouzivanch statistiky sloupcu</title>
    <style>
        body { font-family: 'Poppins', sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #222; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        h1 { color: #39ff14; border-bottom: 3px solid #39ff14; padding-bottom: 10px; }
        h2 { color: #ccc; margin-top: 1.5rem; }
        .success { background: #1a3a1a; border: 1px solid #39ff14; color: #39ff14; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3a1a1a; border: 1px solid #ff4444; color: #ff4444; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3a3a1a; border: 1px solid #ff8800; color: #ff8800; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2a3a; border: 1px solid #4488ff; color: #88bbff; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #444; }
        th { background: #333; color: #39ff14; text-transform: uppercase; font-size: 0.85rem; }
        .btn { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #c82333; }
        .btn-secondary { background: #666; }
        .btn-secondary:hover { background: #555; }
        .status-ok { color: #39ff14; }
        .status-skip { color: #888; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #111; padding: 1rem; border-radius: 5px; overflow-x: auto; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Odstraneni nepouzivanch sloupcu</h1>";

    // Sloupce k odstranění - POUZE ty, které se skutečně nepoužívají!
    $sloupceKOdstraneni = [
        'zeme' => [
            'popis' => 'Kopie fakturace_firma (statistiky pouzivaji fakturace_firma primo)',
            'nahrada' => 'fakturace_firma'
        ],
        'castka' => [
            'popis' => 'Kopie cena (statistiky pouzivaji COALESCE(cena_celkem, cena, 0))',
            'nahrada' => 'cena + cena_celkem'
        ]
    ];

    // Zjistit které sloupce existují
    $existujiciSloupce = [];
    foreach ($sloupceKOdstraneni as $sloupec => $info) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE '$sloupec'");
        if ($checkCol->fetch()) {
            $existujiciSloupce[$sloupec] = $info;
        }
    }

    echo "<div class='info'>";
    echo "<strong>Analyza:</strong><br>";
    echo "Kontrolovano sloupcu: " . count($sloupceKOdstraneni) . "<br>";
    echo "Existujicich v databazi: " . count($existujiciSloupce);
    echo "</div>";

    // Tabulka sloupců
    echo "<h2>Sloupce k odstraneni</h2>";
    echo "<table>
        <tr><th>Sloupec</th><th>Popis</th><th>Nahrada</th><th>Stav</th><th>Zaznamu s daty</th></tr>";

    foreach ($sloupceKOdstraneni as $sloupec => $info) {
        $existuje = isset($existujiciSloupce[$sloupec]);
        $statusClass = $existuje ? 'status-ok' : 'status-skip';
        $statusText = $existuje ? 'EXISTUJE' : 'NEEXISTUJE';

        $pocetSData = 0;
        if ($existuje) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE $sloupec IS NOT NULL AND $sloupec != '' AND $sloupec != '0' AND $sloupec != '0.00'");
            $pocetSData = $stmt->fetchColumn();
        }

        echo "<tr>
            <td><code>$sloupec</code></td>
            <td>{$info['popis']}</td>
            <td><code>{$info['nahrada']}</code></td>
            <td class='$statusClass'>$statusText</td>
            <td>" . ($existuje ? $pocetSData : '-') . "</td>
        </tr>";
    }
    echo "</table>";

    if (empty($existujiciSloupce)) {
        echo "<div class='success'><strong>Vsechny sloupce jiz byly odstraneny.</strong><br>Databaze je cista.</div>";
        echo "<a href='/admin.php' class='btn btn-secondary'>Zpet do admin</a>";
    } else {
        // Kontrola dat před odstraněním
        echo "<h2>Kontrola dat</h2>";

        $bezpecneOdstranit = true;
        foreach ($existujiciSloupce as $sloupec => $info) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE $sloupec IS NOT NULL AND $sloupec != '' AND $sloupec != '0' AND $sloupec != '0.00'");
            $pocet = $stmt->fetchColumn();

            if ($pocet > 0) {
                // Pro tyto sloupce je OK že mají data - jsou redundantní
                echo "<div class='info'><strong>$sloupec:</strong> $pocet zaznamu s daty - OK, data jsou v <code>{$info['nahrada']}</code></div>";
            } else {
                echo "<div class='success'><strong>$sloupec:</strong> Prazdny - bezpecne k odstraneni</div>";
            }
        }

        // Pokud execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<h2>Probiha migrace...</h2>";

            $uspesne = 0;
            $chyby = 0;

            foreach ($existujiciSloupce as $sloupec => $info) {
                try {
                    // Kontrola dat
                    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE $sloupec IS NOT NULL AND $sloupec != '' AND $sloupec != '0' AND $sloupec != '0.00'");
                    $pocet = $stmt->fetchColumn();

                    if ($pocet > 0) {
                        echo "<div class='info'><strong>$sloupec:</strong> $pocet zaznamu - data zustanou v {$info['nahrada']}</div>";
                    }

                    // Odstranit sloupec
                    $pdo->exec("ALTER TABLE wgs_reklamace DROP COLUMN `$sloupec`");
                    echo "<div class='success'><strong>$sloupec:</strong> ODSTRANEN</div>";
                    $uspesne++;

                } catch (PDOException $e) {
                    echo "<div class='error'><strong>$sloupec:</strong> CHYBA - " . htmlspecialchars($e->getMessage()) . "</div>";
                    $chyby++;
                }
            }

            // Odstranit indexy pokud existují
            $indexy = ['idx_zeme', 'idx_castka'];
            foreach ($indexy as $index) {
                try {
                    $checkIndex = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = '$index'");
                    if ($checkIndex->fetch()) {
                        $pdo->exec("ALTER TABLE wgs_reklamace DROP INDEX `$index`");
                        echo "<div class='info'>Index <code>$index</code> odstranen</div>";
                    }
                } catch (PDOException $e) {
                    // Index neexistuje - OK
                }
            }

            echo "<div class='" . ($chyby === 0 ? 'success' : 'warning') . "'>";
            echo "<strong>MIGRACE DOKONCENA</strong><br>";
            echo "Uspesne odstraneno: $uspesne sloupcu<br>";
            if ($chyby > 0) {
                echo "Chyby: $chyby";
            }
            echo "</div>";

            // Zobrazit aktuální strukturu
            echo "<h2>Aktualni struktura wgs_reklamace</h2>";
            $columns = $pdo->query("SHOW COLUMNS FROM wgs_reklamace")->fetchAll(PDO::FETCH_ASSOC);
            echo "<div class='info'>Celkem sloupcu: <strong>" . count($columns) . "</strong></div>";

            echo "<a href='/admin.php' class='btn btn-secondary'>Zpet do admin</a>";
            echo " <a href='/audit_databaze.php' class='btn btn-secondary'>Audit databaze</a>";

        } else {
            // Zobrazit vysvětlení
            echo "<h2>Proc jsou tyto sloupce nepotrebne?</h2>";
            echo "<div class='info'>";
            echo "<strong>statistiky_api.php pouziva:</strong>";
            echo "<pre>";
            echo "// Pro zemi:\n";
            echo "UPPER(COALESCE(r.fakturace_firma, 'cz')) as zeme\n\n";
            echo "// Pro castku:\n";
            echo "COALESCE(r.cena_celkem, r.cena, 0) as castka_celkem\n";
            echo "</pre>";
            echo "Sloupce <code>zeme</code> a <code>castka</code> jsou pouze kopie existujicich dat.";
            echo "</div>";

            // Zobrazit tlačítko pro spuštění
            echo "<div class='warning'>";
            echo "<strong>POZOR:</strong> Tato akce je nevratna!<br>";
            echo "Budou odstraneny sloupce: <code>" . implode('</code>, <code>', array_keys($existujiciSloupce)) . "</code>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn' onclick=\"return confirm('Opravdu odstranit " . count($existujiciSloupce) . " nepouzivanch sloupcu?');\">Odstranit " . count($existujiciSloupce) . " sloupcu</a>";
            echo "<a href='/admin.php' class='btn btn-secondary'>Zrusit</a>";
            echo "<a href='/kontrola_legacy_sloupcu.php' class='btn btn-secondary'>Kontrola legacy</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
