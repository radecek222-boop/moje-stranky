<?php
/**
 * Migrace: Multi-tenant základ - WGS Service
 *
 * Tento skript BEZPEČNĚ zavede multi-tenant architekturu:
 * 1. Vytvoří tabulku wgs_tenants
 * 2. Vloží výchozí tenant "default"
 * 3. Přidá sloupec tenant_id do klíčových tabulek (pokud ještě neexistuje)
 * 4. Nastaví tenant_id = 1 pro všechny existující záznamy
 * 5. Přidá indexy na tenant_id
 *
 * Lze spustit opakovaně — idempotentní operace.
 */

require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('PŘÍSTUP ODEPŘEN: Pouze administrátor.');
}

// Tabulky, do kterých přidáme tenant_id
$tabulkyProTenantId = [
    'wgs_reklamace',
    'wgs_users',
    'wgs_registration_keys',
    'wgs_email_queue',
    'wgs_pending_actions',
];

$spustit = isset($_GET['execute']) && $_GET['execute'] === '1';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Multi-tenant základ</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        h1 { border-bottom: 2px solid #333; padding-bottom: 10px; }
        .ok   { background: #e8f5e9; border: 1px solid #c8e6c9; padding: 10px 14px; border-radius: 4px; margin: 8px 0; }
        .info { background: #e3f2fd; border: 1px solid #bbdefb; padding: 10px 14px; border-radius: 4px; margin: 8px 0; }
        .warn { background: #fff8e1; border: 1px solid #ffe082; padding: 10px 14px; border-radius: 4px; margin: 8px 0; }
        .err  { background: #ffebee; border: 1px solid #ffcdd2; padding: 10px 14px; border-radius: 4px; margin: 8px 0; }
        .btn  { display: inline-block; padding: 10px 24px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 16px; font-weight: 600; }
        code  { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
    </style>
</head>
<body><div class='container'>
<h1>Migrace: Multi-tenant základ</h1>";

try {
    $pdo = getDbConnection();

    // ============================================
    // KROK 1: Tabulka wgs_tenants
    // ============================================
    echo "<h2>Krok 1: Tabulka <code>wgs_tenants</code></h2>";

    $existujeTabulka = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wgs_tenants'"
    )->fetchColumn() > 0;

    if ($existujeTabulka) {
        echo "<div class='info'>Tabulka <code>wgs_tenants</code> již existuje — přeskakuji.</div>";
    } else {
        if ($spustit) {
            $pdo->exec("
                CREATE TABLE wgs_tenants (
                    tenant_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    slug           VARCHAR(64)  NOT NULL,
                    nazev          VARCHAR(255) NOT NULL,
                    domena         VARCHAR(255) NOT NULL DEFAULT '',
                    nastaveni_json TEXT         NULL,
                    je_aktivni     TINYINT(1)   NOT NULL DEFAULT 1,
                    datum_vytvoreni DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (tenant_id),
                    UNIQUE KEY uq_slug (slug),
                    KEY idx_domena (domena)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='ok'>Tabulka <code>wgs_tenants</code> vytvořena.</div>";
        } else {
            echo "<div class='warn'>Bude vytvořena tabulka <code>wgs_tenants</code>.</div>";
        }
    }

    // ============================================
    // KROK 2: Výchozí tenant
    // ============================================
    echo "<h2>Krok 2: Výchozí tenant</h2>";

    if ($existujeTabulka || $spustit) {
        $pocetTenatu = $pdo->query("SELECT COUNT(*) FROM wgs_tenants WHERE slug = 'default'")->fetchColumn();
        if ($pocetTenatu > 0) {
            echo "<div class='info'>Výchozí tenant <code>default</code> již existuje — přeskakuji.</div>";
        } else {
            if ($spustit) {
                $pdo->exec("
                    INSERT INTO wgs_tenants (tenant_id, slug, nazev, je_aktivni)
                    VALUES (1, 'default', 'WGS Service (výchozí)', 1)
                ");
                echo "<div class='ok'>Výchozí tenant <code>default</code> (ID=1) vložen.</div>";
            } else {
                echo "<div class='warn'>Bude vložen výchozí tenant <code>default</code> (ID=1).</div>";
            }
        }
    }

    // ============================================
    // KROK 3: Přidání tenant_id do tabulek
    // ============================================
    echo "<h2>Krok 3: Sloupec <code>tenant_id</code> v tabulkách</h2>";

    foreach ($tabulkyProTenantId as $tabulka) {
        // Kontrola existence tabulky
        $tabulkaExistuje = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabulka}'"
        )->fetchColumn() > 0;

        if (!$tabulkaExistuje) {
            echo "<div class='warn'>Tabulka <code>{$tabulka}</code> neexistuje — přeskakuji.</div>";
            continue;
        }

        // Kontrola existence sloupce
        $sloupecExistuje = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = '{$tabulka}'
               AND COLUMN_NAME  = 'tenant_id'"
        )->fetchColumn() > 0;

        if ($sloupecExistuje) {
            echo "<div class='info'><code>{$tabulka}.tenant_id</code> již existuje — přeskakuji.</div>";
            continue;
        }

        if ($spustit) {
            // Přidat sloupec
            $pdo->exec("
                ALTER TABLE `{$tabulka}`
                ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1
                    COMMENT 'FK na wgs_tenants.tenant_id'
                    AFTER " . (str_contains($tabulka, 'reklamace') ? '`reklamace_id`' : '`id`') . "
            ");
            // Nastavit existující záznamy na výchozí tenant
            $pdo->exec("UPDATE `{$tabulka}` SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL");
            // Index
            $pdo->exec("ALTER TABLE `{$tabulka}` ADD INDEX `idx_tenant_id` (`tenant_id`)");
            $pocet = $pdo->query("SELECT COUNT(*) FROM `{$tabulka}`")->fetchColumn();
            echo "<div class='ok'><code>{$tabulka}</code>: přidán <code>tenant_id</code>, nastaveno {$pocet} záznamů na tenant_id=1.</div>";
        } else {
            $pocet = $pdo->query("SELECT COUNT(*) FROM `{$tabulka}`")->fetchColumn();
            echo "<div class='warn'>Bude přidán <code>tenant_id</code> do <code>{$tabulka}</code> ({$pocet} existujících záznamů → tenant_id=1).</div>";
        }
    }

    // ============================================
    // VÝSLEDEK
    // ============================================
    if ($spustit) {
        echo "<h2>Migrace dokončena</h2>";
        echo "<div class='ok'><strong>Migrace proběhla úspěšně.</strong><br>
              Multi-tenant základ je připraven. Všechna existující data jsou přiřazena výchozímu tenantovi (ID=1).<br><br>
              Dalším krokem je aktivace <code>TenantManager</code> v <code>init.php</code> a přidání <code>tenant_id</code> podmínek do SQL dotazů.</div>";
        echo "<a href='/admin.php' class='btn'>Zpět na admin panel</a>";
    } else {
        echo "<h2>Náhled změn</h2>";
        echo "<div class='info'>Výše uvedené změny budou provedeny po kliknutí na tlačítko níže.<br>Operace je bezpečná — existující data budou zachována.</div>";
        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    error_log('Migrace multi-tenant chyba: ' . $e->getMessage());
    echo "<div class='err'><strong>Chyba:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
