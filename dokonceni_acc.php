<?php
/**
 * Migrace: Dokončení Admin Control Center
 *
 * Tento skript BEZPEČNĚ:
 * 1. Přidá chybějící sloupec scheduled_at do wgs_pending_actions
 * 2. Vytvoří chybějící tabulky wgs_content_texts a wgs_github_webhooks
 *
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
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
    <title>Migrace: Dokončení ACC</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
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
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; font-size: 1em; cursor: pointer; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa;
                border-left: 4px solid #2D5016; }
        .step-title { font-weight: bold; font-size: 1.1em; margin-bottom: 10px; }
        ul { margin: 10px 0; padding-left: 25px; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Migrace: Dokončení Admin Control Center</h1>";

    // 1. Kontrolní fáze
    echo "<div class='info'><strong>FÁZE 1: KONTROLA...</strong></div>";

    $chybejici = [];
    $existujici = [];

    // Kontrola sloupce scheduled_at
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pending_actions LIKE 'scheduled_at'");
    if ($stmt->rowCount() === 0) {
        $chybejici[] = "Sloupec scheduled_at v tabulce wgs_pending_actions";
    } else {
        $existujici[] = "Sloupec scheduled_at v tabulce wgs_pending_actions";
    }

    // Kontrola tabulky wgs_content_texts
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_content_texts'");
    if ($stmt->rowCount() === 0) {
        $chybejici[] = "Tabulka wgs_content_texts";
    } else {
        $existujici[] = "Tabulka wgs_content_texts";
    }

    // Kontrola tabulky wgs_github_webhooks
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_github_webhooks'");
    if ($stmt->rowCount() === 0) {
        $chybejici[] = "Tabulka wgs_github_webhooks";
    } else {
        $existujici[] = "Tabulka wgs_github_webhooks";
    }

    if (empty($chybejici)) {
        echo "<div class='success'>";
        echo "<strong>✅ VŠE JE KOMPLETNÍ</strong><br>";
        echo "Všechny požadované sloupce a tabulky již existují.";
        echo "</div>";

        echo "<ul>";
        foreach ($existujici as $item) {
            echo "<li>✅ {$item}</li>";
        }
        echo "</ul>";

        echo "<a href='admin.php?tab=control_center_actions' class='btn'>← Zpět do Admin Panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='warning'>";
    echo "<strong>⚠️ NALEZENY CHYBĚJÍCÍ KOMPONENTY:</strong><br>";
    echo "<ul>";
    foreach ($chybejici as $item) {
        echo "<li>❌ {$item}</li>";
    }
    echo "</ul>";
    echo "</div>";

    if (!empty($existujici)) {
        echo "<div class='info'>";
        echo "<strong>ℹ️ Již existující komponenty (budou přeskočeny):</strong><br>";
        echo "<ul>";
        foreach ($existujici as $item) {
            echo "<li>✅ {$item}</li>";
        }
        echo "</ul>";
        echo "</div>";
    }

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>FÁZE 2: SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            $provedeneOperace = [];

            // 1. Přidat sloupec scheduled_at do wgs_pending_actions
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pending_actions LIKE 'scheduled_at'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("
                    ALTER TABLE wgs_pending_actions
                    ADD COLUMN scheduled_at TIMESTAMP NULL DEFAULT NULL AFTER created_at
                ");
                $provedeneOperace[] = "✅ Přidán sloupec <code>scheduled_at</code> do <code>wgs_pending_actions</code>";
            }

            // 2. Vytvořit tabulku wgs_content_texts
            $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_content_texts'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("
                    CREATE TABLE wgs_content_texts (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        page VARCHAR(50) NOT NULL COMMENT 'Stránka: homepage, about, contact',
                        section VARCHAR(50) NOT NULL COMMENT 'Sekce: hero, features, footer',
                        text_key VARCHAR(50) NOT NULL COMMENT 'Klíč textu: title, description',
                        value_cz TEXT DEFAULT NULL COMMENT 'Text v češtině',
                        value_en TEXT DEFAULT NULL COMMENT 'Text v angličtině',
                        value_sk TEXT DEFAULT NULL COMMENT 'Text ve slovenštině',
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        updated_by INT DEFAULT NULL COMMENT 'ID uživatele',

                        UNIQUE KEY unique_page_section_key (page, section, text_key),
                        INDEX idx_page (page),
                        INDEX idx_section (section),

                        FOREIGN KEY (updated_by) REFERENCES wgs_users(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Editovatelné texty pro jednotlivé stránky webu'
                ");
                $provedeneOperace[] = "✅ Vytvořena tabulka <code>wgs_content_texts</code>";
            }

            // 3. Vytvořit tabulku wgs_github_webhooks
            $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_github_webhooks'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("
                    CREATE TABLE wgs_github_webhooks (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        event_type VARCHAR(50) NOT NULL COMMENT 'push, pull_request, issues',
                        repository VARCHAR(255) NOT NULL COMMENT 'Název repozitáře',
                        branch VARCHAR(100) DEFAULT NULL COMMENT 'Branch (pro push)',
                        commit_sha VARCHAR(40) DEFAULT NULL COMMENT 'SHA commitu',
                        commit_message TEXT DEFAULT NULL COMMENT 'Zpráva commitu',
                        author VARCHAR(255) DEFAULT NULL COMMENT 'Autor',
                        payload JSON DEFAULT NULL COMMENT 'Celý webhook payload',
                        processed TINYINT(1) DEFAULT 0 COMMENT 'Zda byl webhook zpracován',
                        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        INDEX idx_event_type (event_type),
                        INDEX idx_repository (repository),
                        INDEX idx_processed (processed),
                        INDEX idx_received_at (received_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='GitHub webhook události pro automatizaci'
                ");
                $provedeneOperace[] = "✅ Vytvořena tabulka <code>wgs_github_webhooks</code>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "<div class='step-title'>Provedené operace:</div>";
            echo "<ul>";
            foreach ($provedeneOperace as $operace) {
                echo "<li>{$operace}</li>";
            }
            echo "</ul>";
            echo "</div>";

            // Statistiky
            echo "<div class='step'>";
            echo "<div class='step-title'>📊 Souhrn:</div>";
            echo "<ul>";
            echo "<li><strong>Přidáno sloupců:</strong> " . (in_array("✅ Přidán sloupec <code>scheduled_at</code> do <code>wgs_pending_actions</code>", $provedeneOperace) ? "1" : "0") . "</li>";
            echo "<li><strong>Vytvořeno tabulek:</strong> ";
            $tabulekVytvoreno = 0;
            if (in_array("✅ Vytvořena tabulka <code>wgs_content_texts</code>", $provedeneOperace)) $tabulekVytvoreno++;
            if (in_array("✅ Vytvořena tabulka <code>wgs_github_webhooks</code>", $provedeneOperace)) $tabulekVytvoreno++;
            echo "{$tabulekVytvoreno}</li>";
            echo "</ul>";
            echo "</div>";

            echo "<div class='success'>";
            echo "<strong>🎉 ADMIN CONTROL CENTER JE NYNÍ KOMPLETNÍ!</strong><br><br>";
            echo "Všechny komponenty jsou nainstalované:<br>";
            echo "✅ Sloupec scheduled_at přidán<br>";
            echo "✅ Tabulka wgs_content_texts vytvořena<br>";
            echo "✅ Tabulka wgs_github_webhooks vytvořena<br><br>";
            echo "<strong>Nyní můžete:</strong><br>";
            echo "• Spouštět akce v kartě Akce & Úkoly<br>";
            echo "• Přidávat nové úkoly<br>";
            echo "• Editovat texty stránek<br>";
            echo "• Sledovat GitHub události<br>";
            echo "</div>";

            echo "<a href='admin.php?tab=control_center_actions' class='btn'>← Zpět do Admin Panelu</a>";
            echo "<a href='aktualizuj_akce_ukoly.php?execute=1' class='btn' style='background: #007bff;'>🔄 Spustit aktualizaci úkolů</a>";
            echo "<a href='zjisti_chybejici_tabulky.php' class='btn' style='background: #6c757d;'>📊 Zkontrolovat stav</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
            echo "<a href='dokonceni_acc.php' class='btn'>🔄 Zkusit znovu</a>";
        }
    } else {
        // Náhled co bude provedeno
        echo "<div class='step'>";
        echo "<div class='step-title'>📝 CO BUDE PROVEDENO:</div>";
        echo "<table>";
        echo "<tr><th>Operace</th><th>Typ</th><th>Popis</th></tr>";

        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pending_actions LIKE 'scheduled_at'");
        if ($stmt->rowCount() === 0) {
            echo "<tr>";
            echo "<td><code>ALTER TABLE</code></td>";
            echo "<td>Přidání sloupce</td>";
            echo "<td>Přidá sloupec <code>scheduled_at</code> do <code>wgs_pending_actions</code> pro plánování akcí</td>";
            echo "</tr>";
        }

        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_content_texts'");
        if ($stmt->rowCount() === 0) {
            echo "<tr>";
            echo "<td><code>CREATE TABLE</code></td>";
            echo "<td>Nová tabulka</td>";
            echo "<td>Vytvoří tabulku <code>wgs_content_texts</code> pro editovatelné texty (6 sloupců)</td>";
            echo "</tr>";
        }

        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_github_webhooks'");
        if ($stmt->rowCount() === 0) {
            echo "<tr>";
            echo "<td><code>CREATE TABLE</code></td>";
            echo "<td>Nová tabulka</td>";
            echo "<td>Vytvoří tabulku <code>wgs_github_webhooks</code> pro GitHub integraci (10 sloupců)</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "</div>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
        echo "<ul>";
        echo "<li>Migrace je bezpečná a NEVRATNÁ</li>";
        echo "<li>Pokud spustíte vícekrát, neprovede duplicitní operace</li>";
        echo "<li>Všechny existující data zůstanou zachována</li>";
        echo "<li>Doporučujeme nejprve vytvořit zálohu databáze</li>";
        echo "</ul>";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>✅ SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php?tab=control_center_actions' class='btn' style='background: #6c757d;'>← Zpět bez změn</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ KRITICKÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
    echo "<a href='admin.php?tab=control_center_actions' class='btn'>← Zpět do Admin Panelu</a>";
}

echo "</div></body></html>";
?>