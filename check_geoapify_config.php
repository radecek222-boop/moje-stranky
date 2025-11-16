<?php
/**
 * Geoapify API Configuration Checker
 *
 * Tento skript zkontroluje, jestli je správně nastavený GEOAPIFY_API_KEY
 * a poskytne jasné instrukce jak problém vyřešit.
 *
 * POUŽITÍ: Otevřete tento soubor v prohlížeči
 * URL: https://www.wgs-service.cz/check_geoapify_config.php
 */

// Zakázat cachování
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geoapify API - Diagnostika</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2D5016 0%, #3d6b1f 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .content {
            padding: 2rem;
        }

        .status-box {
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .status-error {
            background: #fee;
            border-color: #c33;
            color: #811;
        }

        .status-warning {
            background: #ffeaa7;
            border-color: #fdcb6e;
            color: #856404;
        }

        .status-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .status-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }

        .status-box h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            background: #f8f9fa;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }

        .info-row strong {
            color: #333;
        }

        .info-row code {
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .instructions {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }

        .instructions h3 {
            color: #2D5016;
            margin-bottom: 1rem;
        }

        .instructions ol {
            padding-left: 1.5rem;
        }

        .instructions li {
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }

        .code-block {
            background: #2d3748;
            color: #68d391;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 1rem 0;
            overflow-x: auto;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 1rem;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #3d6b1f;
        }

        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗺️ Geoapify API Diagnostika</h1>
            <p>Kontrola konfigurace map a našeptávače adres</p>
        </div>

        <div class="content">
            <?php
            // Load environment
            require_once __DIR__ . '/includes/env_loader.php';
            require_once __DIR__ . '/config/config.php';

            // Get GEOAPIFY_KEY value
            $geoapifyKey = defined('GEOAPIFY_KEY') ? GEOAPIFY_KEY : null;
            $envFileExists = file_exists(__DIR__ . '/.env');
            $envFileReadable = $envFileExists && is_readable(__DIR__ . '/.env');

            // Check if API key is valid
            $isPlaceholder = in_array($geoapifyKey, [
                'placeholder_geoapify_key',
                'your_geoapify_api_key',
                'change-this-in-production',
                null,
                ''
            ]);

            // Detect status
            if (!$geoapifyKey || $isPlaceholder) {
                $status = 'error';
                $statusIcon = '❌';
                $statusTitle = 'CHYBA: Neplatný API klíč';
                $statusMessage = 'Geoapify API klíč není správně nastaven. Mapa a našeptávač nebudou fungovat.';
            } else {
                $status = 'success';
                $statusIcon = '✅';
                $statusTitle = 'Konfigurace vypadá dobře';
                $statusMessage = 'API klíč je nastaven. Pokud mapa stále nefunguje, klíč může být neplatný.';
            }
            ?>

            <!-- Status Box -->
            <div class="status-box status-<?= $status ?>">
                <div class="icon"><?= $statusIcon ?></div>
                <h3><?= $statusTitle ?></h3>
                <p><?= $statusMessage ?></p>
            </div>

            <!-- Configuration Details -->
            <h3 style="margin-bottom: 1rem;">📋 Detaily konfigurace</h3>

            <div class="info-row">
                <strong>.env soubor existuje:</strong>
                <code><?= $envFileExists ? '✅ ANO' : '❌ NE' ?></code>
            </div>

            <div class="info-row">
                <strong>.env je čitelný:</strong>
                <code><?= $envFileReadable ? '✅ ANO' : '❌ NE' ?></code>
            </div>

            <div class="info-row">
                <strong>GEOAPIFY_KEY konstanta:</strong>
                <code><?= $geoapifyKey ? (strlen($geoapifyKey) > 30 ? substr($geoapifyKey, 0, 10) . '...' . substr($geoapifyKey, -10) : $geoapifyKey) : '❌ NENÍ NASTAVENO' ?></code>
            </div>

            <div class="info-row">
                <strong>Je placeholder hodnota:</strong>
                <code><?= $isPlaceholder ? '❌ ANO (neplatný klíč)' : '✅ NE' ?></code>
            </div>

            <div class="info-row">
                <strong>$_ENV['GEOAPIFY_API_KEY']:</strong>
                <code><?= isset($_ENV['GEOAPIFY_API_KEY']) ? (strlen($_ENV['GEOAPIFY_API_KEY']) > 30 ? substr($_ENV['GEOAPIFY_API_KEY'], 0, 10) . '...' : $_ENV['GEOAPIFY_API_KEY']) : 'není nastaveno' ?></code>
            </div>

            <div class="info-row">
                <strong>$_SERVER['GEOAPIFY_API_KEY']:</strong>
                <code><?= isset($_SERVER['GEOAPIFY_API_KEY']) ? (strlen($_SERVER['GEOAPIFY_API_KEY']) > 30 ? substr($_SERVER['GEOAPIFY_API_KEY'], 0, 10) . '...' : $_SERVER['GEOAPIFY_API_KEY']) : 'není nastaveno' ?></code>
            </div>

            <div class="info-row">
                <strong>Cesta k .env:</strong>
                <code><?= __DIR__ ?>/.env</code>
            </div>

            <?php if ($isPlaceholder): ?>
            <!-- Fix Instructions -->
            <div class="instructions">
                <h3>🔧 Jak to opravit (krok za krokem)</h3>

                <h4 style="margin-top: 1.5rem; color: #2D5016;">Krok 1: Získejte Geoapify API klíč (ZDARMA)</h4>
                <ol>
                    <li>Otevřete <a href="https://www.geoapify.com/" target="_blank" style="color: #2D5016;">https://www.geoapify.com/</a></li>
                    <li>Klikněte na <strong>"Get Started for Free"</strong></li>
                    <li>Zaregistrujte se pomocí emailu</li>
                    <li>Vytvořte nový projekt (např. "WGS Service")</li>
                    <li>Zkopírujte API klíč (bude vypadat jako: <code>a1b2c3d4e5f6g7h8...</code>)</li>
                </ol>

                <div class="status-box status-info" style="margin: 1rem 0;">
                    <strong>ℹ️ Free tier:</strong> 3,000 requestů denně (ZDARMA) - více než dost pro běžné použití
                </div>

                <h4 style="margin-top: 1.5rem; color: #2D5016;">Krok 2: Nastavte API klíč na serveru</h4>

                <p><strong>Varianta A - Přes .env soubor (doporučeno):</strong></p>
                <ol>
                    <li>Připojte se na server (FTP, SSH, cPanel File Manager)</li>
                    <li>Najděte soubor <code>.env</code> v root složce projektu</li>
                    <li>Editujte řádek s GEOAPIFY_API_KEY:</li>
                </ol>

                <div class="code-block">
# PŘED (nefunguje):<br>
GEOAPIFY_API_KEY=placeholder_geoapify_key<br>
<br>
# PO (funguje):<br>
GEOAPIFY_API_KEY=váš_skutečný_api_klíč_zde
                </div>

                <ol start="4">
                    <li>Uložte soubor</li>
                    <li>Nastavte správná oprávnění (SSH): <code>chmod 600 .env</code></li>
                    <li>Obnovte tuto stránku a zkontrolujte, že se status změnil na ✅</li>
                </ol>

                <p><strong>Varianta B - Přes hosting environment variables:</strong></p>
                <ol>
                    <li>Přihlaste se do control panelu (cPanel/Plesk)</li>
                    <li>Najděte sekci <strong>"Environment Variables"</strong></li>
                    <li>Přidejte novou proměnnou:
                        <ul style="margin: 0.5rem 0 0.5rem 1.5rem;">
                            <li><strong>Name:</strong> GEOAPIFY_API_KEY</li>
                            <li><strong>Value:</strong> váš_skutečný_api_klíč</li>
                        </ul>
                    </li>
                    <li>Restartujte PHP-FPM nebo webserver</li>
                    <li>Obnovte tuto stránku</li>
                </ol>

                <h4 style="margin-top: 1.5rem; color: #2D5016;">Krok 3: Ověření</h4>
                <ol>
                    <li>Obnovte tuto stránku - měli byste vidět ✅ status</li>
                    <li>Otevřete stránku s mapou (např. <a href="/novareklamace.php" style="color: #2D5016;">novareklamace.php</a>)</li>
                    <li>Zkontrolujte, že se mapa načítá a našeptávač funguje</li>
                </ol>
            </div>

            <?php else: ?>
            <!-- Success message -->
            <div class="status-box status-success" style="margin-top: 1.5rem;">
                <h3>✅ Co dál?</h3>
                <p>Konfigurace vypadá správně! Pokud mapa stále nefunguje:</p>
                <ol style="padding-left: 1.5rem; margin-top: 1rem;">
                    <li>Zkontrolujte browser console (F12) na chyby</li>
                    <li>Ověřte, že API klíč je platný na <a href="https://myprojects.geoapify.com/" target="_blank" style="color: #155724;">Geoapify Dashboard</a></li>
                    <li>Zkontrolujte, že nemáte vyčerpaný denní limit (3,000 requests)</li>
                </ol>
            </div>
            <?php endif; ?>

            <!-- Links -->
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #dee2e6;">
                <a href="/" class="btn">← Zpět na hlavní stránku</a>
                <a href="?refresh=1" class="btn" style="background: #17a2b8; margin-left: 1rem;">🔄 Obnovit diagnostiku</a>
            </div>

            <div style="margin-top: 2rem; font-size: 0.9rem; color: #6c757d; text-align: center;">
                <p>Pro více informací viz: <strong>GEOAPIFY_SETUP.md</strong></p>
                <p>Vygenerováno: <?= date('Y-m-d H:i:s') ?></p>
            </div>
        </div>
    </div>
</body>
</html>
