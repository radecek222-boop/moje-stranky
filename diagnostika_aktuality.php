<?php
/**
 * Diagnostika aktualit - zobrazeni obsahu tabulky wgs_natuzzi_aktuality
 */

require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika aktualit</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 1400px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a1a1a; border-bottom: 3px solid #1a1a1a; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .aktualita { background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .aktualita h3 { margin: 0 0 10px 0; color: #1a1a1a; }
        .meta { color: #666; font-size: 14px; margin-bottom: 15px; }
        .clanek { background: white; border-left: 4px solid #333; padding: 15px; margin: 10px 0; }
        .clanek-nadpis { font-weight: bold; color: #1a1a1a; margin-bottom: 5px; }
        .clanek-obsah { font-size: 13px; color: #555; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #1a1a1a; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #333; }
        pre { background: #1a1a1a; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .duplicate { background: #ffe6e6 !important; border-color: #ff9999 !important; }
        .short { background: #fff3e6 !important; border-color: #ffcc80 !important; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Diagnostika aktualit</h1>";

    // Nacist vsechny aktuality
    $stmt = $pdo->query("SELECT * FROM wgs_natuzzi_aktuality ORDER BY datum DESC");
    $aktuality = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Celkem zaznamu:</strong> " . count($aktuality) . "</p>";

    // Analyza clanku
    $vsechnyNadpisy = [];
    $duplicity = [];
    $kratke = [];

    foreach ($aktuality as $aktualita) {
        echo "<div class='aktualita'>";
        echo "<h3>ID: {$aktualita['id']} | Datum: {$aktualita['datum']}</h3>";
        echo "<div class='meta'>Vytvoreno: {$aktualita['vytvoreno']}</div>";

        $obsah = $aktualita['obsah_cz'] ?? '';

        // Rozdelit na clanky
        $parts = preg_split('/(?=^## )/m', $obsah);

        $pocetClanku = 0;
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            if (preg_match('/^## /', $part)) {
                $pocetClanku++;

                // Ziskat nadpis
                preg_match('/^## (.+)$/m', $part, $matches);
                $nadpis = $matches[1] ?? 'BEZ NADPISU';

                // Kontrola duplicit
                $nadpisKey = strtolower(trim($nadpis));
                $jeDuplicita = in_array($nadpisKey, $vsechnyNadpisy);
                if ($jeDuplicita) {
                    $duplicity[] = $nadpis;
                }
                $vsechnyNadpisy[] = $nadpisKey;

                // Kontrola delky
                $delka = strlen($part);
                $jeKratky = $delka < 200;
                if ($jeKratky) {
                    $kratke[] = $nadpis;
                }

                $class = '';
                if ($jeDuplicita) $class = 'duplicate';
                elseif ($jeKratky) $class = 'short';

                echo "<div class='clanek {$class}'>";
                echo "<div class='clanek-nadpis'>{$nadpis}</div>";
                echo "<div class='clanek-obsah'>" . htmlspecialchars(substr($part, 0, 500)) . (strlen($part) > 500 ? '...' : '') . "</div>";
                echo "<div style='font-size:11px;color:#999;margin-top:5px;'>Delka: {$delka} znaku" . ($jeDuplicita ? " | DUPLICITA!" : "") . ($jeKratky ? " | KRATKY!" : "") . "</div>";
                echo "</div>";
            }
        }

        echo "<p><strong>Pocet clanku v teto aktualite:</strong> {$pocetClanku}</p>";
        echo "</div>";
    }

    // Souhrn
    echo "<h2>Souhrn analyzy</h2>";

    if (!empty($duplicity)) {
        echo "<div class='error'>";
        echo "<strong>Nalezene duplicity (" . count($duplicity) . "):</strong><br>";
        foreach (array_unique($duplicity) as $dup) {
            echo "- " . htmlspecialchars($dup) . "<br>";
        }
        echo "</div>";
    } else {
        echo "<div class='success'>Zadne duplicity nenalezeny</div>";
    }

    if (!empty($kratke)) {
        echo "<div class='warning'>";
        echo "<strong>Kratke clanky pod 200 znaku (" . count($kratke) . "):</strong><br>";
        foreach ($kratke as $kr) {
            echo "- " . htmlspecialchars($kr) . "<br>";
        }
        echo "</div>";
    }

    // RAW obsah pro kopirovani
    echo "<h2>RAW obsah databaze (obsah_cz)</h2>";
    foreach ($aktuality as $aktualita) {
        echo "<h3>ID: {$aktualita['id']}</h3>";
        echo "<pre>" . htmlspecialchars($aktualita['obsah_cz'] ?? 'PRAZDNY') . "</pre>";
    }

} catch (Exception $e) {
    echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
