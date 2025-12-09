<?php
/**
 * SEZNAM - PHP VERZE (bez JavaScriptu)
 * Kompletní vykreslení karet v PHP pro test CN logiky
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze admin");
}

$pdo = getDbConnection();

// ============================================
// 1. NAČÍST EMAILY S CN (stejná logika jako API)
// ============================================
$stmt = $pdo->query("
    SELECT DISTINCT LOWER(zakaznik_email) as email
    FROM wgs_nabidky
    WHERE stav IN ('potvrzena', 'odeslana')
    AND zakaznik_email IS NOT NULL
    AND zakaznik_email != ''
");
$emailySCN = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// 2. NAČÍST REKLAMACE (stejná logika jako load.php)
// ============================================
$stmt = $pdo->query("
    SELECT id, reklamace_id, jmeno, email, stav, termin, cas_navstevy,
           adresa, model, popis_problemu, created_at
    FROM wgs_reklamace
    ORDER BY created_at DESC
    LIMIT 50
");
$reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seznam - PHP Test (bez JS)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0a;
            color: #eee;
            padding: 20px;
        }
        h1 {
            color: #39ff14;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #39ff14;
        }
        .info-box {
            background: #1a1a1a;
            border: 1px solid #333;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .info-box h3 { color: #ff9800; margin-bottom: 10px; }
        .info-box pre {
            background: #111;
            padding: 10px;
            overflow-x: auto;
            font-size: 12px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        /* KARTA - základní styl */
        .order-box {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.2s;
        }
        .order-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        /* STAVY - barvy pozadí */
        .status-bg-wait {
            background: rgba(255, 235, 59, 0.08) !important;
            border-color: rgba(255, 235, 59, 0.3) !important;
        }
        .status-bg-open {
            background: rgba(33, 150, 243, 0.08) !important;
            border-color: rgba(33, 150, 243, 0.3) !important;
        }
        .status-bg-done {
            background: rgba(76, 175, 80, 0.08) !important;
            border-color: rgba(76, 175, 80, 0.3) !important;
        }

        /* ORANŽOVÁ - zákazník s CN */
        .ma-cenovou-nabidku {
            border: 2px solid #ff9800 !important;
            box-shadow: 0 0 8px rgba(255, 152, 0, 0.3) !important;
        }
        .ma-cenovou-nabidku:hover {
            box-shadow: 0 0 12px rgba(255, 152, 0, 0.5) !important;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .order-number {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .order-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-wait { background: #FFEB3B; }
        .status-open { background: #2196F3; }
        .status-done { background: #4CAF50; }

        .order-customer {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .order-detail-line {
            font-size: 0.85rem;
            color: #999;
            margin-bottom: 3px;
        }
        .order-cn-text {
            color: #ff9800 !important;
            font-weight: 600 !important;
            font-size: 0.85rem !important;
        }
        .order-status-text {
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-text-wait { color: #FFEB3B; }
        .status-text-open { color: #2196F3; }
        .status-text-done { color: #4CAF50; }

        .debug-info {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #333;
            font-size: 11px;
            color: #666;
        }
        .debug-info .match { color: #39ff14; }
        .debug-info .nomatch { color: #ff4444; }

        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<h1>Seznam reklamací - PHP Test (bez JavaScriptu)</h1>

<div class="info-box">
    <h3>Diagnostika CN</h3>
    <p><strong>Emaily s aktivní CN (emailySCN):</strong></p>
    <pre><?php echo json_encode($emailySCN, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
    <p style="margin-top:10px;">Počet: <strong><?php echo count($emailySCN); ?></strong></p>
</div>

<div class="legend">
    <div class="legend-item">
        <div class="legend-color" style="background: rgba(255, 235, 59, 0.3);"></div>
        <span>ČEKÁ (žlutá)</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: rgba(33, 150, 243, 0.3);"></div>
        <span>DOMLUVENÁ (modrá)</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: rgba(76, 175, 80, 0.3);"></div>
        <span>HOTOVO (zelená)</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #ff9800; border: 2px solid #ff9800;"></div>
        <span>MÁ CN (oranžový rámeček)</span>
    </div>
</div>

<div class="grid">
<?php foreach ($reklamace as $rec):
    // Mapování stavu
    $stav = $rec['stav'] ?? 'wait';
    $stavMap = [
        'wait' => ['class' => 'wait', 'text' => 'ČEKÁ'],
        'ČEKÁ' => ['class' => 'wait', 'text' => 'ČEKÁ'],
        'open' => ['class' => 'open', 'text' => 'DOMLUVENÁ'],
        'DOMLUVENÁ' => ['class' => 'open', 'text' => 'DOMLUVENÁ'],
        'done' => ['class' => 'done', 'text' => 'HOTOVO'],
        'HOTOVO' => ['class' => 'done', 'text' => 'HOTOVO'],
    ];
    $status = $stavMap[$stav] ?? ['class' => 'wait', 'text' => $stav];

    // Kontrola CN - PŘESNĚ JAKO V JAVASCRIPT
    $zakaznikEmail = strtolower(trim($rec['email'] ?? ''));
    $maCenovouNabidku = $zakaznikEmail && in_array($zakaznikEmail, $emailySCN);

    // Kontrola termínu
    $isDomluvena = $status['class'] === 'open';
    $appointmentText = '';
    if ($isDomluvena && $rec['termin'] && $rec['cas_navstevy']) {
        $appointmentText = $rec['termin'] . ' ' . $rec['cas_navstevy'];
    }

    // KLÍČOVÁ LOGIKA - cnClass
    $cnClass = ($maCenovouNabidku && !$appointmentText) ? 'ma-cenovou-nabidku' : '';

    // CSS třídy
    $statusBgClass = 'status-bg-' . $status['class'];
    $allClasses = "order-box {$statusBgClass} {$cnClass}";
?>
    <div class="<?php echo $allClasses; ?>">
        <div class="order-header">
            <div class="order-number"><?php echo htmlspecialchars($rec['reklamace_id']); ?></div>
            <div class="order-status status-<?php echo $status['class']; ?>"></div>
        </div>
        <div class="order-customer"><?php echo htmlspecialchars($rec['jmeno']); ?></div>
        <div class="order-detail-line"><?php echo htmlspecialchars($rec['adresa'] ?? '—'); ?></div>
        <div class="order-detail-line"><?php echo htmlspecialchars($rec['model'] ?? '—'); ?></div>
        <div class="order-detail-line" style="opacity: 0.6;">
            <?php echo date('d.m.Y', strtotime($rec['created_at'])); ?>
        </div>

        <div style="text-align: right; margin-top: 10px;">
            <?php if ($appointmentText): ?>
                <span style="color: #2196F3; font-weight: 500;"><?php echo $appointmentText; ?></span>
            <?php elseif ($maCenovouNabidku): ?>
                <span class="order-cn-text">Poslána CN</span>
            <?php else: ?>
                <span class="order-status-text status-text-<?php echo $status['class']; ?>"><?php echo $status['text']; ?></span>
            <?php endif; ?>
        </div>

        <!-- DEBUG INFO -->
        <div class="debug-info">
            ID: <?php echo $rec['id']; ?> |
            Email: <code><?php echo htmlspecialchars($rec['email'] ?? 'PRÁZDNÝ'); ?></code><br>
            zakaznikEmail: <code><?php echo $zakaznikEmail ?: 'PRÁZDNÝ'; ?></code><br>
            maCenovouNabidku: <?php echo $maCenovouNabidku ? '<span class="match">TRUE</span>' : '<span class="nomatch">FALSE</span>'; ?><br>
            appointmentText: <code><?php echo $appointmentText ?: 'PRÁZDNÝ'; ?></code><br>
            cnClass: <code><?php echo $cnClass ?: 'PRÁZDNÝ'; ?></code><br>
            <strong>Výsledek:</strong>
            <?php if ($cnClass): ?>
                <span class="match">ORANŽOVÁ KARTA</span>
            <?php else: ?>
                <span class="nomatch">STANDARDNÍ BARVA</span>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div class="info-box" style="margin-top: 30px;">
    <h3>Co tato stránka ukazuje</h3>
    <p>Toto je <strong>čistě PHP verze</strong> bez jakéhokoliv JavaScriptu.</p>
    <p>Pokud zde vidíte oranžové karty správně, ale na normálním seznam.php ne, pak je problém v JavaScriptu.</p>
    <p>Pokud zde nevidíte oranžové karty, problém je v datech nebo logice.</p>
</div>

</body>
</html>
<?php
