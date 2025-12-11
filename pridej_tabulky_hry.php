<?php
/**
 * Migrace: Vytvoreni tabulek pro herní zónu
 * - Online hráči
 * - Herní místnosti
 * - Chat
 * - Partie Prší
 */
require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Herní tabulky</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #2d2d2d; padding: 30px; border-radius: 10px; }
        h1 { color: #39ff14; border-bottom: 2px solid #39ff14; padding-bottom: 10px; }
        .success { background: #1a3d1a; border: 1px solid #28a745; color: #90EE90; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3d1a1a; border: 1px solid #dc3545; color: #ff8888; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2d3d; border: 1px solid #17a2b8; color: #87CEEB; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #39ff14; color: #000; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-weight: bold; }
        pre { background: #111; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.85em; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Migrace: Herní tabulky</h1>";

$sql_tabulky = [
    'wgs_hry_online' => "
        CREATE TABLE IF NOT EXISTS `wgs_hry_online` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `username` VARCHAR(100) NOT NULL,
            `posledni_aktivita` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `aktualni_hra` VARCHAR(50) DEFAULT NULL,
            `mistnost_id` INT UNSIGNED DEFAULT NULL,
            UNIQUE KEY `uk_user` (`user_id`),
            INDEX `idx_aktivita` (`posledni_aktivita`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Online hraci v herni zone'
    ",

    'wgs_hry_mistnosti' => "
        CREATE TABLE IF NOT EXISTS `wgs_hry_mistnosti` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `nazev` VARCHAR(100) NOT NULL,
            `hra` VARCHAR(50) NOT NULL DEFAULT 'prsi',
            `stav` ENUM('cekani', 'hra', 'dokonceno') DEFAULT 'cekani',
            `max_hracu` TINYINT UNSIGNED DEFAULT 4,
            `vytvoril_user_id` INT UNSIGNED NOT NULL,
            `vytvoreno` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `aktualizovano` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_stav` (`stav`),
            INDEX `idx_hra` (`hra`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Herni mistnosti'
    ",

    'wgs_hry_hraci_mistnosti' => "
        CREATE TABLE IF NOT EXISTS `wgs_hry_hraci_mistnosti` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `mistnost_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `username` VARCHAR(100) NOT NULL,
            `poradi` TINYINT UNSIGNED DEFAULT 0,
            `pripojeno` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_mistnost_user` (`mistnost_id`, `user_id`),
            INDEX `idx_mistnost` (`mistnost_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Hraci v mistnostech'
    ",

    'wgs_hry_chat' => "
        CREATE TABLE IF NOT EXISTS `wgs_hry_chat` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `mistnost_id` INT UNSIGNED DEFAULT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `username` VARCHAR(100) NOT NULL,
            `zprava` TEXT NOT NULL,
            `cas` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_mistnost` (`mistnost_id`),
            INDEX `idx_cas` (`cas`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Chat v herni zone'
    ",

    'wgs_hry_prsi_partie' => "
        CREATE TABLE IF NOT EXISTS `wgs_hry_prsi_partie` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `mistnost_id` INT UNSIGNED DEFAULT NULL,
            `hrac1_id` INT UNSIGNED NOT NULL,
            `hrac2_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = pocitac',
            `balicek` TEXT NOT NULL COMMENT 'JSON pole karet v balicku',
            `odkladaci` TEXT NOT NULL COMMENT 'JSON pole karet na odkladacim balicku',
            `karty_hrac1` TEXT NOT NULL COMMENT 'JSON pole karet hrace 1',
            `karty_hrac2` TEXT NOT NULL COMMENT 'JSON pole karet hrace 2',
            `na_tahu` TINYINT UNSIGNED DEFAULT 1 COMMENT '1 nebo 2',
            `smer` TINYINT DEFAULT 1 COMMENT '1 = normalni, -1 = opacny',
            `aktivni_barva` VARCHAR(20) DEFAULT NULL COMMENT 'Zmenena barva (svrsek)',
            `karty_k_tazeni` TINYINT UNSIGNED DEFAULT 0 COMMENT 'Pocet karet k tazeni (sedmicky)',
            `stav` ENUM('hra', 'vyhral_hrac1', 'vyhral_hrac2', 'remiza') DEFAULT 'hra',
            `vytvoreno` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `aktualizovano` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_mistnost` (`mistnost_id`),
            INDEX `idx_stav` (`stav`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Partie hry Prsi'
    "
];

try {
    $pdo = getDbConnection();

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>VYTVÁŘÍM TABULKY...</strong></div>";

        foreach ($sql_tabulky as $nazev => $sql) {
            try {
                $pdo->exec($sql);
                echo "<div class='success'>Tabulka <strong>{$nazev}</strong> vytvorena/existuje</div>";
            } catch (PDOException $e) {
                echo "<div class='error'>Chyba u {$nazev}: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        echo "<div class='success' style='margin-top: 20px;'><strong>MIGRACE DOKONCENA!</strong></div>";

    } else {
        echo "<div class='info'><strong>Tabulky k vytvoreni:</strong></div>";

        foreach ($sql_tabulky as $nazev => $sql) {
            echo "<p><strong>{$nazev}</strong></p>";
            echo "<pre>" . htmlspecialchars(trim($sql)) . "</pre>";
        }

        echo "<a href='?execute=1' class='btn'>VYTVORIT TABULKY</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn' style='background:#666;'>Zpet do Adminu</a>";
echo "</div></body></html>";
?>
