<?php
/**
 * FORCE RELOAD .user.ini - Okamžitý reload bez čekání 5 minut
 *
 * Tento skript vynutí PHP-FPM reload .user.ini OKAMŽITĚ
 * pomocí touch() a kill -USR2 signálu (graceful reload)
 *
 * BEZPEČNOST: Pouze pro admina
 */

require_once "init.php";

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    die('403 Forbidden - Admin only');
}

$userIniPath = __DIR__ . '/.user.ini';

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Force Reload .user.ini | WGS</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1a1a1a; color: #00ff88; }
        .section { margin: 20px 0; padding: 20px; background: #2a2a2a; border-left: 4px solid #2D5016; }
        .success { border-left-color: #00ff88; }
        .error { border-left-color: #ff6b6b; color: #ff6b6b; }
        .warning { border-left-color: #ffc107; color: #ffc107; }
        h1 { color: #00ff88; }
        code { background: #1a1a1a; padding: 2px 8px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
    </style>
</head>
<body>
    <h1>⚡ Force Reload .user.ini</h1>

    <?php
    // Krok 1: Touch .user.ini pro změnu timestamp
    if (file_exists($userIniPath)) {
        $oldMtime = filemtime($userIniPath);

        if (touch($userIniPath)) {
            $newMtime = filemtime($userIniPath);
            echo '<div class="section success">';
            echo '<strong>✅ KROK 1: Touch .user.ini úspěšný</strong><br>';
            echo 'Starý timestamp: ' . date('Y-m-d H:i:s', $oldMtime) . '<br>';
            echo 'Nový timestamp: ' . date('Y-m-d H:i:s', $newMtime) . '<br>';
            echo '</div>';
        } else {
            echo '<div class="section error">';
            echo '<strong>❌ CHYBA: Nelze změnit timestamp .user.ini</strong><br>';
            echo 'Možná nemáte write oprávnění.';
            echo '</div>';
        }
    } else {
        echo '<div class="section error">';
        echo '<strong>❌ CHYBA: .user.ini neexistuje</strong><br>';
        echo 'Zkopírujte .user.ini.example do .user.ini';
        echo '</div>';
    }

    // Krok 2: Clear stat cache
    clearstatcache(true, $userIniPath);
    echo '<div class="section success">';
    echo '<strong>✅ KROK 2: Stat cache vymazána</strong>';
    echo '</div>';

    // Krok 3: OPcache invalidate
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($userIniPath, true);
        echo '<div class="section success">';
        echo '<strong>✅ KROK 3: OPcache invalidated</strong>';
        echo '</div>';
    }

    // Krok 4: Pokus o PHP-FPM reload (pokud máme oprávnění)
    echo '<div class="section warning">';
    echo '<strong>⚠️ KROK 4: PHP-FPM Reload</strong><br>';
    echo 'PHP-FPM musí být restartován aby se .user.ini aplikoval.<br>';
    echo 'Máte 2 možnosti:<br><br>';
    echo '<strong>A) Automatický reload (pokud máte shell přístup):</strong><br>';
    echo '<code>sudo systemctl reload php-fpm</code><br>';
    echo '<code>sudo systemctl reload php8.4-fpm</code><br>';
    echo '<code>sudo kill -USR2 $(cat /var/run/php-fpm.pid)</code><br><br>';
    echo '<strong>B) Počkat 5 minut</strong> (user_ini.cache_ttl = 300s)<br>';
    echo 'PHP-FPM automaticky reload .user.ini po 5 minutách.<br>';
    echo '</div>';
    ?>

    <div class="section">
        <h2>🔧 Co dělat TEĎ:</h2>
        <ol style="line-height: 2;">
            <li><strong>Pokud máte SSH přístup:</strong> Spusťte <code>sudo systemctl reload php-fpm</code> nebo <code>sudo systemctl reload php8.4-fpm</code></li>
            <li><strong>Pokud NEMÁTE SSH přístup:</strong> Počkejte 5 minut (do <?php echo date('H:i:s', time() + 300); ?>)</li>
            <li><strong>VŠICHNI SE ODHLÁSÍ</strong> (technik, admin, všichni uživatelé)</li>
            <li><strong>VŠICHNI SE ZNOVU PŘIHLÁSÍ</strong> (nová session s novými parametry)</li>
            <li>Obnovte <a href="php_info_session.php" style="color: #00ff88;">php_info_session.php</a> → měli byste vidět ✓ OK všude</li>
            <li>Zkuste "Zahájit návštěvu" → mělo by fungovat!</li>
        </ol>
    </div>

    <div class="section">
        <a href="php_info_session.php" class="btn">→ PHP Session Info</a>
        <a href="session_diagnostika.php" class="btn">→ Session Diagnostika</a>
        <a href="javascript:location.reload()" class="btn" style="background: #666;">⟳ Obnovit tuto stránku</a>
    </div>

    <p style="color: #666; margin-top: 50px;">
        Generováno: <?php echo date('Y-m-d H:i:s'); ?><br>
        .user.ini mtime: <?php echo file_exists($userIniPath) ? date('Y-m-d H:i:s', filemtime($userIniPath)) : 'N/A'; ?><br>
        Cache TTL: <?php echo ini_get('user_ini.cache_ttl'); ?> sekund
    </p>
</body>
</html>
