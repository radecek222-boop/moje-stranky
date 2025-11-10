<?php
/**
 * DIAGNOSTICKÝ NÁSTROJ: Řízení přístupu a role
 * Kontrola a dokumentace celého systému autorizace
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center; background: #fff;"><h1 style="color: #000; text-transform: uppercase; letter-spacing: 0.1em;">PŘÍSTUP ODEPŘEN</h1><p style="color: #555;">Pouze admin může používat diagnostické nástroje.</p></body></html>');
}

$pdo = getDbConnection();

// Zjistit strukturu tabulek
$stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Zjistit reklamace
$stmt = $pdo->query("SELECT id, reklamace_id, cislo, jmeno, email, created_by, created_by_role, created_at FROM wgs_reklamace ORDER BY created_at DESC LIMIT 10");
$reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Zjistit uživatele
$stmt = $pdo->query("SELECT id, name, email, role FROM wgs_users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Zjistit fotky
$stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_photos");
$photosCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Zjistit dokumenty
$stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_documents");
$documentsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostika: Řízení přístupu</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
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
            color: var(--wgs-black);
            padding: 2rem;
        }

        .header {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid var(--wgs-black);
        }

        h1 {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--wgs-light-grey);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .section {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
            color: var(--wgs-black);
        }

        .role-box {
            background: #f8f8f8;
            border: 1px solid var(--wgs-border);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .role-box h3 {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            color: var(--wgs-black);
        }

        .role-box ul {
            list-style: none;
            font-size: 0.8rem;
            color: var(--wgs-grey);
        }

        .role-box ul li {
            padding: 0.25rem 0;
            padding-left: 1rem;
            position: relative;
        }

        .role-box ul li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--wgs-black);
            font-weight: bold;
        }

        .file-path {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 0.5rem 1rem;
            font-family: monospace;
            font-size: 0.75rem;
            margin: 0.5rem 0;
            border: 2px solid var(--wgs-black);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.8rem;
        }

        table th {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.7rem;
        }

        table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--wgs-border);
            color: var(--wgs-grey);
        }

        table tr:hover {
            background: #f8f8f8;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid var(--wgs-black);
        }

        .badge-admin { background: var(--wgs-black); color: var(--wgs-white); }
        .badge-prodejce { background: #e3f2fd; color: #1976d2; border-color: #1976d2; }
        .badge-technik { background: #fff3e0; color: #f57c00; border-color: #f57c00; }
        .badge-guest { background: #f3e5f5; color: #7b1fa2; border-color: #7b1fa2; }

        .status-ok { color: #4CAF50; font-weight: 600; }
        .status-error { color: #e74c3c; font-weight: 600; }

        code {
            background: #f8f8f8;
            padding: 0.25rem 0.5rem;
            font-family: monospace;
            border: 1px solid var(--wgs-border);
            font-size: 0.75rem;
        }

        .back-link {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.75rem 1.5rem;
            background: var(--wgs-black);
            color: var(--wgs-white);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid var(--wgs-black);
            transition: all 0.3s;
        }

        .back-link:hover {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>DIAGNOSTIKA: ŘÍZENÍ PŘÍSTUPU</h1>
        <p class="subtitle">Kompletní přehled systému autorizace a rolí</p>
    </div>

    <!-- SYSTÉMOVÝ PŘEHLED -->
    <div class="section">
        <h2>SYSTÉMOVÝ PŘEHLED</h2>
        <table>
            <tr>
                <td><strong>Reklamace v DB:</strong></td>
                <td><?= count($reklamace) ?> (zobrazeno 10 nejnovějších)</td>
            </tr>
            <tr>
                <td><strong>Uživatelé:</strong></td>
                <td><?= count($users) ?></td>
            </tr>
            <tr>
                <td><strong>Fotky:</strong></td>
                <td><?= $photosCount ?></td>
            </tr>
            <tr>
                <td><strong>Dokumenty (PDF):</strong></td>
                <td><?= $documentsCount ?></td>
            </tr>
            <tr>
                <td><strong>RBAC systém:</strong></td>
                <td class="<?= in_array('created_by', $columns) ? 'status-ok' : 'status-error' ?>">
                    <?= in_array('created_by', $columns) ? '✓ AKTIVNÍ' : '✗ NENÍ NAINSTALOVÁN' ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- ROLE A PŘÍSTUPOVÁ PRÁVA -->
    <div class="section">
        <h2>ROLE A PŘÍSTUPOVÁ PRÁVA</h2>

        <div class="role-box">
            <h3><span class="badge badge-guest">GUEST</span> Zákazník (bez registrace)</h3>
            <ul>
                <li>Vyplní formulář v <code>novareklamace.php</code></li>
                <li>Reklamace se uloží s <code>created_by_role = 'guest'</code></li>
                <li>V <code>seznam.php</code> vidí POUZE své reklamace (podle emailu)</li>
                <li>Technik má k nim přístup, prodejce NEMÁ</li>
                <li>V detailu vidí vše včetně fotek</li>
            </ul>
        </div>

        <div class="role-box">
            <h3><span class="badge badge-prodejce">PRODEJCE</span> Prodejce (přihlášený)</h3>
            <ul>
                <li>Přihlásí se přes <code>login.php</code></li>
                <li>ID/email se registruje v session: <code>$_SESSION['user_id']</code>, <code>$_SESSION['user_email']</code></li>
                <li>Vyplní <code>novareklamace.php</code></li>
                <li>Reklamace se uloží s <code>created_by = user_id</code> a <code>created_by_role = 'prodejce'</code></li>
                <li>V <code>seznam.php</code> vidí POUZE SVÉ reklamace (WHERE created_by = user_id)</li>
                <li>Vidí změny které udělal technik</li>
                <li>V detailu vidí vše včetně fotek a protokolů</li>
            </ul>
        </div>

        <div class="role-box">
            <h3><span class="badge badge-technik">TECHNIK</span> Technik</h3>
            <ul>
                <li>Vidí VŠECHNY reklamace (žádný WHERE filtr)</li>
                <li>Má přístup ke všem reklamacím od zákazníků i prodejců</li>
                <li>Může domluvit termín, zahájit návštěvu, vytvořit protokol</li>
                <li>Fotografie a protokol se připojí k detailu zákazníka</li>
                <li>Data jsou dostupná min. 5 let zpětně</li>
            </ul>
        </div>

        <div class="role-box">
            <h3><span class="badge badge-admin">ADMIN</span> Administrátor</h3>
            <ul>
                <li>Vidí VŠECHNY reklamace (bez filtrů)</li>
                <li>Může dělat vše co zákazník, prodejce nebo technik</li>
                <li>Má přístup k admin panelu, nástrojům, diagnostice</li>
                <li>Může simulovat jiné role pro testování</li>
            </ul>
        </div>
    </div>

    <!-- KLÍČOVÉ SOUBORY -->
    <div class="section">
        <h2>KLÍČOVÉ SOUBORY A JEJICH FUNKCE</h2>

        <h3 style="font-size: 0.85rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">AUTENTIZACE A AUTORIZACE:</h3>
        <div class="file-path">app/controllers/login_controller.php</div>
        <p style="font-size: 0.8rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Přihlášení uživatelů. Nastavuje <code>$_SESSION['user_id']</code>, <code>$_SESSION['user_email']</code>, <code>$_SESSION['role']</code>
        </p>

        <div class="file-path">app/controllers/load.php</div>
        <p style="font-size: 0.8rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Načítání reklamací pro seznam.php. <strong>KRITICKÝ SOUBOR</strong> - obsahuje logiku filtrování podle rolí.
            <br>Řádky 46-119: Kompletní logika řízení přístupu
        </p>

        <h3 style="font-size: 0.85rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">VYTVÁŘENÍ REKLAMACÍ:</h3>
        <div class="file-path">novareklamace.php</div>
        <p style="font-size: 0.8rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Formulář pro vytvoření reklamace (zákazník nebo prodejce)
        </p>

        <div class="file-path">app/controllers/save.php</div>
        <p style="font-size: 0.8rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Ukládání nové reklamace. Řádky 360-366: Nastavení <code>created_by</code> a <code>created_by_role</code>
        </p>

        <div class="file-path">app/controllers/save_photos.php</div>
        <p style="font-size: 0.8rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Ukládání fotek z formuláře do databáze <code>wgs_photos</code>
        </p>

        <h3 style="font-size: 0.85rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">ZOBRAZENÍ A PRÁCE:</h3>
        <div class="file-path">seznam.php</div>
        <p style="font-size: 0.8rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Seznam reklamací. Volá <code>app/controllers/load.php</code> pro načtení dat podle role
        </p>

        <div class="file-path">assets/js/seznam.js</div>
        <p style="font-size: 0.8rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            JavaScript pro seznam.php. Řádek 1376: Funkce <code>showCustomerDetail()</code> zobrazuje detail zákazníka včetně fotek
        </p>

        <div class="file-path">api/get_photos_api.php</div>
        <p style="font-size: 0.8rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            API pro načítání fotek z databáze. Vrací data pro seznam.js i protokol.min.js
        </p>
    </div>

    <!-- UŽIVATELÉ V SYSTÉMU -->
    <div class="section">
        <h2>UŽIVATELÉ V SYSTÉMU</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Jméno</th>
                    <th>Email</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <?php
                        $role = strtolower($user['role']);
                        $badgeClass = 'badge-guest';
                        if ($role === 'admin') $badgeClass = 'badge-admin';
                        elseif ($role === 'prodejce') $badgeClass = 'badge-prodejce';
                        elseif ($role === 'technik') $badgeClass = 'badge-technik';
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(strtoupper($user['role'])) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- NEJNOVĚJŠÍ REKLAMACE -->
    <div class="section">
        <h2>NEJNOVĚJŠÍ REKLAMACE (10)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Reklamace ID</th>
                    <th>Číslo</th>
                    <th>Jméno</th>
                    <th>Created By</th>
                    <th>Role</th>
                    <th>Datum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reklamace as $rek): ?>
                <tr>
                    <td><?= htmlspecialchars($rek['id']) ?></td>
                    <td><?= htmlspecialchars($rek['reklamace_id']) ?></td>
                    <td><?= htmlspecialchars($rek['cislo']) ?></td>
                    <td><?= htmlspecialchars($rek['jmeno']) ?></td>
                    <td><?= htmlspecialchars($rek['created_by'] ?? 'NULL') ?></td>
                    <td>
                        <?php
                        $role = strtolower($rek['created_by_role'] ?? 'guest');
                        $badgeClass = 'badge-guest';
                        if ($role === 'admin') $badgeClass = 'badge-admin';
                        elseif ($role === 'prodejce') $badgeClass = 'badge-prodejce';
                        elseif ($role === 'technik') $badgeClass = 'badge-technik';
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(strtoupper($role)) ?></span>
                    </td>
                    <td><?= htmlspecialchars($rek['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TESTOVÁNÍ -->
    <div class="section">
        <h2>TESTOVÁNÍ PŘÍSTUPŮ</h2>
        <p style="font-size: 0.85rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Pro testování různých rolí použij nástroj v Admin panelu:
        </p>
        <a href="/admin.php?tab=tools" class="back-link">TESTOVÁNÍ ROLÍ V ADMIN PANELU</a>
    </div>

    <a href="/admin.php?tab=tools" class="back-link">← ZPĚT NA NÁSTROJE</a>

    <div style="margin-top: 2rem; text-align: center; color: var(--wgs-light-grey); font-size: 0.8rem;">
        <small>WGS SERVICE - DIAGNOSTIKA ŘÍZENÍ PŘÍSTUPU © 2025</small>
    </div>
</body>
</html>
