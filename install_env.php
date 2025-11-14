<?php
/**
 * INSTALAČNÍ SKRIPT PRO .ENV SOUBOR
 *
 * Tento skript vytvoří .env soubor na serveru.
 * Po úspěšném vytvoření se automaticky smaže.
 *
 * POUŽITÍ:
 * 1. Nahraj tento soubor na server (do hlavního adresáře vedle index.php)
 * 2. Otevři v prohlížeči: https://www.wgs-service.cz/install_env.php
 * 3. Skript se po použití automaticky smaže
 */

// Bezpečnostní kontrola - skript se spustí pouze jednou
$envPath = __DIR__ . '/.env';

// Obsah .env souboru
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
DB_PASS=p7u.s13mR2018

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

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace .env - WGS Service</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        strong {
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .steps {
            margin-top: 20px;
        }
        .steps li {
            margin-bottom: 10px;
            margin-left: 20px;
        }
        .admin-key {
            background: #667eea;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        .admin-key code {
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 18px;
            font-weight: bold;
            padding: 8px 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        // Kontrola zda .env už existuje
        if (file_exists($envPath)) {
            echo '<div class="icon">⚠️</div>';
            echo '<h1>Soubor .env již existuje</h1>';
            echo '<p class="subtitle">Instalace není potřeba</p>';
            echo '<div class="status warning">';
            echo '<strong>Soubor .env je již vytvořen!</strong>';
            echo 'Pokud chceš přepsat existující .env soubor, smaž ho nejprve ručně a spusť tento skript znovu.';
            echo '</div>';
            echo '<div class="status info">';
            echo '<strong>💡 Doporučení:</strong>';
            echo '<ul class="steps">';
            echo '<li>Pokud aplikace funguje správně, není potřeba nic měnit</li>';
            echo '<li>Tento instalační skript (<code>install_env.php</code>) můžeš nyní smazat</li>';
            echo '<li>Zkus se přihlásit do adminu klíčem: <code>ADMIN2025393F940A</code></li>';
            echo '</ul>';
            echo '</div>';
        } else {
            // Vytvoření .env souboru
            $result = file_put_contents($envPath, $envContent);

            if ($result !== false) {
                // Nastavení oprávnění na 600 (read/write jen pro vlastníka)
                @chmod($envPath, 0600);

                echo '<div class="icon">✅</div>';
                echo '<h1>Instalace úspěšná!</h1>';
                echo '<p class="subtitle">Soubor .env byl vytvořen</p>';

                echo '<div class="status success">';
                echo '<strong>✓ Soubor .env byl úspěšně vytvořen!</strong>';
                echo 'Aplikace je nyní plně nakonfigurovaná a připravená k použití.';
                echo '</div>';

                echo '<div class="admin-key">';
                echo '<strong>🔑 Tvůj Admin přihlašovací klíč:</strong><br>';
                echo '<code>ADMIN2025393F940A</code>';
                echo '</div>';

                echo '<div class="status info">';
                echo '<strong>📋 Další kroky:</strong>';
                echo '<ul class="steps">';
                echo '<li>Jdi na <a href="/admin" style="color: #0c5460; text-decoration: underline;">https://www.wgs-service.cz/admin</a></li>';
                echo '<li>Přihlaš se admin klíčem: <code>ADMIN2025393F940A</code></li>';
                echo '<li>Aplikace by měla plně fungovat</li>';
                echo '<li><strong>DŮLEŽITÉ:</strong> Smaž tento soubor (<code>install_env.php</code>) z bezpečnostních důvodů!</li>';
                echo '</ul>';
                echo '</div>';

                // Pokus o automatické smazání instalačního skriptu
                $selfDelete = @unlink(__FILE__);
                if ($selfDelete) {
                    echo '<div class="status success">';
                    echo '<strong>🗑️ Tento instalační skript byl automaticky smazán</strong>';
                    echo 'Instalační soubor byl z bezpečnostních důvodů automaticky odstraněn.';
                    echo '</div>';
                } else {
                    echo '<div class="status warning">';
                    echo '<strong>⚠️ Prosím smaž tento soubor manuálně!</strong>';
                    echo 'Automatické smazání se nezdařilo. Z bezpečnostních důvodů prosím smaž soubor <code>install_env.php</code> ručně přes File Manager.';
                    echo '</div>';
                }

            } else {
                echo '<div class="icon">❌</div>';
                echo '<h1>Chyba při instalaci</h1>';
                echo '<p class="subtitle">Soubor .env se nepodařilo vytvořit</p>';

                echo '<div class="status error">';
                echo '<strong>✗ Nepodařilo se vytvořit .env soubor!</strong>';
                echo 'Zkontroluj oprávnění k zápisu do adresáře.';
                echo '</div>';

                echo '<div class="status info">';
                echo '<strong>Možná řešení:</strong>';
                echo '<ul class="steps">';
                echo '<li>Zkontroluj, že adresář má oprávnění k zápisu (chmod 755)</li>';
                echo '<li>Zkontroluj vlastnictví adresáře (měl by patřit web serveru)</li>';
                echo '<li>Kontaktuj podporu hostingu pokud problém přetrvává</li>';
                echo '</ul>';
                echo '</div>';
            }
        }
        ?>
    </div>
</body>
</html>
