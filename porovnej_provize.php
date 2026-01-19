<?php
/**
 * Porovnání provizí - statistiky vs tech_provize_api
 * Pro Milan Kolín - najde přesný rozdíl
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pouze pro admina");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Porovnání provizí</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #000; color: #0f0; }
        h1, h2 { color: #39ff14; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #222; color: #39ff14; }
        .diff { background: #ff4444; color: #fff; font-weight: bold; }
    </style>
</head>
<body>
<h1>POROVNÁNÍ PROVIZÍ - Milan Kolín</h1>";

try {
    $pdo = getDbConnection();

    // Aktuální měsíc
    $rok = date('Y');
    $mesic = date('m');

    echo "<h2>Měsíc: {$mesic}/{$rok}</h2>";

    // === 1. STATISTIKY DOTAZ ===
    echo "<h2>1. STATISTIKY - REKLAMACE (s created_by)</h2>";

    $hasDokoncenokym = true;
    $hasDatumDokonceni = true;
    $datumSloupec = 'COALESCE(r.datum_dokonceni, r.created_at)';
    $technikJoinChart = "LEFT JOIN wgs_users u ON (r.dokonceno_kym = u.id OR (r.dokonceno_kym IS NULL AND r.assigned_to = u.id)) AND u.role = 'technik'";

    $stmtStat = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno as zakaznik,
            COALESCE(u.name, r.technik, '-') as technik,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as cena,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize,
            DATE_FORMAT({$datumSloupec}, '%d.%m.%Y') as datum,
            r.stav
        FROM wgs_reklamace r
        {$technikJoinChart}
        WHERE COALESCE(u.name, r.technik) = 'Milan Kolín'
          AND YEAR({$datumSloupec}) = :rok
          AND MONTH({$datumSloupec}) = :mesic
          AND r.stav = 'done'
          AND (r.created_by IS NOT NULL AND r.created_by != '')
        ORDER BY r.cislo
    ");

    $stmtStat->execute(['rok' => $rok, 'mesic' => $mesic]);
    $reklamaceStat = $stmtStat->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Počet zakázek: <strong>" . count($reklamaceStat) . "</strong></p>";

    if (count($reklamaceStat) > 0) {
        echo "<table><tr><th>Číslo</th><th>Zákazník</th><th>Technik</th><th>Cena</th><th>Provize</th><th>Datum</th><th>Stav</th></tr>";
        $celkemProvize = 0;
        foreach ($reklamaceStat as $r) {
            echo "<tr>";
            echo "<td>{$r['cislo']}</td>";
            echo "<td>{$r['zakaznik']}</td>";
            echo "<td>{$r['technik']}</td>";
            echo "<td>{$r['cena']} €</td>";
            echo "<td>{$r['provize']} €</td>";
            echo "<td>{$r['datum']}</td>";
            echo "<td>{$r['stav']}</td>";
            echo "</tr>";
            $celkemProvize += (float)$r['provize'];
        }
        echo "</table>";
        echo "<p><strong>CELKEM provize (STATISTIKY): {$celkemProvize} €</strong></p>";
    }

    // === 2. TECH_PROVIZE_API DOTAZ ===
    echo "<h2>2. TECH_PROVIZE_API - REKLAMACE (s created_by)</h2>";

    $stmtApi = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno as zakaznik,
            COALESCE(u.name, r.technik, '-') as technik,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as cena,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize,
            DATE_FORMAT({$datumSloupec}, '%d.%m.%Y') as datum,
            r.stav
        FROM wgs_reklamace r
        {$technikJoinChart}
        WHERE COALESCE(u.name, r.technik) = 'Milan Kolín'
          AND YEAR({$datumSloupec}) = :rok
          AND MONTH({$datumSloupec}) = :mesic
          AND r.stav = 'done'
          AND (r.created_by IS NOT NULL AND r.created_by != '')
        ORDER BY r.cislo
    ");

    $stmtApi->execute(['rok' => $rok, 'mesic' => $mesic]);
    $reklamaceApi = $stmtApi->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Počet zakázek: <strong>" . count($reklamaceApi) . "</strong></p>";

    if (count($reklamaceApi) > 0) {
        echo "<table><tr><th>Číslo</th><th>Zákazník</th><th>Technik</th><th>Cena</th><th>Provize</th><th>Datum</th><th>Stav</th></tr>";
        $celkemProvizeApi = 0;
        foreach ($reklamaceApi as $r) {
            echo "<tr>";
            echo "<td>{$r['cislo']}</td>";
            echo "<td>{$r['zakaznik']}</td>";
            echo "<td>{$r['technik']}</td>";
            echo "<td>{$r['cena']} €</td>";
            echo "<td>{$r['provize']} €</td>";
            echo "<td>{$r['datum']}</td>";
            echo "<td>{$r['stav']}</td>";
            echo "</tr>";
            $celkemProvizeApi += (float)$r['provize'];
        }
        echo "</table>";
        echo "<p><strong>CELKEM provize (API): {$celkemProvizeApi} €</strong></p>";
    }

    // === 3. POROVNÁNÍ ===
    echo "<h2>3. VÝSLEDEK POROVNÁNÍ</h2>";

    $cislaStat = array_column($reklamaceStat, 'cislo');
    $cislaApi = array_column($reklamaceApi, 'cislo');

    $rozdil = array_diff($cislaStat, $cislaApi);
    $navic = array_diff($cislaApi, $cislaStat);

    if (count($rozdil) > 0) {
        echo "<p class='diff'>Zakázky v STATISTIKÁCH, ale NE v API: " . implode(', ', $rozdil) . "</p>";
    }

    if (count($navic) > 0) {
        echo "<p class='diff'>Zakázky v API, ale NE ve STATISTIKÁCH: " . implode(', ', $navic) . "</p>";
    }

    if (count($rozdil) === 0 && count($navic) === 0) {
        echo "<p style='color: #39ff14;'>✅ Oba dotazy vrací STEJNÉ zakázky!</p>";

        if (abs($celkemProvize - $celkemProvizeApi) < 0.01) {
            echo "<p style='color: #39ff14;'>✅ Provize se SHODUJÍ!</p>";
        } else {
            echo "<p class='diff'>❌ Provize se NESHODUJÍ: STATISTIKY={$celkemProvize} €, API={$celkemProvizeApi} €</p>";
        }
    }

} catch (Exception $e) {
    echo "<p class='diff'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
