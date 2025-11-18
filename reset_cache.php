<?php
/**
 * RESET PHP CACHE - Vymazání OPcache a session cache
 *
 * Tento skript vymaže PHP OPcache a vynutí reload všech souborů.
 * Použijte po změnách v init.php nebo jiných core souborech.
 *
 * BEZPEČNOST: Pouze pro admina
 */

require_once "init.php";

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    die('<html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: sans-serif; text-align: center; padding: 50px;"><h1>🔒 Přístup odepřen</h1><p>Tento nástroj je dostupný pouze pro administrátory.</p><a href="login.php" style="display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;">Přihlásit se jako admin</a></body></html>');
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Reset Cache | WGS Service</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #2D5016;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn:hover {
            background: #1a300d;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #bd2130;
        }
        pre {
            background: #1a1a1a;
            color: #00ff88;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔄 Reset PHP Cache</h1>

    <div class="section info">
        <strong>ℹ️ O tomto nástroji:</strong><br>
        Tento skript vymaže PHP OPcache a vynutí reload všech souborů.<br>
        Použijte po změnách v <code>init.php</code> nebo jiných core souborech, které se zdají, že se neprojevují.
    </div>

    <?php
    if (isset($_POST['reset_cache'])) {
        $vysledky = [];

        // 1. OPcache reset
        if (function_exists('opcache_reset')) {
            if (opcache_reset()) {
                $vysledky[] = ['type' => 'success', 'msg' => '✅ OPcache úspěšně vymazána'];
            } else {
                $vysledky[] = ['type' => 'error', 'msg' => '❌ OPcache reset selhal (možná není povolena)'];
            }
        } else {
            $vysledky[] = ['type' => 'warning', 'msg' => '⚠️ OPcache není dostupná na tomto serveru'];
        }

        // 2. Realpath cache clear
        if (function_exists('clearstatcache')) {
            clearstatcache(true);
            $vysledky[] = ['type' => 'success', 'msg' => '✅ Realpath cache vymazána'];
        }

        // 3. Invalidace konkrétních souborů
        $criticalFiles = [
            __DIR__ . '/init.php',
            __DIR__ . '/config/config.php',
            __DIR__ . '/includes/csrf_helper.php'
        ];

        if (function_exists('opcache_invalidate')) {
            foreach ($criticalFiles as $file) {
                if (file_exists($file)) {
                    opcache_invalidate($file, true);
                }
            }
            $vysledky[] = ['type' => 'success', 'msg' => '✅ Kritické soubory invalidovány'];
        }

        // 4. Touch init.php pro vynucení reloadu
        if (touch(__DIR__ . '/init.php')) {
            $vysledky[] = ['type' => 'success', 'msg' => '✅ init.php timestamp aktualizován'];
        }

        echo '<h2>📊 Výsledky:</h2>';
        foreach ($vysledky as $v) {
            echo '<div class="section ' . $v['type'] . '">' . $v['msg'] . '</div>';
        }

        echo '<div class="section warning">';
        echo '<strong>⚠️ DŮLEŽITÉ: Provedené akce:</strong><br>';
        echo '<ol>';
        echo '<li>OPcache byla vymazána (pokud je povolena)</li>';
        echo '<li>Realpath cache byla vymazána</li>';
        echo '<li>Kritické soubory byly invalidovány</li>';
        echo '<li>Všichni uživatelé se MUSÍ ODHLÁSIT A ZNOVU PŘIHLÁSIT!</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="section info">';
        echo '<strong>🔧 Další kroky:</strong><br>';
        echo '<ol>';
        echo '<li>Technik se <strong>ODHLÁSÍ</strong> (logout.php)</li>';
        echo '<li>Technik se <strong>ZNOVU PŘIHLÁSÍ</strong></li>';
        echo '<li>Otevřete <code>session_diagnostika.php</code> a zkontrolujte, zda se vše opravilo</li>';
        echo '<li>Zkuste "Zahájit návštěvu" → mělo by fungovat!</li>';
        echo '</ol>';
        echo '</div>';
    }
    ?>

    <h2>📋 Informace o PHP cache</h2>
    <div class="section">
        <strong>OPcache status:</strong><br>
        <?php
        if (function_exists('opcache_get_status')) {
            $status = opcache_get_status(false);
            if ($status) {
                echo 'Povolena: ' . ($status['opcache_enabled'] ? '✅ ANO' : '❌ NE') . '<br>';
                echo 'Používaná paměť: ' . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . ' MB<br>';
                echo 'Volná paměť: ' . round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . ' MB<br>';
                echo 'Cached scripts: ' . $status['opcache_statistics']['num_cached_scripts'] . '<br>';
            } else {
                echo '❌ OPcache není aktivní';
            }
        } else {
            echo '⚠️ OPcache není dostupná';
        }
        ?>
    </div>

    <h2>🚀 Akce</h2>
    <?php if (!isset($_POST['reset_cache'])): ?>
    <form method="POST">
        <div class="section warning">
            <strong>⚠️ VAROVÁNÍ:</strong><br>
            Tato akce vymaže celou PHP cache a vynutí reload všech souborů.<br>
            Všichni přihlášení uživatelé se budou muset odhlásit a znovu přihlásit!
        </div>
        <button type="submit" name="reset_cache" class="btn btn-danger">🔄 VYMAZAT CACHE A RELOAD</button>
        <a href="session_diagnostika.php" class="btn">Zpět na diagnostiku</a>
    </form>
    <?php else: ?>
    <a href="session_diagnostika.php" class="btn">✅ Otevřít diagnostiku</a>
    <a href="logout.php" class="btn" style="background: #dc3545;">Odhlásit se</a>
    <?php endif; ?>

    <h2>🔍 PHP Info</h2>
    <div class="section">
        <strong>PHP verze:</strong> <?php echo PHP_VERSION; ?><br>
        <strong>PHP_VERSION_ID:</strong> <?php echo PHP_VERSION_ID; ?><br>
        <strong>Session handler:</strong> <?php echo ini_get('session.save_handler'); ?><br>
        <strong>Session path:</strong> <?php echo ini_get('session.save_path'); ?>
    </div>
</div>
</body>
</html>
