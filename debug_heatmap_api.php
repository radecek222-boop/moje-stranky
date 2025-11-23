<?php
/**
 * Debug script pro analytics_heatmap.php API
 * Simuluje API call a vypíše detailní chybovou zprávu
 */

// Zapnout zobrazení všech chyb
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/init.php';

echo "<h1>Debug Analytics Heatmap API</h1>";
echo "<pre>";

// Simulace admin session
$_SESSION['is_admin'] = true;

// Simulace GET parametrů
$_GET['page_url'] = 'https://www.wgs-service.cz/analytics';
$_GET['type'] = 'click';
$_GET['csrf_token'] = generateCSRFToken(); // Vytvořit validní CSRF token

echo "=== PARAMETRY ===\n";
echo "page_url: " . $_GET['page_url'] . "\n";
echo "type: " . $_GET['type'] . "\n";
echo "csrf_token: nastaven\n\n";

echo "=== KONTROLA DATABÁZE ===\n";

try {
    $pdo = getDbConnection();
    echo "✓ Připojení k databázi: OK\n\n";

    // Kontrola existence tabulek
    echo "Kontrola tabulky wgs_analytics_heatmap_clicks...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_heatmap_clicks'");
    $clicksExist = $stmt && $stmt->rowCount() > 0;

    if ($clicksExist) {
        echo "✓ Tabulka wgs_analytics_heatmap_clicks EXISTUJE\n";

        // Struktura
        $stmt = $pdo->query("DESCRIBE wgs_analytics_heatmap_clicks");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  Sloupce:\n";
        foreach ($cols as $col) {
            echo "    - {$col['Field']} ({$col['Type']})\n";
        }

        // Počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_analytics_heatmap_clicks");
        $count = $stmt->fetchColumn();
        echo "  Počet záznamů: $count\n";
    } else {
        echo "✗ Tabulka wgs_analytics_heatmap_clicks NEEXISTUJE!\n";
        echo "\nMUSÍTE SPUSTIT MIGRACI:\n";
        echo "https://www.wgs-service.cz/migrace_module6_heatmaps.php?execute=1\n";
    }

    echo "\nKontrola tabulky wgs_analytics_heatmap_scroll...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_heatmap_scroll'");
    $scrollExist = $stmt && $stmt->rowCount() > 0;

    if ($scrollExist) {
        echo "✓ Tabulka wgs_analytics_heatmap_scroll EXISTUJE\n";
    } else {
        echo "✗ Tabulka wgs_analytics_heatmap_scroll NEEXISTUJE!\n";
    }

    echo "\n=== TEST API DOTAZU ===\n";

    if ($clicksExist) {
        // Normalizace URL (stejně jako v API)
        $pageUrl = filter_var($_GET['page_url'], FILTER_VALIDATE_URL);
        $parsedUrl = parse_url($pageUrl);
        $normalizedUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ($parsedUrl['path'] ?? '/');

        echo "Normalized URL: $normalizedUrl\n\n";

        // Test dotazu
        $sql = "
            SELECT
                click_x_percent AS x,
                click_y_percent AS y,
                click_count AS count,
                viewport_width_avg,
                viewport_height_avg
            FROM wgs_analytics_heatmap_clicks
            WHERE page_url = :page_url
            ORDER BY click_count DESC
            LIMIT 10
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['page_url' => $normalizedUrl]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "✓ SQL dotaz proběhl úspěšně\n";
        echo "  Nalezeno záznamů: " . count($points) . "\n";

        if (count($points) > 0) {
            echo "\n  Ukázka dat:\n";
            foreach (array_slice($points, 0, 3) as $point) {
                echo "  - x: {$point['x']}%, y: {$point['y']}%, count: {$point['count']}\n";
            }
        } else {
            echo "\n  Žádná data pro URL: $normalizedUrl\n";
            echo "  To je OK - tabulka je prázdná (zatím žádné kliknutí)\n";
        }
    }

    echo "\n=== VÝSLEDEK ===\n";

    if ($clicksExist && $scrollExist) {
        echo "✓✓✓ VEŠKERÉ TABULKY EXISTUJÍ - API BY MĚLO FUNGOVAT\n";
        echo "\nPokud stále vidíte HTTP 500, zkontrolujte:\n";
        echo "1. Browser console (F12) - je tam detailní error?\n";
        echo "2. PHP error log: tail -100 /home/user/moje-stranky/logs/php_errors.log\n";
        echo "3. Možná je problém s CSRF tokenem nebo autentizací\n";
    } else {
        echo "✗✗✗ NĚKTERÉ TABULKY CHYBÍ - API NEMŮŽE FUNGOVAT\n";
        echo "\nSPUSTITE MIGRACI:\n";
        echo "https://www.wgs-service.cz/migrace_module6_heatmaps.php?execute=1\n";
    }

} catch (PDOException $e) {
    echo "\n✗ CHYBA DATABÁZE:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "\n✗ OBECNÁ CHYBA:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<a href='/analytics' style='padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px;'>Zpět na Analytics</a>";
