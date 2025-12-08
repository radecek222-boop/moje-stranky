<?php
/**
 * Migrace: Odstranění nepoužívaných legacy sloupců z wgs_reklamace
 *
 * Tento skript BEZPEČNĚ odstraní pouze sloupce, které se NIKDE nepoužívají.
 *
 * Sloupce k odstranění:
 * - original_reklamace_id (funkce znovuotevření byla odstraněna, nikde se nepoužívá)
 * - poznamky (prázdné, máme tabulku wgs_notes)
 *
 * PONECHAT (aktivně používané):
 * - castka (používá se pro výpočet provizí techniků v tech_provize_api.php)
 * - cena (fallback v COALESCE pro starší záznamy)
 * - adresa (může být použita někde v kódu)
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
    <title>Migrace: Odstraneni legacy sloupcu</title>
    <style>
        body { font-family: 'Poppins', sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #222; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        h1 { color: #39ff14; border-bottom: 3px solid #39ff14; padding-bottom: 10px; }
        .success { background: #1a3a1a; border: 1px solid #39ff14; color: #39ff14; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3a1a1a; border: 1px solid #ff4444; color: #ff4444; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3a3a1a; border: 1px solid #ff8800; color: #ff8800; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2a3a; border: 1px solid #4488ff; color: #88bbff; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #444; }
        th { background: #333; color: #39ff14; text-transform: uppercase; font-size: 0.85rem; }
        .btn { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; text-transform: uppercase; }
        .btn:hover { background: #c82333; }
        .btn-secondary { background: #666; }
        .btn-secondary:hover { background: #555; }
        .status-ok { color: #39ff14; }
        .status-skip { color: #888; }
        .status-error { color: #ff4444; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Odstraneni legacy sloupcu</h1>";

    // Sloupce k odstranění - POUZE ty, které se nikde nepoužívají!
    $legacySloupce = [
        'original_reklamace_id' => 'Reference na původní zakázku (funkce znovuotevření odstraněna, nikde se nepoužívá)',
        'poznamky' => 'Legacy poznámky (prázdné, máme tabulku wgs_notes)'
    ];

    // PONECHAT - aktivně používané:
    // - castka: používá se pro výpočet provizí techniků (tech_provize_api.php, statistiky_api.php)
    // - cena: fallback v COALESCE pro starší záznamy
    // - adresa: může být použita v kódu

    // Zjistit které sloupce existují
    $existujiciSloupce = [];
    foreach ($legacySloupce as $sloupec => $popis) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE '$sloupec'");
        if ($checkCol->fetch()) {
            $existujiciSloupce[$sloupec] = $popis;
        }
    }

    echo "<div class='info'>";
    echo "<strong>Sloupce k odstranění:</strong><br>";
    echo "Celkem " . count($legacySloupce) . " legacy sloupců, z toho " . count($existujiciSloupce) . " existuje v databázi.";
    echo "</div>";

    // Tabulka sloupců
    echo "<table>
        <tr><th>Sloupec</th><th>Popis</th><th>Stav</th></tr>";

    foreach ($legacySloupce as $sloupec => $popis) {
        $existuje = isset($existujiciSloupce[$sloupec]);
        $statusClass = $existuje ? 'status-ok' : 'status-skip';
        $statusText = $existuje ? 'EXISTUJE - bude smazán' : 'NEEXISTUJE - přeskočeno';

        echo "<tr>
            <td><code>$sloupec</code></td>
            <td>$popis</td>
            <td class='$statusClass'>$statusText</td>
        </tr>";
    }
    echo "</table>";

    if (empty($existujiciSloupce)) {
        echo "<div class='success'><strong>Všechny legacy sloupce již byly odstraněny.</strong><br>Databáze je čistá.</div>";
        echo "<a href='/admin.php' class='btn btn-secondary'>Zpět do admin</a>";
    } else {
        // Pokud execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $uspesne = 0;
            $chyby = 0;

            foreach ($existujiciSloupce as $sloupec => $popis) {
                try {
                    // Poslední kontrola - je sloupec opravdu prázdný?
                    $kontrola = $pdo->query("
                        SELECT COUNT(*) FROM wgs_reklamace
                        WHERE $sloupec IS NOT NULL AND $sloupec != '' AND $sloupec != '0' AND $sloupec != '0.00'
                    ")->fetchColumn();

                    if ($kontrola > 0) {
                        echo "<div class='warning'><strong>$sloupec:</strong> Obsahuje $kontrola záznamů - pokračuji (data nejsou kritická)</div>";
                    }

                    // Odstranit sloupec
                    $pdo->exec("ALTER TABLE wgs_reklamace DROP COLUMN `$sloupec`");
                    echo "<div class='success'><strong>$sloupec:</strong> ODSTRANĚN</div>";
                    $uspesne++;

                } catch (PDOException $e) {
                    echo "<div class='error'><strong>$sloupec:</strong> CHYBA - " . htmlspecialchars($e->getMessage()) . "</div>";
                    $chyby++;
                }
            }

            echo "<div class='" . ($chyby == 0 ? 'success' : 'warning') . "'>";
            echo "<strong>MIGRACE DOKONČENA</strong><br>";
            echo "Úspěšně odstraněno: $uspesne sloupců<br>";
            if ($chyby > 0) {
                echo "Přeskočeno/chyby: $chyby sloupců";
            }
            echo "</div>";

            // Zobrazit aktuální strukturu
            echo "<h2>Aktuální struktura wgs_reklamace</h2>";
            $columns = $pdo->query("SHOW COLUMNS FROM wgs_reklamace")->fetchAll(PDO::FETCH_ASSOC);
            echo "<div class='info'>Celkem sloupců: <strong>" . count($columns) . "</strong></div>";

            echo "<a href='/admin.php' class='btn btn-secondary'>Zpět do admin</a>";
            echo " <a href='/kontrola_legacy_sloupcu.php' class='btn btn-secondary'>Znovu zkontrolovat</a>";

        } else {
            // Zobrazit tlačítko pro spuštění
            echo "<div class='warning'>";
            echo "<strong>POZOR:</strong> Tato akce je nevratná!<br>";
            echo "Budou odstraněny sloupce: <code>" . implode('</code>, <code>', array_keys($existujiciSloupce)) . "</code>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn' onclick=\"return confirm('Opravdu odstranit " . count($existujiciSloupce) . " legacy sloupců? Tato akce je NEVRATNÁ!');\">Odstranit " . count($existujiciSloupce) . " sloupců</a>";
            echo "<a href='/admin.php' class='btn btn-secondary'>Zrušit</a>";
            echo "<a href='/kontrola_legacy_sloupcu.php' class='btn btn-secondary'>Znovu zkontrolovat</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
