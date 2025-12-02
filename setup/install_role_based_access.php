<?php
/**
 * INSTALÁTOR: Role-Based Access Control
 * BEZPEČNOST: Pouze pro přihlášené adminy
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin může spustit instalaci
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center; background: #fff;"><h2 style="color: #000; text-transform: uppercase; letter-spacing: 0.1em;">PŘÍSTUP ODEPŘEN</h2><p style="color: #555;">Pouze admin může spustit instalaci.</p><p><a href="/login" style="color: #000; border-bottom: 2px solid #000; text-decoration: none;">Přihlásit se jako admin</a></p></body></html>');
}

$step = $_GET['step'] ?? 'start';
$action = $_POST['action'] ?? null;

// DEBUG - zapiš do error logu
error_log("INSTALL RBAC - Step: $step, Action: " . ($action ?? 'NULL'));
// SECURITY FIX: NIKDY NELOGOVAT $_POST - obsahuje citlivá data (hesla, tokeny)!
// Original line removed: error_log("INSTALL RBAC - POST data: " . print_r($_POST, true));

// Zkontroluj jestli už je nainstalováno
$isInstalled = false;
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'created_by'");
    if ($stmt->rowCount() > 0) {
        $isInstalled = true;
        error_log("INSTALL RBAC - Already installed!");
    } else {
        error_log("INSTALL RBAC - NOT installed yet");
    }
} catch (Exception $e) {
    error_log("INSTALL RBAC - Error checking: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace Role-Based Access</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=optional" rel="stylesheet">
    <style>
        :root {
            --wgs-white: #FFFFFF;
            --wgs-black: #000000;
            --wgs-grey: #555555;
            --wgs-light-grey: #999999;
            --wgs-border: #E0E0E0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--wgs-white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--wgs-black);
        }

        .container {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-black);
            max-width: 800px;
            width: 100%;
            padding: 0;
            overflow: hidden;
        }

        .header {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 2rem;
            border-bottom: 2px solid var(--wgs-black);
        }

        h1 {
            color: inherit;
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .subtitle {
            color: var(--wgs-light-grey);
            margin-bottom: 0;
            font-size: 0.875rem;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .content {
            padding: 2rem;
        }

        .step {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .step h2 {
            color: var(--wgs-black);
            margin-bottom: 1rem;
            font-size: 1.125rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .step p {
            color: var(--wgs-grey);
            line-height: 1.6;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .step ul {
            margin: 1rem 0 1rem 1.5rem;
            color: var(--wgs-grey);
        }

        .step li {
            margin: 0.5rem 0;
            font-size: 0.875rem;
        }

        button, .button {
            background: var(--wgs-black);
            color: var(--wgs-white);
            border: 2px solid var(--wgs-black);
            padding: 1rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            margin-top: 1.5rem;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: normal;
            line-height: 1.4;
        }

        button:hover, .button:hover {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        button:disabled {
            background: var(--wgs-light-grey);
            border-color: var(--wgs-light-grey);
            cursor: not-allowed;
        }

        .success {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
            padding: 1.5rem;
            color: var(--wgs-grey);
            margin-bottom: 1.5rem;
        }

        .success h3 {
            color: var(--wgs-black);
            margin-bottom: 0.75rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
        }

        .success p {
            font-size: 0.875rem;
            margin: 0.5rem 0;
        }

        .error {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-grey);
            border-left: 4px solid var(--wgs-grey);
            padding: 1.5rem;
            color: var(--wgs-grey);
            margin-bottom: 1.5rem;
        }

        .error h3 {
            color: var(--wgs-black);
            margin-bottom: 0.75rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
        }

        .error p {
            font-size: 0.875rem;
        }

        .info {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-grey);
            padding: 1.5rem;
            color: var(--wgs-grey);
            margin-bottom: 1.5rem;
        }

        .info strong {
            color: var(--wgs-black);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
        }

        .log {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            padding: 1rem;
            font-family: monospace;
            font-size: 0.75rem;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 1.5rem;
        }

        .log div {
            margin: 0.25rem 0;
            padding: 0.25rem;
        }

        .log .ok { color: var(--wgs-black); font-weight: 600; }
        .log .error { color: var(--wgs-grey); }
        .log .info { color: var(--wgs-light-grey); }

        .progress {
            background: var(--wgs-border);
            height: 40px;
            overflow: hidden;
            margin: 1.5rem 0;
            border: 2px solid var(--wgs-black);
        }

        .progress-bar {
            background: var(--wgs-black);
            height: 100%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--wgs-white);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        code {
            background: var(--wgs-border);
            padding: 0.25rem 0.5rem;
            font-family: monospace;
            color: var(--wgs-black);
            border: 1px solid var(--wgs-black);
            font-size: 0.75rem;
        }

        .button-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .button-grid a, .button-grid button {
            margin-top: 0;
        }

        .button-secondary {
            background: var(--wgs-white);
            color: var(--wgs-black);
            border: 2px solid var(--wgs-black);
        }

        .button-secondary:hover {
            background: var(--wgs-black);
            color: var(--wgs-white);
        }

        .button-grey {
            background: var(--wgs-grey);
            border-color: var(--wgs-grey);
        }

        .button-grey:hover {
            background: var(--wgs-white);
            color: var(--wgs-grey);
            border-color: var(--wgs-grey);
        }

        .footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--wgs-border);
            text-align: center;
            color: var(--wgs-light-grey);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($isInstalled && $action !== 'install'): ?>
            <!-- JIŽ NAINSTALOVÁNO -->
            <div class="header">
                <h1>ROLE-BASED ACCESS JE NAINSTALOVÁN</h1>
                <p class="subtitle">Systém je již aktivní a funkční</p>
            </div>

            <div class="content">
                <div class="success">
                    <h3>ÚSPĚCH!</h3>
                    <p>Role-Based Access Control systém je již nainstalován a funkční.</p>
                    <p style="margin-top: 0.75rem;">Všechny potřebné sloupce a indexy jsou v databázi.</p>
                </div>

                <div class="info">
                    <strong>CO JE AKTIVNÍ:</strong><br>
                    <ul style="margin-top: 0.75rem;">
                        <li>Sloupce <code>created_by</code> a <code>created_by_role</code> existují</li>
                        <li>Systém podporuje neomezený počet prodejců a techniků</li>
                        <li>Prodejci vidí všechny reklamace, technici pouze přiřazené</li>
                    </ul>
                </div>

                <div class="button-grid">
                    <a href="/admin.php?tab=tools" class="button button-secondary">
                        ZPĚT NA ADMIN
                    </a>
                    <a href="/seznam.php" class="button">
                        SEZNAM REKLAMACÍ
                    </a>
                </div>
            </div>

        <?php elseif ($step === 'start' && !$isInstalled && $action !== 'install'): ?>
            <!-- FORMULÁŘ PRO INSTALACI -->
            <div class="header">
                <h1>INSTALACE ROLE-BASED ACCESS</h1>
                <p class="subtitle">Automatická migrace databáze pro škálovatelný systém rolí</p>
            </div>

            <div class="content">
                <div class="step">
                    <h2>CO SE STANE?</h2>
                    <p>Tento instalátor automaticky:</p>
                    <ul>
                        <li>Přidá sloupce <code>created_by</code> a <code>created_by_role</code></li>
                        <li>Naplní existující data (Gustav, Jiří)</li>
                        <li>Vytvoří indexy pro rychlé vyhledávání</li>
                        <li>Nastaví roli <code>'prodejce'</code> pro naty@naty.cz</li>
                    </ul>
                </div>

                <div class="info">
                    <strong>INFORMACE:</strong><br>
                    Po instalaci bude systém fungovat pro neomezený počet prodejců a techniků.
                    Prodejci uvidí všechny reklamace, technici pouze přiřazené.
                </div>

                <form method="POST" action="" id="installForm">
                    <input type="hidden" name="action" value="install">
                    <button type="submit" id="installBtn">SPUSTIT INSTALACI</button>
                </form>
            </div>

        <?php elseif ($action === 'install'): ?>
            <!-- INSTALACE PROBÍHÁ -->
            <div class="header">
                <h1>PROBÍHÁ INSTALACE...</h1>
            </div>

            <div class="content">
                <div class="progress">
                    <div class="progress-bar" id="progress" style="width: 0%">0%</div>
                </div>
                <div class="log" id="log"></div>

                <?php
                // DŮLEŽITÉ: Vypnout output buffering pro real-time feedback
                if (ob_get_level()) ob_end_flush();

                $errors = [];
                $success = true;

                try {
                    $pdo = getDbConnection();

                                        /**
                     * AddLog
                     *
                     * @param mixed $message Message
                     * @param mixed $type Type
                     */
function addLog($message, $type = 'info') {
                        $msg = htmlspecialchars($message, ENT_QUOTES);
                        echo "<script>document.getElementById('log').innerHTML += '<div class=\"{$type}\">{$msg}</div>'; document.getElementById('log').scrollTop = document.getElementById('log').scrollHeight;</script>";
                        echo str_repeat(' ', 1024); // Flush buffer
                        flush();
                        usleep(300000); // 300ms delay pro vizuální efekt
                    }

                                        /**
                     * UpdateProgress
                     *
                     * @param mixed $percent Percent
                     */
function updateProgress($percent) {
                        echo "<script>document.getElementById('progress').style.width = '{$percent}%'; document.getElementById('progress').textContent = '{$percent}%';</script>";
                        echo str_repeat(' ', 1024); // Flush buffer
                        flush();
                    }

                    addLog('[DATABÁZE] Připojení k databázi...', 'info');
                    updateProgress(10);

                    // Krok 1: Zkontroluj jestli sloupce už existují
                    addLog('[KONTROLA] Kontroluji existující strukturu...', 'info');
                    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'created_by'");
                    $hasCreatedBy = $stmt->rowCount() > 0;

                    if ($hasCreatedBy) {
                        addLog('[INFO] Sloupec created_by již existuje - přeskakuji', 'info');
                        updateProgress(50);
                    } else {
                        addLog('[MIGRACE] Přidávám sloupec created_by...', 'info');
                        $pdo->exec("ALTER TABLE wgs_reklamace
                            ADD COLUMN created_by INT NULL COMMENT 'ID uživatele který vytvořil reklamaci' AFTER zpracoval_id");
                        addLog('[OK] Sloupec created_by přidán', 'ok');
                        updateProgress(30);

                        addLog('[MIGRACE] Přidávám sloupec created_by_role...', 'info');
                        $pdo->exec("ALTER TABLE wgs_reklamace
                            ADD COLUMN created_by_role VARCHAR(20) NULL DEFAULT 'user' COMMENT 'Role uživatele' AFTER created_by");
                        addLog('[OK] Sloupec created_by_role přidán', 'ok');
                        updateProgress(40);

                        addLog('[DATA] Naplňuji existující data...', 'info');
                        $stmt = $pdo->exec("UPDATE wgs_reklamace
                            SET created_by = zpracoval_id, created_by_role = 'user'
                            WHERE zpracoval_id IS NOT NULL");
                        addLog("[OK] Aktualizováno {$stmt} záznamů", 'ok');
                        updateProgress(50);

                        addLog('[DATA] Nastavuji role pro reklamace bez zpracoval_id...', 'info');
                        $pdo->exec("UPDATE wgs_reklamace
                            SET created_by_role = 'guest'
                            WHERE created_by IS NULL");
                        addLog('[OK] Role nastaveny', 'ok');
                        updateProgress(60);

                        addLog('[INDEX] Vytvářím indexy...', 'info');
                        try {
                            $pdo->exec("CREATE INDEX idx_created_by ON wgs_reklamace(created_by)");
                            addLog('[OK] Index idx_created_by vytvořen', 'ok');
                        } catch (Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                                addLog('[INFO] Index idx_created_by již existuje', 'info');
                            } else {
                                throw $e;
                            }
                        }
                        updateProgress(70);

                        try {
                            $pdo->exec("CREATE INDEX idx_created_by_role ON wgs_reklamace(created_by_role)");
                            addLog('[OK] Index idx_created_by_role vytvořen', 'ok');
                        } catch (Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                                addLog('[INFO] Index idx_created_by_role již existuje', 'info');
                            } else {
                                throw $e;
                            }
                        }
                        updateProgress(80);
                    }

                    // Krok 2: Nastav roli pro naty@naty.cz
                    addLog('[USER] Nastavuji roli pro naty@naty.cz...', 'info');
                    $stmt = $pdo->prepare("UPDATE wgs_users SET role = 'prodejce' WHERE email = 'naty@naty.cz'");
                    $stmt->execute();
                    if ($stmt->rowCount() > 0) {
                        addLog('[OK] Role "prodejce" nastavena pro naty@naty.cz', 'ok');
                    } else {
                        addLog('[INFO] Uživatel naty@naty.cz nenalezen nebo již má správnou roli', 'info');
                    }
                    updateProgress(90);

                    // Krok 3: Zpětná validace
                    addLog('', 'info');
                    addLog('[VALIDACE] Ověřuji instalaci...', 'info');
                    updateProgress(90);

                    // Kontrola existence sloupců
                    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'created_by'");
                    if ($stmt->rowCount() > 0) {
                        addLog('[OK] Sloupec created_by existuje', 'ok');
                    } else {
                        throw new Exception('Sloupec created_by nebyl vytvořen!');
                    }

                    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'created_by_role'");
                    if ($stmt->rowCount() > 0) {
                        addLog('[OK] Sloupec created_by_role existuje', 'ok');
                    } else {
                        throw new Exception('Sloupec created_by_role nebyl vytvořen!');
                    }

                    // Kontrola dat
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_reklamace WHERE created_by IS NOT NULL OR created_by_role IS NOT NULL");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    addLog("[OK] Reklamací s nastavenou rolí: {$result['total']}", 'ok');

                    // Kontrola role naty@naty.cz
                    $stmt = $pdo->query("SELECT role FROM wgs_users WHERE email = 'naty@naty.cz'");
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        addLog("[OK] Role naty@naty.cz: {$user['role']}", 'ok');
                    } else {
                        addLog('[INFO] Uživatel naty@naty.cz nenalezen v databázi', 'info');
                    }

                    // Kontrola indexů
                    $stmt = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = 'idx_created_by'");
                    if ($stmt->rowCount() > 0) {
                        addLog('[OK] Index idx_created_by existuje', 'ok');
                    }

                    $stmt = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = 'idx_created_by_role'");
                    if ($stmt->rowCount() > 0) {
                        addLog('[OK] Index idx_created_by_role existuje', 'ok');
                    }

                    updateProgress(100);
                    addLog('', 'info');
                    addLog('[DOKONČENO] INSTALACE DOKONČENA ÚSPĚŠNĚ!', 'ok');
                    addLog('[OK] Všechny validace prošly', 'ok');

                } catch (Exception $e) {
                    $success = false;
                    addLog('[CHYBA] ' . $e->getMessage(), 'error');
                    addLog('[TRACE] ' . $e->getTraceAsString(), 'error');
                }
                ?>

                <?php if ($success): ?>
                    <div class="success">
                        <h3>INSTALACE BYLA ÚSPĚŠNÁ!</h3>
                        <p>Systém je nyní připravený pro neomezený počet prodejců a techniků.</p>
                        <p style="margin-top: 0.75rem;">Všechny validační kontroly prošly úspěšně.</p>
                    </div>

                    <div class="button-grid">
                        <a href="/admin.php?tab=tools" class="button button-secondary">
                            ZPĚT NA ADMIN
                        </a>
                        <a href="/seznam.php" class="button">
                            SEZNAM REKLAMACÍ
                        </a>
                    </div>

                    <button data-action="closeWindow" class="button button-grey">
                        ZAVŘÍT OKNO
                    </button>
                <?php else: ?>
                    <div class="error">
                        <h3>INSTALACE SELHALA</h3>
                        <p>Zkontroluj chybovou zprávu výše a kontaktuj podporu.</p>
                    </div>
                    <div class="button-grid">
                        <a href="?step=start" class="button button-grey">
                            ZKUSIT ZNOVU
                        </a>
                        <a href="/admin.php?tab=tools" class="button button-secondary">
                            ZPĚT NA ADMIN
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <div class="content">
            <div class="footer">
                <small>WGS SERVICE - ROLE-BASED ACCESS INSTALLER © 2025</small>
            </div>
        </div>
    </div>

    <script>
        // Event delegation pro data-action
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-action]');
            if (!target) return;

            const action = target.getAttribute('data-action');

            if (action === 'closeWindow') {
                window.close();
            }
        });

        // Formulář handling
        const form = document.getElementById('installForm');
        const btn = document.getElementById('installBtn');

        if (form && btn) {
            form.addEventListener('submit', function(e) {
                // Změň text tlačítka
                btn.textContent = 'SPOUŠTÍM...';
                btn.disabled = true;
                // Nech formulář odeslat normálně
            });
        }
    </script>
</body>
</html>
