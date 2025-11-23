<?php
/**
 * Migrace: Modul #4 - Geolocation Service
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku pro ukládání geolokačních dat z IP adres.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 *
 * Co tento skript dělá:
 * 1. Vytvoří tabulku `wgs_analytics_geolocation_cache` pro cache geolokačních dat
 * 2. Přidá všechny potřebné indexy pro optimální výkon
 * 3. Integruje se s Modulem #2 (Session Tracking)
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #4 - Geolocation Service
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
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migrace: Modul #4 - Geolocation Service</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        h2 {
            color: #2D5016;
            margin-top: 30px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            font-weight: bold;
        }
        .btn:hover {
            background: #1a300d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #2D5016;
            color: white;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Modul #4 - Geolocation Service</h1>";
    echo "<p><strong>Datum:</strong> " . date('d.m.Y H:i:s') . "</p>";

    // =======================================================
    // KONTROLNÍ FÁZE - Zjistit aktuální stav databáze
    // =======================================================
    echo "<h2>KROK 1: Kontrola aktuálního stavu</h2>";

    $tabulkyKKontrole = [
        'wgs_analytics_geolocation_cache'
    ];

    $existujiciTabulky = [];
    $chybejiciTabulky = [];

    foreach ($tabulkyKKontrole as $tabulka) {
        // SHOW TABLES LIKE nepodporuje bind parametry - použít přímý dotaz
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
        if ($stmt && $stmt->rowCount() > 0) {
            $existujiciTabulky[] = $tabulka;
        } else {
            $chybejiciTabulky[] = $tabulka;
        }
    }

    // Zobrazit výsledky kontroly
    if (count($existujiciTabulky) > 0) {
        echo "<div class='warning'>";
        echo "<strong>VAROVÁNÍ:</strong> Některé tabulky již existují:<br>";
        foreach ($existujiciTabulky as $tab) {
            echo "- <code>{$tab}</code><br>";
        }
        echo "</div>";
    }

    if (count($chybejiciTabulky) > 0) {
        echo "<div class='info'>";
        echo "<strong>INFO:</strong> Následující tabulky budou vytvořeny:<br>";
        foreach ($chybejiciTabulky as $tab) {
            echo "- <code>{$tab}</code><br>";
        }
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "<strong>DOKONČENO:</strong> Všechny tabulky pro Modul #4 již existují.";
        echo "</div>";
        echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
        echo "</div></body></html>";
        exit;
    }

    // =======================================================
    // POKUD JE ?execute=1, PROVÉST MIGRACI
    // =======================================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>KROK 2: Spouštím migraci...</h2>";

        $pdo->beginTransaction();

        try {
            $vytvorenoTabulek = 0;
            $pridanoIndexu = 0;

            // ===============================================
            // TABULKA: wgs_analytics_geolocation_cache
            // ===============================================
            if (in_array('wgs_analytics_geolocation_cache', $chybejiciTabulky)) {
                echo "<div class='info'><strong>Vytvářím tabulku:</strong> wgs_analytics_geolocation_cache</div>";

                $pdo->exec("
                    CREATE TABLE `wgs_analytics_geolocation_cache` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP adresa (IPv4 nebo IPv6)',
                        `country_code` CHAR(2) NULL COMMENT 'ISO kód země (CZ, SK, DE...)',
                        `country_name` VARCHAR(100) NULL COMMENT 'Název země',
                        `city` VARCHAR(100) NULL COMMENT 'Název města',
                        `region` VARCHAR(100) NULL COMMENT 'Region/kraj',
                        `latitude` DECIMAL(10, 7) NULL COMMENT 'Zeměpisná šířka',
                        `longitude` DECIMAL(10, 7) NULL COMMENT 'Zeměpisná délka',
                        `timezone` VARCHAR(50) NULL COMMENT 'Časová zóna (Europe/Prague)',
                        `isp` VARCHAR(200) NULL COMMENT 'Poskytovatel internetu',
                        `is_vpn` BOOLEAN DEFAULT FALSE COMMENT 'Detekce VPN',
                        `is_datacenter` BOOLEAN DEFAULT FALSE COMMENT 'Detekce datacentra',
                        `api_source` ENUM('ipapi', 'ip-api', 'default') DEFAULT 'ipapi' COMMENT 'Zdroj dat',
                        `cached_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Kdy bylo uloženo do cache',
                        `expires_at` DATETIME NOT NULL COMMENT 'Kdy vyprší platnost cache (cached_at + 3 dny)',
                        `last_accessed` DATETIME NULL COMMENT 'Poslední přístup k cache',

                        PRIMARY KEY (`id`),
                        UNIQUE INDEX `idx_ip_address` (`ip_address`),
                        INDEX `idx_expires_at` (`expires_at`),
                        INDEX `idx_country_code` (`country_code`),
                        INDEX `idx_is_vpn` (`is_vpn`),
                        INDEX `idx_is_datacenter` (`is_datacenter`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Cache geolokačních dat z IP adres (TTL 3 dny)'
                ");

                $vytvorenoTabulek++;
                $pridanoIndexu += 6; // PRIMARY + 5 indexů

                echo "<div class='success'>Tabulka <code>wgs_analytics_geolocation_cache</code> úspěšně vytvořena (16 sloupců, 6 indexů)</div>";
            }

            // Commit transakce
            $pdo->commit();

            // =======================================================
            // SOUHRN MIGRACE
            // =======================================================
            echo "<h2>SHRNUTÍ MIGRACE</h2>";

            echo "<table>";
            echo "<tr><th>Operace</th><th>Počet</th></tr>";
            echo "<tr><td>Vytvořeno tabulek</td><td><strong>{$vytvorenoTabulek}</strong></td></tr>";
            echo "<tr><td>Přidáno indexů</td><td><strong>{$pridanoIndexu}</strong></td></tr>";
            echo "</table>";

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Modul #4 (Geolocation Service) je nyní připraven k použití.";
            echo "</div>";

            // =======================================================
            // NEXT STEPS
            // =======================================================
            echo "<h2>DALŠÍ KROKY</h2>";
            echo "<div class='info'>";
            echo "<strong>Co dál:</strong><br>";
            echo "1. Zkontrolujte strukturu tabulky přes SQL kartu v Control Centre<br>";
            echo "2. Otestujte GeolocationService třídu přes tracking API<br>";
            echo "3. Nastavte cron job pro <code>cleanup_geo_cache.php</code> (denně 4:00)<br>";
            echo "4. Sledujte cache hit ratio v admin dashboardu<br>";
            echo "</div>";

            echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
            echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit SQL strukturu</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();

            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";

            error_log("Migrace Module #4 FAILED: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }

    } else {
        // =======================================================
        // ZOBRAZIT NÁHLED CO BUDE PROVEDENO
        // =======================================================
        echo "<h2>KROK 2: Co bude provedeno?</h2>";

        echo "<div class='info'>";
        echo "<strong>Tato migrace provede následující operace:</strong>";
        echo "<ol>";
        echo "<li>Vytvoří tabulku <code>wgs_analytics_geolocation_cache</code> (16 sloupců, 6 indexů)</li>";
        echo "<li>Nastaví cache TTL na 3 dny</li>";
        echo "<li>Přidá indexy pro optimalizaci dotazů</li>";
        echo "</ol>";
        echo "</div>";

        echo "<h3>Detaily tabulky: wgs_analytics_geolocation_cache</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Popis</th></tr>";
        echo "<tr><td>id</td><td>INT UNSIGNED PK</td><td>Primární klíč</td></tr>";
        echo "<tr><td>ip_address</td><td>VARCHAR(45) UNIQUE</td><td>IP adresa (IPv4/IPv6)</td></tr>";
        echo "<tr><td>country_code</td><td>CHAR(2)</td><td>ISO kód země</td></tr>";
        echo "<tr><td>country_name</td><td>VARCHAR(100)</td><td>Název země</td></tr>";
        echo "<tr><td>city</td><td>VARCHAR(100)</td><td>Název města</td></tr>";
        echo "<tr><td>region</td><td>VARCHAR(100)</td><td>Region/kraj</td></tr>";
        echo "<tr><td>latitude</td><td>DECIMAL(10,7)</td><td>Zeměpisná šířka</td></tr>";
        echo "<tr><td>longitude</td><td>DECIMAL(10,7)</td><td>Zeměpisná délka</td></tr>";
        echo "<tr><td>timezone</td><td>VARCHAR(50)</td><td>Časová zóna</td></tr>";
        echo "<tr><td>isp</td><td>VARCHAR(200)</td><td>ISP poskytovatel</td></tr>";
        echo "<tr><td>is_vpn</td><td>BOOLEAN</td><td>Detekce VPN</td></tr>";
        echo "<tr><td>is_datacenter</td><td>BOOLEAN</td><td>Detekce datacentra</td></tr>";
        echo "<tr><td>api_source</td><td>ENUM</td><td>Zdroj dat (ipapi/ip-api/default)</td></tr>";
        echo "<tr><td>cached_at</td><td>DATETIME</td><td>Čas uložení do cache</td></tr>";
        echo "<tr><td>expires_at</td><td>DATETIME</td><td>Čas expirace (3 dny)</td></tr>";
        echo "<tr><td>last_accessed</td><td>DATETIME</td><td>Poslední přístup</td></tr>";
        echo "</table>";

        echo "<h3>Indexy</h3>";
        echo "<ul>";
        echo "<li><code>PRIMARY KEY</code> na <code>id</code></li>";
        echo "<li><code>UNIQUE INDEX</code> na <code>ip_address</code> (optimalizace cache lookup)</li>";
        echo "<li><code>INDEX</code> na <code>expires_at</code> (rychlé čištění vypršené cache)</li>";
        echo "<li><code>INDEX</code> na <code>country_code</code> (statistiky podle zemí)</li>";
        echo "<li><code>INDEX</code> na <code>is_vpn</code> (detekce VPN traffic)</li>";
        echo "<li><code>INDEX</code> na <code>is_datacenter</code> (detekce datacenter traffic)</li>";
        echo "</ul>";

        echo "<div class='warning'>";
        echo "<strong>POZOR:</strong> Tato migrace je BEZPEČNÁ a IDEMPOTENTNÍ (můžete ji spustit vícekrát).";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn' style='background: #6c757d;'>Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>NEOČEKÁVANÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";

    error_log("Migrace Module #4 Exception: " . $e->getMessage());
}

echo "</div></body></html>";
?>
