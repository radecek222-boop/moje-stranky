<?php
/**
 * Migrace: Smazání nesmyslného popisu u položky "Příklad: Pouze sedák"
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

header('Content-Type: text/html; charset=utf-8');

$pdo = getDbConnection();

echo "<!DOCTYPE html><html lang='cs'><head><meta charset='UTF-8'><title>Oprava popisu ceníku</title>
<style>
body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
.box { background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 1px 6px rgba(0,0,0,.1); margin-bottom: 16px; }
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-top:10px; }
.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; }
.btn { display: inline-block; padding: 10px 20px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; margin: 4px; }
pre { background: #eee; padding: 8px; border-radius: 4px; font-size: 0.82em; white-space: pre-wrap; word-break: break-all; }
table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; vertical-align: top; }
th { background: #333; color: #fff; }
</style></head><body><div class='box'>
<h2>Oprava popisu: Příklad: Pouze sedák</h2>";

// Hledáme podle kategorie "MODELOV" nebo "sedák"
$stmt = $pdo->query("SELECT id, category, service_name, description FROM wgs_pricing ORDER BY category, display_order");
$vsechny = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrovat relevantní
$relevantni = array_filter($vsechny, function($r) {
    $hledat = strtolower($r['service_name'] . ' ' . $r['category'] . ' ' . $r['description']);
    return strpos($hledat, 'sedák') !== false
        || strpos($hledat, 'sedak') !== false
        || strpos($hledar = strtolower($r['category']), 'model') !== false
        || strpos($hledat, 'příklad') !== false
        || strpos($hledat, 'priklad') !== false;
});

if (empty($relevantni)) {
    echo "<div class='info'>Žádné relevantní položky nenalezeny. Zobrazuji všechny kategorie:</div><br>";
    $kategorie = array_unique(array_column($vsechny, 'category'));
    echo "<pre>" . htmlspecialchars(implode("\n", $kategorie)) . "</pre>";
} else {
    echo "<table><tr><th>ID</th><th>Kategorie</th><th>Název</th><th>Popis</th><th>Akce</th></tr>";
    foreach ($relevantni as $r) {
        echo "<tr>
            <td>{$r['id']}</td>
            <td>" . htmlspecialchars($r['category']) . "</td>
            <td>" . htmlspecialchars($r['service_name']) . "</td>
            <td><pre>" . htmlspecialchars($r['description']) . "</pre></td>
            <td><a href='?smazat_id={$r['id']}' class='btn'>Smazat popis</a></td>
        </tr>";
    }
    echo "</table>";
}

// Smazání konkrétního ID
if (isset($_GET['smazat_id']) && is_numeric($_GET['smazat_id'])) {
    $id = (int)$_GET['smazat_id'];
    try {
        $stmt = $pdo->prepare("UPDATE wgs_pricing SET description = '', description_en = '', description_it = '' WHERE id = :id");
        $stmt->execute(['id' => $id]);
        echo "<div class='success'>Popis u položky ID {$id} byl smazán. <a href='?' class='btn'>Obnovit</a></div>";
    } catch (PDOException $e) {
        error_log("Migrace chyba: " . $e->getMessage());
        echo "<div style='background:#f8d7da;padding:12px;border-radius:4px;color:#721c24;'>Chyba při aktualizaci.</div>";
    }
}

echo "</div></body></html>";
?>
