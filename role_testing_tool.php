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
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title><link rel="stylesheet" href="assets/css/styles.min.css"></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center;"><h1 style="font-weight: 600; letter-spacing: 0.05em;">PŘÍSTUP ODEPŘEN</h1><p>Pouze admin může používat testovací nástroje.</p><p><a href="/login" style="color: #000;">Přihlásit se jako admin</a></p></body></html>');
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
    <link rel="stylesheet" href="assets/css/styles.min.css">
    <style>
        :root {
            --wgs-white: #FFFFFF;
            --wgs-black: #000000;
            --wgs-dark-grey: #222222;
            --wgs-grey: #555555;
            --wgs-light-grey: #999999;
            --wgs-border: #E0E0E0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--wgs-white);
            color: var(--wgs-black);
            padding: 0;
            margin: 0;
        }

        .header {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 2rem;
            border-bottom: 2px solid var(--wgs-black);
        }

        .header h1 {
            color: var(--wgs-white);
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .header p {
            color: var(--wgs-light-grey);
            margin: 0;
            font-size: 0.9rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .alert {
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--wgs-black);
            background: #f8f8f8;
        }

        .alert strong {
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--wgs-black);
        }

        .alert-warning {
            background: #fff3e0;
            border-left-color: #f57c00;
        }

        .session-info {
            background: #f8f8f8;
            border: 1px solid var(--wgs-border);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .session-info h3 {
            margin-bottom: 1rem;
            color: var(--wgs-black);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .session-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .session-info td {
            padding: 0.5rem 0;
            font-size: 0.9rem;
        }

        .session-info td:first-child {
            font-weight: 600;
            color: var(--wgs-black);
            width: 150px;
        }

        .session-info td:last-child {
            color: var(--wgs-grey);
        }

        h2 {
            margin-bottom: 1.5rem;
            color: var(--wgs-black);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .role-card {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            padding: 2rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .role-card:hover {
            border-color: var(--wgs-black);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .role-card.active {
            background: var(--wgs-black);
            border-color: var(--wgs-black);
        }

        .role-card.active * {
            color: var(--wgs-white) !important;
        }

        .role-card h3 {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--wgs-black);
            margin-bottom: 0.75rem;
        }

        .role-card p {
            font-size: 0.85rem;
            color: var(--wgs-grey);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .role-card ul {
            list-style: none;
            font-size: 0.8rem;
            color: var(--wgs-grey);
        }

        .role-card ul li {
            padding: 0.25rem 0;
            padding-left: 1rem;
            position: relative;
        }

        .role-card ul li:before {
            content: "—";
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        button {
            background: var(--wgs-black);
            color: var(--wgs-white);
            border: 2px solid var(--wgs-black);
            padding: 0.875rem 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 1rem;
            font-family: 'Poppins', sans-serif;
            white-space: normal;
            line-height: 1.4;
        }

        button:hover {
            background: var(--wgs-white);
            color: var(--wgs-black);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-reset {
            background: var(--wgs-grey);
            border-color: var(--wgs-grey);
        }

        .test-links {
            background: #f8f8f8;
            border: 1px solid var(--wgs-border);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .test-links h2 {
            margin-bottom: 1rem;
        }

        .test-links p {
            color: var(--wgs-grey);
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .test-links a {
            display: inline-block;
            margin: 0.25rem 0.5rem 0.25rem 0;
            padding: 0.5rem 1rem;
            background: var(--wgs-black);
            color: var(--wgs-white);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            transition: all 0.3s;
        }

        .test-links a:hover {
            background: var(--wgs-grey);
        }

        .back-link {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.5rem 0;
            color: var(--wgs-grey);
            text-decoration: none;
            font-size: 0.85rem;
            border-bottom: 1px solid transparent;
            transition: all 0.3s;
        }

        .back-link:hover {
            color: var(--wgs-black);
            border-bottom-color: var(--wgs-black);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ROLE TESTING TOOL</h1>
        <p>Diagnostický nástroj pro testování přístupových práv různých rolí</p>
    </div>

    <div class="container">
        <?php if (isset($_GET['simulated'])): ?>
            <div class="alert">
                <strong>Úspěch:</strong> Simulace role <strong><?= htmlspecialchars($_GET['simulated']) ?></strong> aktivována!
            </div>
        <?php elseif (isset($_GET['reset'])): ?>
            <div class="alert">
                <strong>Informace:</strong> Obnovena původní admin session.
            </div>
        <?php endif; ?>

        <?php if ($currentSimulation): ?>
            <div class="alert alert-warning">
                <strong>Pozor:</strong> Momentálně simuluješ roli <strong><?= htmlspecialchars($currentSimulation) ?></strong>. Všechny stránky vidíš z pohledu této role!
            </div>
        <?php else: ?>
            <div class="alert">
                <strong>Informace:</strong> Momentálně jsi přihlášen jako <strong>Admin</strong> (normální režim).
            </div>
        <?php endif; ?>

        <!-- Aktuální session info -->
        <div class="session-info">
            <h3>Aktuální Session</h3>
            <table>
                <tr>
                    <td>user_id:</td>
                    <td><?= isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : 'NULL' ?></td>
                </tr>
                <tr>
                    <td>email:</td>
                    <td><?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'NULL' ?></td>
                </tr>
                <tr>
                    <td>role:</td>
                    <td><?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'NULL' ?></td>
                </tr>
                <tr>
                    <td>is_admin:</td>
                    <td><?= isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? 'true' : 'false' ?></td>
                </tr>
                <tr>
                    <td>name:</td>
                    <td><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'NULL' ?></td>
                </tr>
                <?php if ($currentSimulation): ?>
                <tr>
                    <td>_simulating:</td>
                    <td style="color: #f57c00;"><?= htmlspecialchars($currentSimulation) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <h2>Vyber roli k testování</h2>

        <div class="role-grid">
            <!-- ADMIN -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="admin">
                <div class="role-card <?= $currentSimulation === 'admin' ? 'active' : '' ?>">
                    <h3>Admin</h3>
                    <p>Vidí a může upravovat vše</p>
                    <ul>
                        <li>Všechny reklamace</li>
                        <li>Všichni uživatelé</li>
                        <li>Nastavení systému</li>
                        <li>Debug nástroje</li>
                    </ul>
                    <button type="submit">Testovat jako Admin</button>
                </div>
            </form>

            <!-- PRODEJCE -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="prodejce">
                <div class="role-card <?= $currentSimulation === 'prodejce' ? 'active' : '' ?>">
                    <h3>Prodejce</h3>
                    <p>Vytváří reklamace pro zákazníky</p>
                    <ul>
                        <li>Vidí VŠECHNY reklamace</li>
                        <li>Může vytvářet nové</li>
                        <li>Může editovat vlastní</li>
                        <li>Nemůže měnit nastavení</li>
                    </ul>
                    <button type="submit">Testovat jako Prodejce</button>
                </div>
            </form>

            <!-- TECHNIK -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="technik">
                <div class="role-card <?= $currentSimulation === 'technik' ? 'active' : '' ?>">
                    <h3>Technik</h3>
                    <p>Opravuje přiřazené reklamace</p>
                    <ul>
                        <li>Vidí JEN přiřazené</li>
                        <li>Může psát poznámky</li>
                        <li>Může měnit stav</li>
                        <li>Nemůže vidět cizí</li>
                    </ul>
                    <button type="submit">Testovat jako Technik</button>
                </div>
            </form>

            <!-- GUEST -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="guest">
                <div class="role-card <?= $currentSimulation === 'guest' ? 'active' : '' ?>">
                    <h3>Guest</h3>
                    <p>Zákazník s účtem</p>
                    <ul>
                        <li>Vidí JEN svoje reklamace</li>
                        <li>Filtr podle emailu</li>
                        <li>Může vytvářet nové</li>
                        <li>Nemůže editovat</li>
                    </ul>
                    <button type="submit">Testovat jako Guest</button>
                </div>
            </form>

            <!-- UNREGISTERED -->
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="simulate">
                <input type="hidden" name="simulate_role" value="unregistered">
                <div class="role-card <?= $currentSimulation === 'unregistered' ? 'active' : '' ?>">
                    <h3>Nepřihlášený</h3>
                    <p>Nepřihlášený uživatel</p>
                    <ul>
                        <li>Omezený přístup</li>
                        <li>Jen veřejné stránky</li>
                        <li>Nemůže vytvářet</li>
                        <li>Nemůže vidět seznam</li>
                    </ul>
                    <button type="submit">Testovat Nepřihlášený</button>
                </div>
            </form>
        </div>

        <?php if ($currentSimulation): ?>
            <form method="POST" style="margin-top: 2rem;">
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="btn-reset">Reset na Admin Session</button>
            </form>
        <?php endif; ?>

        <!-- Test Links -->
        <div class="test-links">
            <h2>Testovací odkazy</h2>
            <p>Otevři tyto stránky v novém okně a uvidíš je z pohledu simulované role:</p>
            <a href="/seznam.php" target="_blank">Seznam reklamací</a>
            <a href="/admin.php" target="_blank">Admin panel</a>
            <a href="/show_table_structure.php" target="_blank">Struktura DB</a>
            <a href="/debug_photos.php" target="_blank">Debug fotek</a>
            <a href="/quick_debug.php" target="_blank">Rychlý debug</a>
        </div>

        <a href="/admin.php?tab=tools" class="back-link">Zpět na Admin Tools</a>

        <div style="margin-top: 2rem; text-align: center; color: var(--wgs-light-grey); font-size: 0.8rem;">
            <small>WGS Service - Role Testing Tool</small>
        </div>
    </div>
</body>
</html>
