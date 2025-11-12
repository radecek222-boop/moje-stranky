<?php
/**
 * DIAGNOSTICKÝ SCRIPT: Ověření a přidání SMTP úlohy
 *
 * Tento script:
 * 1. Zkontroluje databázové připojení
 * 2. Ověří existenci tabulky wgs_pending_actions
 * 3. Zobrazí aktuální úlohy
 * 4. Přidá SMTP instalační úlohu (pokud neexistuje)
 *
 * POUŽITÍ: Otevřete v prohlížeči: https://your-domain.com/check_and_add_smtp_task.php
 */

// Bez session - použijeme přímé připojení
define('BYPASS_SESSION_CHECK', true);

require_once __DIR__ . '/init.php';

// HTML hlavička
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostika SMTP úlohy</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #333; font-size: 1.5rem; margin: 0 0 0.5rem 0; }
        h2 { color: #666; font-size: 1.1rem; margin: 1rem 0 0.5rem 0; border-bottom: 2px solid #eee; padding-bottom: 0.5rem; }
        pre {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
            border-left: 4px solid #007bff;
        }
        .success { color: #28a745; font-weight: 600; }
        .error { color: #dc3545; font-weight: 600; }
        .warning { color: #ffc107; font-weight: 600; }
        .info { color: #17a2b8; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        table th, table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Diagnostika SMTP úlohy v systému akcí</h1>
        <p style="color: #666;">Datum: <?= date('Y-m-d H:i:s') ?></p>
    </div>

<?php

try {
    $pdo = getDbConnection();

    echo '<div class="card">';
    echo '<h2>✓ Krok 1: Databázové připojení</h2>';
    echo '<p class="success">✓ Připojení k databázi úspěšné</p>';
    echo '</div>';

    // Zkontrolovat, jestli tabulka existuje
    echo '<div class="card">';
    echo '<h2>🔍 Krok 2: Kontrola tabulky wgs_pending_actions</h2>';

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pending_actions'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo '<p class="success">✓ Tabulka wgs_pending_actions existuje</p>';

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_pending_actions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo '<h3>Struktura tabulky:</h3>';
        echo '<table>';
        echo '<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Default</th></tr>';
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo '</table>';
    } else {
        echo '<p class="error">✗ Tabulka wgs_pending_actions NEEXISTUJE!</p>';
        echo '<p class="warning">⚠ Musíte nejprve vytvořit tabulku. Kontaktujte vývojáře.</p>';
        echo '</div></body></html>';
        exit;
    }
    echo '</div>';

    // Zobrazit aktuální úlohy
    echo '<div class="card">';
    echo '<h2>📋 Krok 3: Aktuální úlohy v databázi</h2>';

    $stmt = $pdo->query("SELECT * FROM wgs_pending_actions ORDER BY created_at DESC");
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($actions) > 0) {
        echo '<p class="info">Nalezeno úloh: ' . count($actions) . '</p>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Typ</th><th>Název</th><th>Status</th><th>Priorita</th><th>Vytvořeno</th></tr>';
        foreach ($actions as $action) {
            $statusColor = $action['status'] === 'pending' ? 'warning' : ($action['status'] === 'completed' ? 'success' : 'error');
            echo "<tr>";
            echo "<td>{$action['id']}</td>";
            echo "<td>{$action['action_type']}</td>";
            echo "<td>{$action['action_title']}</td>";
            echo "<td class='{$statusColor}'>{$action['status']}</td>";
            echo "<td>{$action['priority']}</td>";
            echo "<td>{$action['created_at']}</td>";
            echo "</tr>";
        }
        echo '</table>';
    } else {
        echo '<p class="warning">⚠ Žádné úlohy v databázi nenalezeny</p>';
    }
    echo '</div>';

    // Zkontrolovat, jestli SMTP úloha už existuje
    echo '<div class="card">';
    echo '<h2>🔧 Krok 4: Kontrola SMTP instalační úlohy</h2>';

    $stmt = $pdo->prepare("SELECT * FROM wgs_pending_actions WHERE action_type = 'install_smtp' AND status = 'pending'");
    $stmt->execute();
    $smtpTask = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($smtpTask) {
        echo '<p class="success">✓ SMTP instalační úloha již existuje (ID: ' . $smtpTask['id'] . ')</p>';
        echo '<p class="info">Status: ' . $smtpTask['status'] . '</p>';
        echo '<p class="info">Priorita: ' . $smtpTask['priority'] . '</p>';
        echo '<p class="info">Vytvořeno: ' . $smtpTask['created_at'] . '</p>';
        echo '<p style="margin-top: 1rem;">→ Přejděte do <strong>Control Center → Akce & Úkoly</strong> a měli byste úlohu vidět.</p>';
    } else {
        echo '<p class="warning">⚠ SMTP instalační úloha NEEXISTUJE</p>';
        echo '<p>Přidávám úlohu do databáze...</p>';

        try {
            $stmt = $pdo->prepare("
                INSERT INTO wgs_pending_actions (
                    action_type,
                    action_title,
                    action_description,
                    priority,
                    status,
                    created_at
                )
                VALUES (
                    'install_smtp',
                    'Instalovat SMTP konfiguraci',
                    'Přidá smtp_password a smtp_encryption klíče do system_config a vytvoří tabulku wgs_notification_history pro sledování odeslaných emailů a SMS.',
                    'high',
                    'pending',
                    NOW()
                )
            ");

            $stmt->execute();
            $newId = $pdo->lastInsertId();

            echo '<p class="success">✓ SMTP úloha byla úspěšně přidána! (ID: ' . $newId . ')</p>';

            // Ověření
            $stmt = $pdo->prepare("SELECT * FROM wgs_pending_actions WHERE id = ?");
            $stmt->execute([$newId]);
            $verifyTask = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($verifyTask) {
                echo '<h3>Ověření nově vytvořené úlohy:</h3>';
                echo '<pre>';
                echo "ID:          {$verifyTask['id']}\n";
                echo "Typ:         {$verifyTask['action_type']}\n";
                echo "Název:       {$verifyTask['action_title']}\n";
                echo "Popis:       {$verifyTask['action_description']}\n";
                echo "Priorita:    {$verifyTask['priority']}\n";
                echo "Status:      {$verifyTask['status']}\n";
                echo "Vytvořeno:   {$verifyTask['created_at']}\n";
                echo '</pre>';

                echo '<p class="success" style="font-size: 1.1rem; margin-top: 1rem;">✅ HOTOVO! Nyní přejděte do Control Center → Akce & Úkoly</p>';
            }

        } catch (Exception $e) {
            echo '<p class="error">✗ Chyba při přidávání úlohy: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    echo '</div>';

} catch (Exception $e) {
    echo '<div class="card">';
    echo '<h2 class="error">✗ Chyba</h2>';
    echo '<p class="error">ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}

?>

    <div class="card">
        <h2>📌 Co dělat dál?</h2>
        <ol>
            <li>Přejděte do <strong>admin.php → Control Center</strong></li>
            <li>Otevřete kartu <strong>"Akce & Úkoly"</strong></li>
            <li>Měli byste vidět úlohu <strong>"Instalovat SMTP konfiguraci"</strong> s prioritou HIGH</li>
            <li>Klikněte na zelené tlačítko <strong>"Spustit akci"</strong></li>
            <li>Systém automaticky nainstaluje SMTP konfiguraci</li>
            <li>Poté můžete nastavit SMTP v <strong>Email & SMS → SMTP nastavení</strong></li>
        </ol>

        <a href="admin.php" class="btn">→ Přejít do Control Center</a>
    </div>

</body>
</html>
