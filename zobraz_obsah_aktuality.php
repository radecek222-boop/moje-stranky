<?php
/**
 * Zobrazí celý obsah aktuality pro ruční překlad
 */
require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id, datum, obsah_cz FROM wgs_natuzzi_aktuality ORDER BY datum DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "=== AKTUALITA ID: {$row['id']} | DATUM: {$row['datum']} ===\n\n";
    echo $row['obsah_cz'];

} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage();
}
?>
