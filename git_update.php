<?php
/**
 * GIT UPDATE SCRIPT
 *
 * Stáhne nejnovější kód z GitHubu
 * Automaticky se smaže po použití
 */

$output = [];
$returnCode = 0;

// Změnit do správného adresáře
chdir(__DIR__);

// Zjistit aktuální branch
exec('git rev-parse --abbrev-ref HEAD 2>&1', $branchOutput, $branchCode);
$currentBranch = trim($branchOutput[0] ?? 'main');

// Git pull
$command = "git pull origin main 2>&1";
exec($command, $output, $returnCode);

$success = ($returnCode === 0);

// Automatické smazání
$deleted = false;
if ($success) {
    $deleted = @unlink(__FILE__);
}

?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? '✅ Aktualizováno' : '❌ Chyba'; ?> - Git Update</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .icon { font-size: 80px; margin-bottom: 20px; }
        h1 { color: #333; font-size: 32px; margin-bottom: 10px; }
        .status {
            background: <?php echo $success ? '#d4edda' : '#f8d7da'; ?>;
            border: 2px solid <?php echo $success ? '#28a745' : '#dc3545'; ?>;
            color: <?php echo $success ? '#155724' : '#721c24'; ?>;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .output {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 40px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .next {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .next h3 { color: #1976D2; margin-bottom: 15px; }
        .next ol { margin-left: 20px; }
        .next li { margin: 10px 0; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon"><?php echo $success ? '✅' : '❌'; ?></div>
            <h1><?php echo $success ? 'Kód aktualizován!' : 'Git pull selhal'; ?></h1>
        </div>

        <?php if ($success): ?>
            <div class="status">
                <strong>✓ Server úspěšně aktualizován z GitHubu!</strong><br>
                Stránky nyní běží s nejnovějším kódem.
                <?php if ($deleted): ?>
                    <br><br>✓ Tento skript byl automaticky smazán.
                <?php endif; ?>
            </div>

            <div class="output">
                <strong>Git output:</strong><br>
                <?php echo htmlspecialchars(implode("\n", $output)); ?>
            </div>

            <div class="next">
                <h3>📋 Další kroky:</h3>
                <ol>
                    <li><strong>Otevři:</strong> <a href="/setup_env.php">setup_env.php</a> - vytvoří .env soubor</li>
                    <li><strong>Pak jdi na:</strong> <a href="/admin">Admin panel</a></li>
                    <li><strong>Přihlaš se klíčem:</strong> <code>ADMIN2025393F940A</code></li>
                    <li><strong>JavaScript chyba by měla být pryč! 🎉</strong></li>
                </ol>
            </div>

            <a href="/setup_env.php" class="btn">→ Pokračovat na vytvoření .env</a>

        <?php else: ?>
            <div class="status">
                <strong>✗ Git pull selhal</strong><br>
                Server pravděpodobně nemá oprávnění nebo git není nakonfigurován.
            </div>

            <div class="output">
                <strong>Chybový výstup:</strong><br>
                <?php echo htmlspecialchars(implode("\n", $output)); ?>
            </div>

            <div class="next">
                <h3>⚠️ Možná řešení:</h3>
                <ul>
                    <li>Zkontroluj SSH klíče pro GitHub</li>
                    <li>Zkontroluj oprávnění k zápisu</li>
                    <li>Zkus ruční git pull přes SSH/Terminal</li>
                    <li>Kontaktuj podporu hostingu</li>
                </ul>
            </div>
        <?php endif; ?>

        <p style="margin-top: 30px; text-align: center; color: #999; font-size: 12px;">
            Branch: <?php echo htmlspecialchars($currentBranch); ?> | Return code: <?php echo $returnCode; ?>
        </p>
    </div>

    <?php if ($success): ?>
    <script>
        setTimeout(() => {
            if (confirm('Pokračovat na instalaci .env souboru?')) {
                window.location.href = '/setup_env.php';
            }
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>
