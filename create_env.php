<?php
/**
 * JEDNODUCHÝ INSTALAČNÍ SKRIPT - POUZE .ENV
 *
 * Vytvoří .env soubor a smaže se.
 * POUŽITÍ: Otevři https://www.wgs-service.cz/create_env.php
 */

// Zobrazení chyb pro debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$envPath = __DIR__ . '/.env';
$success = false;
$message = '';
$alreadyExists = false;

// Kontrola zda .env už existuje
if (file_exists($envPath)) {
    $alreadyExists = true;
    $message = 'Soubor .env již existuje. Nebyl přepsán.';
} else {
    // Obsah .env
    $envContent = <<<'ENV'
# ========================================
# WHITE GLOVE SERVICE - ENVIRONMENT CONFIG
# ========================================
# DŮLEŽITÉ: Tento soubor obsahuje citlivé údaje!
# Nikdy ho necommituj do GITu!

# ========== DATABÁZE ==========
# Údaje z hostingu Český hosting
DB_HOST=127.0.0.1
DB_NAME=wgs-servicecz01
DB_USER=wgs-servicecz002
DB_PASS=O7cw+hkbKSrg/Eew

# ========== SMTP / EMAIL ==========
# Pokud nevíš SMTP údaje, můžeš prozatím nechat tyto hodnoty
# Aplikace bude fungovat, jen nebude odesílat emaily
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_FROM=noreply@wgs-service.cz
SMTP_USER=smtp_user_placeholder
SMTP_PASS=smtp_pass_placeholder

# ========== API KLÍČE ==========
# Geoapify pro mapy - pokud nemáš klíč, nech placeholder
GEOAPIFY_API_KEY=placeholder_geoapify_key

# Deepl pro překlady (nepovinné)
DEEPL_API_KEY=optional_later

# ========== BEZPEČNOST ==========
# JWT Secret pro autentizaci (VYGENEROVÁNO)
JWT_SECRET=7a5a93f9a41880e37bcecfcfe758633f117c459d9b2022711a2e82300509c8cc

# Admin přihlašovací klíč hash (VYGENEROVÁNO)
# Tvůj admin klíč pro přihlášení je: ADMIN2025393F940A
ADMIN_KEY_HASH=3d408179d8180f5dcfa23531919422088ba5b489053de123961ab08eb1003381

# ========== PROSTŘEDÍ ==========
ENVIRONMENT=production
ENV;

    // Pokus o vytvoření .env
    $result = @file_put_contents($envPath, $envContent);

    if ($result !== false) {
        @chmod($envPath, 0600);
        $success = true;
        $message = 'Soubor .env byl úspěšně vytvořen!';
    } else {
        $message = 'CHYBA: Nepodařilo se vytvořit .env soubor. Zkontroluj oprávnění adresáře.';
    }
}

// Automatické smazání tohoto skriptu
$selfDeleted = false;
if ($success) {
    $selfDeleted = @unlink(__FILE__);
}

?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? '✅ Hotovo' : ($alreadyExists ? '⚠️ Již existuje' : '❌ Chyba'); ?> - WGS Service</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 28px;
        }
        .message {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .success-box {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-box {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .admin-key {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .admin-key h3 {
            font-size: 14px;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        .admin-key code {
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 28px;
            font-weight: bold;
            padding: 12px 24px;
            border-radius: 8px;
            display: inline-block;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            background: #5568d3;
        }
        .steps {
            text-align: left;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .steps h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .steps ol {
            margin-left: 20px;
        }
        .steps li {
            margin-bottom: 10px;
            line-height: 1.6;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($success): ?>
            <div class="icon">✅</div>
            <h1>Instalace dokončena!</h1>
            <div class="success-box">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($selfDeleted): ?>
                    <br><br>✓ Tento instalační skript byl automaticky smazán.
                <?php endif; ?>
            </div>

            <div class="admin-key">
                <h3>🔑 TVŮJ ADMIN PŘIHLAŠOVACÍ KLÍČ</h3>
                <code>ADMIN2025393F940A</code>
            </div>

            <div class="steps">
                <h3>📋 Co dál?</h3>
                <ol>
                    <li>Klikni na tlačítko níže a jdi na Admin panel</li>
                    <li>Přihlaš se klíčem: <strong>ADMIN2025393F940A</strong></li>
                    <li>Aplikace je plně funkční! 🎉</li>
                </ol>
            </div>

            <a href="/admin" class="btn">→ Přejít do Admin panelu</a>

        <?php elseif ($alreadyExists): ?>
            <div class="icon">⚠️</div>
            <h1>Soubor .env již existuje</h1>
            <div class="warning-box">
                <?php echo htmlspecialchars($message); ?>
                <br><br>
                Pokud chceš přepsat existující .env, smaž ho nejprve ručně a spusť tento skript znovu.
            </div>

            <div class="admin-key">
                <h3>🔑 TVŮJ ADMIN PŘIHLAŠOVACÍ KLÍČ</h3>
                <code>ADMIN2025393F940A</code>
            </div>

            <a href="/admin" class="btn">→ Přejít do Admin panelu</a>

        <?php else: ?>
            <div class="icon">❌</div>
            <h1>Chyba při instalaci</h1>
            <div class="error-box">
                <?php echo htmlspecialchars($message); ?>
                <br><br>
                <strong>Možná řešení:</strong>
                <ul style="text-align: left; margin: 10px 0 0 20px;">
                    <li>Zkontroluj oprávnění adresáře (chmod 755)</li>
                    <li>Zkontroluj vlastníka adresáře</li>
                    <li>Zkus vytvořit .env ručně přes File Manager</li>
                </ul>
            </div>
        <?php endif; ?>

        <p style="margin-top: 30px; color: #999; font-size: 12px;">
            WGS Service © <?php echo date('Y'); ?>
        </p>
    </div>
</body>
</html>
