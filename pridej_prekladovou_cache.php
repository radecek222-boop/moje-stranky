<?php
/**
 * Migrace: Vytvoreni tabulky pro cache prekladu
 *
 * Tento skript BEZPECNE vytvori tabulku wgs_translation_cache.
 * Muzete jej spustit vicekrat - neprovede duplicitni operace.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Prekladova cache</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #2d2d2d; padding: 30px; border-radius: 10px; }
        h1 { color: #fff; border-bottom: 3px solid #39ff14; padding-bottom: 10px; }
        .success { background: #1a3d1a; border: 1px solid #28a745; color: #90EE90; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3d1a1a; border: 1px solid #dc3545; color: #ff8888; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3d3d1a; border: 1px solid #f59e0b; color: #ffd700; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2d3d; border: 1px solid #17a2b8; color: #87CEEB; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #218838; }
        pre { background: #1a1a1a; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.85rem; border: 1px solid #444; color: #ccc; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Prekladova cache pro aktuality</h1>";

    // Kontrola pred migraci
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $stmtCheck = $pdo->query("SHOW TABLES LIKE 'wgs_translation_cache'");
    $tabulkaExistuje = $stmtCheck->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='warning'>";
        echo "<strong>UPOZORNENI:</strong> Tabulka <code>wgs_translation_cache</code> jiz existuje.<br>";
        echo "Migrace nebude provedena.";
        echo "</div>";

        // Zobrazit strukturu existujici tabulky
        $stmtDesc = $pdo->query("DESCRIBE wgs_translation_cache");
        $sloupce = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Aktualni struktura tabulky:</h3>";
        echo "<pre>";
        foreach ($sloupce as $sloupec) {
            echo sprintf("%-25s %-20s %s\n",
                $sloupec['Field'],
                $sloupec['Type'],
                $sloupec['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
            );
        }
        echo "</pre>";

        // Zobrazit pocet zaznamu
        $stmtCount = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_translation_cache");
        $pocet = $stmtCount->fetch(PDO::FETCH_ASSOC)['pocet'];
        echo "<div class='info'><strong>Pocet cachovanych prekladu:</strong> {$pocet}</div>";

    } else {
        // Pokud je nastaveno ?execute=1, provest migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            try {
                // Vytvoreni tabulky pro cache prekladu
                // POZN: DDL prikazy v MySQL automaticky commitují, transakce zde nefunguje
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `wgs_translation_cache` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `source_hash` VARCHAR(32) NOT NULL COMMENT 'MD5 hash zdrojoveho textu',
                        `source_lang` VARCHAR(5) NOT NULL DEFAULT 'cs' COMMENT 'Zdrojovy jazyk',
                        `target_lang` VARCHAR(5) NOT NULL COMMENT 'Cilovy jazyk (en/it)',
                        `source_text` LONGTEXT NOT NULL COMMENT 'Puvodni text',
                        `translated_text` LONGTEXT NOT NULL COMMENT 'Prelozeny text',
                        `entity_type` VARCHAR(50) DEFAULT 'aktualita' COMMENT 'Typ entity (aktualita, clanek...)',
                        `entity_id` INT UNSIGNED NULL COMMENT 'ID entity (volitelne)',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY `uk_hash_lang` (`source_hash`, `target_lang`),
                        INDEX `idx_entity` (`entity_type`, `entity_id`),
                        INDEX `idx_lang` (`source_lang`, `target_lang`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Cache pro preklady textu (Google Translate)'
                ");

                echo "<div class='success'>";
                echo "<strong>MIGRACE USPESNE DOKONCENA</strong><br><br>";
                echo "Tabulka <code>wgs_translation_cache</code> byla vytvorena.<br><br>";
                echo "<strong>Jak to funguje:</strong>";
                echo "<ul>";
                echo "<li>Pri ulozeni ceskeho textu se vypocita MD5 hash</li>";
                echo "<li>System zkontroluje, zda preklad pro tento hash existuje</li>";
                echo "<li>Pokud NE nebo se hash zmenil - zavola Google Translate API</li>";
                echo "<li>Preklad se ulozi do cache a pouzije</li>";
                echo "<li>Pri dalsim nacteni se pouzije cache (rychle, bez API volani)</li>";
                echo "</ul>";
                echo "</div>";

            } catch (PDOException $e) {
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Nahled co bude provedeno
            echo "<div class='info'>";
            echo "<strong>CO BUDE PROVEDENO:</strong><br>";
            echo "Vytvori se tabulka <code>wgs_translation_cache</code> pro ukladani prekladu.";
            echo "</div>";

            echo "<pre>";
            echo "CREATE TABLE wgs_translation_cache (
    id               INT AUTO_INCREMENT PRIMARY KEY
    source_hash      VARCHAR(32) - MD5 hash zdrojoveho textu
    source_lang      VARCHAR(5) - Zdrojovy jazyk (cs)
    target_lang      VARCHAR(5) - Cilovy jazyk (en/it)
    source_text      LONGTEXT - Puvodni text
    translated_text  LONGTEXT - Prelozeny text
    entity_type      VARCHAR(50) - Typ entity
    entity_id        INT - ID entity
    created_at       TIMESTAMP
    updated_at       TIMESTAMP

    UNIQUE KEY (source_hash, target_lang)
)";
            echo "</pre>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn' style='background: #6c757d;'>Zpet do Admin</a>";
echo "</div></body></html>";
?>
