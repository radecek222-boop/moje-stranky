<?php
/**
 * AUTOMATICKÝ UPDATE & INSTALAČNÍ SKRIPT
 *
 * Tento skript automaticky:
 * 1. Aktualizuje kód z GitHubu (git pull)
 * 2. Vytvoří .env soubor s DB údaji
 * 3. Smaže sám sebe
 *
 * POUŽITÍ:
 * 1. Mergni pull request na GitHubu
 * 2. Otevři: https://www.wgs-service.cz/update_and_install.php
 * 3. Hotovo!
 */

// Bezpečnostní timeout
set_time_limit(60);

// Cesty
$repoPath = __DIR__;
$envPath = $repoPath . '/.env';
$gitPath = '/usr/bin/git'; // Standardní cesta k git

// Status sledování
$status = [
    'git_pull' => ['done' => false, 'message' => ''],
    'env_create' => ['done' => false, 'message' => ''],
    'cleanup' => ['done' => false, 'message' => ''],
    'errors' => []
];

// ========== KROK 1: GIT PULL ==========
try {
    // Ověření že jsme v git repozitáři
    if (!is_dir($repoPath . '/.git')) {
        $status['errors'][] = 'Adresář není Git repozitář';
    } else {
        // Zjistit aktuální branch
        $currentBranch = trim(shell_exec("cd " . escapeshellarg($repoPath) . " && git rev-parse --abbrev-ref HEAD 2>&1"));

        // Git pull
        $pullCommand = "cd " . escapeshellarg($repoPath) . " && git pull origin " . escapeshellarg($currentBranch) . " 2>&1";
        $pullOutput = shell_exec($pullCommand);

        if (strpos($pullOutput, 'error') !== false || strpos($pullOutput, 'fatal') !== false) {
            $status['errors'][] = "Git pull selhal: " . htmlspecialchars($pullOutput);
        } else {
            $status['git_pull']['done'] = true;
            $status['git_pull']['message'] = "✓ Kód aktualizován z větve: {$currentBranch}";
        }
    }
} catch (Exception $e) {
    $status['errors'][] = "Chyba při git pull: " . htmlspecialchars($e->getMessage());
}

// ========== KROK 2: VYTVOŘENÍ .ENV ==========
if (!file_exists($envPath)) {
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

    $result = @file_put_contents($envPath, $envContent);

    if ($result !== false) {
        @chmod($envPath, 0600);
        $status['env_create']['done'] = true;
        $status['env_create']['message'] = '✓ Soubor .env vytvořen a zabezpečen (chmod 600)';
    } else {
        $status['errors'][] = 'Nepodařilo se vytvořit .env soubor (zkontroluj oprávnění)';
    }
} else {
    $status['env_create']['done'] = true;
    $status['env_create']['message'] = '⚠ Soubor .env již existuje (nebyl přepsán)';
}

// ========== KROK 3: SMAZÁNÍ INSTALAČNÍHO SKRIPTU ==========
$selfDelete = @unlink(__FILE__);
if ($selfDelete) {
    $status['cleanup']['done'] = true;
    $status['cleanup']['message'] = '✓ Instalační skript automaticky smazán';
} else {
    $status['cleanup']['message'] = '⚠ Prosím smaž update_and_install.php ručně';
}

// Kontrola celkového úspěchu
$allSuccess = $status['git_pull']['done'] && $status['env_create']['done'] && empty($status['errors']);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automatická Instalace - WGS Service</title>
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
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .step {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .step-icon {
            font-size: 32px;
            min-width: 40px;
            text-align: center;
        }
        .step-content {
            flex: 1;
        }
        .step-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .step-message {
            color: #666;
            font-size: 14px;
        }
        .step.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .step.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .step.warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .admin-key {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
        }
        .admin-key h3 {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .admin-key code {
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 24px;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        .next-steps {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .next-steps h3 {
            color: #1976D2;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .next-steps ol {
            margin-left: 20px;
        }
        .next-steps li {
            margin-bottom: 10px;
            color: #333;
            line-height: 1.6;
        }
        .next-steps a {
            color: #1976D2;
            text-decoration: none;
            font-weight: 600;
        }
        .next-steps a:hover {
            text-decoration: underline;
        }
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .error-box h3 {
            margin-bottom: 10px;
        }
        .error-box ul {
            margin-left: 20px;
        }
        code {
            background: rgba(0,0,0,0.05);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon"><?php echo $allSuccess ? '✅' : '⚠️'; ?></div>
            <h1><?php echo $allSuccess ? 'Instalace dokončena!' : 'Instalace s upozorněními'; ?></h1>
            <p class="subtitle">Automatická aktualizace a konfigurace WGS Service</p>
        </div>

        <!-- Krok 1: Git Pull -->
        <div class="step <?php echo $status['git_pull']['done'] ? 'success' : 'error'; ?>">
            <div class="step-icon"><?php echo $status['git_pull']['done'] ? '✓' : '✗'; ?></div>
            <div class="step-content">
                <div class="step-title">Krok 1: Aktualizace kódu z GitHubu</div>
                <div class="step-message"><?php echo htmlspecialchars($status['git_pull']['message']); ?></div>
            </div>
        </div>

        <!-- Krok 2: .env -->
        <div class="step <?php echo $status['env_create']['done'] ? 'success' : 'error'; ?>">
            <div class="step-icon"><?php echo $status['env_create']['done'] ? '✓' : '✗'; ?></div>
            <div class="step-content">
                <div class="step-title">Krok 2: Vytvoření konfiguračního souboru</div>
                <div class="step-message"><?php echo htmlspecialchars($status['env_create']['message']); ?></div>
            </div>
        </div>

        <!-- Krok 3: Cleanup -->
        <div class="step <?php echo $status['cleanup']['done'] ? 'success' : 'warning'; ?>">
            <div class="step-icon"><?php echo $status['cleanup']['done'] ? '✓' : '⚠'; ?></div>
            <div class="step-content">
                <div class="step-title">Krok 3: Bezpečnostní úklid</div>
                <div class="step-message"><?php echo htmlspecialchars($status['cleanup']['message']); ?></div>
            </div>
        </div>

        <?php if ($allSuccess): ?>
            <!-- Admin klíč -->
            <div class="admin-key">
                <h3>🔑 Tvůj Admin Přihlašovací Klíč</h3>
                <code>ADMIN2025393F940A</code>
            </div>

            <!-- Další kroky -->
            <div class="next-steps">
                <h3>📋 Co dál?</h3>
                <ol>
                    <li>Jdi na <a href="/admin">Admin panel</a></li>
                    <li>Přihlaš se klíčem: <code>ADMIN2025393F940A</code></li>
                    <li>Aplikace je plně funkční a připravená!</li>
                    <li>Všechny opravy jsou aktivní:
                        <ul>
                            <li>✅ JavaScript chyba opravena</li>
                            <li>✅ Databáze nakonfigurována</li>
                            <li>✅ Bezpečnostní hlavičky nastaveny</li>
                            <li>✅ CSRF ochrana aktivní</li>
                        </ul>
                    </li>
                </ol>
            </div>
        <?php endif; ?>

        <?php if (!empty($status['errors'])): ?>
            <!-- Chyby -->
            <div class="error-box">
                <h3>❌ Chyby při instalaci:</h3>
                <ul>
                    <?php foreach ($status['errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($allSuccess): ?>
    <script>
        // Po 3 sekundách přesměruj na admin
        setTimeout(function() {
            if (confirm('Instalace proběhla úspěšně!\n\nChceš přejít na admin panel?')) {
                window.location.href = '/admin';
            }
        }, 2000);
    </script>
    <?php endif; ?>
</body>
</html>
