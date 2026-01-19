<?php
/**
 * Kontrola zakázky GREY M - proč se zobrazuje v lednu 2026?
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pouze pro admina");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola GREY M</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #000; color: #0f0; }
        h1, h2 { color: #39ff14; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #222; color: #39ff14; }
        .highlight { background: #ff4444; color: #fff; font-weight: bold; }
        pre { background: #111; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>KONTROLA ZAKÁZKY GREY M</h1>";

try {
    $pdo = getDbConnection();

    // Najít zakázku GREY M (obsahuje "GREY" v jménu zákazníka)
    echo "<h2>1. Vyhledání zakázky GREY M</h2>";

    $stmt = $pdo->prepare("
        SELECT
            r.reklamace_id,
            r.cislo,
            r.jmeno as zakaznik,
            r.stav,
            r.technik,
            r.assigned_to,
            r.dokonceno_kym,
            r.created_by,
            r.cena_celkem,
            r.created_at,
            r.updated_at,
            r.datum_dokonceni,
            YEAR(r.datum_dokonceni) as rok_dokonceni,
            MONTH(r.datum_dokonceni) as mesic_dokonceni,
            YEAR(r.created_at) as rok_vytvoreni,
            MONTH(r.created_at) as mesic_vytvoreni,
            YEAR(r.updated_at) as rok_updatu,
            MONTH(r.updated_at) as mesic_updatu,
            u.name as technik_jmeno,
            u.provize_procent,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON (r.dokonceno_kym = u.id OR (r.dokonceno_kym IS NULL AND r.assigned_to = u.id)) AND u.role = 'technik'
        WHERE r.jmeno LIKE '%GREY%'
        ORDER BY r.reklamace_id DESC
        LIMIT 5
    ");

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) === 0) {
        echo "<p class='highlight'>❌ Zakázka GREY M nebyla nalezena!</p>";
    } else {
        echo "<p>✅ Nalezeno zakázek: <strong>" . count($results) . "</strong></p>";

        foreach ($results as $r) {
            echo "<h3>Zakázka #{$r['cislo']} - {$r['zakaznik']}</h3>";
            echo "<table>";
            echo "<tr><th>Pole</th><th>Hodnota</th></tr>";

            foreach ($r as $key => $value) {
                $displayValue = $value ?? '<em>NULL</em>';

                // Zvýraznit klíčové datumy
                if (in_array($key, ['datum_dokonceni', 'created_at', 'updated_at', 'rok_dokonceni', 'mesic_dokonceni'])) {
                    echo "<tr class='highlight'><th>{$key}</th><td>{$displayValue}</td></tr>";
                } else {
                    echo "<tr><th>{$key}</th><td>{$displayValue}</td></tr>";
                }
            }
            echo "</table>";
        }
    }

    // Zkontrolovat co vráti dotaz pro statistiky (leden 2026)
    echo "<h2>2. Test dotazu STATISTIKY pro Milan Kolín (leden 2026)</h2>";

    $datumSloupec = 'COALESCE(r.datum_dokonceni, r.created_at)';
    $technikJoin = "LEFT JOIN wgs_users u ON (r.dokonceno_kym = u.id OR (r.dokonceno_kym IS NULL AND r.assigned_to = u.id)) AND u.role = 'technik'";

    $stmtStat = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno as zakaznik,
            COALESCE(u.name, r.technik, '-') as technik,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as cena,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize,
            r.datum_dokonceni,
            r.created_at,
            r.updated_at,
            DATE_FORMAT({$datumSloupec}, '%d.%m.%Y') as datum,
            {$datumSloupec} as datum_raw,
            r.stav,
            r.created_by
        FROM wgs_reklamace r
        {$technikJoin}
        WHERE COALESCE(u.name, r.technik) = 'Milan Kolín'
          AND YEAR({$datumSloupec}) = 2026
          AND MONTH({$datumSloupec}) = 1
          AND r.stav = 'done'
          AND (r.created_by IS NOT NULL AND r.created_by != '')
        ORDER BY r.cislo
    ");

    $stmtStat->execute();
    $reklamace = $stmtStat->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Počet zakázek v lednu 2026: <strong>" . count($reklamace) . "</strong></p>";

    if (count($reklamace) > 0) {
        echo "<table>";
        echo "<tr><th>Číslo</th><th>Zákazník</th><th>Cena</th><th>Provize</th><th>datum_dokonceni</th><th>created_at</th><th>Použitý datum</th></tr>";

        $celkemProvize = 0;
        foreach ($reklamace as $r) {
            $highlightClass = (strpos($r['zakaznik'], 'GREY') !== false) ? ' class="highlight"' : '';
            echo "<tr{$highlightClass}>";
            echo "<td>{$r['cislo']}</td>";
            echo "<td>{$r['zakaznik']}</td>";
            echo "<td>{$r['cena']} €</td>";
            echo "<td>{$r['provize']} €</td>";
            echo "<td>" . ($r['datum_dokonceni'] ?? '<em>NULL</em>') . "</td>";
            echo "<td>{$r['created_at']}</td>";
            echo "<td>{$r['datum_raw']}</td>";
            echo "</tr>";
            $celkemProvize += (float)$r['provize'];
        }
        echo "</table>";
        echo "<p><strong>CELKEM provize: {$celkemProvize} €</strong></p>";
    }

    // Zkontrolovat všechny zakázky Milana včetně prosince
    echo "<h2>3. Všechny zakázky Milan Kolín (prosinec 2025 + leden 2026)</h2>";

    $stmtAll = $pdo->prepare("
        SELECT
            r.cislo,
            r.jmeno as zakaznik,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as cena,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) as provize,
            r.datum_dokonceni,
            r.created_at,
            COALESCE(r.datum_dokonceni, r.created_at) as pouzity_datum,
            r.stav,
            r.created_by
        FROM wgs_reklamace r
        {$technikJoin}
        WHERE COALESCE(u.name, r.technik) = 'Milan Kolín'
          AND (
            (YEAR(COALESCE(r.datum_dokonceni, r.created_at)) = 2025 AND MONTH(COALESCE(r.datum_dokonceni, r.created_at)) = 12)
            OR
            (YEAR(COALESCE(r.datum_dokonceni, r.created_at)) = 2026 AND MONTH(COALESCE(r.datum_dokonceni, r.created_at)) = 1)
          )
          AND r.stav = 'done'
          AND (r.created_by IS NOT NULL AND r.created_by != '')
        ORDER BY pouzity_datum, r.cislo
    ");

    $stmtAll->execute();
    $allRecords = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Celkem zakázek (prosinec 2025 + leden 2026): <strong>" . count($allRecords) . "</strong></p>";

    if (count($allRecords) > 0) {
        echo "<table>";
        echo "<tr><th>Číslo</th><th>Zákazník</th><th>Cena</th><th>Provize</th><th>Použitý datum</th><th>Měsíc</th></tr>";

        $provizeProsinec = 0;
        $provizeLeden = 0;
        $pocetProsinec = 0;
        $pocetLeden = 0;

        foreach ($allRecords as $r) {
            $mesic = date('m', strtotime($r['pouzity_datum']));
            $rok = date('Y', strtotime($r['pouzity_datum']));
            $mesicText = ($mesic == '12' && $rok == '2025') ? 'PROSINEC 2025' : 'LEDEN 2026';

            $highlightClass = (strpos($r['zakaznik'], 'GREY') !== false) ? ' class="highlight"' : '';
            echo "<tr{$highlightClass}>";
            echo "<td>{$r['cislo']}</td>";
            echo "<td>{$r['zakaznik']}</td>";
            echo "<td>{$r['cena']} €</td>";
            echo "<td>{$r['provize']} €</td>";
            echo "<td>{$r['pouzity_datum']}</td>";
            echo "<td>{$mesicText}</td>";
            echo "</tr>";

            if ($mesic == '12' && $rok == '2025') {
                $provizeProsinec += (float)$r['provize'];
                $pocetProsinec++;
            } else {
                $provizeLeden += (float)$r['provize'];
                $pocetLeden++;
            }
        }
        echo "</table>";
        echo "<p><strong>PROSINEC 2025: {$pocetProsinec} zakázek = {$provizeProsinec} €</strong></p>";
        echo "<p><strong>LEDEN 2026: {$pocetLeden} zakázek = {$provizeLeden} €</strong></p>";
        echo "<p><strong>CELKEM: " . ($pocetProsinec + $pocetLeden) . " zakázek = " . ($provizeProsinec + $provizeLeden) . " €</strong></p>";
    }

} catch (Exception $e) {
    echo "<p class='highlight'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
