<?php
/**
 * Migrace: Smazání nesmyslného popisu u položky "Příklad: Pouze sedák"
 *
 * Odstraní text "První díl 205€ = 105€. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU."
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

header('Content-Type: text/html; charset=utf-8');

$pdo = getDbConnection();

// Načtení aktuálního stavu
$stmt = $pdo->prepare("SELECT id, service_name, description FROM wgs_pricing WHERE service_name LIKE '%Pouze sedák%' LIMIT 5");
$stmt->execute();
$polozky = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<!DOCTYPE html><html lang='cs'><head><meta charset='UTF-8'><title>Oprava popisu ceníku</title>
<style>
body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
.box { background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 1px 6px rgba(0,0,0,.1); margin-bottom: 16px; }
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; }
.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; }
.btn { display: inline-block; padding: 10px 20px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 12px; }
pre { background: #eee; padding: 10px; border-radius: 4px; font-size: 0.85em; white-space: pre-wrap; }
</style></head><body><div class='box'>
<h2>Oprava popisu: Příklad: Pouze sedák</h2>";

if (empty($polozky)) {
    echo "<div class='info'>Položka 'Pouze sedák' nebyla nalezena v databázi.</div>";
    echo "</div></body></html>";
    exit;
}

foreach ($polozky as $polozka) {
    echo "<p><strong>ID:</strong> {$polozka['id']}<br>
    <strong>Název:</strong> " . htmlspecialchars($polozka['service_name']) . "<br>
    <strong>Aktuální popis:</strong></p>
    <pre>" . htmlspecialchars($polozka['description']) . "</pre>";
}

if (isset($_GET['spustit']) && $_GET['spustit'] === '1') {
    try {
        $stmt = $pdo->prepare("UPDATE wgs_pricing SET description = '', description_en = '', description_it = '' WHERE service_name LIKE '%Pouze sedák%'");
        $stmt->execute();
        $pocet = $stmt->rowCount();
        echo "<div class='success'>Hotovo: Popis byl smazán u {$pocet} položky/položek.</div>";
    } catch (PDOException $e) {
        error_log("Migrace chyba: " . $e->getMessage());
        echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:12px;border-radius:4px;'>Chyba při aktualizaci.</div>";
    }
} else {
    echo "<a href='?spustit=1' class='btn'>Smazat popis</a>";
}

echo "</div></body></html>";
?>
