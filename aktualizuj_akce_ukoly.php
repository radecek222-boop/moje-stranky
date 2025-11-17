<?php
/**
 * Aktualizace Akcí & Úkolů v Admin Panelu
 *
 * Tento skript:
 * 1. Vymaže všechny staré nevyřešené úkoly
 * 2. Přidá aktuální úkol: Instalace PHPMailer
 *
 * Spuštění: https://www.wgs-service.cz/aktualizuj_akce_ukoly.php
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tuto migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Aktualizace Akcí & Úkolů</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
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
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: monospace; }
        .step { background: #e9ecef; padding: 15px; margin: 15px 0;
                border-left: 4px solid #007bff; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Aktualizace Akcí & Úkolů</h1>";

    // Pokud je nastaveno ?execute=1, provést aktualizaci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM AKTUALIZACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // KROK 1: Zobrazit aktuální úkoly
            $stmt = $pdo->query("
                SELECT id, action_title, priority, status
                FROM wgs_pending_actions
                WHERE status IN ('pending', 'in_progress')
                ORDER BY created_at DESC
            ");
            $oldActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($oldActions) > 0) {
                echo "<div class='step'>";
                echo "<strong>📋 KROK 1: Nalezené staré úkoly (" . count($oldActions) . ")</strong><br>";
                foreach ($oldActions as $action) {
                    echo "• [{$action['priority']}] {$action['action_title']}<br>";
                }
                echo "</div>";

                // KROK 2: Smazat všechny staré úkoly
                $deletedCount = $pdo->exec("
                    DELETE FROM wgs_pending_actions
                    WHERE status IN ('pending', 'in_progress')
                ");

                echo "<div class='success'>";
                echo "<strong>✓ KROK 2: Smazáno {$deletedCount} starých úkolů</strong>";
                echo "</div>";
            } else {
                echo "<div class='info'>";
                echo "<strong>ℹ️ KROK 1-2: Žádné staré úkoly k odstranění</strong>";
                echo "</div>";
            }

            // KROK 3: Přidat nový úkol - Instalace PHPMailer
            echo "<div class='step'>";
            echo "<strong>📥 KROK 3: Přidávám aktuální úkol...</strong><br>";
            echo "</div>";

            // Kontrola, zda PHPMailer úkol už neexistuje
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM wgs_pending_actions
                WHERE action_type = 'install_phpmailer'
                AND status IN ('pending', 'in_progress')
            ");
            $stmt->execute();
            $existuje = $stmt->fetchColumn() > 0;

            if (!$existuje) {
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_pending_actions (
                        action_type,
                        action_title,
                        action_description,
                        priority,
                        status,
                        created_at,
                        scheduled_at
                    ) VALUES (
                        'install_phpmailer',
                        'Instalace PHPMailer pro odesílání emailů',
                        'PHPMailer je potřeba pro funkční odesílání protokolů zákazníkům.\n\n🔧 INSTALACE:\n1. Otevřete: https://www.wgs-service.cz/scripts/install_phpmailer.php\n2. Nebo spusťte v terminálu:\n   cd /home/www/wgs-service.cz/www\n   php scripts/install_phpmailer.php\n\n✅ Po instalaci se emaily budou posílat správně přes SMTP.\n\n📚 DOKUMENTACE:\nViz PRAVIDLA_SPRAVA_DB_A_AKCI.md pro další info o správě akcí.',
                        'high',
                        'pending',
                        NOW(),
                        NOW()
                    )
                ");
                $stmt->execute();

                echo "<div class='success'>";
                echo "✓ Přidán úkol: Instalace PHPMailer<br>";
                echo "</div>";
            } else {
                echo "<div class='info'>";
                echo "ℹ️ Úkol 'Instalace PHPMailer' již existuje (ponechán)<br>";
                echo "</div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ AKTUALIZACE DOKONČENA!</strong><br><br>";
            echo "Karta 'Akce & Úkoly' byla vyčištěna a aktualizována.<br><br>";
            echo "<strong>Aktuální úkol:</strong><br>";
            echo "• [high] Instalace PHPMailer pro odesílání emailů<br>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>📌 DALŠÍ KROKY:</strong><br>";
            echo "1. Přejděte do <a href='admin.php'>Admin Panelu</a><br>";
            echo "2. Otevřete kartu 'Akce & Úkoly'<br>";
            echo "3. Klikněte na úkol 'Instalace PHPMailer'<br>";
            echo "4. Postupujte podle instrukcí v úkolu<br><br>";
            echo "<strong>📚 Dokumentace:</strong><br>";
            echo "Viz <a href='PRAVIDLA_SPRAVA_DB_A_AKCI.md'>PRAVIDLA_SPRAVA_DB_A_AKCI.md</a> pro pravidla správy databáze a akcí.";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Náhled co bude provedeno
        echo "<div class='warning'>";
        echo "<strong>⚠️ NÁHLED AKTUALIZACE</strong><br><br>";
        echo "Tento skript provede následující:<br>";
        echo "1. Smaže všechny nevyřešené úkoly z karty 'Akce & Úkoly'<br>";
        echo "2. Přidá nový úkol: 'Instalace PHPMailer pro odesílání emailů'<br>";
        echo "</div>";

        // Zobrazit aktuální úkoly
        $stmt = $pdo->query("
            SELECT id, action_title, priority, status
            FROM wgs_pending_actions
            WHERE status IN ('pending', 'in_progress')
            ORDER BY created_at DESC
        ");
        $currentActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($currentActions) > 0) {
            echo "<div class='info'>";
            echo "<strong>📋 Aktuální úkoly ke smazání (" . count($currentActions) . "):</strong><br>";
            foreach ($currentActions as $action) {
                echo "• [{$action['priority']}] {$action['action_title']}<br>";
            }
            echo "</div>";
        } else {
            echo "<div class='info'>";
            echo "<strong>ℹ️ Žádné úkoly ke smazání</strong>";
            echo "</div>";
        }

        echo "<div style='margin: 30px 0;'>";
        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT AKTUALIZACI</a>";
        echo "<a href='admin.php' class='btn' style='background: #6c757d;'>← Zpět do Admin Panelu</a>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
