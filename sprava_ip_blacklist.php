<?php
/**
 * Správa IP Blacklistu pro Analytics
 *
 * Admin rozhraní pro:
 * - Zobrazení všech IP adres které navštívily web
 * - Přidání IP do blacklistu (ignorované v analytics)
 * - Odebrání IP z blacklistu
 *
 * @date 2025-11-24
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spravovat IP blacklist.");
}

$pdo = getDbConnection();
$zprava = '';
$chyba = '';

// ========================================
// AKCE: Přidat IP do blacklistu
// ========================================
if (isset($_POST['action']) && $_POST['action'] === 'add_to_blacklist') {
    $ip = trim($_POST['ip_address'] ?? '');
    $popis = trim($_POST['description'] ?? '');

    if (empty($ip)) {
        $chyba = 'IP adresa je povinná.';
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $chyba = 'Neplatná IP adresa.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO wgs_analytics_ignored_ips (ip_address, description)
                VALUES (:ip, :popis)
            ");
            $stmt->execute(['ip' => $ip, 'popis' => $popis ?: 'Manuálně přidáno']);
            $zprava = "IP adresa {$ip} byla přidána do blacklistu.";
        } catch (PDOException $e) {
            $chyba = 'Chyba při přidávání: ' . $e->getMessage();
        }
    }
}

// ========================================
// AKCE: Odebrat IP z blacklistu
// ========================================
if (isset($_POST['action']) && $_POST['action'] === 'remove_from_blacklist') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM wgs_analytics_ignored_ips WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $zprava = "IP adresa byla odebrána z blacklistu.";
        } catch (PDOException $e) {
            $chyba = 'Chyba při odebírání: ' . $e->getMessage();
        }
    }
}

// ========================================
// NAČTENÍ DAT
// ========================================

// Aktuální IP uživatele
require_once __DIR__ . '/includes/geoip_helper.php';
$mojeIP = GeoIPHelper::ziskejKlientIP();

// IP adresy v blacklistu
$stmtBlacklist = $pdo->query("SELECT * FROM wgs_analytics_ignored_ips ORDER BY created_at DESC");
$blacklist = $stmtBlacklist->fetchAll(PDO::FETCH_ASSOC);

// Všechny IP které navštívily web (za posledních 7 dní)
$stmtNavstevy = $pdo->query("
    SELECT
        ip_address,
        COUNT(*) as pocet_navstev,
        MAX(created_at) as posledni_navsteva,
        MIN(created_at) as prvni_navsteva
    FROM wgs_pageviews
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY ip_address
    ORDER BY pocet_navstev DESC
    LIMIT 100
");
$navstevy = $stmtNavstevy->fetchAll(PDO::FETCH_ASSOC);

// Převést blacklist na pole IP pro rychlou kontrolu
$blacklistIPs = array_column($blacklist, 'ip_address');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa IP Blacklistu - WGS Analytics</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #333;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        tr:hover {
            background: #f0f0f0;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #555;
        }
        .btn-danger {
            background: #721c24;
        }
        .btn-danger:hover {
            background: #5a1a1f;
        }
        .btn-success {
            background: #155724;
        }
        .btn-success:hover {
            background: #0d3d18;
        }
        .btn-small {
            padding: 4px 10px;
            font-size: 12px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .highlight {
            background: #fff3cd;
            font-weight: bold;
        }
        .ignored {
            color: #155724;
            font-weight: bold;
        }
        input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 250px;
        }
        .form-inline {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Správa IP Blacklistu</h1>

        <?php if ($zprava): ?>
            <div class="success"><?= htmlspecialchars($zprava) ?></div>
        <?php endif; ?>

        <?php if ($chyba): ?>
            <div class="error"><?= htmlspecialchars($chyba) ?></div>
        <?php endif; ?>

        <div class="info">
            <strong>Vaše aktuální IP:</strong> <code><?= htmlspecialchars($mojeIP) ?></code>
            <?php if (in_array($mojeIP, $blacklistIPs)): ?>
                <span class="ignored">- IGNOROVÁNA (v blacklistu)</span>
            <?php else: ?>
                - <strong style="color: #721c24;">NENÍ v blacklistu!</strong>
                <form method="POST" style="display: inline; margin-left: 10px;">
                    <input type="hidden" name="action" value="add_to_blacklist">
                    <input type="hidden" name="ip_address" value="<?= htmlspecialchars($mojeIP) ?>">
                    <input type="hidden" name="description" value="Admin IP - automaticky">
                    <button type="submit" class="btn btn-success btn-small">Přidat do blacklistu</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <!-- BLACKLIST -->
        <div class="container">
            <h2>IP adresy v blacklistu</h2>
            <p>Tyto IP adresy jsou ignorovány v analytics.</p>

            <!-- Formulář pro přidání -->
            <form method="POST" class="form-inline" style="margin-bottom: 15px;">
                <input type="hidden" name="action" value="add_to_blacklist">
                <input type="text" name="ip_address" placeholder="IP adresa" required>
                <input type="text" name="description" placeholder="Popis (volitelné)">
                <button type="submit" class="btn btn-success">Přidat</button>
            </form>

            <?php if (empty($blacklist)): ?>
                <div class="warning">Žádné IP adresy v blacklistu.</div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>IP adresa</th>
                        <th>Popis</th>
                        <th>Přidáno</th>
                        <th>Akce</th>
                    </tr>
                    <?php foreach ($blacklist as $row): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($row['ip_address']) ?></code></td>
                            <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_from_blacklist">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Opravdu odebrat?')">Odebrat</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- NÁVŠTĚVY -->
        <div class="container">
            <h2>IP adresy z posledních 7 dní</h2>
            <p>Klikněte na tlačítko pro přidání IP do blacklistu.</p>

            <?php if (empty($navstevy)): ?>
                <div class="warning">Žádné návštěvy za posledních 7 dní.</div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>IP adresa</th>
                        <th>Návštěvy</th>
                        <th>Poslední</th>
                        <th>Akce</th>
                    </tr>
                    <?php foreach ($navstevy as $row): ?>
                        <?php $jeIgnorovana = in_array($row['ip_address'], $blacklistIPs); ?>
                        <tr class="<?= $row['ip_address'] === $mojeIP ? 'highlight' : '' ?>">
                            <td>
                                <code><?= htmlspecialchars($row['ip_address']) ?></code>
                                <?php if ($row['ip_address'] === $mojeIP): ?>
                                    <br><small>(Vaše IP)</small>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($row['pocet_navstev']) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($row['posledni_navsteva'])) ?></td>
                            <td>
                                <?php if ($jeIgnorovana): ?>
                                    <span class="ignored">Ignorována</span>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="add_to_blacklist">
                                        <input type="hidden" name="ip_address" value="<?= htmlspecialchars($row['ip_address']) ?>">
                                        <input type="hidden" name="description" value="Z návštěv">
                                        <button type="submit" class="btn btn-small">Ignorovat</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <a href="admin.php" class="btn" style="background: #666;">Zpět na Admin</a>
        <a href="vycisti_analytics_data.php" class="btn" style="background: #666;">Vyčistit Analytics</a>
        <a href="statistiky.php" class="btn" style="background: #666;">Statistiky</a>
    </div>
</body>
</html>
