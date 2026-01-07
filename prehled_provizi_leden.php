<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pouze admin");
}

header('Content-Type: text/html; charset=utf-8');

$pdo = getDbConnection();

$rok = $_GET['rok'] ?? date('Y');
$mesic = $_GET['mesic'] ?? date('m');

$mesiceCS = [
    '01' => 'Leden', '02' => 'Únor', '03' => 'Březen', '04' => 'Duben',
    '05' => 'Květen', '06' => 'Červen', '07' => 'Červenec', '08' => 'Srpen',
    '09' => 'Září', '10' => 'Říjen', '11' => 'Listopad', '12' => 'Prosinec'
];

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Přehled provizí - {$mesiceCS[$mesic]} {$rok}</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #333; color: #fff; }
        tr:hover { background: #f9f9f9; }
        .total { background: #f0f0f0; font-weight: bold; }
        .technik-box { background: #fafafa; border: 2px solid #333; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .technik-name { font-size: 1.3em; font-weight: bold; margin-bottom: 15px; }
        .technik-total { font-size: 1.5em; color: #333; }
        .zero { color: #999; }
        .nav { margin-bottom: 20px; }
        .nav a { padding: 8px 16px; background: #333; color: #fff; text-decoration: none; margin-right: 5px; border-radius: 4px; }
        .nav a.active { background: #666; }
        .summary { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap; }
        .summary-box { background: #333; color: #fff; padding: 20px; border-radius: 8px; min-width: 200px; }
        .summary-value { font-size: 2em; font-weight: bold; }
        .summary-label { opacity: 0.8; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Přehled provizí - {$mesiceCS[$mesic]} {$rok}</h1>";

// Navigace měsíců
echo "<div class='nav'>";
for ($m = 1; $m <= 12; $m++) {
    $mStr = str_pad($m, 2, '0', STR_PAD_LEFT);
    $active = ($mStr == $mesic) ? 'active' : '';
    echo "<a href='?rok={$rok}&mesic={$mStr}' class='{$active}'>{$mesiceCS[$mStr]}</a>";
}
echo "</div>";

// Zjistit sloupce
$stmtCol1 = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'dokonceno_kym'");
$hasDokoncenokym = $stmtCol1->rowCount() > 0;

$stmtCol2 = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'datum_dokonceni'");
$hasDatumDokonceni = $stmtCol2->rowCount() > 0;

$datumSloupec = $hasDatumDokonceni ? 'COALESCE(r.datum_dokonceni, r.updated_at)' : 'r.updated_at';

// Načíst všechny techniky
$stmt = $pdo->query("SELECT id, name, user_id FROM wgs_users WHERE role = 'technik' ORDER BY name");
$technici = $stmt->fetchAll(PDO::FETCH_ASSOC);

$celkemVsechny = 0;
$celkemProvize = 0;

foreach ($technici as $technik) {
    $technikId = $technik['id'];
    $technikName = $technik['name'];

    // Podmínka podle dokonceno_kym nebo fallback
    if ($hasDokoncenokym) {
        $whereCondition = "(r.dokonceno_kym = :technik_id OR (r.dokonceno_kym IS NULL AND r.assigned_to = :technik_id2))";
        $params = [
            'technik_id' => $technikId,
            'technik_id2' => $technikId,
            'rok' => $rok,
            'mesic' => $mesic
        ];
    } else {
        $whereCondition = "r.assigned_to = :technik_id";
        $params = [
            'technik_id' => $technikId,
            'rok' => $rok,
            'mesic' => $mesic
        ];
    }

    // Zakázky technika v daném měsíci
    $stmt = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno,
            r.model,
            COALESCE(r.cena_celkem, r.cena, 0) as cena,
            COALESCE(r.cena_celkem, r.cena, 0) * 0.33 as provize,
            {$datumSloupec} as datum_dokonceni,
            r.dokonceno_kym,
            r.assigned_to
        FROM wgs_reklamace r
        WHERE {$whereCondition}
          AND YEAR({$datumSloupec}) = :rok
          AND MONTH({$datumSloupec}) = :mesic
          AND r.stav = 'done'
        ORDER BY {$datumSloupec} DESC
    ");
    $stmt->execute($params);
    $zakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $technikCelkem = 0;
    $technikProvize = 0;

    foreach ($zakazky as $z) {
        $technikCelkem += (float)$z['cena'];
        $technikProvize += (float)$z['provize'];
    }

    $celkemVsechny += $technikCelkem;
    $celkemProvize += $technikProvize;

    // Box technika
    $zeroClass = ($technikProvize == 0) ? 'zero' : '';
    echo "<div class='technik-box'>";
    echo "<div class='technik-name'>{$technikName}</div>";
    echo "<div class='technik-total {$zeroClass}'>Provize: " . number_format($technikProvize, 2, ',', ' ') . " € <small>(z " . number_format($technikCelkem, 2, ',', ' ') . " €)</small></div>";

    if (count($zakazky) > 0) {
        echo "<table>";
        echo "<tr><th>Číslo</th><th>Zákazník</th><th>Model</th><th>Cena</th><th>Provize (33%)</th><th>Dokončeno</th></tr>";
        foreach ($zakazky as $z) {
            echo "<tr>";
            echo "<td>{$z['cislo']}</td>";
            echo "<td>" . htmlspecialchars($z['jmeno']) . "</td>";
            echo "<td>" . htmlspecialchars($z['model'] ?? '-') . "</td>";
            echo "<td>" . number_format((float)$z['cena'], 2, ',', ' ') . " €</td>";
            echo "<td><strong>" . number_format((float)$z['provize'], 2, ',', ' ') . " €</strong></td>";
            echo "<td>" . date('d.m.Y H:i', strtotime($z['datum_dokonceni'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='zero'>Žádné dokončené zakázky v tomto měsíci.</p>";
    }

    echo "</div>";
}

// Souhrn
echo "<div class='summary'>";
echo "<div class='summary-box'>";
echo "<div class='summary-value'>" . number_format($celkemVsechny, 2, ',', ' ') . " €</div>";
echo "<div class='summary-label'>Celkem tržby</div>";
echo "</div>";
echo "<div class='summary-box'>";
echo "<div class='summary-value'>" . number_format($celkemProvize, 2, ',', ' ') . " €</div>";
echo "<div class='summary-label'>Celkem provize</div>";
echo "</div>";
echo "</div>";

echo "<p><a href='admin.php'>← Zpět do Admin</a></p>";

echo "</div></body></html>";
?>
