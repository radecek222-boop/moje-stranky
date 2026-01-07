<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pouze admin");
}

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Provize Radek - debug</h1>";

$pdo = getDbConnection();

// Najít Radka
$stmt = $pdo->query("SELECT id, name, user_id FROM wgs_users WHERE name LIKE '%Radek%' OR user_id LIKE '%radek%'");
$radek = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<p><strong>Uživatel:</strong> " . ($radek ? htmlspecialchars($radek['name']) . " (ID: {$radek['id']}, user_id: {$radek['user_id']})" : "Nenalezen") . "</p>";

$aktualniRok = date('Y');
$aktualniMesic = date('m');
$mesicNazev = ['01'=>'leden','02'=>'únor','03'=>'březen','04'=>'duben','05'=>'květen','06'=>'červen','07'=>'červenec','08'=>'srpen','09'=>'září','10'=>'říjen','11'=>'listopad','12'=>'prosinec'][$aktualniMesic];

echo "<h2>Hotové zakázky v {$mesicNazev} {$aktualniRok}</h2>";

// Zjistit zda existuje datum_dokonceni
$stmtCol = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'datum_dokonceni'");
$hasDatumDokonceni = $stmtCol->rowCount() > 0;
$datumSloupec = $hasDatumDokonceni ? 'COALESCE(datum_dokonceni, updated_at)' : 'updated_at';

echo "<p><em>Používám sloupec: {$datumSloupec}</em></p>";

if ($radek) {
    $stmt = $pdo->prepare("
        SELECT
            cislo,
            jmeno,
            technik,
            assigned_to,
            stav,
            COALESCE(cena_celkem, cena, 0) as cena,
            COALESCE(cena_celkem, cena, 0) * 0.33 as provize,
            {$datumSloupec} as datum_dokonceni_calc,
            updated_at
        FROM wgs_reklamace
        WHERE (assigned_to = :id OR technik LIKE :name)
          AND YEAR({$datumSloupec}) = :rok
          AND MONTH({$datumSloupec}) = :mesic
          AND stav = 'done'
        ORDER BY {$datumSloupec} DESC
    ");
    $stmt->execute([
        'id' => $radek['id'],
        'name' => '%' . $radek['name'] . '%',
        'rok' => $aktualniRok,
        'mesic' => $aktualniMesic
    ]);
    $zakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr style='background:#333;color:#fff;'><th>Číslo</th><th>Zákazník</th><th>Technik</th><th>assigned_to</th><th>Cena</th><th>Provize (33%)</th><th>Dokončeno</th></tr>";

    $celkemProvize = 0;
    foreach ($zakazky as $z) {
        $celkemProvize += (float)$z['provize'];
        $highlightStyle = abs((float)$z['provize'] - 36.30) < 0.1 ? 'background:#ffff00;font-weight:bold;' : '';
        echo "<tr style='{$highlightStyle}'>";
        echo "<td>{$z['cislo']}</td>";
        echo "<td>" . htmlspecialchars($z['jmeno']) . "</td>";
        echo "<td>" . htmlspecialchars($z['technik']) . "</td>";
        echo "<td>{$z['assigned_to']}</td>";
        echo "<td>" . number_format((float)$z['cena'], 2) . " €</td>";
        echo "<td>" . number_format((float)$z['provize'], 2) . " €</td>";
        echo "<td>{$z['datum_dokonceni_calc']}</td>";
        echo "</tr>";
    }
    echo "<tr style='background:#eee;font-weight:bold;'><td colspan='5'>CELKEM</td><td>" . number_format($celkemProvize, 2) . " €</td><td></td></tr>";
    echo "</table>";

    echo "<p>Počet zakázek: " . count($zakazky) . "</p>";
} else {
    echo "<p>Radek nebyl nalezen v systému.</p>";
}
?>
