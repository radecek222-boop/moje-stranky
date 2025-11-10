<?php
/**
 * DIAGNOSTICKÝ NÁSTROJ: Role Testing Tool
 * Umožňuje adminovi testovat aplikaci z pohledu různých rolí
 * BEZPEČNOST: Pouze pro přihlášené adminy
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin může používat tento nástroj
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Arial; padding: 40px; text-align: center;"><h1>🔒 Přístup odepřen</h1><p>Pouze admin může používat testovací nástroje.</p><p><a href="/login" style="color: #2196F3;">Přihlásit se jako admin</a></p></body></html>');
}

// Uložit původní admin session před simulací
if (!isset($_SESSION['_original_admin_session'])) {
    $_SESSION['_original_admin_session'] = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'is_admin' => $_SESSION['is_admin'] ?? null,
        'name' => $_SESSION['name'] ?? null,
    ];
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$originalSession = $_SESSION['_original_admin_session'];

// AKCE: Simulovat roli
if ($action === 'simulate') {
    $simulateRole = $_POST['simulate_role'] ?? null;

    switch ($simulateRole) {
        case 'admin':
            $_SESSION['user_id'] = 1;
            $_SESSION['email'] = 'admin@wgs-service.cz';
            $_SESSION['role'] = 'admin';
            $_SESSION['is_admin'] = true;
            $_SESSION['name'] = 'Admin (TEST)';
            $_SESSION['_simulating'] = 'admin';
            break;

        case 'prodejce':
            $_SESSION['user_id'] = 7;
            $_SESSION['email'] = 'naty@naty.cz';
            $_SESSION['role'] = 'prodejce';
            $_SESSION['is_admin'] = false;
            $_SESSION['name'] = 'Naty Prodejce (TEST)';
            $_SESSION['_simulating'] = 'prodejce';
            break;

        case 'technik':
            $_SESSION['user_id'] = 15;
            $_SESSION['email'] = 'milan@technik.cz';
            $_SESSION['role'] = 'technik';
            $_SESSION['is_admin'] = false;
            $_SESSION['name'] = 'Milan Technik (TEST)';
            $_SESSION['_simulating'] = 'technik';
            break;

        case 'guest':
            $_SESSION['user_id'] = null;
            $_SESSION['email'] = 'jiri@novacek.cz';
            $_SESSION['role'] = 'guest';
            $_SESSION['is_admin'] = false;
            $_SESSION['name'] = 'Jiří Nováček (TEST)';
            $_SESSION['_simulating'] = 'guest';
            break;

        case 'unregistered':
            unset($_SESSION['user_id']);
            unset($_SESSION['email']);
            unset($_SESSION['role']);
            $_SESSION['is_admin'] = false;
            unset($_SESSION['name']);
            $_SESSION['_simulating'] = 'unregistered';
            break;
    }

    header('Location: role_testing_tool.php?simulated=' . urlencode($simulateRole));
    exit;
}

// AKCE: Reset na původní admin session
if ($action === 'reset') {
    $_SESSION['user_id'] = $originalSession['user_id'];
    $_SESSION['email'] = $originalSession['email'];
    $_SESSION['role'] = $originalSession['role'];
    $_SESSION['is_admin'] = $originalSession['is_admin'];
    $_SESSION['name'] = $originalSession['name'];
    unset($_SESSION['_simulating']);

    header('Location: role_testing_tool.php?reset=1');
    exit;
}

$currentSimulation = $_SESSION['_simulating'] ?? null;

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Testing Tool - WGS Service</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-weight: 600;
        }
        .alert-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }
        .alert-info {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            color: #0c5460;
        }
        .alert-success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        .role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .role-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            border: 3px solid #dee2e6;
            transition: all 0.3s;
            cursor: pointer;
        }
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .role-card.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
        }
        .role-card h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }
        .role-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .role-card ul {
            list-style: none;
            font-size: 13px;
            color: #666;
        }
        .role-card ul li {
            padding: 5px 0;
        }
        .role-card ul li:before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            margin-top: 15px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-reset {
            background: #dc3545;
        }
        .btn-reset:hover {
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
        }
        .test-links {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-top: 30px;
        }
        .test-links h2 {
            color: #333;
            margin-bottom: 15px;
        }
        .test-links a {
            display: inline-block;
            margin: 5px 10px 5px 0;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .test-links a:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }
        .session-info {
            background: #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            font-family: monospace;
            font-size: 13px;
        }
        .session-info div {
            margin: 5px 0;
        }
        .session-info strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎭 Role Testing Tool</h1>
        <p class="subtitle">Diagnostický nástroj pro testování přístupových práv různých rolí</p>

        <?php if (isset($_GET['simulated'])): ?>
            <div class="alert alert-success">
                ✅ Simulace role <strong><?= htmlspecialchars($_GET['simulated']) ?></strong> aktivována!
            </div>
        <?php elseif (isset($_GET['reset'])): ?>
            <div class="alert alert-info">
                🔄 Obnovena původní admin session.
            </div>
        <?php endif; ?>

        <?php if ($currentSimulation): ?>
            <div class="alert alert-warning">
                ⚠️ <strong>POZOR:</strong> Momentálně simuluješ roli <strong><?= htmlspecialchars($currentSimulation) ?></strong>.<br>
                Všechny stránky vidíš z pohledu této role!
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                ℹ️ Momentálně jsi přihlášen jako <strong>Admin</strong> (normální režim).
            </div>
        <?php endif; ?>

        <!-- Aktuální session info -->
        <div class="session-info">
            <h3 style="margin-bottom: 10px; color: #333;">📊 Aktuální SESSION</h3>
            <div><strong>user_id:</strong> <?= isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : 'NULL' ?></div>
            <div><strong>email:</strong> <?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'NULL' ?></div>
            <div><strong>role:</strong> <?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'NULL' ?></div>
            <div><strong>is_admin:</strong> <?= isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? 'true' : 'false' ?></div>
            <div><strong>name:</strong> <?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'NULL' ?></div>
            <?php if ($currentSimulation): ?>
                <div><strong>_simulating:</strong> <span style="color: #dc3545;"><?= htmlspecialchars($currentSimulation) ?></span></div>
            <?php endif; ?>
        </div>

        <h2 style="margin-bottom: 20px; color: #333;">🎯 Vyber roli k testování</h2>

        <div class="role-grid">
            <!-- ADMIN -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="admin">
                <div class="role-card <?= $currentSimulation === 'admin' ? 'active' : '' ?>">
                    <h3>🔵 ADMIN</h3>
                    <p>Vidí a může upravovat vše</p>
                    <ul>
                        <li>Všechny reklamace</li>
                        <li>Všichni uživatelé</li>
                        <li>Nastavení systému</li>
                        <li>Debug nástroje</li>
                    </ul>
                    <button type="submit">🔵 Testovat jako Admin</button>
                </div>
            </form>

            <!-- PRODEJCE -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="prodejce">
                <div class="role-card <?= $currentSimulation === 'prodejce' ? 'active' : '' ?>">
                    <h3>🟢 PRODEJCE</h3>
                    <p>Vytváří reklamace pro zákazníky</p>
                    <ul>
                        <li>Vidí VŠECHNY reklamace</li>
                        <li>Může vytvářet nové</li>
                        <li>Může editovat vlastní</li>
                        <li>Nemůže měnit nastavení</li>
                    </ul>
                    <button type="submit">🟢 Testovat jako Prodejce</button>
                </div>
            </form>

            <!-- TECHNIK -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="technik">
                <div class="role-card <?= $currentSimulation === 'technik' ? 'active' : '' ?>">
                    <h3>🟡 TECHNIK</h3>
                    <p>Opravuje přiřazené reklamace</p>
                    <ul>
                        <li>Vidí JEN přiřazené</li>
                        <li>Může psát poznámky</li>
                        <li>Může měnit stav</li>
                        <li>Nemůže vidět cizí</li>
                    </ul>
                    <button type="submit">🟡 Testovat jako Technik</button>
                </div>
            </form>

            <!-- GUEST (Registrovaný zákazník) -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="guest">
                <div class="role-card <?= $currentSimulation === 'guest' ? 'active' : '' ?>">
                    <h3>🔴 GUEST</h3>
                    <p>Zákazník s účtem</p>
                    <ul>
                        <li>Vidí JEN svoje reklamace</li>
                        <li>Filtr podle emailu</li>
                        <li>Může vytvářet nové</li>
                        <li>Nemůže editovat</li>
                    </ul>
                    <button type="submit">🔴 Testovat jako Guest</button>
                </div>
            </form>

            <!-- UNREGISTERED -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="unregistered">
                <div class="role-card <?= $currentSimulation === 'unregistered' ? 'active' : '' ?>">
                    <h3>⚪ NEPŘIHLÁŠENÝ</h3>
                    <p>Nepřihlášený uživatel</p>
                    <ul>
                        <li>Omezený přístup</li>
                        <li>Jen veřejné stránky</li>
                        <li>Nemůže vytvářet</li>
                        <li>Nemůže vidět seznam</li>
                    </ul>
                    <button type="submit">⚪ Testovat Nepřihlášený</button>
                </div>
            </form>
        </div>

        <?php if ($currentSimulation): ?>
            <form method="POST" style="margin-top: 30px;">
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="btn-reset">🔄 Reset na Admin Session</button>
            </form>
        <?php endif; ?>

        <!-- Test Links -->
        <div class="test-links">
            <h2>🔗 Testovací odkazy</h2>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                Otevři tyto stránky v novém okně a uvidíš je z pohledu simulované role:
            </p>
            <a href="/seznam.php" target="_blank">📋 Seznam reklamací</a>
            <a href="/admin.php" target="_blank">⚙️ Admin panel</a>
            <a href="/show_table_structure.php" target="_blank">📊 Struktura DB</a>
            <a href="/debug_photos.php" target="_blank">📸 Debug fotek</a>
            <a href="/quick_debug.php" target="_blank">🔍 Rychlý debug</a>
        </div>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center;">
            <a href="/admin.php?tab=tools" style="color: #667eea; text-decoration: none; font-weight: 600;">
                ← Zpět na Admin Tools
            </a>
        </div>

        <div style="margin-top: 20px; text-align: center; color: #999; font-size: 14px;">
            <small>WGS Service - Role Testing Tool © 2025</small>
        </div>
    </div>
</body>
</html>
