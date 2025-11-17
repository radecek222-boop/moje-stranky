<?php
/**
 * OPRAVA EMAIL WORKER - Jednoduché přidání sloupců
 */

require_once __DIR__ . '/init.php';

// Bezpečnost - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html><html lang='cs'><head><meta charset='UTF-8'><title>Oprava Email Worker</title>";
echo "<style>body{font-family:'Poppins',sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#fff}";
echo ".ok{background:#d4edda;border:2px solid #28a745;color:#155724;padding:15px;margin:10px 0;border-radius:5px}";
echo ".err{background:#f8d7da;border:2px solid #dc3545;color:#721c24;padding:15px;margin:10px 0;border-radius:5px}";
echo ".info{background:#d1ecf1;border:2px solid #17a2b8;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px}";
echo "h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px}";
echo "code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-family:monospace}</style></head><body>";

echo "<h1>Oprava Email Worker</h1>";

try {
    $pdo = getDbConnection();

    echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

    $sql_prikazy = [
        "ALTER TABLE wgs_email_queue ADD COLUMN IF NOT EXISTS attempts INT DEFAULT 0",
        "ALTER TABLE wgs_email_queue ADD COLUMN IF NOT EXISTS max_attempts INT DEFAULT 3",
        "ALTER TABLE wgs_email_queue ADD COLUMN IF NOT EXISTS scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE wgs_email_queue ADD COLUMN IF NOT EXISTS priority INT DEFAULT 0",
        "ALTER TABLE wgs_email_queue ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(255)",
        "UPDATE wgs_email_queue SET attempts = COALESCE(retry_count, 0) WHERE attempts = 0",
        "UPDATE wgs_email_queue SET recipient_email = to_email WHERE recipient_email IS NULL OR recipient_email = ''"
    ];

    foreach ($sql_prikazy as $index => $sql) {
        try {
            $pdo->exec($sql);
            echo "<div class='ok'>✓ Příkaz " . ($index + 1) . " proveden</div>";
        } catch (PDOException $e) {
            // Sloupec už může existovat - to je OK
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<div class='info'>⚠ Příkaz " . ($index + 1) . " - sloupec už existuje (OK)</div>";
            } else {
                echo "<div class='err'>✗ Příkaz " . ($index + 1) . " selhal: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    echo "<div class='ok'><strong>✓ HOTOVO!</strong><br><br>";
    echo "Email worker má nyní všechny potřebné sloupce.<br><br>";
    echo "<strong>DALŠÍ KROKY:</strong><br>";
    echo "1. Nastav Webcron: <code>https://www.wgs-service.cz/cron/process-email-queue.php</code><br>";
    echo "2. Interval: Každých 5-10 minut<br>";
    echo "3. Nebo spusť ručně: <a href='cron/process-email-queue.php' target='_blank'>Zpracovat emaily</a>";
    echo "</div>";

    // Zobrazit aktuální stav fronty
    $stmt = $pdo->query("SELECT status, COUNT(*) as pocet FROM wgs_email_queue GROUP BY status");
    echo "<div class='info'><strong>Aktuální stav emailové fronty:</strong><br>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "• <strong>" . strtoupper($row['status']) . ":</strong> " . $row['pocet'] . " emailů<br>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='err'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<p style='text-align:center;margin-top:30px'><a href='admin.php?tab=notifications'>← Zpět do Admin Panelu</a></p>";
echo "</body></html>";
?>
