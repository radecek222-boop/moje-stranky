<?php
/**
 * TEST PHOTOCUSTOMER ACCESS - Diagnostika přístupu
 * Zobrazí přesně co má uživatel v session a jestli by měl mít přístup
 */

require_once "init.php";

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Test Photocustomer Access</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff88; padding: 20px; }
        .success { color: #00ff88; }
        .error { color: #ff6b6b; }
        .warning { color: #ffc107; }
        table { border-collapse: collapse; margin: 20px 0; background: #2a2a2a; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background: #2D5016; color: white; }
        pre { background: #2a2a2a; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Test Photocustomer Access</h1>

    <h2>1. Aktuální SESSION data:</h2>
    <pre><?php print_r($_SESSION); ?></pre>

    <h2>2. Kontrola přístupu (podle photocustomer.php logiky):</h2>
    <table>
        <tr>
            <th>Kontrola</th>
            <th>Hodnota</th>
            <th>Status</th>
        </tr>
        <?php
        $role = $_SESSION['role'] ?? '';
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
        $isTechnik = in_array(strtolower($role), ['technik', 'technician']);
        $maaPristup = $isAdmin || $isTechnik;
        ?>
        <tr>
            <td>$_SESSION['role']</td>
            <td><code><?php echo htmlspecialchars($role ?: '(prázdné)'); ?></code></td>
            <td><?php echo $role ? '<span class="success">✓</span>' : '<span class="error">✗</span>'; ?></td>
        </tr>
        <tr>
            <td>$_SESSION['is_admin']</td>
            <td><code><?php echo var_export($_SESSION['is_admin'] ?? null, true); ?></code></td>
            <td><?php echo $isAdmin ? '<span class="success">TRUE</span>' : '<span class="warning">FALSE</span>'; ?></td>
        </tr>
        <tr>
            <td>strtolower($role)</td>
            <td><code><?php echo htmlspecialchars(strtolower($role)); ?></code></td>
            <td>-</td>
        </tr>
        <tr>
            <td>$isAdmin (computed)</td>
            <td><code><?php echo $isAdmin ? 'TRUE' : 'FALSE'; ?></code></td>
            <td><?php echo $isAdmin ? '<span class="success">✓ JE ADMIN</span>' : '<span class="warning">NENÍ ADMIN</span>'; ?></td>
        </tr>
        <tr>
            <td>$isTechnik (computed)</td>
            <td><code><?php echo $isTechnik ? 'TRUE' : 'FALSE'; ?></code></td>
            <td><?php echo $isTechnik ? '<span class="success">✓ JE TECHNIK</span>' : '<span class="error">✗ NENÍ TECHNIK</span>'; ?></td>
        </tr>
        <tr style="background: <?php echo $maaPristup ? '#1a4d1a' : '#4d1a1a'; ?>;">
            <td><strong>VÝSLEDEK PŘÍSTUPU</strong></td>
            <td><strong><?php echo $maaPristup ? 'POVOLEN' : 'ODEPŘEN'; ?></strong></td>
            <td><?php echo $maaPristup ? '<span class="success">✓✓✓ MŮŽE VSTOUPIT</span>' : '<span class="error">✗✗✗ REDIRECT NA LOGIN</span>'; ?></td>
        </tr>
    </table>

    <h2>3. Podmínka v photocustomer.php:</h2>
    <pre>if (!$isAdmin && !$isTechnik) {
    // BLOKACE - redirect na login
    header('Location: login.php');
    exit;
}</pre>

    <p>Vyhodnocení:</p>
    <pre>!$isAdmin = <?php echo !$isAdmin ? 'TRUE' : 'FALSE'; ?>

!$isTechnik = <?php echo !$isTechnik ? 'TRUE' : 'FALSE'; ?>

(!$isAdmin && !$isTechnik) = <?php echo (!$isAdmin && !$isTechnik) ? 'TRUE' : 'FALSE'; ?>

<?php if (!$isAdmin && !$isTechnik): ?>
<span class="error">→ PODMÍNKA JE TRUE → REDIRECT NA LOGIN!</span>
<?php else: ?>
<span class="success">→ PODMÍNKA JE FALSE → PŘÍSTUP POVOLEN!</span>
<?php endif; ?>
</pre>

    <h2>4. Co dělat:</h2>
    <?php if ($maaPristup): ?>
        <p class="success">✅ Podle této logiky MÁTE přístup na photocustomer.php!</p>
        <p>Zkuste otevřít: <a href="photocustomer.php" style="color: #00ff88;">photocustomer.php</a></p>
    <?php else: ?>
        <p class="error">❌ Podle této logiky NEMÁTE přístup.</p>
        <p>Problém:</p>
        <ul>
            <?php if (!$role): ?>
                <li class="error">$_SESSION['role'] není nastaveno!</li>
            <?php endif; ?>
            <?php if ($role && !$isTechnik && !$isAdmin): ?>
                <li class="error">Role "<?php echo htmlspecialchars($role); ?>" není ani 'technik', ani 'technician', ani admin!</li>
            <?php endif; ?>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <li class="error">$_SESSION['user_id'] není nastaveno!</li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>

    <p style="margin-top: 40px; color: #666;">
        Generováno: <?php echo date('Y-m-d H:i:s'); ?><br>
        Session ID: <?php echo session_id(); ?>
    </p>
</body>
</html>
