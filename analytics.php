<?php
/**
 * Analytics Dashboard - WGS Service
 * Jednoduchý přehled návštěvnosti webu
 *
 * @version 2.0
 * @date 2025-12-04
 */

require_once "init.php";

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=analytics.php');
    exit;
}

$pdo = getDbConnection();

// Získat časové období (výchozí = týden)
$obdobi = $_GET['obdobi'] ?? 'tyden';
$obdobiMap = [
    'dnes' => 'DATE(created_at) = CURDATE()',
    'vcera' => 'DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
    'tyden' => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
    'mesic' => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    'rok' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)'
];
$whereObdobi = $obdobiMap[$obdobi] ?? $obdobiMap['tyden'];

// === ZÍSKÁNÍ DAT ===

// 1. Základní metriky
$metriky = ['celkem' => 0, 'unikatni' => 0, 'dnes' => 0, 'online' => 0];
try {
    // Celkem návštěv
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM wgs_pageviews WHERE $whereObdobi");
    $metriky['celkem'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Unikátní návštěvníci (podle session_id)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT session_id) as cnt FROM wgs_pageviews WHERE $whereObdobi");
    $metriky['unikatni'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Dnes
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM wgs_pageviews WHERE DATE(created_at) = CURDATE()");
    $metriky['dnes'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Online teď (posledních 5 minut)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT session_id) as cnt FROM wgs_pageviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $metriky['online'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
} catch (PDOException $e) {
    // Tabulka neexistuje - OK
}

// 2. Top stránky
$topStranky = [];
try {
    $stmt = $pdo->query("
        SELECT
            page_url,
            page_title,
            COUNT(*) as navstevy,
            COUNT(DISTINCT session_id) as unikatni
        FROM wgs_pageviews
        WHERE $whereObdobi
        GROUP BY page_url, page_title
        ORDER BY navstevy DESC
        LIMIT 10
    ");
    $topStranky = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 3. Zdroje návštěvnosti (referrer)
$zdroje = [];
try {
    $stmt = $pdo->query("
        SELECT
            CASE
                WHEN referrer IS NULL OR referrer = '' THEN 'Přímý přístup'
                WHEN referrer LIKE '%google%' THEN 'Google'
                WHEN referrer LIKE '%seznam%' THEN 'Seznam'
                WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                WHEN referrer LIKE '%instagram%' THEN 'Instagram'
                ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '/', 3), '/', -1)
            END as zdroj,
            COUNT(*) as navstevy
        FROM wgs_pageviews
        WHERE $whereObdobi
        GROUP BY zdroj
        ORDER BY navstevy DESC
        LIMIT 10
    ");
    $zdroje = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 4. Lokace (země)
$lokace = [];
try {
    $stmt = $pdo->query("
        SELECT
            COALESCE(country_code, 'CZ') as zeme,
            city,
            COUNT(*) as navstevy
        FROM wgs_pageviews
        WHERE $whereObdobi
        GROUP BY zeme, city
        ORDER BY navstevy DESC
        LIMIT 10
    ");
    $lokace = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 5. Zařízení
$zarizeni = [];
try {
    $stmt = $pdo->query("
        SELECT
            device_type,
            COUNT(*) as navstevy
        FROM wgs_pageviews
        WHERE $whereObdobi
        GROUP BY device_type
        ORDER BY navstevy DESC
    ");
    $zarizeni = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 6. Prohlížeče
$prohlizece = [];
try {
    $stmt = $pdo->query("
        SELECT
            browser,
            COUNT(*) as navstevy
        FROM wgs_pageviews
        WHERE $whereObdobi
        GROUP BY browser
        ORDER BY navstevy DESC
        LIMIT 5
    ");
    $prohlizece = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 7. Blokované IP adresy
$blokovaneIP = [];
try {
    $stmt = $pdo->query("SELECT * FROM wgs_analytics_ignored_ips ORDER BY created_at DESC");
    $blokovaneIP = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabulka neexistuje - vytvoříme ji
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wgs_analytics_ignored_ips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                reason VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_ip (ip_address)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e2) {}
}

// 8. Návštěvnost po dnech (pro graf)
$navstevnostDny = [];
try {
    $stmt = $pdo->query("
        SELECT
            DATE(created_at) as den,
            COUNT(*) as navstevy,
            COUNT(DISTINCT session_id) as unikatni
        FROM wgs_pageviews
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY den ASC
    ");
    $navstevnostDny = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Mapování zemí
$zemeNazvy = [
    'CZ' => 'Česko',
    'SK' => 'Slovensko',
    'DE' => 'Německo',
    'AT' => 'Rakousko',
    'PL' => 'Polsko',
    'US' => 'USA',
    'GB' => 'Velká Británie'
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    <title>Analytics | WGS Service</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/styles.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fafafa 0%, #f0f0f0 100%);
            color: #222;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #ddd;
        }
        .header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #333;
        }
        .header-actions {
            display: flex;
            gap: 0.75rem;
        }
        .btn {
            padding: 0.6rem 1.25rem;
            border: 1px solid #ccc;
            background: #fff;
            color: #333;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .btn:hover, .btn.active {
            background: #333;
            color: #fff;
            border-color: #333;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Období selector */
        .obdobi-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: #fff;
            padding: 0.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            width: fit-content;
        }
        .obdobi-btn {
            padding: 0.6rem 1.2rem;
            border: none;
            background: transparent;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            color: #666;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .obdobi-btn.active, .obdobi-btn:hover {
            background: #333;
            color: #fff;
        }

        /* Metriky grid */
        .metriky-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }
        .metrika-card {
            background: #fff;
            border: none;
            border-radius: 16px;
            padding: 1.75rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .metrika-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        .metrika-card.highlight {
            background: linear-gradient(135deg, #333 0%, #111 100%);
            color: #fff;
        }
        .metrika-card.online {
            background: linear-gradient(135deg, #2d5a27 0%, #1a3518 100%);
            color: #fff;
        }
        .metrika-hodnota {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .metrika-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
        }

        /* Sekce */
        .sekce {
            background: #fff;
            border: none;
            border-radius: 16px;
            margin-bottom: 1.75rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .sekce-header {
            background: linear-gradient(135deg, #444 0%, #222 100%);
            color: #fff;
            padding: 1rem 1.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        .sekce-content {
            padding: 1.5rem;
        }

        /* Tabulky */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.85rem 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }
        th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            color: #888;
        }
        tr:hover {
            background: #fafafa;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }

        /* Grid layout */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 1.75rem;
        }
        @media (max-width: 900px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            .container {
                padding: 1rem;
            }
        }

        /* IP blokace */
        .ip-form {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .ip-form input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            transition: border-color 0.3s ease;
        }
        .ip-form input:focus {
            outline: none;
            border-color: #333;
        }
        .ip-list {
            max-height: 250px;
            overflow-y: auto;
        }
        .ip-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: #fafafa;
        }
        .ip-item:hover {
            background: #f0f0f0;
        }
        .ip-remove {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem;
            color: #999;
            transition: color 0.2s ease;
        }
        .ip-remove:hover {
            color: #333;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal {
            background: #fff;
            border: none;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .modal-header {
            background: linear-gradient(135deg, #333 0%, #111 100%);
            color: #fff;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 20px 20px 0 0;
        }
        .modal-header h3 {
            font-size: 1rem;
            font-weight: 500;
        }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.75rem;
            cursor: pointer;
            line-height: 1;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        .modal-close:hover {
            opacity: 1;
        }
        .modal-body {
            padding: 2rem;
        }

        /* Progress bar */
        .progress-bar {
            height: 6px;
            background: #f0f0f0;
            margin-top: 0.5rem;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #666 0%, #333 100%);
            border-radius: 3px;
        }

        /* ======== GRAF NÁVŠTĚVNOSTI - KOMPAKTNÍ BOXY ======== */
        .graf-wrapper {
            padding: 0.5rem 0;
        }
        .graf-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(28px, 1fr));
            gap: 3px;
        }
        .graf-box {
            aspect-ratio: 1;
            background: #fafafa;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.55rem;
            color: #999;
        }
        .graf-box:hover {
            transform: scale(1.15);
            z-index: 10;
        }
        .graf-box::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #111;
            color: #fff;
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            font-size: 0.65rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease;
            z-index: 100;
        }
        .graf-box:hover::after {
            opacity: 1;
        }
        /* Intenzita - pouze border s neonově zeleným gradientem */
        .int-0 { border: 2px solid #e0e0e0; }
        .int-1 { border: 2px solid #aaa; }
        .int-2 { border: 2px solid #888; }
        .int-3 { border: 2px solid #666; }
        .int-4 { border: 2px solid #444; }
        .int-5 { border: 2px solid #39ff14; box-shadow: 0 0 6px rgba(57, 255, 20, 0.3); }
        .int-max { border: 3px solid #39ff14; box-shadow: 0 0 12px rgba(57, 255, 20, 0.5); background: rgba(57, 255, 20, 0.05); }
        .graf-legenda {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
            font-size: 0.65rem;
            color: #888;
        }
        .graf-legenda-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .graf-legenda-box {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            background: #fafafa;
        }

        /* Zařízení */
        .device-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main id="main-content">
<div class="container">

    <!-- HEADER -->
    <div class="header">
        <h1>Analytics</h1>
        <div class="header-actions">
            <button class="btn" onclick="otevritModal('ip-modal')">Blokace IP</button>
            <a href="admin.php" class="btn">Zpet do admin</a>
        </div>
    </div>

    <!-- OBDOBÍ SELECTOR -->
    <div class="obdobi-selector">
        <a href="?obdobi=dnes" class="obdobi-btn <?= $obdobi === 'dnes' ? 'active' : '' ?>">Dnes</a>
        <a href="?obdobi=vcera" class="obdobi-btn <?= $obdobi === 'vcera' ? 'active' : '' ?>">Vcera</a>
        <a href="?obdobi=tyden" class="obdobi-btn <?= $obdobi === 'tyden' ? 'active' : '' ?>">Tyden</a>
        <a href="?obdobi=mesic" class="obdobi-btn <?= $obdobi === 'mesic' ? 'active' : '' ?>">Mesic</a>
        <a href="?obdobi=rok" class="obdobi-btn <?= $obdobi === 'rok' ? 'active' : '' ?>">Rok</a>
    </div>

    <!-- HLAVNÍ METRIKY -->
    <div class="metriky-grid">
        <div class="metrika-card highlight">
            <div class="metrika-hodnota"><?= number_format($metriky['celkem']) ?></div>
            <div class="metrika-label">Celkem navstev</div>
        </div>
        <div class="metrika-card">
            <div class="metrika-hodnota"><?= number_format($metriky['unikatni']) ?></div>
            <div class="metrika-label">Unikatnich navstevniku</div>
        </div>
        <div class="metrika-card">
            <div class="metrika-hodnota"><?= number_format($metriky['dnes']) ?></div>
            <div class="metrika-label">Dnes</div>
        </div>
        <div class="metrika-card online">
            <div class="metrika-hodnota"><?= $metriky['online'] ?></div>
            <div class="metrika-label">Online ted</div>
        </div>
    </div>

    <!-- GRAF NÁVŠTĚVNOSTI -->
    <div class="sekce">
        <div class="sekce-header">Navstevnost za poslednich 30 dni</div>
        <div class="sekce-content">
            <?php if (!empty($navstevnostDny)): ?>
                <?php
                $maxNavstev = max(array_column($navstevnostDny, 'navstevy'));
                $maxNavstev = $maxNavstev > 0 ? $maxNavstev : 1;

                // Funkce pro určení intenzity (0-max)
                function getInt($hodnota, $max) {
                    if ($hodnota == 0) return '0';
                    if ($max == 0) return '0';
                    $procento = ($hodnota / $max) * 100;
                    if ($procento >= 95) return 'max';
                    if ($procento >= 70) return '5';
                    if ($procento >= 50) return '4';
                    if ($procento >= 30) return '3';
                    if ($procento >= 10) return '2';
                    return '1';
                }
                ?>
                <div class="graf-wrapper">
                    <div class="graf-grid">
                        <?php foreach ($navstevnostDny as $den):
                            $intenzita = getInt($den['navstevy'], $maxNavstev);
                            $datum = date('j.n.', strtotime($den['den']));
                            $denVTydnu = ['Ne','Po','Ut','St','Ct','Pa','So'][date('w', strtotime($den['den']))];
                            $tooltip = $denVTydnu . ' ' . date('j.n.', strtotime($den['den'])) . ': ' . $den['navstevy'];
                        ?>
                            <div class="graf-box int-<?= $intenzita ?>" data-tooltip="<?= htmlspecialchars($tooltip) ?>">
                                <?= date('j', strtotime($den['den'])) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="graf-legenda">
                        <div class="graf-legenda-item"><div class="graf-legenda-box int-0"></div>0</div>
                        <div class="graf-legenda-item"><div class="graf-legenda-box int-2"></div>Malo</div>
                        <div class="graf-legenda-item"><div class="graf-legenda-box int-4"></div>Vice</div>
                        <div class="graf-legenda-item"><div class="graf-legenda-box int-max"></div>Max</div>
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #888; text-align: center; padding: 2rem; font-size: 0.85rem;">Zatim zadna data</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <!-- TOP STRÁNKY -->
        <div class="sekce">
            <div class="sekce-header">Nejnavstevovanejsi stranky</div>
            <div class="sekce-content">
                <?php if (!empty($topStranky)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Stranka</th>
                            <th class="text-right">Navstevy</th>
                            <th class="text-right">Unikatni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topStranky as $stranka): ?>
                        <tr>
                            <td title="<?= htmlspecialchars($stranka['page_url']) ?>">
                                <?= htmlspecialchars($stranka['page_title'] ?: basename($stranka['page_url']) ?: '/') ?>
                            </td>
                            <td class="text-right"><?= number_format($stranka['navstevy']) ?></td>
                            <td class="text-right"><?= number_format($stranka['unikatni']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #999; text-align: center; padding: 1rem;">Zadna data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ZDROJE NÁVŠTĚVNOSTI -->
        <div class="sekce">
            <div class="sekce-header">Jak se k nam dostali</div>
            <div class="sekce-content">
                <?php if (!empty($zdroje)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Zdroj</th>
                            <th class="text-right">Navstevy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $maxZdroj = $zdroje[0]['navstevy'] ?? 1;
                        foreach ($zdroje as $zdroj):
                            $procento = ($zdroj['navstevy'] / $maxZdroj) * 100;
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($zdroj['zdroj']) ?>
                                <div class="progress-bar"><div class="progress-fill" style="width: <?= $procento ?>%"></div></div>
                            </td>
                            <td class="text-right"><?= number_format($zdroj['navstevy']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #999; text-align: center; padding: 1rem;">Zadna data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- LOKACE -->
        <div class="sekce">
            <div class="sekce-header">Odkud jsou navstevnici</div>
            <div class="sekce-content">
                <?php if (!empty($lokace)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Lokace</th>
                            <th class="text-right">Navstevy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lokace as $lok): ?>
                        <tr>
                            <td>
                                <?= $zemeNazvy[$lok['zeme']] ?? $lok['zeme'] ?>
                                <?php if ($lok['city']): ?>
                                    <span style="color: #999;">- <?= htmlspecialchars($lok['city']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= number_format($lok['navstevy']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #999; text-align: center; padding: 1rem;">Zadna data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ZAŘÍZENÍ A PROHLÍŽEČE -->
        <div class="sekce">
            <div class="sekce-header">Zarizeni a prohlizece</div>
            <div class="sekce-content">
                <?php if (!empty($zarizeni) || !empty($prohlizece)): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="font-size: 0.7rem; text-transform: uppercase; color: #888; margin-bottom: 0.75rem; letter-spacing: 0.5px;">Zarizeni</h4>
                        <?php
                        $deviceNazvy = ['desktop' => 'Pocitac', 'mobile' => 'Mobil', 'tablet' => 'Tablet'];
                        foreach ($zarizeni as $z):
                        ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; font-size: 0.85rem; border-bottom: 1px solid #f0f0f0;">
                            <span class="device-label"><?= $deviceNazvy[$z['device_type']] ?? ucfirst($z['device_type']) ?></span>
                            <span style="color: #666;"><?= number_format($z['navstevy']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <h4 style="font-size: 0.7rem; text-transform: uppercase; color: #888; margin-bottom: 0.75rem; letter-spacing: 0.5px;">Prohlizece</h4>
                        <?php foreach ($prohlizece as $p): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; font-size: 0.85rem; border-bottom: 1px solid #f0f0f0;">
                            <span><?= htmlspecialchars($p['browser'] ?: 'Neznamy') ?></span>
                            <span style="color: #666;"><?= number_format($p['navstevy']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <p style="color: #888; text-align: center; padding: 2rem;">Zadna data</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</main>

<!-- MODAL: BLOKACE IP -->
<div class="modal-overlay" id="ip-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Blokace IP adres</h3>
            <button class="modal-close" onclick="zavritModal('ip-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 0.8rem; color: #666; margin-bottom: 1rem;">
                IP adresy v tomto seznamu nebudou zahrnuty do analytics (napr. vase vlastni IP).
            </p>

            <form class="ip-form" onsubmit="return pridatIP(event)">
                <input type="text" id="nova-ip" placeholder="Zadejte IP adresu (napr. 192.168.1.1)" required>
                <button type="submit" class="btn">Pridat</button>
            </form>

            <div class="ip-list">
                <?php if (empty($blokovaneIP)): ?>
                    <p style="color: #999; text-align: center; padding: 1rem;">Zadne blokovane IP</p>
                <?php else: ?>
                    <?php foreach ($blokovaneIP as $ip): ?>
                    <div class="ip-item" data-id="<?= $ip['id'] ?>">
                        <div>
                            <strong><?= htmlspecialchars($ip['ip_address']) ?></strong>
                            <?php if ($ip['reason']): ?>
                                <span style="color: #999; font-size: 0.7rem;"> - <?= htmlspecialchars($ip['reason']) ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="ip-remove" onclick="odebratIP(<?= $ip['id'] ?>)" title="Odebrat">&times;</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                <button class="btn" onclick="pridatMojiIP()">Pridat moji aktualni IP</button>
            </div>
        </div>
    </div>
</div>

<script>
// Modal funkce
function otevritModal(id) {
    document.getElementById(id).classList.add('active');
}
function zavritModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Zavřít modal kliknutím mimo
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// IP blokace
async function pridatIP(e) {
    e.preventDefault();
    const ip = document.getElementById('nova-ip').value.trim();
    if (!ip) return false;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    try {
        const response = await fetch('/api/analytics_api.php?action=add_blocked_ip', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ip_address: ip, csrf_token: csrfToken })
        });
        const data = await response.json();

        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.message || 'Chyba pri pridavani IP');
        }
    } catch (err) {
        alert('Chyba: ' + err.message);
    }
    return false;
}

async function odebratIP(id) {
    if (!confirm('Opravdu odebrat tuto IP z blokace?')) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    try {
        const response = await fetch('/api/analytics_api.php?action=remove_blocked_ip', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, csrf_token: csrfToken })
        });
        const data = await response.json();

        if (data.status === 'success') {
            document.querySelector(`.ip-item[data-id="${id}"]`)?.remove();
        } else {
            alert(data.message || 'Chyba pri odebirani IP');
        }
    } catch (err) {
        alert('Chyba: ' + err.message);
    }
}

async function pridatMojiIP() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    try {
        const response = await fetch('/api/analytics_api.php?action=add_my_ip', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken })
        });
        const data = await response.json();

        if (data.status === 'success') {
            alert('Vase IP (' + data.ip + ') byla pridana do blokace');
            location.reload();
        } else {
            alert(data.message || 'Chyba');
        }
    } catch (err) {
        alert('Chyba: ' + err.message);
    }
}
</script>

</body>
</html>
