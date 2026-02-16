<?php
/**
 * Diagnostický skript pro kontrolu kalkulací v databázi
 *
 * Zkontroluje jestli se kalkulace ukládají správně
 * a jestli obsahují správný formát pro PRICE LIST
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("<h1>PŘÍSTUP ODEPŘEN</h1><p>Pouze administrátor může spustit diagnózu.</p>");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnóza kalkulací</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        pre { background: #f8f8f8; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Diagnóza kalkulací v databázi</h1>";

    // 1. Zjistit kolik reklamací má kalkulaci
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as celkem_reklamaci,
            SUM(CASE WHEN kalkulace_data IS NOT NULL THEN 1 ELSE 0 END) as s_kalkulaci,
            SUM(CASE WHEN kalkulace_data IS NULL THEN 1 ELSE 0 END) as bez_kalkulace
        FROM wgs_reklamace
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>STATISTIKA:</strong><br>";
    echo "Celkem reklamací: <strong>{$stats['celkem_reklamaci']}</strong><br>";
    echo "S kalkulací: <strong>{$stats['s_kalkulaci']}</strong><br>";
    echo "Bez kalkulace: <strong>{$stats['bez_kalkulace']}</strong>";
    echo "</div>";

    // 2. Zobrazit 5 nejnovějších reklamací s kalkulací
    echo "<h2>📋 Nejnovější reklamace s kalkulací</h2>";

    $stmt = $pdo->query("
        SELECT
            reklamace_id,
            jmeno,
            LENGTH(kalkulace_data) as delka_json,
            DATE_FORMAT(updated_at, '%d.%m.%Y %H:%i') as upraveno
        FROM wgs_reklamace
        WHERE kalkulace_data IS NOT NULL
        ORDER BY updated_at DESC
        LIMIT 5
    ");
    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($reklamace) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Jméno</th><th>Velikost JSON</th><th>Upraveno</th><th>Akce</th></tr>";
        foreach ($reklamace as $rek) {
            echo "<tr>";
            echo "<td>{$rek['reklamace_id']}</td>";
            echo "<td>{$rek['jmeno']}</td>";
            echo "<td>" . number_format($rek['delka_json']) . " bytů</td>";
            echo "<td>{$rek['upraveno']}</td>";
            echo "<td><a href='?detail={$rek['reklamace_id']}' class='btn'>Detail</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>Žádné reklamace s kalkulací nenalezeny.</div>";
    }

    // 3. Zobrazit detail konkrétní kalkulace
    if (isset($_GET['detail'])) {
        $reklamaceId = $_GET['detail'];

        echo "<h2>🔬 Detail kalkulace pro reklamaci: {$reklamaceId}</h2>";

        $stmt = $pdo->prepare("
            SELECT
                reklamace_id,
                jmeno,
                adresa,
                kalkulace_data,
                DATE_FORMAT(updated_at, '%d.%m.%Y %H:%i:%s') as upraveno
            FROM wgs_reklamace
            WHERE reklamace_id = :rek_id OR cislo = :cislo OR id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':rek_id' => $reklamaceId,
            ':cislo' => $reklamaceId,
            ':id' => is_numeric($reklamaceId) ? intval($reklamaceId) : 0
        ]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detail) {
            echo "<div class='info'>";
            echo "<strong>Zákazník:</strong> {$detail['jmeno']}<br>";
            echo "<strong>Adresa:</strong> {$detail['adresa']}<br>";
            echo "<strong>Naposledy upraveno:</strong> {$detail['upraveno']}";
            echo "</div>";

            if ($detail['kalkulace_data']) {
                $kalkulace = json_decode($detail['kalkulace_data'], true);

                echo "<h3>📊 Struktura kalkulace:</h3>";

                // Kontrola formátu
                $maRozpis = isset($kalkulace['rozpis']);
                $maSluzby = isset($kalkulace['sluzby']) && is_array($kalkulace['sluzby']);
                $maDilyPrace = isset($kalkulace['dilyPrace']) && is_array($kalkulace['dilyPrace']);

                echo "<div class='" . ($maRozpis || ($maSluzby && $maDilyPrace) ? "success" : "error") . "'>";
                echo "<strong>Formát kalkulace:</strong><br>";
                echo "✅ Má 'rozpis': " . ($maRozpis ? "ANO" : "NE") . "<br>";
                echo "✅ Má 'sluzby': " . ($maSluzby ? "ANO (" . count($kalkulace['sluzby']) . " položek)" : "NE") . "<br>";
                echo "✅ Má 'dilyPrace': " . ($maDilyPrace ? "ANO (" . count($kalkulace['dilyPrace']) . " položek)" : "NE") . "<br>";
                echo "✅ Celková cena: " . (isset($kalkulace['celkovaCena']) ? number_format($kalkulace['celkovaCena'], 2) . " €" : "CHYBÍ") . "<br>";
                echo "</div>";

                // Zobrazit rozpis pokud existuje
                if ($maRozpis) {
                    echo "<h3>🔍 Obsah rozpisu:</h3>";
                    echo "<pre>" . json_encode($kalkulace['rozpis'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                }

                if ($maSluzby) {
                    echo "<h3>🛠️ Služby:</h3>";
                    echo "<pre>" . json_encode($kalkulace['sluzby'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                }

                if ($maDilyPrace) {
                    echo "<h3>🔧 Díly a práce:</h3>";
                    echo "<pre>" . json_encode($kalkulace['dilyPrace'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                }

                // Celý JSON
                echo "<h3>📄 Celý JSON (raw):</h3>";
                echo "<pre>" . json_encode($kalkulace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

                // Simulovat PRICE LIST výstup
                echo "<h3>🖨️ Simulace PRICE LIST výstupu:</h3>";
                echo "<div class='info'>";

                // Dopravné
                if (!($kalkulace['reklamaceBezDopravy'] ?? false)) {
                    echo "Dopravné ({$kalkulace['vzdalenost']} km): {$kalkulace['dopravne']} EUR<br>";
                } else {
                    echo "Dopravné (reklamace): 0.00 EUR<br>";
                }

                // Služby
                if ($maSluzby && count($kalkulace['sluzby']) > 0) {
                    echo "<br><strong>Služby:</strong><br>";
                    foreach ($kalkulace['sluzby'] as $sluzba) {
                        echo "&nbsp;&nbsp;{$sluzba['nazev']}: " . number_format($sluzba['cena'], 2) . " EUR<br>";
                    }
                } else {
                    echo "<br><span style='color: #dc3545;'>❌ SLUŽBY NEJSOU - PRICELIST BUDE PRÁZDNÝ!</span><br>";
                }

                // Díly a práce
                if ($maDilyPrace && count($kalkulace['dilyPrace']) > 0) {
                    echo "<br><strong>Díly a práce:</strong><br>";
                    foreach ($kalkulace['dilyPrace'] as $polozka) {
                        $jednotkovaCena = $polozka['pocet'] > 1 ? ($polozka['cena'] / $polozka['pocet']) : $polozka['cena'];
                        echo "&nbsp;&nbsp;{$polozka['nazev']}: {$polozka['pocet']} ks × " . number_format($jednotkovaCena, 2) . " EUR = " . number_format($polozka['cena'], 2) . " EUR<br>";
                    }
                } else {
                    echo "<br><span style='color: #dc3545;'>❌ DÍLY A PRÁCE NEJSOU - PRICELIST BUDE PRÁZDNÝ!</span><br>";
                }

                // Celkem
                echo "<br><strong>CELKEM: " . number_format($kalkulace['celkovaCena'], 2) . " EUR</strong>";
                echo "</div>";

            } else {
                echo "<div class='error'>Kalkulace data jsou NULL</div>";
            }
        } else {
            echo "<div class='error'>Reklamace nenalezena</div>";
        }
    }

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA DATABÁZE:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><a href='diagnoza_kalkulace.php' class='btn'>← Zpět na přehled</a>";
echo "<a href='admin.php' class='btn'>Zpět do admin</a>";
echo "</div></body></html>";
?>
