<?php
/**
 * Nastavení .env souboru pro WGS Service
 *
 * Tento skript pomáhá vytvořit .env soubor s databázovými údaji
 * Můžete jej spustit vícekrát - existující .env nebude přepsán bez potvrzení
 */

// Bezpečnostní kontrola - vyžaduje admin přihlášení vždy
session_start();
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$isAdmin) {
    die("<h1>PŘÍSTUP ODEPŘEN</h1><p>Tento skript může spustit pouze přihlášený administrátor.</p>");
}

$rootPath = __DIR__;
$envPath = $rootPath . '/.env';
$envExamplePath = $rootPath . '/.env.example';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Nastavení .env souboru - WGS Service</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
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
        .form-group { margin: 20px 0; }
        label { display: block; font-weight: bold; margin-bottom: 5px;
                color: #333; }
        input[type=text], input[type=password] {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 5px; font-size: 14px; box-sizing: border-box; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        pre { background: #f8f8f8; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; font-size: 12px; }
        .help-text { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
<div class='container'>";

// FÁZE 1: Kontrola stavu .env souboru
echo "<h1>🔧 Nastavení .env souboru</h1>";

$envExists = file_exists($envPath);
$envExampleExists = file_exists($envExamplePath);

if ($envExists) {
    echo "<div class='warning'>";
    echo "<strong>⚠️ VAROVÁNÍ:</strong> Soubor .env již existuje!<br>";
    echo "<small>Cesta: <code>{$envPath}</code></small><br><br>";

    if (!isset($_POST['overwrite'])) {
        echo "<form method='post'>";
        echo "<p>Chcete přepsat existující .env soubor?</p>";
        echo "<button type='submit' name='overwrite' value='1' class='btn btn-danger'>Ano, přepsat .env</button>";
        echo "<a href='admin.php' class='btn'>Zpět do admin</a>";
        echo "</form>";
        echo "</div></div></body></html>";
        exit;
    }
}

// FÁZE 2: Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_env'])) {
    echo "<h2>📝 Vytváření .env souboru...</h2>";

    try {
        // Získat data z formuláře
        $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? ''; // Netrimmovat heslo!

        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = trim($_POST['smtp_port'] ?? '587');
        $smtpFrom = trim($_POST['smtp_from'] ?? '');
        $smtpUser = trim($_POST['smtp_user'] ?? '');
        $smtpPass = $_POST['smtp_pass'] ?? '';

        $geoapifyKey = trim($_POST['geoapify_key'] ?? '');
        $jwtSecret = trim($_POST['jwt_secret'] ?? '');
        $adminKeyHash = trim($_POST['admin_key_hash'] ?? '');
        $environment = trim($_POST['environment'] ?? 'production');

        // Validace povinných polí
        $errors = [];
        if (empty($dbName)) $errors[] = "Název databáze (DB_NAME) je povinný";
        if (empty($dbUser)) $errors[] = "Uživatel databáze (DB_USER) je povinný";

        if (!empty($errors)) {
            echo "<div class='error'>";
            echo "<strong>CHYBA: Chybí povinná pole</strong><br>";
            foreach ($errors as $err) {
                echo "• {$err}<br>";
            }
            echo "</div>";
        } else {
            // Vytvořit obsah .env souboru
            $envContent = "# WGS Service - Environment Configuration
# Generated: " . date('Y-m-d H:i:s') . "

# ========================================
# DATABASE CONFIGURATION
# ========================================
DB_HOST={$dbHost}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}

# ========================================
# SMTP CONFIGURATION
# ========================================
SMTP_HOST={$smtpHost}
SMTP_PORT={$smtpPort}
SMTP_FROM={$smtpFrom}
SMTP_USER={$smtpUser}
SMTP_PASS={$smtpPass}

# ========================================
# API KEYS
# ========================================
GEOAPIFY_KEY={$geoapifyKey}
GEOAPIFY_API_KEY={$geoapifyKey}
DEEPL_API_KEY=optional_later

# ========================================
# SECURITY
# ========================================
JWT_SECRET={$jwtSecret}
ADMIN_KEY_HASH={$adminKeyHash}

# ========================================
# ENVIRONMENT
# ========================================
ENVIRONMENT={$environment}

# ========================================
# WEB PUSH NOTIFICATIONS (VAPID)
# ========================================
# Vygenerujte klice pomoci setup_web_push.php
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:info@wgs-service.cz
";

            // Uložit .env soubor
            if (file_put_contents($envPath, $envContent) !== false) {
                // Nastavit správná oprávnění (pouze owner může číst/zapisovat)
                chmod($envPath, 0600);

                echo "<div class='success'>";
                echo "<strong>✅ ÚSPĚCH:</strong> Soubor .env byl vytvořen!<br>";
                echo "<small>Cesta: <code>{$envPath}</code></small><br>";
                echo "<small>Oprávnění: 0600 (pouze vlastník může číst/zapisovat)</small>";
                echo "</div>";

                // FÁZE 3: Test databázového připojení
                echo "<h2>🔍 Test databázového připojení...</h2>";

                try {
                    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);

                    echo "<div class='success'>";
                    echo "<strong>✅ Databázové připojení FUNGUJE!</strong><br>";
                    echo "Host: <code>{$dbHost}</code><br>";
                    echo "Databáze: <code>{$dbName}</code><br>";
                    echo "Uživatel: <code>{$dbUser}</code>";
                    echo "</div>";

                    // Test dotazu
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    echo "<div class='info'>";
                    echo "<strong>📊 Test dotazu:</strong><br>";
                    echo "Počet reklamací v databázi: <strong>{$result['count']}</strong>";
                    echo "</div>";

                    echo "<a href='seznam.php' class='btn'>Otevřít seznam reklamací</a>";
                    echo "<a href='admin.php' class='btn'>Zpět do admin</a>";

                } catch (PDOException $e) {
                    echo "<div class='error'>";
                    echo "<strong>❌ CHYBA: Databázové připojení SELHALO</strong><br>";
                    echo "Zkontrolujte přihlašovací údaje a zkuste to znovu.<br><br>";
                    echo "<details>";
                    echo "<summary>Zobrazit technické detaily</summary>";
                    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
                    echo "</details>";
                    echo "</div>";
                }

            } else {
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong> Nepodařilo se vytvořit soubor .env<br>";
                echo "Zkontrolujte oprávnění složky.";
                echo "</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }

    echo "</div></body></html>";
    exit;
}

// FÁZE 4: Zobrazit formulář
echo "<div class='info'>";
echo "<strong>ℹ️ Stav:</strong><br>";
echo "Soubor .env: " . ($envExists ? "✅ Existuje" : "❌ Neexistuje") . "<br>";
echo "Šablona .env.example: " . ($envExampleExists ? "✅ Existuje" : "❌ Neexistuje");
echo "</div>";

// Načíst výchozí hodnoty z .env.example pokud existuje
$defaults = [
    'db_host' => '127.0.0.1',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_from' => '',
    'smtp_user' => '',
    'smtp_pass' => '',
    'geoapify_key' => '',
    'jwt_secret' => bin2hex(random_bytes(32)), // Generovat náhodný JWT secret
    'admin_key_hash' => '',
    'environment' => 'production'
];

echo "<h2>📋 Vyplňte databázové údaje</h2>";
echo "<form method='post'>";

echo "<h3>🗄️ Databáze</h3>";

echo "<div class='form-group'>";
echo "<label>Host databáze (DB_HOST):</label>";
echo "<input type='text' name='db_host' value='{$defaults['db_host']}' required>";
echo "<div class='help-text'>Obvykle 127.0.0.1 nebo localhost</div>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>Název databáze (DB_NAME):</label>";
echo "<input type='text' name='db_name' value='{$defaults['db_name']}' required>";
echo "<div class='help-text'>Např. wgs-servicecz01 nebo wgs_database</div>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>Uživatel databáze (DB_USER):</label>";
echo "<input type='text' name='db_user' value='{$defaults['db_user']}' required>";
echo "<div class='help-text'>Databázový uživatel s přístupovými právy</div>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>Heslo databáze (DB_PASS):</label>";
echo "<input type='password' name='db_pass' value='{$defaults['db_pass']}'>";
echo "<div class='help-text'>Nechte prázdné, pokud databáze nemá heslo</div>";
echo "</div>";

echo "<h3>📧 SMTP (Email) - Volitelné</h3>";

echo "<div class='form-group'>";
echo "<label>SMTP Host:</label>";
echo "<input type='text' name='smtp_host' value='{$defaults['smtp_host']}'>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>SMTP Port:</label>";
echo "<input type='text' name='smtp_port' value='{$defaults['smtp_port']}'>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>SMTP Email (od):</label>";
echo "<input type='text' name='smtp_from' value='{$defaults['smtp_from']}'>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>SMTP Uživatel:</label>";
echo "<input type='text' name='smtp_user' value='{$defaults['smtp_user']}'>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>SMTP Heslo:</label>";
echo "<input type='password' name='smtp_pass' value='{$defaults['smtp_pass']}'>";
echo "</div>";

echo "<h3>🔐 Bezpečnost - Volitelné</h3>";

echo "<div class='form-group'>";
echo "<label>JWT Secret (auto-generováno):</label>";
echo "<input type='text' name='jwt_secret' value='{$defaults['jwt_secret']}' readonly>";
echo "<div class='help-text'>Náhodně generovaný 64-znakový hex string</div>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>Admin Key Hash (SHA256):</label>";
echo "<input type='text' name='admin_key_hash' value='{$defaults['admin_key_hash']}'>";
echo "<div class='help-text'>SHA256 hash registračního klíče admina</div>";
echo "</div>";

echo "<h3>🗺️ API Klíče - Volitelné</h3>";

echo "<div class='form-group'>";
echo "<label>Geoapify API Key:</label>";
echo "<input type='text' name='geoapify_key' value='{$defaults['geoapify_key']}'>";
echo "<div class='help-text'>Pro mapy a geokódování adres</div>";
echo "</div>";

echo "<h3>⚙️ Prostředí</h3>";

echo "<div class='form-group'>";
echo "<label>Environment:</label>";
echo "<select name='environment' class='btn' style='width: auto; margin: 0;'>";
echo "<option value='production'" . ($defaults['environment'] === 'production' ? ' selected' : '') . ">Production</option>";
echo "<option value='development'" . ($defaults['environment'] === 'development' ? ' selected' : '') . ">Development</option>";
echo "<option value='staging'" . ($defaults['environment'] === 'staging' ? ' selected' : '') . ">Staging</option>";
echo "</select>";
echo "</div>";

echo "<button type='submit' name='create_env' value='1' class='btn'>Vytvořit .env soubor</button>";
echo "<a href='admin.php' class='btn' style='background: #999;'>Zrušit</a>";
echo "</form>";

echo "</div></body></html>";
?>
