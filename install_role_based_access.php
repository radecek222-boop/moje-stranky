<?php
/**
 * INSTALÁTOR: Role-Based Access Control
 * BEZPEČNOST: Pouze pro přihlášené adminy
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin může spustit instalaci
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Arial; padding: 40px; text-align: center;"><h1>🔒 Přístup odepřen</h1><p>Pouze admin může spustit instalaci.</p><p><a href="/login" style="color: #2196F3;">Přihlásit se jako admin</a></p></body></html>');
}

$step = $_GET['step'] ?? 'start';
$action = $_POST['action'] ?? null;

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace Role-Based Access</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .step {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .step h2 {
            color: #444;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .step p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .step ul {
            margin: 15px 0 15px 20px;
            color: #666;
        }
        .step li {
            margin: 8px 0;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 40px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
            margin-top: 20px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            color: #155724;
            margin-bottom: 20px;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 20px;
            color: #721c24;
            margin-bottom: 20px;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            color: #0c5460;
            margin-bottom: 20px;
        }
        .log {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            font-family: monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        .log div {
            margin: 5px 0;
            padding: 5px;
        }
        .log .ok { color: #28a745; }
        .log .error { color: #dc3545; }
        .log .info { color: #17a2b8; }
        .progress {
            background: #e9ecef;
            border-radius: 8px;
            height: 30px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        code {
            background: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            color: #e83e8c;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($step === 'start'): ?>
            <h1>🚀 Instalace Role-Based Access</h1>
            <p class="subtitle">Automatická migrace databáze pro škálovatelný systém rolí</p>

            <div class="step">
                <h2>📋 Co se stane?</h2>
                <p>Tento instalátor automaticky:</p>
                <ul>
                    <li>✅ Přidá sloupce <code>created_by</code> a <code>created_by_role</code></li>
                    <li>✅ Naplní existující data (Gustav, Jiří)</li>
                    <li>✅ Vytvoří indexy pro rychlé vyhledávání</li>
                    <li>✅ Nastaví roli <code>'prodejce'</code> pro naty@naty.cz</li>
                </ul>
            </div>

            <div class="info">
                <strong>ℹ️ Informace:</strong><br>
                Po instalaci bude systém fungovat pro neomezený počet prodejců a techniků.
                Prodejci uvidí všechny reklamace, technici pouze přiřazené.
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="install">
                <button type="submit">🚀 Spustit instalaci</button>
            </form>

        <?php elseif ($action === 'install'): ?>
            <h1>⚙️ Probíhá instalace...</h1>
            <div class="progress">
                <div class="progress-bar" id="progress" style="width: 0%">0%</div>
            </div>
            <div class="log" id="log"></div>

            <?php
            $logs = [];
            $errors = [];
            $success = true;

            try {
                $pdo = getDbConnection();

                function addLog($message, $type = 'info') {
                    global $logs;
                    $logs[] = ['msg' => $message, 'type' => $type];
                }

                function updateProgress($percent) {
                    echo "<script>document.getElementById('progress').style.width = '{$percent}%'; document.getElementById('progress').textContent = '{$percent}%';</script>";
                    flush();
                }

                addLog('🔌 Připojení k databázi...', 'info');
                updateProgress(10);

                // Krok 1: Zkontroluj jestli sloupce už existují
                addLog('🔍 Kontroluji existující strukturu...', 'info');
                $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'created_by'");
                $hasCreatedBy = $stmt->rowCount() > 0;

                if ($hasCreatedBy) {
                    addLog('⚠️ Sloupec created_by již existuje - přeskakuji', 'info');
                    updateProgress(50);
                } else {
                    addLog('➕ Přidávám sloupec created_by...', 'info');
                    $pdo->exec("ALTER TABLE wgs_reklamace
                        ADD COLUMN created_by INT NULL COMMENT 'ID uživatele který vytvořil reklamaci' AFTER zpracoval_id");
                    addLog('✅ Sloupec created_by přidán', 'ok');
                    updateProgress(30);

                    addLog('➕ Přidávám sloupec created_by_role...', 'info');
                    $pdo->exec("ALTER TABLE wgs_reklamace
                        ADD COLUMN created_by_role VARCHAR(20) NULL DEFAULT 'user' COMMENT 'Role uživatele' AFTER created_by");
                    addLog('✅ Sloupec created_by_role přidán', 'ok');
                    updateProgress(40);

                    addLog('📊 Naplňuji existující data...', 'info');
                    $stmt = $pdo->exec("UPDATE wgs_reklamace
                        SET created_by = zpracoval_id, created_by_role = 'user'
                        WHERE zpracoval_id IS NOT NULL");
                    addLog("✅ Aktualizováno {$stmt} záznamů", 'ok');
                    updateProgress(50);

                    addLog('📝 Nastavuji role pro reklamace bez zpracoval_id...', 'info');
                    $pdo->exec("UPDATE wgs_reklamace
                        SET created_by_role = 'guest'
                        WHERE created_by IS NULL");
                    addLog('✅ Role nastaveny', 'ok');
                    updateProgress(60);

                    addLog('🔗 Vytvářím indexy...', 'info');
                    try {
                        $pdo->exec("CREATE INDEX idx_created_by ON wgs_reklamace(created_by)");
                        addLog('✅ Index idx_created_by vytvořen', 'ok');
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate') !== false) {
                            addLog('⚠️ Index idx_created_by již existuje', 'info');
                        } else {
                            throw $e;
                        }
                    }
                    updateProgress(70);

                    try {
                        $pdo->exec("CREATE INDEX idx_created_by_role ON wgs_reklamace(created_by_role)");
                        addLog('✅ Index idx_created_by_role vytvořen', 'ok');
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate') !== false) {
                            addLog('⚠️ Index idx_created_by_role již existuje', 'info');
                        } else {
                            throw $e;
                        }
                    }
                    updateProgress(80);
                }

                // Krok 2: Nastav roli pro naty@naty.cz
                addLog('👤 Nastavuji roli pro naty@naty.cz...', 'info');
                $stmt = $pdo->prepare("UPDATE wgs_users SET role = 'prodejce' WHERE email = 'naty@naty.cz'");
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    addLog('✅ Role "prodejce" nastavena pro naty@naty.cz', 'ok');
                } else {
                    addLog('⚠️ Uživatel naty@naty.cz nenalezen nebo již má správnou roli', 'info');
                }
                updateProgress(90);

                // Krok 3: Ověření
                addLog('🔍 Ověřuji instalaci...', 'info');
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_reklamace WHERE created_by IS NOT NULL OR created_by_role IS NOT NULL");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                addLog("✅ Ověřeno: {$result['total']} reklamací má nastavené role", 'ok');
                updateProgress(100);

                addLog('', 'info');
                addLog('🎉 INSTALACE DOKONČENA ÚSPĚŠNĚ!', 'ok');

            } catch (Exception $e) {
                $success = false;
                addLog('❌ CHYBA: ' . $e->getMessage(), 'error');
                addLog('📄 Stack trace: ' . $e->getTraceAsString(), 'error');
            }

            // Vypsat všechny logy
            echo "<script>";
            foreach ($logs as $log) {
                $msg = htmlspecialchars($log['msg'], ENT_QUOTES);
                $type = $log['type'];
                echo "document.getElementById('log').innerHTML += '<div class=\"{$type}\">{$msg}</div>';";
            }
            echo "</script>";
            ?>

            <?php if ($success): ?>
                <div class="success">
                    <h3>✅ Instalace byla úspěšná!</h3>
                    <p>Systém je nyní připravený pro neomezený počet prodejců a techniků.</p>
                </div>
                <a href="/seznam.php" style="display: block; text-align: center; margin-top: 20px; padding: 16px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    🎯 Otevřít seznam reklamací
                </a>
            <?php else: ?>
                <div class="error">
                    <h3>❌ Instalace selhala</h3>
                    <p>Zkontroluj chybovou zprávu výše a kontaktuj podporu.</p>
                </div>
                <a href="?step=start" style="display: block; text-align: center; margin-top: 20px; padding: 16px; background: #dc3545; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    🔄 Zkusit znovu
                </a>
            <?php endif; ?>

        <?php endif; ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999; font-size: 14px;">
            <small>WGS Service - Role-Based Access Installer © 2025</small>
        </div>
    </div>
</body>
</html>
