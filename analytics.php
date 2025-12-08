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

// 4. Lokace (město jako primární, fallback na zemi)
// Zobrazit POUZE záznamy s městem, bez města nezobrazovat (jsou to staré záznamy)
$lokace = [];
try {
    $stmt = $pdo->query("
        SELECT
            city,
            COALESCE(country_code, 'CZ') as zeme,
            COUNT(*) as navstevy
        FROM wgs_pageviews
        WHERE $whereObdobi
          AND city IS NOT NULL
          AND city != ''
        GROUP BY city, country_code
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

        /* Grid layout - sekce pod sebou */
        .grid-2 {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .grid-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        @media (max-width: 900px) {
            .container {
                padding: 1rem;
            }
        }
        /* Kompaktnější sekce */
        .sekce.kompakt .sekce-content {
            padding: 1rem;
        }
        .sekce.kompakt table th,
        .sekce.kompakt table td {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
        }
        .sekce.kompakt table th {
            font-size: 0.65rem;
        }

        /* IP blokace - DARK THEME */
        .ip-form {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .ip-form input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #444;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            transition: border-color 0.3s ease;
            background: #222;
            color: #fff;
        }
        .ip-form input:focus {
            outline: none;
            border-color: #666;
        }
        .ip-form input::placeholder {
            color: #888;
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
            border: 1px solid #333;
            font-size: 0.85rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: #222;
            color: #fff;
        }
        .ip-item:hover {
            background: #2a2a2a;
        }
        .ip-remove {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem;
            color: #888;
            transition: color 0.2s ease;
        }
        .ip-remove:hover {
            color: #dc3545;
        }

        /* Modal - DARK THEME */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 12px;
            width: 90%;
            max-width: 550px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .modal-header {
            background: #333;
            color: #fff;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px 12px 0 0;
            border-bottom: 1px solid #444;
        }
        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }
        .modal-close {
            background: none;
            border: none;
            color: #ccc;
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
            color: #ccc;
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
            <button class="btn" onclick="otevritModal('heatmap-modal')">Heatmapy</button>
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

    <!-- TOP STRÁNKY - plná šířka -->
    <div class="sekce kompakt">
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
                        <td title="<?= htmlspecialchars($stranka['page_url']) ?>"><?= htmlspecialchars(mb_substr($stranka['page_title'] ?: basename($stranka['page_url']) ?: '/', 0, 60)) ?><?= mb_strlen($stranka['page_title'] ?: '') > 60 ? '...' : '' ?></td>
                        <td class="text-right"><?= number_format($stranka['navstevy']) ?></td>
                        <td class="text-right"><?= number_format($stranka['unikatni']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color: #888; text-align: center; padding: 1rem;">Zadna data</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Spodní sekce vedle sebe -->
    <div class="grid-row">
        <!-- ZDROJE -->
        <div class="sekce kompakt">
            <div class="sekce-header">Jak se k nam dostali</div>
            <div class="sekce-content">
                <?php if (!empty($zdroje)): ?>
                <?php $maxZdroj = $zdroje[0]['navstevy'] ?? 1; foreach ($zdroje as $zdroj): $proc = ($zdroj['navstevy'] / $maxZdroj) * 100; ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; font-size: 0.8rem; border-bottom: 1px solid #f5f5f5;">
                    <span style="flex: 1;"><?= htmlspecialchars($zdroj['zdroj']) ?></span>
                    <span style="color: #666; min-width: 40px; text-align: right;"><?= $zdroj['navstevy'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="color: #888; text-align: center;">Zadna data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- LOKACE -->
        <div class="sekce kompakt">
            <div class="sekce-header">Odkud jsou</div>
            <div class="sekce-content">
                <?php if (!empty($lokace)): ?>
                <?php foreach ($lokace as $lok): ?>
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.8rem; border-bottom: 1px solid #f5f5f5;">
                    <span><?php
                        $zemeNazev = $zemeNazvy[$lok['zeme']] ?? $lok['zeme'];
                        if (!empty($lok['city'])) {
                            // Město + země
                            echo htmlspecialchars($lok['city']);
                        } else {
                            // Jen země
                            echo $zemeNazev;
                        }
                    ?></span>
                    <span style="color: #666;"><?= $lok['navstevy'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="color: #888; text-align: center;">Zadna data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ZAŘÍZENÍ -->
        <div class="sekce kompakt">
            <div class="sekce-header">Zarizeni</div>
            <div class="sekce-content">
                <?php if (!empty($zarizeni)): ?>
                <?php $deviceNazvy = ['desktop' => 'Pocitac', 'mobile' => 'Mobil', 'tablet' => 'Tablet']; foreach ($zarizeni as $z): ?>
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.8rem; border-bottom: 1px solid #f5f5f5;">
                    <span><?= $deviceNazvy[$z['device_type']] ?? ucfirst($z['device_type']) ?></span>
                    <span style="color: #666;"><?= $z['navstevy'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="color: #888; text-align: center;">Zadna data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- PROHLÍŽEČE -->
        <div class="sekce kompakt">
            <div class="sekce-header">Prohlizece</div>
            <div class="sekce-content">
                <?php if (!empty($prohlizece)): ?>
                <?php foreach ($prohlizece as $p): ?>
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.8rem; border-bottom: 1px solid #f5f5f5;">
                    <span><?= htmlspecialchars($p['browser'] ?: 'Neznamy') ?></span>
                    <span style="color: #666;"><?= $p['navstevy'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="color: #888; text-align: center;">Zadna data</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</main>

<!-- MODAL: HEATMAPY -->
<div class="modal-overlay" id="heatmap-modal">
    <div class="modal" style="max-width: 1200px; width: 95%;">
        <div class="modal-header">
            <h3>Heatmap Viewer</h3>
            <button class="modal-close" onclick="zavritModal('heatmap-modal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <!-- Ovladaci prvky -->
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                <div style="flex: 1; min-width: 180px;">
                    <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.3rem;">Stranka</label>
                    <select id="heatmap-page" style="width: 100%; padding: 0.6rem; background: #222; border: 1px solid #444; color: #fff; border-radius: 6px;">
                        <option value="https://www.wgs-service.cz/" data-path="/">DOMU</option>
                        <option value="https://www.wgs-service.cz/novareklamace" data-path="/novareklamace">OBJEDNAT SERVIS</option>
                        <option value="https://www.wgs-service.cz/nasesluzby" data-path="/nasesluzby">NASE SLUZBY</option>
                        <option value="https://www.wgs-service.cz/cenik" data-path="/cenik">CENIK</option>
                        <option value="https://www.wgs-service.cz/onas" data-path="/onas">O NAS</option>
                        <option value="https://www.wgs-service.cz/aktuality" data-path="/aktuality">AKTUALITY</option>
                        <option value="https://www.wgs-service.cz/login" data-path="/login">PRIHLASENI</option>
                    </select>
                </div>
                <div style="min-width: 120px;">
                    <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.3rem;">Zarizeni</label>
                    <select id="heatmap-device" style="width: 100%; padding: 0.6rem; background: #222; border: 1px solid #444; color: #fff; border-radius: 6px;">
                        <option value="">Vsechna</option>
                        <option value="desktop">Desktop</option>
                        <option value="mobile">Mobile</option>
                        <option value="tablet">Tablet</option>
                    </select>
                </div>
                <div style="min-width: 120px;">
                    <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.3rem;">Typ</label>
                    <select id="heatmap-type" style="width: 100%; padding: 0.6rem; background: #222; border: 1px solid #444; color: #fff; border-radius: 6px;">
                        <option value="click">Kliky</option>
                        <option value="scroll">Scroll</option>
                    </select>
                </div>
                <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                    <button class="btn" onclick="nacistHeatmap()">Nacist</button>
                    <button class="btn" style="background: #444;" onclick="nacistHeatmapDemo()">Demo</button>
                </div>
            </div>

            <!-- Statistiky -->
            <div id="heatmap-stats" style="display: none; margin-bottom: 1rem;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <div style="background: #222; padding: 1rem; border-radius: 8px; text-align: center;">
                        <div id="hm-stat-total" style="font-size: 1.5rem; font-weight: 700; color: #39ff14;">0</div>
                        <div style="font-size: 0.7rem; color: #888; text-transform: uppercase;">Celkem</div>
                    </div>
                    <div style="background: #222; padding: 1rem; border-radius: 8px; text-align: center;">
                        <div id="hm-stat-max" style="font-size: 1.5rem; font-weight: 700; color: #fff;">0</div>
                        <div style="font-size: 0.7rem; color: #888; text-transform: uppercase;">Max intenzita</div>
                    </div>
                    <div style="background: #222; padding: 1rem; border-radius: 8px; text-align: center;">
                        <div id="hm-stat-points" style="font-size: 1.5rem; font-weight: 700; color: #fff;">0</div>
                        <div style="font-size: 0.7rem; color: #888; text-transform: uppercase;">Bodu</div>
                    </div>
                </div>
            </div>

            <!-- Kontejner pro heatmapu -->
            <div id="heatmap-container" style="position: relative; background: #111; border-radius: 8px; overflow: hidden; min-height: 500px;">
                <div id="heatmap-placeholder" style="display: flex; align-items: center; justify-content: center; height: 500px; color: #666;">
                    Vyberte stranku a kliknete na "Nacist"
                </div>
                <iframe id="heatmap-iframe" title="Nahled stranky" style="display: none; width: 100%; height: 600px; border: none;"></iframe>
                <canvas id="heatmap-canvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 10; opacity: 0.7;"></canvas>
            </div>

            <!-- Legenda -->
            <div style="display: flex; justify-content: center; gap: 1.5rem; margin-top: 1rem; font-size: 0.75rem; color: #888;">
                <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width: 16px; height: 16px; background: rgba(0,0,255,0.7); border-radius: 50%;"></div> Malo</div>
                <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width: 16px; height: 16px; background: rgba(0,255,0,0.7); border-radius: 50%;"></div> Stredne</div>
                <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width: 16px; height: 16px; background: rgba(255,255,0,0.8); border-radius: 50%;"></div> Vice</div>
                <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width: 16px; height: 16px; background: rgba(255,0,0,0.9); border-radius: 50%;"></div> Max</div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: BLOKACE IP -->
<div class="modal-overlay" id="ip-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Blokace IP adres</h3>
            <button class="modal-close" onclick="zavritModal('ip-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 0.8rem; color: #888; margin-bottom: 1rem;">
                IP adresy v tomto seznamu nebudou zahrnuty do analytics (napr. vase vlastni IP).
            </p>

            <form class="ip-form" onsubmit="return pridatIP(event)">
                <input type="text" id="nova-ip" placeholder="Zadejte IP adresu (napr. 192.168.1.1)" required>
                <button type="submit" class="btn">Pridat</button>
            </form>

            <div class="ip-list">
                <?php if (empty($blokovaneIP)): ?>
                    <p style="color: #666; text-align: center; padding: 1rem;">Zadne blokovane IP</p>
                <?php else: ?>
                    <?php foreach ($blokovaneIP as $ip): ?>
                    <div class="ip-item" data-id="<?= $ip['id'] ?>">
                        <div>
                            <strong><?= htmlspecialchars($ip['ip_address']) ?></strong>
                            <?php if ($ip['reason']): ?>
                                <span style="color: #888; font-size: 0.7rem;"> - <?= htmlspecialchars($ip['reason']) ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="ip-remove" onclick="odebratIP(<?= $ip['id'] ?>)" title="Odebrat">&times;</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #333;">
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

// ========== HEATMAP FUNKCE ==========
let heatmapData = null;

function nacistHeatmapStranku(url) {
    const iframe = document.getElementById('heatmap-iframe');
    const placeholder = document.getElementById('heatmap-placeholder');
    const selectedOption = document.querySelector('#heatmap-page option:checked');
    const relativePath = selectedOption.dataset.path || '/';

    placeholder.style.display = 'none';
    iframe.style.display = 'block';
    iframe.src = relativePath + (relativePath.includes('?') ? '&' : '?') + '_heatmap_preview=1';

    iframe.onload = function() {
        resizeHeatmapCanvas();
    };
}

function resizeHeatmapCanvas() {
    const iframe = document.getElementById('heatmap-iframe');
    const canvas = document.getElementById('heatmap-canvas');
    const container = document.getElementById('heatmap-container');

    canvas.width = iframe.offsetWidth || container.offsetWidth;
    canvas.height = iframe.offsetHeight || 600;

    if (heatmapData) {
        const type = document.getElementById('heatmap-type').value;
        if (type === 'click') {
            vykresliClickHeatmap(heatmapData);
        } else {
            vykresliScrollHeatmap(heatmapData);
        }
    }
}

async function nacistHeatmap() {
    const pageUrl = document.getElementById('heatmap-page').value;
    const deviceType = document.getElementById('heatmap-device').value;
    const type = document.getElementById('heatmap-type').value;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    nacistHeatmapStranku(pageUrl);
    document.getElementById('heatmap-stats').style.display = 'none';

    try {
        const url = `/api/analytics_heatmap.php?page_url=${encodeURIComponent(pageUrl)}&device_type=${deviceType}&type=${type}&csrf_token=${csrfToken}`;
        const response = await fetch(url);
        const result = await response.json();

        if (result.status === 'success') {
            heatmapData = result;
            document.getElementById('heatmap-stats').style.display = 'block';

            if (type === 'click') {
                document.getElementById('hm-stat-total').textContent = (result.total_clicks || 0).toLocaleString();
                document.getElementById('hm-stat-max').textContent = result.max_intensity || 0;
                document.getElementById('hm-stat-points').textContent = (result.points_count || 0).toLocaleString();
                setTimeout(() => { resizeHeatmapCanvas(); vykresliClickHeatmap(result); }, 500);
            } else {
                document.getElementById('hm-stat-total').textContent = (result.total_views || 0).toLocaleString();
                document.getElementById('hm-stat-max').textContent = '100%';
                document.getElementById('hm-stat-points').textContent = result.buckets_count || 0;
                setTimeout(() => { resizeHeatmapCanvas(); vykresliScrollHeatmap(result); }, 500);
            }
        } else {
            console.warn('Heatmap API:', result.message);
        }
    } catch (err) {
        console.error('Heatmap error:', err);
    }
}

function nacistHeatmapDemo() {
    const type = document.getElementById('heatmap-type').value;
    const pageUrl = document.getElementById('heatmap-page').value;

    nacistHeatmapStranku(pageUrl);

    if (type === 'click') {
        const demoPoints = [];
        for (let i = 0; i < 50; i++) {
            demoPoints.push({ x: Math.random() * 80 + 10, y: Math.random() * 80 + 10, count: Math.floor(Math.random() * 100) + 1 });
        }
        heatmapData = { points: demoPoints, total_clicks: demoPoints.reduce((s,p) => s + p.count, 0), max_intensity: Math.max(...demoPoints.map(p => p.count)), points_count: demoPoints.length };

        document.getElementById('heatmap-stats').style.display = 'block';
        document.getElementById('hm-stat-total').textContent = heatmapData.total_clicks.toLocaleString();
        document.getElementById('hm-stat-max').textContent = heatmapData.max_intensity;
        document.getElementById('hm-stat-points').textContent = heatmapData.points_count;

        setTimeout(() => { resizeHeatmapCanvas(); vykresliClickHeatmap(heatmapData); }, 500);
    } else {
        const demoBuckets = [];
        for (let d = 0; d <= 100; d += 10) {
            demoBuckets.push({ depth: d, count: Math.max(100 - d + Math.random() * 20, 10) });
        }
        heatmapData = { buckets: demoBuckets, total_views: demoBuckets[0].count, buckets_count: demoBuckets.length };

        document.getElementById('heatmap-stats').style.display = 'block';
        document.getElementById('hm-stat-total').textContent = Math.round(heatmapData.total_views);
        document.getElementById('hm-stat-max').textContent = '100%';
        document.getElementById('hm-stat-points').textContent = heatmapData.buckets_count;

        setTimeout(() => { resizeHeatmapCanvas(); vykresliScrollHeatmap(heatmapData); }, 500);
    }
}

function vykresliClickHeatmap(data) {
    const canvas = document.getElementById('heatmap-canvas');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (!data.points || data.points.length === 0) return;

    const maxCount = data.max_intensity || Math.max(...data.points.map(p => p.count));

    data.points.forEach(point => {
        const x = (point.x / 100) * canvas.width;
        const y = (point.y / 100) * canvas.height;
        const intensity = point.count / maxCount;
        const radius = 20 + intensity * 30;

        const gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
        if (intensity < 0.25) {
            gradient.addColorStop(0, 'rgba(0, 0, 255, 0.8)');
            gradient.addColorStop(1, 'rgba(0, 0, 255, 0)');
        } else if (intensity < 0.5) {
            gradient.addColorStop(0, 'rgba(0, 255, 0, 0.8)');
            gradient.addColorStop(1, 'rgba(0, 255, 0, 0)');
        } else if (intensity < 0.75) {
            gradient.addColorStop(0, 'rgba(255, 255, 0, 0.9)');
            gradient.addColorStop(1, 'rgba(255, 255, 0, 0)');
        } else {
            gradient.addColorStop(0, 'rgba(255, 0, 0, 1)');
            gradient.addColorStop(1, 'rgba(255, 0, 0, 0)');
        }

        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fillStyle = gradient;
        ctx.fill();
    });
}

function vykresliScrollHeatmap(data) {
    const canvas = document.getElementById('heatmap-canvas');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (!data.buckets || data.buckets.length === 0) return;

    const maxCount = Math.max(...data.buckets.map(b => b.count));

    data.buckets.forEach(bucket => {
        const yStart = (bucket.depth / 100) * canvas.height;
        const height = canvas.height / data.buckets.length;
        const intensity = bucket.count / maxCount;

        let r, g, b;
        if (intensity > 0.75) { r = 0; g = 255; b = 0; }
        else if (intensity > 0.5) { r = 255; g = 255; b = 0; }
        else if (intensity > 0.25) { r = 255; g = 165; b = 0; }
        else { r = 255; g = 0; b = 0; }

        ctx.fillStyle = `rgba(${r}, ${g}, ${b}, 0.4)`;
        ctx.fillRect(0, yStart, canvas.width, height);

        ctx.fillStyle = '#fff';
        ctx.font = '12px Poppins';
        ctx.fillText(`${bucket.depth}% - ${Math.round(intensity * 100)}%`, 10, yStart + height / 2 + 4);
    });
}
</script>

</body>
</html>
