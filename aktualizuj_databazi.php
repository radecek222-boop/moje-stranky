<?php
/**
 * AKTUALIZACE DATABÁZOVÝCH CREDENTIALS
 * Bezpečný skript pro opravu .env souboru
 *
 * DŮLEŽITÉ: Pouze pro adminy!
 */

// Kontrola že session není ještě spuštěna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Načíst init.php pro kontrolu admin přístupu (pokud funguje)
$initExists = file_exists(__DIR__ . '/init.php');
if ($initExists) {
    try {
        require_once __DIR__ . '/init.php';
    } catch (Exception $e) {
        // Init.php může selhat kvůli DB - to je OK, pokračujeme
        error_log("Init.php failed (expected): " . $e->getMessage());
    }
}

// BEZPEČNOSTNÍ KONTROLA - dvě úrovně:
// 1. Admin přihlášen přes session
// 2. NEBO zadá správný admin klíč přímo
$jeAdminPrihlaseny = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$adminKlicZadan = false;

// Lock soubor - zamezit opakovanému spuštění
$lockFile = __DIR__ . '/.env_update_lock';
$uzAktualizovano = file_exists($lockFile);

// ==================================================
// GET REQUEST - Zobrazit formulář
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktualizace databázových credentials | WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #000;
            padding: 2rem;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            border: 2px solid #000;
        }
        .header {
            background: #000;
            color: #fff;
            padding: 2rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.5rem;
        }
        .content {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
        }
        input:focus {
            outline: none;
            border-color: #000;
        }
        .btn {
            background: #000;
            color: #fff;
            border: 2px solid #000;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            width: 100%;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #fff;
            color: #000;
        }
        .warning {
            background: #fff9e6;
            border-left: 4px solid #ff9900;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .success {
            background: #f0fff0;
            border-left: 4px solid #006600;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .info {
            background: #f5f5f5;
            border-left: 4px solid #555;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        code {
            background: #f5f5f5;
            padding: 0.2rem 0.5rem;
            border: 1px solid #ddd;
            font-family: monospace;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Aktualizace databáze</h1>
            <p style="font-size: 0.85rem; opacity: 0.8;">Oprava .env souboru s novými credentials</p>
        </div>

        <div class="content">
            <?php if ($uzAktualizovano): ?>
                <div class="success">
                    <h2 style="font-size: 1.1rem; margin-bottom: 0.5rem;">✓ Již aktualizováno</h2>
                    <p>Databázové credentials byly již aktualizovány.</p>
                    <p style="margin-top: 0.5rem;"><strong>Lock soubor:</strong> <code><?php echo htmlspecialchars($lockFile); ?></code></p>
                    <p style="margin-top: 1rem;">Pokud stále máš problémy, smaž lock soubor a zkus znovu.</p>
                </div>
                <a href="admin.php" class="btn" style="display: block; text-decoration: none; text-align: center;">← ZPĚT DO ADMIN PANELU</a>
            <?php else: ?>
                <?php if (!$jeAdminPrihlaseny): ?>
                    <div class="warning">
                        <strong>⚠️ POZOR:</strong> Tento skript upravuje kritický .env soubor!<br>
                        Pro spuštění zadej admin klíč níže.
                    </div>
                <?php endif; ?>

                <div class="info">
                    <strong>Aktuální informace z phpMyAdmin:</strong><br>
                    • <strong>Databáze:</strong> <code>wgs-servicecz01</code><br>
                    • <strong>Heslo:</strong> <code>p7u.s13mR2018</code><br>
                    • <strong>Uživatel:</strong> pravděpodobně <code>wgs-servicecz01</code> (zkontroluj v phpMyAdmin → Oprávnění)
                </div>

                <form method="POST">
                    <?php if (!$jeAdminPrihlaseny): ?>
                        <div class="form-group">
                            <label for="admin_key">Admin klíč (pro ověření)</label>
                            <input type="password" id="admin_key" name="admin_key" required placeholder="Zadej admin klíč">
                            <p style="margin-top: 0.3rem; font-size: 0.8rem; color: #666;">Stejný klíč jako pro přihlášení do admin panelu</p>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="db_host">DB Host</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>

                    <div class="form-group">
                        <label for="db_name">Název databáze</label>
                        <input type="text" id="db_name" name="db_name" value="wgs-servicecz01" required>
                    </div>

                    <div class="form-group">
                        <label for="db_user">DB Uživatel</label>
                        <input type="text" id="db_user" name="db_user" value="wgs-servicecz01" required>
                        <p style="margin-top: 0.3rem; font-size: 0.8rem; color: #666;">Obvykle stejné jako název databáze</p>
                    </div>

                    <div class="form-group">
                        <label for="db_pass">DB Heslo</label>
                        <input type="text" id="db_pass" name="db_pass" value="p7u.s13mR2018" required>
                    </div>

                    <button type="submit" class="btn">AKTUALIZOVAT .ENV SOUBOR</button>
                </form>

                <div class="info" style="margin-top: 1.5rem;">
                    <strong>Co se stane:</strong><br>
                    1. Načte se stávající .env soubor (pokud existuje)<br>
                    2. Aktualizují se pouze DB_* proměnné<br>
                    3. Vytvoří se záloha do <code>.env.backup</code><br>
                    4. Uloží se nový .env soubor<br>
                    5. Otestuje se připojení k databázi
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// ==================================================
// POST REQUEST - Provést aktualizaci
// ==================================================

// BEZPEČNOSTNÍ KONTROLA #1: Admin session NEBO admin klíč
if (!$jeAdminPrihlaseny) {
    $adminKey = $_POST['admin_key'] ?? '';

    // Načíst ADMIN_KEY_HASH z .env nebo použít fallback
    $envFile = __DIR__ . '/.env';
    $adminKeyHash = null;

    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        if (preg_match('/ADMIN_KEY_HASH\s*=\s*["\']?([^"\'\r\n]+)["\']?/', $envContent, $matches)) {
            $adminKeyHash = $matches[1];
        }
    }

    // Pokud není hash v .env, zkusit hardcoded fallback (POUZE PRO NOUZI!)
    // TODO: Změnit toto heslo po první instalaci!
    if (!$adminKeyHash) {
        $adminKeyHash = 'fa68c5eb3854cd3b2054dc93eb77a3b2a8e37dd66dcd16006d3a7c1b8e8c0e84'; // SHA256 z "wgs2024admin"
    }

    // Ověřit admin klíč
    if (hash('sha256', $adminKey) === $adminKeyHash) {
        $adminKlicZadan = true;
    } else {
        http_response_code(403);
        die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Chyba</title></head><body style="font-family: Poppins; padding: 40px; text-align: center;"><h1 style="color: #cc0000;">❌ NEPLATNÝ ADMIN KLÍČ</h1><p>Zadaný admin klíč je nesprávný.</p><p><a href="?" style="color: #000; border-bottom: 2px solid #000;">← Zkusit znovu</a></p></body></html>');
    }
}

// Kontrola že je povolen zápis
if (!$jeAdminPrihlaseny && !$adminKlicZadan) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins; padding: 40px; text-align: center;"><h1 style="color: #000;">❌ PŘÍSTUP ODEPŘEN</h1><p>Pouze administrátor může aktualizovat .env soubor.</p></body></html>');
}

// BEZPEČNOSTNÍ KONTROLA #2: Lock soubor
if ($uzAktualizovano) {
    http_response_code(400);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Již provedeno</title></head><body style="font-family: Poppins; padding: 40px; text-align: center;"><h1>✓ Již aktualizováno</h1><p>Databázové credentials byly již aktualizovány.</p><p><a href="admin.php">← Zpět do admin panelu</a></p></body></html>');
}

// Získat data z formuláře
$dbHost = trim($_POST['db_host'] ?? 'localhost');
$dbName = trim($_POST['db_name'] ?? 'wgs-servicecz01');
$dbUser = trim($_POST['db_user'] ?? 'wgs-servicecz01');
$dbPass = $_POST['db_pass'] ?? '';

// Validace
if (empty($dbName) || empty($dbUser)) {
    http_response_code(400);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Chyba</title></head><body style="font-family: Poppins; padding: 40px; text-align: center;"><h1 style="color: #cc0000;">❌ CHYBA</h1><p>Název databáze a uživatel jsou povinné.</p><p><a href="?">← Zpět</a></p></body></html>');
}

$vysledky = [];
$chyby = [];

try {
    // ==================================================
    // KROK 1: Záloha stávajícího .env (pokud existuje)
    // ==================================================
    $envFile = __DIR__ . '/.env';
    $envBackup = __DIR__ . '/.env.backup';

    if (file_exists($envFile)) {
        if (!copy($envFile, $envBackup)) {
            throw new Exception('Nepodařilo se vytvořit zálohu .env souboru');
        }
        $vysledky[] = "✓ Vytvořena záloha: .env.backup";
    } else {
        $vysledky[] = "⊙ .env soubor neexistuje, bude vytvořen nový";
    }

    // ==================================================
    // KROK 2: Načíst stávající .env (pokud existuje)
    // ==================================================
    $envVars = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $envVars[$key] = $value;
            }
        }
        $vysledky[] = "✓ Načten stávající .env soubor (" . count($envVars) . " proměnných)";
    }

    // ==================================================
    // KROK 3: Aktualizovat DB credentials
    // ==================================================
    $envVars['DB_HOST'] = $dbHost;
    $envVars['DB_NAME'] = $dbName;
    $envVars['DB_USER'] = $dbUser;
    $envVars['DB_PASS'] = $dbPass;

    $vysledky[] = "✓ Aktualizovány DB credentials";

    // ==================================================
    // KROK 4: Uložit nový .env soubor
    // ==================================================
    $envContent = "# WHITE GLOVE SERVICE - Environment Configuration\n";
    $envContent .= "# Aktualizováno: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($envVars as $key => $value) {
        // Obalit hodnoty s mezerami do uvozovek
        if (strpos($value, ' ') !== false) {
            $value = '"' . $value . '"';
        }
        $envContent .= "{$key}={$value}\n";
    }

    if (file_put_contents($envFile, $envContent) === false) {
        throw new Exception('Nepodařilo se uložit .env soubor');
    }

    $vysledky[] = "✓ Uložen nový .env soubor";

    // ==================================================
    // KROK 5: Otestovat připojení k databázi
    // ==================================================
    try {
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        $vysledky[] = "✓ ÚSPĚCH: Připojení k databázi funguje!";

        // Otestovat že wgs_reklamace existuje
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_reklamace'");
        if ($stmt->rowCount() > 0) {
            $vysledky[] = "✓ Tabulka wgs_reklamace nalezena";
        } else {
            $chyby[] = "⚠ Varování: Tabulka wgs_reklamace nebyla nalezena v databázi";
        }

    } catch (PDOException $e) {
        $chyby[] = "✗ CHYBA: Nepodařilo se připojit k databázi: " . $e->getMessage();
        $chyby[] = "  Zkontroluj že credentials jsou správné v phpMyAdmin";
    }

    // ==================================================
    // KROK 6: Vytvořit lock soubor
    // ==================================================
    if (empty($chyby)) {
        $lockContent = json_encode([
            'datum' => date('Y-m-d H:i:s'),
            'db_host' => $dbHost,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'admin' => $_SESSION['user_email'] ?? 'admin',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], JSON_PRETTY_PRINT);

        file_put_contents($lockFile, $lockContent);
        $vysledky[] = "✓ Vytvořen lock soubor";
    }

} catch (Exception $e) {
    $chyby[] = "✗ KRITICKÁ CHYBA: " . $e->getMessage();
}

// ==================================================
// ZOBRAZENÍ VÝSLEDKU
// ==================================================
$uspech = empty($chyby);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $uspech ? 'Aktualizace úspěšná' : 'Chyba aktualizace'; ?> | WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #000;
            padding: 2rem;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            border: 2px solid #000;
        }
        .header {
            background: <?php echo $uspech ? '#006600' : '#cc0000'; ?>;
            color: #fff;
            padding: 2rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .content {
            padding: 2rem;
        }
        .log {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            margin: 1rem 0;
            max-height: 400px;
            overflow-y: auto;
        }
        .log-item {
            margin: 0.3rem 0;
        }
        .error-log {
            background: #fff0f0;
            border-color: #cc0000;
        }
        .btn {
            background: #000;
            color: #fff;
            border: 2px solid #000;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-decoration: none;
            display: inline-block;
            margin-top: 1.5rem;
        }
        .btn:hover {
            background: #fff;
            color: #000;
        }
        .next-steps {
            background: #f0fff0;
            border-left: 4px solid #006600;
            padding: 1rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $uspech ? '✓ AKTUALIZACE ÚSPĚŠNÁ' : '✗ CHYBA AKTUALIZACE'; ?></h1>
            <p style="font-size: 0.85rem; opacity: 0.8;"><?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <div class="content">
            <?php if (!empty($vysledky)): ?>
                <h2 style="font-size: 1.1rem; margin-bottom: 0.75rem;">Výsledky operací</h2>
                <div class="log">
                    <?php foreach ($vysledky as $vysledek): ?>
                        <div class="log-item"><?php echo htmlspecialchars($vysledek); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($chyby)): ?>
                <h2 style="font-size: 1.1rem; margin-bottom: 0.75rem; color: #cc0000;">Chyby</h2>
                <div class="log error-log">
                    <?php foreach ($chyby as $chyba): ?>
                        <div class="log-item"><?php echo htmlspecialchars($chyba); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($uspech): ?>
                <div class="next-steps">
                    <h3 style="font-size: 1rem; margin-bottom: 0.75rem;">✓ Další kroky</h3>
                    <ol style="margin-left: 1.5rem; line-height: 1.8; font-size: 0.9rem;">
                        <li>Otevři <strong>admin panel</strong> - chyba "Database connection failed" by měla zmizet</li>
                        <li>Spusť <strong>migrační skript</strong>: <a href="oprava_databaze_2025_11_16.php" style="color: #000; text-decoration: underline;">oprava_databaze_2025_11_16.php</a></li>
                        <li>Zkontroluj že <strong>statistiky</strong> a <strong>protokoly</strong> fungují</li>
                    </ol>
                </div>
            <?php endif; ?>

            <a href="admin.php" class="btn">← ZPĚT DO ADMIN PANELU</a>

            <?php if (!$uspech): ?>
                <a href="?" class="btn" style="background: #fff; color: #000; margin-left: 1rem;">ZKUSIT ZNOVU</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
