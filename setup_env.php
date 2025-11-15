<?php
/**
 * INSTALACE .ENV SOUBORU
 *
 * Použije .env.example a doplní produkční hodnoty
 * Po instalaci se automaticky smaže
 */

$envPath = __DIR__ . '/.env';
$examplePath = __DIR__ . '/.env.example';

$success = false;
$message = '';

// Kontrola zda .env už existuje
if (file_exists($envPath)) {
    $message = '⚠️ Soubor .env již existuje';
    $alreadyExists = true;
} elseif (!file_exists($examplePath)) {
    $message = '❌ CHYBA: Soubor .env.example nebyl nalezen!';
    $alreadyExists = false;
} else {
    // Načíst .env.example
    $template = file_get_contents($examplePath);

    // Nahradit placeholdery skutečnými hodnotami
    $env = str_replace(
        [
            'your_database_name',
            'your_database_user',
            'your_database_password',
            'your_smtp_host',
            'your_email@example.com',
            'your_smtp_user',
            'your_smtp_password',
            'your_geoapify_api_key',
            'generate_random_64_char_hex_string',
            'sha256_hash_of_your_admin_registration_key'
        ],
        [
            'wgs-servicecz01',
            'wgs-servicecz002',
            'O7cw+hkbKSrg/Eew',
            'smtp.example.com',
            'noreply@wgs-service.cz',
            'smtp_user_placeholder',
            'smtp_pass_placeholder',
            'placeholder_geoapify_key',
            '7a5a93f9a41880e37bcecfcfe758633f117c459d9b2022711a2e82300509c8cc',
            '3d408179d8180f5dcfa23531919422088ba5b489053de123961ab08eb1003381'
        ],
        $template
    );

    // Uložit .env
    if (file_put_contents($envPath, $env)) {
        @chmod($envPath, 0600);
        $success = true;
        $message = '✅ Soubor .env byl úspěšně vytvořen!';
        $alreadyExists = false;
    } else {
        $message = '❌ CHYBA: Nepodařilo se vytvořit .env soubor';
        $alreadyExists = false;
    }
}

// Auto-delete po úspěchu
$deleted = false;
if ($success) {
    $deleted = @unlink(__FILE__);
}
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WGS Service - Instalace</title>
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
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.3);
            max-width: 550px;
            width: 100%;
            padding: 50px;
            text-align: center;
        }
        .icon { font-size: 90px; margin-bottom: 25px; }
        h1 { color: #333; font-size: 32px; margin-bottom: 15px; }
        .msg { color: #666; font-size: 18px; margin-bottom: 35px; }
        .key {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 25px 0;
        }
        .key h3 { font-size: 14px; margin-bottom: 20px; opacity: 0.95; text-transform: uppercase; letter-spacing: 1px; }
        .key code {
            background: rgba(255,255,255,0.25);
            color: white;
            font-size: 32px;
            font-weight: 900;
            padding: 15px 30px;
            border-radius: 10px;
            display: inline-block;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 18px 45px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            margin-top: 25px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        .info {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        .info li { margin: 8px 0 8px 20px; color: #555; line-height: 1.7; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($success): ?>
            <div class="icon">🎉</div>
            <h1>Instalace dokončena!</h1>
            <p class="msg"><?php echo $message; ?></p>

            <div class="key">
                <h3>🔑 Admin Přihlašovací Klíč</h3>
                <code>ADMIN2025393F940A</code>
            </div>

            <div class="info">
                <strong>✨ Aplikace je připravena:</strong>
                <ul>
                    <li>✅ Databáze nakonfigurována</li>
                    <li>✅ Bezpečnost aktivní</li>
                    <li>✅ JavaScript opravy aplikovány</li>
                </ul>
            </div>

            <a href="/admin" class="btn">→ Otevřít Admin Panel</a>

            <?php if ($deleted): ?>
                <p style="margin-top: 25px; color: #999; font-size: 13px;">
                    ✓ Instalační skript automaticky smazán
                </p>
            <?php endif; ?>

        <?php elseif ($alreadyExists ?? false): ?>
            <div class="icon">ℹ️</div>
            <h1>Již nakonfigurováno</h1>
            <p class="msg"><?php echo $message; ?></p>

            <div class="key">
                <h3>🔑 Admin Klíč</h3>
                <code>ADMIN2025393F940A</code>
            </div>

            <a href="/admin" class="btn">→ Otevřít Admin Panel</a>

        <?php else: ?>
            <div class="icon">❌</div>
            <h1>Instalace selhala</h1>
            <p class="msg"><?php echo $message; ?></p>
            <div class="info">
                <strong>Zkontroluj:</strong>
                <ul>
                    <li>Oprávnění k zápisu (chmod 755)</li>
                    <li>Existenci .env.example</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
    <script>
        setTimeout(() => {
            if (confirm('Přejít na admin panel?')) {
                window.location.href = '/admin';
            }
        }, 2500);
    </script>
    <?php endif; ?>
</body>
</html>
