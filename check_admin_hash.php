<?php
/**
 * DIAGNOSTIKA ADMIN KLÍČE
 */

require_once __DIR__ . '/init.php';

$expectedKey = 'ADMIN2025393F940A';
$expectedHash = '3d408179d8180f5dcfa23531919422088ba5b489053de123961ab08eb1003381';
$actualHash = hash('sha256', $expectedKey);

?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Admin Hash Diagnostika</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 40px;
            line-height: 1.6;
        }
        .section {
            background: #252526;
            border-left: 4px solid #007acc;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        h2 { color: #4ec9b0; margin-bottom: 15px; }
        .good { color: #4ec9b0; }
        .bad { color: #f48771; }
        .warn { color: #ce9178; }
        code { background: #1e1e1e; padding: 2px 6px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        td { padding: 8px; border-bottom: 1px solid #3e3e3e; }
        td:first-child { color: #9cdcfe; width: 250px; }
    </style>
</head>
<body>

<div class="section">
    <h2>🔐 ADMIN KEY HASH - Diagnostika</h2>

    <table>
        <tr>
            <td>Očekávaný klíč:</td>
            <td><code><?php echo htmlspecialchars($expectedKey); ?></code></td>
        </tr>
        <tr>
            <td>Očekávaný hash:</td>
            <td><code><?php echo htmlspecialchars($expectedHash); ?></code></td>
        </tr>
        <tr>
            <td>Skutečný hash klíče:</td>
            <td><code><?php echo htmlspecialchars($actualHash); ?></code></td>
        </tr>
        <tr>
            <td>Hash match:</td>
            <td class="<?php echo ($actualHash === $expectedHash) ? 'good' : 'bad'; ?>">
                <?php echo ($actualHash === $expectedHash) ? '✓ ANO' : '✗ NE'; ?>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>📁 ADMIN_KEY_HASH z .env</h2>

    <table>
        <tr>
            <td>Konstanta definována:</td>
            <td class="<?php echo defined('ADMIN_KEY_HASH') ? 'good' : 'bad'; ?>">
                <?php echo defined('ADMIN_KEY_HASH') ? '✓ ANO' : '✗ NE'; ?>
            </td>
        </tr>
        <?php if (defined('ADMIN_KEY_HASH')): ?>
        <tr>
            <td>Hodnota ADMIN_KEY_HASH:</td>
            <td><code><?php echo htmlspecialchars(ADMIN_KEY_HASH); ?></code></td>
        </tr>
        <tr>
            <td>Shoduje se s očekávaným:</td>
            <td class="<?php echo (ADMIN_KEY_HASH === $expectedHash) ? 'good' : 'bad'; ?>">
                <?php echo (ADMIN_KEY_HASH === $expectedHash) ? '✓ ANO' : '✗ NE - PROBLÉM!'; ?>
            </td>
        </tr>
        <tr>
            <td>Je to fallback hodnota:</td>
            <td class="<?php echo (ADMIN_KEY_HASH === 'change-in-production') ? 'warn' : 'good'; ?>">
                <?php echo (ADMIN_KEY_HASH === 'change-in-production') ? '⚠ ANO - .env není načtený!' : '✓ NE'; ?>
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<div class="section">
    <h2>🔍 Test přihlášení</h2>

    <?php
    $testHash = hash('sha256', $expectedKey);
    $wouldWork = defined('ADMIN_KEY_HASH') && hash_equals(ADMIN_KEY_HASH, $testHash);
    ?>

    <table>
        <tr>
            <td>Klíč <code>ADMIN2025393F940A</code> by fungoval:</td>
            <td class="<?php echo $wouldWork ? 'good' : 'bad'; ?>">
                <?php echo $wouldWork ? '✓ ANO' : '✗ NE'; ?>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>🩹 Řešení</h2>

    <?php if (!defined('ADMIN_KEY_HASH')): ?>
        <p class="bad">✗ ADMIN_KEY_HASH není definována! .env soubor není načten.</p>
        <p>Řešení: Zkontroluj že .env existuje a má správná oprávnění.</p>
    <?php elseif (ADMIN_KEY_HASH === 'change-in-production'): ?>
        <p class="warn">⚠ ADMIN_KEY_HASH má fallback hodnotu! .env není načten.</p>
        <p>Řešení: .env soubor buď neexistuje nebo není správně načten.</p>
    <?php elseif (ADMIN_KEY_HASH !== $expectedHash): ?>
        <p class="bad">✗ ADMIN_KEY_HASH má jinou hodnotu než očekáváme!</p>
        <p>Aktuální: <code><?php echo htmlspecialchars(ADMIN_KEY_HASH); ?></code></p>
        <p>Očekávaný: <code><?php echo htmlspecialchars($expectedHash); ?></code></p>
        <p>Řešení: Aktualizuj .env soubor s novým hashem.</p>
    <?php else: ?>
        <p class="good">✓ Vše je v pořádku! Admin klíč by měl fungovat.</p>
    <?php endif; ?>
</div>

<p style="margin-top: 30px; opacity: 0.5; font-size: 12px;">
    Tento skript nezapisuje do souborů. Po diagnostice ho můžeš smazat.
</p>

</body>
</html>
