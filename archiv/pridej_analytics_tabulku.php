<?php
/**
 * Migrace: Vytvoření tabulky pro Web Analytics
 *
 * Tento skript vytvoří tabulku wgs_pageviews pro sledování návštěvnosti webu.
 * Můžete jej spustit vícekrát - automaticky kontroluje existenci tabulky.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Analytics Tabulka</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333333; border-bottom: 3px solid #333333;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // Kontrola před migrací
    echo "<h1>Migrace: Web Analytics Tabulka</h1>";

    // 1. Kontrolní fáze
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    // Zkontrolovat jestli tabulka existuje
    $stmtCheck = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
    $existuje = $stmtCheck->rowCount() > 0;

    if ($existuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ UPOZORNĚNÍ:</strong> Tabulka <code>wgs_pageviews</code> již existuje.<br>";
        echo "Pokud chcete provést migraci znovu, nejprve smažte starou tabulku.";
        echo "</div>";

        // Zobrazit strukturu
        $stmtStructure = $pdo->query("DESCRIBE wgs_pageviews");
        $structure = $stmtStructure->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Aktuální struktura tabulky:</h3>";
        echo "<pre>";
        foreach ($structure as $col) {
            echo sprintf("%-20s %-20s %-10s %-10s\n",
                $col['Field'],
                $col['Type'],
                $col['Null'],
                $col['Key']
            );
        }
        echo "</pre>";

        // Spočítat záznamy
        $stmtCount = $pdo->query("SELECT COUNT(*) as count FROM wgs_pageviews");
        $count = $stmtCount->fetch(PDO::FETCH_ASSOC)['count'];

        echo "<div class='info'>";
        echo "📊 <strong>Počet záznamů:</strong> " . number_format($count, 0, ',', ' ');
        echo "</div>";

    } else {
        echo "<div class='info'>";
        echo "✅ Tabulka <code>wgs_pageviews</code> neexistuje. Připraveno k vytvoření.";
        echo "</div>";
    }

    // 2. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        if ($existuje) {
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong> Tabulka již existuje. Migrace nebyla provedena.";
            echo "</div>";
        } else {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            $migrationSuccess = false;
            $adminIPs = [
                '46.135.14.161' => 'Admin IP - IPv4',
                '2a00:11b1:100d:445b:550:e51:9352:3106' => 'Admin IP - IPv6'
            ];

            try {
                // SQL pro vytvoření tabulky
                $sql = "
                    CREATE TABLE `wgs_pageviews` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `session_id` VARCHAR(64) NOT NULL,
                        `user_id` INT UNSIGNED NULL,
                        `ip_address` VARCHAR(45) NOT NULL,
                        `user_agent` VARCHAR(500) NULL,
                        `page_url` VARCHAR(500) NOT NULL,
                        `page_title` VARCHAR(200) NULL,
                        `referrer` VARCHAR(500) NULL,
                        `device_type` VARCHAR(20) NULL COMMENT 'desktop, mobile, tablet',
                        `browser` VARCHAR(100) NULL,
                        `os` VARCHAR(100) NULL,
                        `screen_resolution` VARCHAR(20) NULL,
                        `language` VARCHAR(10) NULL,
                        `country_code` VARCHAR(2) NULL,
                        `city` VARCHAR(100) NULL,
                        `visit_duration` INT UNSIGNED NULL DEFAULT 0 COMMENT 'Doba na stránce v sekundách',
                        `is_bounce` TINYINT(1) DEFAULT 0 COMMENT 'Odskočení bez další interakce',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX `idx_session` (`session_id`),
                        INDEX `idx_user` (`user_id`),
                        INDEX `idx_ip` (`ip_address`),
                        INDEX `idx_page` (`page_url`(100)),
                        INDEX `idx_created` (`created_at`),
                        INDEX `idx_device` (`device_type`),
                        INDEX `idx_country` (`country_code`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Web analytics - sledování návštěvnosti stránek';
                ";

                $pdo->exec($sql);

                // Vytvořit také tabulku pro ignorované IP adresy
                $sqlIgnored = "
                    CREATE TABLE IF NOT EXISTS `wgs_analytics_ignored_ips` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `ip_address` VARCHAR(45) NOT NULL UNIQUE,
                        `description` VARCHAR(200) NULL COMMENT 'Popis - např. Admin IP',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX `idx_ip` (`ip_address`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Ignorované IP adresy pro analytics';
                ";

                $pdo->exec($sqlIgnored);

                // Přidat admin IP do ignorovaných
                $stmtInsert = $pdo->prepare("
                    INSERT IGNORE INTO wgs_analytics_ignored_ips (ip_address, description)
                    VALUES (:ip, :desc)
                ");

                foreach ($adminIPs as $ip => $desc) {
                    $stmtInsert->execute(['ip' => $ip, 'desc' => $desc]);
                }

                $pdo->commit();
                $migrationSuccess = true;

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }

            // Výpis výsledku migrace (mimo try-catch)
            if ($migrationSuccess) {
                echo "<div class='success'>";
                echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "Vytvořené tabulky:<br>";
                echo "• <code>wgs_pageviews</code> - sledování návštěvnosti<br>";
                echo "• <code>wgs_analytics_ignored_ips</code> - ignorované IP adresy<br><br>";
                echo "Přidané ignorované IP adresy:<br>";
                foreach ($adminIPs as $ip => $desc) {
                    echo "• <code>$ip</code> - $desc<br>";
                }
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>Další kroky:</strong><br>";
                echo "1. Tracking skript bude automaticky zaznamenávat návštěvy<br>";
                echo "2. Vaše IP bude ignorována<br>";
                echo "3. Analytics budou zobrazovat skutečná data<br>";
                echo "</div>";
            }
        }
    } else {
        // Náhled co bude provedeno
        if (!$existuje) {
            echo "<h3>Co bude vytvořeno:</h3>";
            echo "<div class='info'>";
            echo "<strong>Tabulka:</strong> <code>wgs_pageviews</code><br>";
            echo "<strong>Sloupce:</strong><br>";
            echo "• session_id, user_id, ip_address, user_agent<br>";
            echo "• page_url, page_title, referrer<br>";
            echo "• device_type, browser, os, screen_resolution<br>";
            echo "• language, country_code, city<br>";
            echo "• visit_duration, is_bounce, created_at<br><br>";

            echo "<strong>Tabulka:</strong> <code>wgs_analytics_ignored_ips</code><br>";
            echo "<strong>Ignorované IP:</strong><br>";
            echo "• <code>46.135.14.161</code> (Admin IPv4)<br>";
            echo "• <code>2a00:11b1:100d:445b:550:e51:9352:3106</code> (Admin IPv6)<br>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>🚀 SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn' style='background: #666;'>← Zpět na Admin</a>";
echo "</div></body></html>";
?>
