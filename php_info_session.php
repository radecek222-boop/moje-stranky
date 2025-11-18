<?php
/**
 * PHP INFO - Session Diagnostic
 * Minimalistická diagnostika session nastavení
 *
 * BEZPEČNOST: Pouze pro admina
 */

require_once "init.php";

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    die('403 Forbidden');
}

// Touch .user.ini pro vynucení reload (PHP-FPM cache je 5min)
touch(__DIR__ . '/.user.ini');
clearstatcache(true, __DIR__ . '/.user.ini');

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>PHP Session Info | WGS</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1a1a1a; color: #00ff88; }
        table { border-collapse: collapse; width: 100%; max-width: 1200px; background: #2a2a2a; margin: 20px 0; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background: #2D5016; color: white; }
        .ok { color: #00ff88; }
        .error { color: #ff6b6b; }
        .warning { color: #ffc107; }
        h1 { color: #00ff88; }
        .section { margin: 30px 0; padding: 20px; background: #2a2a2a; border-left: 4px solid #2D5016; }
    </style>
</head>
<body>
    <h1>🔍 PHP Session Configuration Diagnostic</h1>

    <div class="section">
        <h2>1. PHP Handler Info</h2>
        <table>
            <tr>
                <th>Parametr</th>
                <th>Hodnota</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><?php echo PHP_VERSION; ?></td>
                <td class="ok">✓</td>
            </tr>
            <tr>
                <td>Server API (SAPI)</td>
                <td><?php echo php_sapi_name(); ?></td>
                <td><?php echo (php_sapi_name() === 'fpm-fcgi' || php_sapi_name() === 'cgi-fcgi') ? '<span class="ok">PHP-FPM (use .user.ini)</span>' : '<span class="warning">mod_php (use .htaccess)</span>'; ?></td>
            </tr>
            <tr>
                <td>OPcache Enabled</td>
                <td><?php echo function_exists('opcache_get_status') && opcache_get_status() ? 'YES' : 'NO'; ?></td>
                <td><?php echo function_exists('opcache_get_status') && opcache_get_status() ? '<span class="ok">Active</span>' : '<span class="warning">Inactive</span>'; ?></td>
            </tr>
            <tr>
                <td>.user.ini support</td>
                <td><?php echo ini_get('user_ini.filename') ?: '.user.ini'; ?></td>
                <td><?php echo ini_get('user_ini.cache_ttl'); ?> sec cache</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>2. Session Settings (Current)</h2>
        <table>
            <tr>
                <th>Parametr</th>
                <th>Aktuální hodnota</th>
                <th>Očekáváno</th>
                <th>Status</th>
            </tr>
            <?php
            $checks = [
                ['session.cookie_lifetime', ini_get('session.cookie_lifetime'), ['0', '3600'], 'Cookie Lifetime'],
                ['session.gc_maxlifetime', ini_get('session.gc_maxlifetime'), ['3600'], 'GC Maxlifetime'],
                ['session.cookie_httponly', ini_get('session.cookie_httponly'), ['1', 'On'], 'Cookie HTTPOnly'],
                ['session.cookie_secure', ini_get('session.cookie_secure'), ['1', 'On'], 'Cookie Secure'],
                ['session.cookie_samesite', ini_get('session.cookie_samesite'), ['Lax'], 'Cookie SameSite'],
                ['session.use_only_cookies', ini_get('session.use_only_cookies'), ['1', 'On'], 'Use Only Cookies'],
                ['session.cookie_path', ini_get('session.cookie_path'), ['/'], 'Cookie Path'],
            ];

            foreach ($checks as list($key, $current, $expected, $label)) {
                $isOk = in_array($current, $expected);
                $status = $isOk ? '<span class="ok">✓ OK</span>' : '<span class="error">✗ CHYBA</span>';
                echo "<tr>";
                echo "<td><strong>{$label}</strong><br><small>{$key}</small></td>";
                echo "<td><code>" . htmlspecialchars($current ?: '(prázdné)') . "</code></td>";
                echo "<td><code>" . implode(' nebo ', $expected) . "</code></td>";
                echo "<td>{$status}</td>";
                echo "</tr>";
            }
            ?>
        </table>
    </div>

    <div class="section">
        <h2>3. Session Cookie Params (Runtime)</h2>
        <?php
        $params = session_get_cookie_params();
        ?>
        <table>
            <tr>
                <th>Parametr</th>
                <th>Hodnota</th>
            </tr>
            <?php foreach ($params as $key => $value): ?>
            <tr>
                <td><?php echo htmlspecialchars($key); ?></td>
                <td><code><?php echo htmlspecialchars(var_export($value, true)); ?></code></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>4. File Locations</h2>
        <table>
            <tr>
                <th>Soubor</th>
                <th>Existuje</th>
                <th>Poslední změna</th>
            </tr>
            <?php
            $files = [
                '.user.ini' => __DIR__ . '/.user.ini',
                '.htaccess' => __DIR__ . '/.htaccess',
                'init.php' => __DIR__ . '/init.php',
            ];
            foreach ($files as $name => $path) {
                $exists = file_exists($path);
                $mtime = $exists ? date('Y-m-d H:i:s', filemtime($path)) : 'N/A';
                echo "<tr>";
                echo "<td><strong>{$name}</strong></td>";
                echo "<td>" . ($exists ? '<span class="ok">✓ Ano</span>' : '<span class="error">✗ Ne</span>') . "</td>";
                echo "<td>{$mtime}</td>";
                echo "</tr>";
            }
            ?>
        </table>
    </div>

    <div class="section">
        <h2>5. Co dělat TEĎKA:</h2>
        <ol style="line-height: 2;">
            <li><strong>Zkontrolujte tabulku #2</strong> - Pokud vidíte červené ✗ CHYBA:</li>
            <li>
                <?php if (php_sapi_name() === 'fpm-fcgi' || php_sapi_name() === 'cgi-fcgi'): ?>
                    <span class="ok">✓ Server běží na PHP-FPM</span> → Počkejte <strong><?php echo ini_get('user_ini.cache_ttl'); ?> sekund</strong> pro reload .user.ini
                <?php else: ?>
                    <span class="warning">Server běží na mod_php</span> → Použijte .htaccess direktivy
                <?php endif; ?>
            </li>
            <li><strong>ODHLASTE SE A ZNOVU SE PŘIHLASTE</strong> (všichni uživatelé včetně technika)</li>
            <li>Obnovte <a href="session_diagnostika.php" style="color: #00ff88;">session_diagnostika.php</a> a zkontrolujte změny</li>
            <li>Zkuste "Zahájit návštěvu" → photocustomer.php</li>
        </ol>
    </div>

    <div class="section">
        <a href="session_diagnostika.php" style="display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px;">→ Session Diagnostika</a>
        <a href="reset_cache.php" style="display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">→ Reset Cache</a>
    </div>

    <p style="color: #666; margin-top: 50px;">
        Generováno: <?php echo date('Y-m-d H:i:s'); ?> |
        Session ID: <?php echo session_id(); ?>
    </p>
</body>
</html>
