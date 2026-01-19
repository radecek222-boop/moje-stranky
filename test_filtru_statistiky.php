<?php
/**
 * Test různých kombinací filtrů pro Milan Kolín
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pouze pro admina");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test filtrů statistik</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #000; color: #0f0; }
        h1, h2, h3 { color: #39ff14; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #222; color: #39ff14; }
        .highlight { background: #ff4444; color: #fff; font-weight: bold; }
        .info { background: #333; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
<h1>TEST FILTRŮ STATISTIK - Milan Kolín</h1>";

try {
    $pdo = getDbConnection();

    // Definovat datum sloupec
    $datumSloupec = 'COALESCE(r.datum_dokonceni, r.created_at)';
    $technikJoin = "LEFT JOIN wgs_users u ON (r.dokonceno_kym = u.id OR (r.dokonceno_kym IS NULL AND r.assigned_to = u.id)) AND u.role = 'technik'";

    // === TEST 1: Rok=2026, Měsíc=1 (co používá API) ===
    echo "<h2>TEST 1: Rok=2026, Měsíc=1 (API hamburger menu)</h2>";
    $stmt1 = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno as zakaznik,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as cena,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize,
            r.datum_dokonceni,
            r.created_at,
            {$datumSloupec} as pouzity_datum
        FROM wgs_reklamace r
        {$technikJoin}
        WHERE COALESCE(u.name, r.technik) = 'Milan Kolín'
          AND YEAR({$datumSloupec}) = 2026
          AND MONTH({$datumSloupec}) = 1
          AND r.stav = 'done'
          AND (r.created_by IS NOT NULL AND r.created_by != '')
        ORDER BY pouzity_datum
    ");
    $stmt1->execute();
    $results1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>Počet: <strong>" . count($results1) . " zakázek</strong></div>";
    if (count($results1) > 0) {
        echo "<table><tr><th>Číslo</th><th>Zákazník</th><th>Cena</th><th>Provize</th><th>Datum</th></tr>";
        $celkem = 0;
        foreach ($results1 as $r) {
            $highlightClass = (strpos($r['zakaznik'], 'Pelikán') !== false || strpos($r['cislo'], 'GREY') !== false) ? ' class="highlight"' : '';
            echo "<tr{$highlightClass}><td>{$r['cislo']}</td><td>{$r['zakaznik']}</td><td>{$r['cena']} €</td><td>{$r['provize']} €</td><td>{$r['pouzity_datum']}</td></tr>";
            $celkem += (float)$r['provize'];
        }
        echo "</table>";
        echo "<div class='info'><strong>CELKEM provize: {$celkem} €</strong></div>";
    }

    // === TEST 2: Rok=prázdné, Měsíc=1 (pouze leden ze všech let) ===
    echo "<h2>TEST 2: Rok=prázdné, Měsíc=1 (leden ze VŠECH let)</h2>";
    $stmt2 = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno as zakaznik,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as cena,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize,
            r.datum_dokonceni,
            r.created_at,
            {$datumSloupec} as pouzity_datum,
            YEAR({$datumSloupec}) as rok
        FROM wgs_reklamace r
        {$technikJoin}
        WHERE COALESCE(u.name, r.technik) = 'Milan Kolín'
          AND MONTH({$datumSloupec}) = 1
          AND r.stav = 'done'
          AND (r.created_by IS NOT NULL AND r.created_by != '')
        ORDER BY pouzity_datum DESC
    ");
    $stmt2->execute();
    $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>Počet: <strong>" . count($results2) . " zakázek</strong></div>";
    if (count($results2) > 0) {
        echo "<table><tr><th>Rok</th><th>Číslo</th><th>Zákazník</th><th>Cena</th><th>Provize</th><th>Datum</th></tr>";
        $celkem = 0;
        foreach ($results2 as $r) {
            $highlightClass = (strpos($r['zakaznik'], 'Pelikán') !== false || strpos($r['cislo'], 'GREY') !== false) ? ' class="highlight"' : '';
            echo "<tr{$highlightClass}><td>{$r['rok']}</td><td>{$r['cislo']}</td><td>{$r['zakaznik']}</td><td>{$r['cena']} €</td><td>{$r['provize']} €</td><td>{$r['pouzity_datum']}</td></tr>";
            $celkem += (float)$r['provize'];
        }
        echo "</table>";
        echo "<div class='info'><strong>CELKEM provize: {$celkem} €</strong></div>";
    }

    // === TEST 3: Rok=prázdné, Měsíc=prázdné (všechny zakázky) ===
    echo "<h2>TEST 3: Rok=prázdné, Měsíc=prázdné (VŠECHNY zakázky Milan Kolín)</h2>";
    $stmt3 = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno as zakaznik,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as cena,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize,
            r.datum_dokonceni,
            r.created_at,
            {$datumSloupec} as pouzity_datum,
            YEAR({$datumSloupec}) as rok,
            MONTH({$datumSloupec}) as mesic
        FROM wgs_reklamace r
        {$technikJoin}
        WHERE COALESCE(u.name, r.technik) = 'Milan Kolín'
          AND r.stav = 'done'
          AND (r.created_by IS NOT NULL AND r.created_by != '')
        ORDER BY pouzity_datum DESC
        LIMIT 20
    ");
    $stmt3->execute();
    $results3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>Počet: <strong>" . count($results3) . " zakázek</strong> (limit 20, seřazeno od nejnovější)</div>";
    if (count($results3) > 0) {
        echo "<table><tr><th>Rok</th><th>Měsíc</th><th>Číslo</th><th>Zákazník</th><th>Cena</th><th>Provize</th><th>Datum</th></tr>";
        $celkem = 0;
        foreach ($results3 as $r) {
            $highlightClass = (strpos($r['zakaznik'], 'Pelikán') !== false || strpos($r['cislo'], 'GREY') !== false) ? ' class="highlight"' : '';
            echo "<tr{$highlightClass}><td>{$r['rok']}</td><td>{$r['mesic']}</td><td>{$r['cislo']}</td><td>{$r['zakaznik']}</td><td>{$r['cena']} €</td><td>{$r['provize']} €</td><td>{$r['pouzity_datum']}</td></tr>";
            $celkem += (float)$r['provize'];
        }
        echo "</table>";
        echo "<div class='info'><strong>CELKEM provize (20 nejnovějších): {$celkem} €</strong></div>";
    }

    // === TEST 4: Speciálně GREY M ===
    echo "<h2>TEST 4: Detail GREY M / Pelikán Martin</h2>";
    $stmt4 = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno as zakaznik,
            r.stav,
            r.created_by,
            r.technik as technik_text,
            r.assigned_to,
            r.dokonceno_kym,
            u.name as technik_db,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as cena,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize,
            r.datum_dokonceni,
            r.created_at,
            r.updated_at,
            {$datumSloupec} as pouzity_datum,
            YEAR({$datumSloupec}) as rok,
            MONTH({$datumSloupec}) as mesic,
            DAY({$datumSloupec}) as den
        FROM wgs_reklamace r
        {$technikJoin}
        WHERE (r.cislo LIKE '%GREY%' OR r.jmeno LIKE '%Pelikán%')
        LIMIT 5
    ");
    $stmt4->execute();
    $results4 = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    if (count($results4) > 0) {
        foreach ($results4 as $r) {
            echo "<div class='info highlight'>";
            echo "<h3>Zakázka: {$r['cislo']} - {$r['zakaznik']}</h3>";
            echo "<table>";
            foreach ($r as $key => $value) {
                $displayValue = $value ?? '<em>NULL</em>';
                echo "<tr><th>{$key}</th><td>{$displayValue}</td></tr>";
            }
            echo "</table>";
            echo "</div>";
        }
    } else {
        echo "<div class='info'>GREY M nebyla nalezena</div>";
    }

} catch (Exception $e) {
    echo "<div class='highlight'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
