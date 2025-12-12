<?php
/**
 * Migrace: Vytvoreni tabulek pro herni zonu
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Herni tabulky</title>
<style>body{font-family:sans-serif;padding:30px;max-width:800px;margin:0 auto;background:#f5f5f5;}
.box{background:white;padding:20px;border-radius:8px;margin:10px 0;box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.ok{color:#28a745;}.err{color:#dc3545;}.info{color:#666;}</style></head><body>";

echo "<h1>Vytvoreni hernich tabulek</h1>";

try {
    $pdo = getDbConnection();

    // 1. wgs_hry_online
    $pdo->exec("CREATE TABLE IF NOT EXISTS wgs_hry_online (
        user_id INT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        aktualni_hra VARCHAR(50) DEFAULT NULL,
        mistnost_id INT DEFAULT NULL,
        posledni_aktivita DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<div class='box'><span class='ok'>OK</span> wgs_hry_online</div>";

    // 2. wgs_hry_chat
    $pdo->exec("CREATE TABLE IF NOT EXISTS wgs_hry_chat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mistnost_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        username VARCHAR(100) NOT NULL,
        zprava TEXT NOT NULL,
        cas DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mistnost (mistnost_id),
        INDEX idx_cas (cas)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<div class='box'><span class='ok'>OK</span> wgs_hry_chat</div>";

    // 3. wgs_hry_mistnosti
    $pdo->exec("CREATE TABLE IF NOT EXISTS wgs_hry_mistnosti (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nazev VARCHAR(100) NOT NULL,
        hra VARCHAR(50) NOT NULL,
        max_hracu INT DEFAULT 2,
        vytvoril_user_id INT NOT NULL,
        stav ENUM('ceka','hra','dokoncena') DEFAULT 'ceka',
        herni_stav JSON DEFAULT NULL,
        vytvoreno DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_hra (hra),
        INDEX idx_stav (stav)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<div class='box'><span class='ok'>OK</span> wgs_hry_mistnosti</div>";

    // 4. wgs_hry_hraci_mistnosti
    $pdo->exec("CREATE TABLE IF NOT EXISTS wgs_hry_hraci_mistnosti (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mistnost_id INT NOT NULL,
        user_id INT NOT NULL,
        username VARCHAR(100) NOT NULL,
        poradi INT DEFAULT 1,
        pripojeno DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_mistnost_user (mistnost_id, user_id),
        INDEX idx_mistnost (mistnost_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<div class='box'><span class='ok'>OK</span> wgs_hry_hraci_mistnosti</div>";

    echo "<div class='box' style='background:#d4edda;'><strong>HOTOVO!</strong> Vsechny herni tabulky vytvoreny.</div>";
    echo "<p><a href='/hry.php'>Prejit do herni zony</a> | <a href='/admin.php'>Admin</a></p>";

} catch (Exception $e) {
    echo "<div class='box' style='background:#f8d7da;'><span class='err'>CHYBA:</span> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
