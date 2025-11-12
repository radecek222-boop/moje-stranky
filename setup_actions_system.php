<?php
/**
 * SETUP SCRIPT: Actions System
 * Vytvoří tabulky wgs_pending_actions a wgs_action_history
 * a přidá iniciální pending actions
 *
 * Použití: Navštivte tuto stránku jako admin v browseru
 * URL: https://www.wgs-service.cz/setup_actions_system.php
 */

require_once __DIR__ . '/init.php';

// ============================================
// BEZPEČNOSTNÍ KONTROLA - POUZE ADMIN
// ============================================
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('
    <html>
    <head>
        <title>Přístup odepřen</title>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; padding: 2rem; max-width: 600px; margin: 0 auto; }
            .error { background: #FFE5E5; border: 2px solid #DC3545; padding: 2rem; border-radius: 8px; }
            h1 { color: #DC3545; margin: 0 0 1rem 0; }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>🔒 Přístup odepřen</h1>
            <p>Tento setup script mohou spouštět pouze administrátoři.</p>
            <p><a href="login.php">Přihlásit se jako admin</a></p>
        </div>
    </body>
    </html>
    ');
}

header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup: Actions System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 2rem;
        }
        .step {
            background: #F8F9FA;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .step.success {
            border-left-color: #28A745;
            background: #E8F5E9;
        }
        .step.error {
            border-left-color: #DC3545;
            background: #FFE5E5;
        }
        .step.warning {
            border-left-color: #FFC107;
            background: #FFF9E6;
        }
        .step-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        .step-content {
            color: #666;
            line-height: 1.6;
        }
        code {
            background: #2D2D2D;
            color: #F8F8F2;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        pre {
            background: #2D2D2D;
            color: #F8F8F2;
            padding: 1rem;
            border-radius: 6px;
            overflow-x: auto;
            margin: 1rem 0;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            margin-top: 1rem;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn.success {
            background: #28A745;
        }
        .btn.success:hover {
            background: #218838;
        }
        .icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-top: 2rem;
        }
        .summary h2 {
            margin-bottom: 1rem;
        }
        .summary ul {
            list-style: none;
            padding-left: 0;
        }
        .summary li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .summary li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

<div class="container">
    <h1><span class="icon">🚀</span>Setup: Actions System</h1>
    <p style="color: #666; margin-bottom: 2rem;">
        Tento script nastaví systém akcí a úkolů pro Admin Control Center.
        Automaticky vytvoří potřebné databázové tabulky a přidá iniciální úkoly.
    </p>

<?php

// ============================================
// KROK 1: Připojení k databázi
// ============================================
echo '<div class="step">';
echo '<div class="step-title">⚙️ Krok 1: Připojení k databázi</div>';
echo '<div class="step-content">';

try {
    $pdo = getDbConnection();
    echo '✅ Připojení k databázi: <code>Úspěšné</code>';
} catch (Exception $e) {
    echo '❌ Chyba připojení: <code>' . htmlspecialchars($e->getMessage()) . '</code>';
    echo '</div></div>';
    echo '<a href="admin.php" class="btn">← Zpět do Admin panelu</a>';
    echo '</div></body></html>';
    exit;
}

echo '</div></div>';

// ============================================
// KROK 2: Kontrola existujících tabulek
// ============================================
echo '<div class="step">';
echo '<div class="step-title">🔍 Krok 2: Kontrola existujících tabulek</div>';
echo '<div class="step-content">';

$tablesExist = [];
$tablesToCheck = ['wgs_pending_actions', 'wgs_action_history'];

foreach ($tablesToCheck as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        $tablesExist[$table] = $exists;

        if ($exists) {
            echo "✅ Tabulka <code>$table</code> již existuje<br>";
        } else {
            echo "⚠️ Tabulka <code>$table</code> neexistuje - bude vytvořena<br>";
        }
    } catch (PDOException $e) {
        echo "❌ Chyba při kontrole tabulky <code>$table</code>: " . htmlspecialchars($e->getMessage()) . "<br>";
    }
}

echo '</div></div>';

// ============================================
// KROK 3: Vytvoření tabulek (pokud neexistují)
// ============================================
if (!$tablesExist['wgs_pending_actions'] || !$tablesExist['wgs_action_history']) {
    echo '<div class="step success">';
    echo '<div class="step-title">🔨 Krok 3: Vytváření tabulek</div>';
    echo '<div class="step-content">';

    try {
        // Načtení SQL migrace
        $migrationFile = __DIR__ . '/migrations/create_actions_system.sql';

        if (!file_exists($migrationFile)) {
            throw new Exception("Migrační soubor nenalezen: $migrationFile");
        }

        $sql = file_get_contents($migrationFile);

        // Rozdělit na jednotlivé příkazy (oddělené středníky)
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) &&
                       strpos($stmt, '--') !== 0 &&
                       strpos($stmt, 'SELECT') !== 0;
            }
        );

        $executedCount = 0;
        foreach ($statements as $statement) {
            if (empty($statement)) continue;

            try {
                $pdo->exec($statement);
                $executedCount++;
            } catch (PDOException $e) {
                // Ignorovat "table already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }

        echo "✅ Migrace úspěšně provedena<br>";
        echo "📊 Vykonáno <code>$executedCount</code> SQL příkazů<br>";

    } catch (Exception $e) {
        echo "❌ Chyba při vytváření tabulek: <code>" . htmlspecialchars($e->getMessage()) . "</code><br>";
        echo '</div></div>';
        echo '<a href="admin.php" class="btn">← Zpět do Admin panelu</a>';
        echo '</div></body></html>';
        exit;
    }

    echo '</div></div>';
} else {
    echo '<div class="step warning">';
    echo '<div class="step-title">ℹ️ Krok 3: Tabulky již existují</div>';
    echo '<div class="step-content">';
    echo 'Všechny potřebné tabulky již existují. Přeskakuji vytváření.';
    echo '</div></div>';
}

// ============================================
// KROK 4: Kontrola a přidání testovacích akcí
// ============================================
echo '<div class="step success">';
echo '<div class="step-title">✨ Krok 4: Kontrola pending actions</div>';
echo '<div class="step-content">';

try {
    // Zkontrolovat, kolik je pending actions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_pending_actions WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $result['count'];

    echo "📋 Počet nevyřešených úkolů: <code>$pendingCount</code><br>";

    // Pokud je prázdná tabulka, přidat testovací akce
    if ($pendingCount == 0) {
        echo "<br>➕ Přidávám testovací pending actions...<br><br>";

        // Akce 1: SMTP konfigurace
        $stmt = $pdo->prepare("
            INSERT INTO wgs_pending_actions (
                action_type, action_title, action_description, priority, status
            ) VALUES (
                :type, :title, :description, :priority, 'pending'
            )
        ");

        $actions = [
            [
                'type' => 'install_smtp',
                'title' => '📧 Zkontrolovat SMTP konfiguraci',
                'description' => 'Ověřte, že SMTP nastavení v Control Center → Konfigurace systému je správně nastaveno pro odesílání emailů.',
                'priority' => 'high'
            ],
            [
                'type' => 'check_photos',
                'title' => '📸 Zkontrolovat fotodokumentaci',
                'description' => 'Ujistěte se, že složka /uploads/photos/ má správná oprávnění (755) a je dostupná pro ukládání fotografií.',
                'priority' => 'medium'
            ],
            [
                'type' => 'backup',
                'title' => '💾 Nastavit pravidelné zálohy',
                'description' => 'Doporučujeme nastavit automatické zálohy databáze (denně) a souborů (týdně).',
                'priority' => 'medium'
            ],
            [
                'type' => 'security_audit',
                'title' => '🔒 Bezpečnostní audit',
                'description' => 'Zkontrolujte, že všechny admin účty mají silná hesla a 2FA je aktivní.',
                'priority' => 'low'
            ]
        ];

        foreach ($actions as $action) {
            $stmt->execute($action);
            echo "✅ Přidána akce: <strong>{$action['title']}</strong> (priorita: {$action['priority']})<br>";
        }

        echo "<br>🎉 Úspěšně přidáno <code>" . count($actions) . "</code> testovacích úkolů!";
    } else {
        echo "✅ Tabulka už obsahuje úkoly, nepřidávám nové.";
    }

} catch (PDOException $e) {
    echo "❌ Chyba: <code>" . htmlspecialchars($e->getMessage()) . "</code>";
}

echo '</div></div>';

// ============================================
// KROK 5: Ověření action_history tabulky
// ============================================
echo '<div class="step">';
echo '<div class="step-title">📜 Krok 5: Ověření historie akcí</div>';
echo '<div class="step-content">';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_action_history");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $historyCount = $result['count'];

    echo "✅ Tabulka <code>wgs_action_history</code> je funkční<br>";
    echo "📊 Počet záznamů v historii: <code>$historyCount</code>";

} catch (PDOException $e) {
    echo "⚠️ Upozornění: <code>" . htmlspecialchars($e->getMessage()) . "</code>";
}

echo '</div></div>';

// ============================================
// SHRNUTÍ
// ============================================
echo '<div class="summary">';
echo '<h2>✅ Setup dokončen!</h2>';
echo '<p style="margin-bottom: 1rem;">Systém akcí a úkolů byl úspěšně nastaven. Nyní můžete:</p>';
echo '<ul>';
echo '<li>✨ Zobrazit nevyřešené úkoly v <strong>Admin Control Center → Akce & Úkoly</strong></li>';
echo '<li>🔧 Spravovat pending actions z administrace</li>';
echo '<li>📊 Sledovat historii vykonaných akcí</li>';
echo '<li>🔗 Integrovat GitHub webhooks pro automatické úkoly</li>';
echo '</ul>';
echo '<a href="admin.php?tab=control_center" class="btn success">→ Otevřít Control Center</a>';
echo '</div>';

?>

</div>

<script>
console.log('✅ Actions System setup completed successfully');
</script>

</body>
</html>
