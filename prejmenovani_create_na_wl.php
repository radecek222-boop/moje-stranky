<?php
/**
 * Migrace: Přejmenování tenanta 'create' na 'wl' (White Label)
 *
 * Aktualizuje slug v tabulce wgs_tenants a doménu.
 * Bezpečné opakované spuštění - zkontroluje stav před změnou.
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

$pdo = getDbConnection();

// Zjistit aktuální stav
$stmtCreate = $pdo->prepare("SELECT tenant_id, slug, nazev, domena FROM wgs_tenants WHERE slug = 'create'");
$stmtCreate->execute();
$tenantCreate = $stmtCreate->fetch(PDO::FETCH_ASSOC);

$stmtWl = $pdo->prepare("SELECT tenant_id, slug, nazev, domena FROM wgs_tenants WHERE slug = 'wl'");
$stmtWl->execute();
$tenantWl = $stmtWl->fetch(PDO::FETCH_ASSOC);

$zprava     = null;
$typZpravy  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akce']) && $_POST['akce'] === 'prejmenovatát') {
    require_once __DIR__ . '/includes/csrf_helper.php';
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $zprava    = 'Neplatný CSRF token.';
        $typZpravy = 'chyba';
    } elseif ($tenantWl) {
        $zprava    = 'Tenant se slugem "wl" již existuje (tenant_id: ' . $tenantWl['tenant_id'] . '). Migrace není potřeba.';
        $typZpravy = 'info';
    } elseif (!$tenantCreate) {
        $zprava    = 'Tenant se slugem "create" nebyl nalezen. Možná již byl přejmenován nebo neexistuje.';
        $typZpravy = 'info';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "UPDATE wgs_tenants SET slug = 'wl', domena = 'wl.wgs-service.cz' WHERE slug = 'create'"
            );
            $stmt->execute();
            $pocetRadku = $stmt->rowCount();

            $pdo->commit();

            $zprava    = "Tenant úspěšně přejmenován: 'create' → 'wl'. Aktualizováno {$pocetRadku} záznam(ů). Nová doména: wl.wgs-service.cz";
            $typZpravy = 'ok';

            // Znovu načíst stav
            $stmtCreate->execute();
            $tenantCreate = $stmtCreate->fetch(PDO::FETCH_ASSOC);
            $stmtWl->execute();
            $tenantWl = $stmtWl->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $pdo->rollBack();
            $zprava    = 'Chyba databáze: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $typZpravy = 'chyba';
        }
    }
}

require_once __DIR__ . '/includes/csrf_helper.php';
$csrfToken = generateCSRFToken();
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrace: Create → WL (White Label)</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; color: #222; }
        .container { background: #fff; padding: 35px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.12); }
        h1 { font-size: 1.4rem; border-bottom: 2px solid #222; padding-bottom: 10px; margin-bottom: 25px; }
        h2 { font-size: 1rem; margin: 20px 0 10px; color: #333; }
        .zprava-ok    { background: #eee; border: 1px solid #999; color: #222; padding: 12px 16px; border-radius: 5px; margin: 15px 0; }
        .zprava-chyba { background: #222; border: 1px solid #666; color: #fff; padding: 12px 16px; border-radius: 5px; margin: 15px 0; }
        .zprava-info  { background: #f0f0f0; border: 1px solid #ccc; color: #444; padding: 12px 16px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        td, th { padding: 9px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        th { background: #f5f5f5; font-weight: 600; }
        .stav-ok    { display: inline-block; background: #eee; border: 1px solid #999; color: #222; padding: 2px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 700; }
        .stav-chybi { display: inline-block; background: #222; color: #fff; padding: 2px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 700; }
        .btn { display: inline-block; padding: 10px 22px; background: #222; color: #fff; border: none; border-radius: 5px; font-size: 0.95rem; cursor: pointer; margin: 5px 5px 5px 0; }
        .btn:hover { background: #444; }
        .btn-sekundarni { background: #888; }
        .btn-sekundarni:hover { background: #666; }
        .sekce { margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        code { font-family: 'Courier New', monospace; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="container">
    <h1>Migrace: Přejmenování tenanta <code>create</code> → <code>wl</code></h1>

    <?php if ($zprava): ?>
        <div class="zprava-<?= $typZpravy ?>">
            <?= htmlspecialchars($zprava, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <h2>Aktuální stav tenantů</h2>
    <table>
        <tr>
            <th>Slug</th>
            <th>Stav</th>
            <th>Název</th>
            <th>Doména</th>
        </tr>
        <tr>
            <td><code>create</code></td>
            <td>
                <?php if ($tenantCreate): ?>
                    <span class="stav-ok">Nalezen (ID: <?= (int)$tenantCreate['tenant_id'] ?>)</span>
                <?php else: ?>
                    <span class="stav-chybi">Neexistuje</span>
                <?php endif; ?>
            </td>
            <td><?= $tenantCreate ? htmlspecialchars($tenantCreate['nazev'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
            <td><?= $tenantCreate ? htmlspecialchars($tenantCreate['domena'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
        </tr>
        <tr>
            <td><code>wl</code></td>
            <td>
                <?php if ($tenantWl): ?>
                    <span class="stav-ok">Nalezen (ID: <?= (int)$tenantWl['tenant_id'] ?>)</span>
                <?php else: ?>
                    <span class="stav-chybi">Neexistuje</span>
                <?php endif; ?>
            </td>
            <td><?= $tenantWl ? htmlspecialchars($tenantWl['nazev'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
            <td><?= $tenantWl ? htmlspecialchars($tenantWl['domena'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
        </tr>
    </table>

    <?php if (!$tenantWl && $tenantCreate): ?>
    <div class="sekce">
        <p style="font-size:0.9rem;color:#555;margin-bottom:14px;">
            Migrace přejmenuje slug <code>create</code> na <code>wl</code> a nastaví doménu na <code>wl.wgs-service.cz</code>.<br>
            Všechna data tenanta (uživatelé, reklamace) zůstanou zachována — mění se pouze identifikátor.
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="akce" value="prejmenovatát">
            <button type="submit" class="btn">Spustit přejmenování create → wl</button>
        </form>
    </div>
    <?php elseif ($tenantWl): ?>
    <div class="sekce">
        <p style="color:#555;font-size:0.9rem;">Tenant <code>wl</code> již existuje. Migrace dokončena nebo není potřeba.</p>
    </div>
    <?php else: ?>
    <div class="sekce">
        <p style="color:#555;font-size:0.9rem;">Tenant <code>create</code> nebyl nalezen. Zkontrolujte databázi nebo vytvořte nový tenant přes admin panel.</p>
    </div>
    <?php endif; ?>

    <div class="sekce">
        <p style="font-size:0.85rem;color:#888;">
            Po přejmenování nezapomeňte nastavit DNS záznamu <code>wl.wgs-service.cz</code> u hostingu.<br>
            Subdoména <code>create.wgs-service.cz</code> přestane fungovat.
        </p>
        <a href="/admin" class="btn btn-sekundarni">Zpět do adminu</a>
    </div>
</div>
</body>
</html>
