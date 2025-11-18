<?php
/**
 * Cleanup starých SMTP konfigurací
 * Smaže neaktivní konfigurace, nechá pouze WebSMTP
 */

require_once __DIR__ . '/init.php';

// Bezpečnost - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$success = false;
$deleted = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup'])) {
    try {
        $pdo = getDbConnection();

        // Zjistit kolik je neaktivních konfigurací
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_smtp_settings WHERE is_active = 0");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $inactiveCount = $result['count'];

        if ($inactiveCount > 0) {
            // Smazat neaktivní konfigurace
            $stmt = $pdo->exec("DELETE FROM wgs_smtp_settings WHERE is_active = 0");
            $deleted = $stmt;
            $success = true;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Načíst aktuální stav
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings ORDER BY id");
    $allConfigs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activeCount = count(array_filter($allConfigs, function($c) { return $c['is_active'] == 1; }));
    $inactiveCount = count(array_filter($allConfigs, function($c) { return $c['is_active'] == 0; }));
} catch (Exception $e) {
    die("Chyba: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>SMTP Cleanup</title>
    <style>
        body { font-family: Arial; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th { background: #2D5016; color: white; padding: 10px; text-align: left; }
        table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .btn { display: inline-block; padding: 12px 24px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; border: none; font-size: 16px; cursor: pointer; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .active { background: #d4edda; }
        .inactive { background: #f8d7da; }
    </style>
</head>
<body>
<div class='container'>

<h1>🧹 SMTP Konfigurace - Cleanup</h1>

<?php if ($success): ?>
    <div class='success'>
        <strong>✅ Úspěšně smazáno <?= $deleted ?> neaktivních konfigurací!</strong>
    </div>
<?php endif; ?>

<h2>📊 Aktuální stav</h2>

<div class='info'>
    <strong>Celkem konfigurací:</strong> <?= count($allConfigs) ?><br>
    <strong>Aktivní:</strong> <?= $activeCount ?><br>
    <strong>Neaktivní:</strong> <?= $inactiveCount ?>
</div>

<?php if (!empty($allConfigs)): ?>
    <table>
        <tr>
            <th>ID</th>
            <th>Status</th>
            <th>SMTP Host</th>
            <th>Port</th>
            <th>Username</th>
            <th>Vytvořeno</th>
        </tr>
        <?php foreach ($allConfigs as $config): ?>
            <tr class='<?= $config['is_active'] ? 'active' : 'inactive' ?>'>
                <td><?= htmlspecialchars($config['id']) ?></td>
                <td><?= $config['is_active'] ? '✅ Aktivní' : '❌ Neaktivní' ?></td>
                <td><?= htmlspecialchars($config['smtp_host']) ?></td>
                <td><?= htmlspecialchars($config['smtp_port']) ?></td>
                <td><?= htmlspecialchars($config['smtp_username']) ?></td>
                <td><?= htmlspecialchars($config['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if ($inactiveCount > 0): ?>
    <div class='warning'>
        <strong>⚠️ Máte <?= $inactiveCount ?> neaktivní konfiguraci/í</strong><br><br>
        Tyto konfigurace se nepoužívají a zabírají místo v databázi.
        Doporučujeme je smazat.
    </div>

    <form method='POST'>
        <button type='submit' name='cleanup' class='btn btn-danger'>
            🗑️ Smazat neaktivní konfigurace
        </button>
    </form>
<?php else: ?>
    <div class='success'>
        <strong>✅ Databáze je čistá!</strong><br>
        Máte pouze aktivní SMTP konfiguraci.
    </div>
<?php endif; ?>

<h2>ℹ️ Informace</h2>

<div class='info'>
    <strong>Co tento cleanup dělá?</strong><br><br>

    - Zobrazí všechny SMTP konfigurace<br>
    - Smaže neaktivní konfigurace (is_active = 0)<br>
    - Zachová pouze aktivní funkční konfiguraci<br><br>

    <strong>Bezpečnost:</strong> Aktivní konfigurace nebude nikdy smazána.
</div>

<a href='/diagnoza_smtp.php' class='btn'>🔍 Diagnostika SMTP</a>
<a href='/admin.php' class='btn'>← Zpět do admin</a>

</div>
</body>
</html>
