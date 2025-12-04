<?php
/**
 * Test pricing API
 */
require_once __DIR__ . '/init.php';

try {
    $pdo = getDbConnection();

    // Zkontrolovat zda tabulka existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pricing'");
    $exists = $stmt->rowCount() > 0;

    if (!$exists) {
        echo "<h1 style='color: red;'>TABULKA wgs_pricing NEEXISTUJE!</h1>";
        echo "<p>Musíte spustit migraci: <a href='update_cenik_2025.php'>update_cenik_2025.php</a></p>";
    } else {
        echo "<h1 style='color: green;'>Tabulka wgs_pricing existuje</h1>";

        // Zjistit počet položek
        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_pricing");
        $count = $stmt->fetchColumn();

        echo "<p>Počet položek v ceníku: <strong>$count</strong></p>";

        if ($count == 0) {
            echo "<p style='color: orange;'>⚠️ Tabulka je prázdná! Spusťte migraci: <a href='update_cenik_2025.php'>update_cenik_2025.php</a></p>";
        } else {
            echo "<h2>Test API endpointu:</h2>";
            echo "<p>Zkouším zavolat <code>/api/pricing_api.php?action=list</code></p>";

            // Simulovat API call
            ob_start();
            $_GET['action'] = 'list';
            require __DIR__ . '/api/pricing_api.php';
            $apiOutput = ob_get_clean();

            echo "<h3>Odpověď API:</h3>";
            echo "<pre>" . htmlspecialchars($apiOutput) . "</pre>";

            $decoded = json_decode($apiOutput, true);
            if ($decoded && isset($decoded['status']) && $decoded['status'] === 'success') {
                echo "<p style='color: green;'>API funguje!</p>";
                echo "<p>Počet položek v odpovědi: " . count($decoded['items'] ?? []) . "</p>";
            } else {
                echo "<p style='color: red;'>API vrací chybnou odpověď</p>";
            }
        }
    }

} catch (Exception $e) {
    echo "<h1 style='color: red;'>CHYBA</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
