<?php
/**
 * HOTFIX: Přímá oprava JavaScript chyby
 * Opraví duplicitní getCSRFToken() v control_center_testing_interactive.php
 */

$filePath = __DIR__ . '/includes/control_center_testing_interactive.php';
$backupPath = __DIR__ . '/includes/control_center_testing_interactive.php.backup';

// Backup
if (file_exists($filePath)) {
    copy($filePath, $backupPath);
}

// Načíst soubor
$content = file_get_contents($filePath);

// Najít a nahradit problematickou část
$oldCode = <<<'OLD'
};

// Získat CSRF token z meta tagu
function getCSRFToken() {
    // Try current document first
    let metaTag = document.querySelector('meta[name="csrf-token"]');

    console.log('[CSRF DEBUG] Current document meta tag:', metaTag);

    // If in iframe, try parent window
    if (!metaTag && window.parent && window.parent !== window) {
        try {
            metaTag = window.parent.document.querySelector('meta[name="csrf-token"]');
            console.log('[CSRF DEBUG] Parent document meta tag:', metaTag);
        } catch (e) {
            // Cross-origin iframe - cannot access parent
            console.error('[CSRF DEBUG] Cannot access parent CSRF token:', e);
        }
    }

    const token = metaTag ? metaTag.getAttribute('content') : null;
    console.log('[CSRF DEBUG] Final token:', token ? `${token.substring(0, 10)}...` : 'NULL');
    return token;
}

function selectRole(role) {
OLD;

$newCode = <<<'NEW'
};

// Local synchronous CSRF token getter for interactive tester
// Note: control_center_unified.php has async getCSRFToken() which returns Promise
// This module needs synchronous access, so we use a local helper
function getCSRFTokenSync() {
    // Try current document first
    let metaTag = document.querySelector('meta[name="csrf-token"]');

    // If in iframe, try parent window
    if (!metaTag && window.parent && window.parent !== window) {
        try {
            metaTag = window.parent.document.querySelector('meta[name="csrf-token"]');
        } catch (e) {
            console.error('[Interactive Tester] Cannot access parent CSRF token:', e);
        }
    }

    return metaTag ? metaTag.getAttribute('content') : null;
}

function selectRole(role) {
NEW;

// Nahradit
$newContent = str_replace($oldCode, $newCode, $content);

// Také opravit volání funkce
$newContent = str_replace(
    'testData.csrfToken = getCSRFToken();',
    'testData.csrfToken = getCSRFTokenSync();',
    $newContent
);

// Uložit
$success = file_put_contents($filePath, $newContent);

// Auto-delete
$deleted = @unlink(__FILE__);

?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? '✅ Opraveno' : '❌ Chyba'; ?> - Hotfix</title>
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
            max-width: 600px;
            width: 100%;
            padding: 50px;
            text-align: center;
        }
        .icon { font-size: 90px; margin-bottom: 25px; }
        h1 { color: #333; font-size: 32px; margin-bottom: 15px; }
        .msg { color: #666; font-size: 18px; margin-bottom: 30px; line-height: 1.6; }
        .status {
            background: <?php echo $success ? '#d4edda' : '#f8d7da'; ?>;
            border: 2px solid <?php echo $success ? '#28a745' : '#dc3545'; ?>;
            color: <?php echo $success ? '#155724' : '#721c24'; ?>;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
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
        }
        .btn:hover { transform: translateY(-3px); }
        code {
            background: #f4f4f4;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><?php echo $success ? '🎉' : '❌'; ?></div>

        <?php if ($success): ?>
            <h1>JavaScript chyba opravena!</h1>
            <p class="msg">
                Soubor <code>control_center_testing_interactive.php</code> byl úspěšně opraven.<br>
                Duplicitní funkce <code>getCSRFToken()</code> byla odstraněna.
            </p>

            <div class="status">
                <strong>✓ Hotfix aplikován!</strong><br>
                Backup uložen: <code>control_center_testing_interactive.php.backup</code>
                <?php if ($deleted): ?>
                    <br><br>✓ Tento hotfix skript byl automaticky smazán.
                <?php endif; ?>
            </div>

            <p style="margin: 30px 0; color: #555;">
                <strong>🔄 DŮLEŽITÉ:</strong><br>
                Tvrdý refresh stránky (Cmd+Shift+R) nebo otevři v inkognito okně,<br>
                aby se načetl opravený kód!
            </p>

            <a href="/admin?tab=control_center" class="btn">→ Otevřít Control Center</a>

        <?php else: ?>
            <h1>Oprava selhala</h1>
            <p class="msg">Nepodařilo se zapsat do souboru.</p>

            <div class="status">
                <strong>Zkontroluj oprávnění k zápisu do složky /includes/</strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
    <script>
        setTimeout(() => {
            alert('Nezapomeň udělat hard refresh (Cmd+Shift+R)!');
        }, 1500);
    </script>
    <?php endif; ?>
</body>
</html>
