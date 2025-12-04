<?php
/**
 * Test script - kontrola heatmap tabulek
 */

require_once __DIR__ . '/init.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== KONTROLA HEATMAP TABULEK ===\n\n";

try {
    $pdo = getDbConnection();

    // Zkontrolovat existenci tabulky wgs_analytics_heatmap_clicks
    echo "1. Kontrola tabulky wgs_analytics_heatmap_clicks...\n";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_heatmap_clicks'");
        $exists = $stmt->fetch();

        if ($exists) {
            echo "   ✓ Tabulka EXISTUJE\n";

            // Zjistit strukturu
            $stmt = $pdo->query("DESCRIBE wgs_analytics_heatmap_clicks");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "   Sloupce: " . implode(', ', $columns) . "\n";

            // Počet záznamů
            $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_analytics_heatmap_clicks");
            $count = $stmt->fetchColumn();
            echo "   Počet záznamů: $count\n";
        } else {
            echo "   ✗ Tabulka NEEXISTUJE!\n";
        }
    } catch (PDOException $e) {
        echo "   ✗ CHYBA: " . $e->getMessage() . "\n";
    }

    echo "\n2. Kontrola tabulky wgs_analytics_heatmap_scroll...\n";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_heatmap_scroll'");
        $exists = $stmt->fetch();

        if ($exists) {
            echo "   ✓ Tabulka EXISTUJE\n";

            // Zjistit strukturu
            $stmt = $pdo->query("DESCRIBE wgs_analytics_heatmap_scroll");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "   Sloupce: " . implode(', ', $columns) . "\n";

            // Počet záznamů
            $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_analytics_heatmap_scroll");
            $count = $stmt->fetchColumn();
            echo "   Počet záznamů: $count\n";
        } else {
            echo "   ✗ Tabulka NEEXISTUJE!\n";
        }
    } catch (PDOException $e) {
        echo "   ✗ CHYBA: " . $e->getMessage() . "\n";
    }

    echo "\n3. Test dotazu z analytics_heatmap.php...\n";
    try {
        $testUrl = 'https://www.wgs-service.cz/analytics';
        $sql = "
            SELECT
                click_x_percent AS x,
                click_y_percent AS y,
                click_count AS count,
                viewport_width_avg,
                viewport_height_avg
            FROM wgs_analytics_heatmap_clicks
            WHERE page_url = :page_url
            LIMIT 10
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['page_url' => $testUrl]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "   ✓ Dotaz proběhl úspěšně\n";
        echo "   Nalezeno záznamů: " . count($points) . "\n";
    } catch (PDOException $e) {
        echo "   ✗ CHYBA: " . $e->getMessage() . "\n";
    }

    echo "\n=== KONEC KONTROLY ===\n";

} catch (Exception $e) {
    echo "FATÁLNÍ CHYBA: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
