<?php
/**
 * Oprava datum_dokonceni pro prosincové zakázky
 * Tyto zakázky byly dokončeny v prosinci, ale mají špatné datum_dokonceni
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava datum_dokonceni - prosinec</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Oprava datum_dokonceni - prosincove zakazky</h1>";

    // Najít prosincové zakázky podle čísla nebo termin_datum
    $sql = "
        SELECT
            r.id,
            r.cislo,
            r.jmeno,
            r.adresa,
            r.stav,
            r.termin_datum,
            r.created_at,
            r.updated_at,
            r.datum_dokonceni,
            r.dokonceno_kym,
            u.name as technik_jmeno
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.dokonceno_kym = u.id
        WHERE r.stav = 'done'
        AND (
            r.cislo LIKE '%2025%12%'
            OR r.cislo LIKE 'POZ/2025/17-12%'
            OR r.cislo = '3039'
            OR r.termin_datum LIKE '2025-12%'
            OR (r.created_at >= '2025-12-01' AND r.created_at < '2026-01-01')
        )
        ORDER BY r.created_at DESC
    ";

    $stmt = $pdo->query($sql);
    $zakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Nalezene zakazky (potencialne prosincove):</h2>";
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Cislo</th>
            <th>Jmeno</th>
            <th>Termin</th>
            <th>Vytvoreno</th>
            <th>datum_dokonceni</th>
            <th>Dokoncil</th>
            <th>Stav</th>
        </tr>";

    foreach ($zakazky as $z) {
        $datumDokonceniClass = '';
        if ($z['datum_dokonceni'] && strpos($z['datum_dokonceni'], '2026-01') === 0) {
            $datumDokonceniClass = 'style="background: #f8d7da;"'; // Červené - špatné datum
        }

        echo "<tr>
            <td>{$z['id']}</td>
            <td>{$z['cislo']}</td>
            <td>{$z['jmeno']}</td>
            <td>{$z['termin_datum']}</td>
            <td>{$z['created_at']}</td>
            <td {$datumDokonceniClass}>{$z['datum_dokonceni']}</td>
            <td>{$z['technik_jmeno']}</td>
            <td>{$z['stav']}</td>
        </tr>";
    }
    echo "</table>";

    // Najít všechny HOTOVO zakázky s lednovým datum_dokonceni ale prosincovým termínem
    echo "<h2>Zakazky k oprave (datum_dokonceni v lednu, ale termin/vytvoreni v prosinci):</h2>";

    $sqlOpravit = "
        SELECT
            r.id,
            r.cislo,
            r.jmeno,
            r.termin_datum,
            r.created_at,
            r.datum_dokonceni
        FROM wgs_reklamace r
        WHERE r.stav = 'done'
        AND r.datum_dokonceni >= '2026-01-01'
        AND (
            r.termin_datum < '2026-01-01'
            OR r.created_at < '2026-01-01'
        )
    ";

    $stmtOpravit = $pdo->query($sqlOpravit);
    $kOprave = $stmtOpravit->fetchAll(PDO::FETCH_ASSOC);

    if (empty($kOprave)) {
        echo "<div class='success'>Zadne zakazky k oprave.</div>";
    } else {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Cislo</th>
                <th>Jmeno</th>
                <th>Termin</th>
                <th>Vytvoreno</th>
                <th>datum_dokonceni (spatne)</th>
                <th>Nove datum_dokonceni</th>
            </tr>";

        foreach ($kOprave as $z) {
            // Určit správné datum dokončení - použít termin_datum nebo den po termínu
            $spravneDatum = $z['termin_datum'] ?: $z['created_at'];
            if ($spravneDatum) {
                $spravneDatum = date('Y-m-d 17:00:00', strtotime($spravneDatum));
            }

            echo "<tr>
                <td>{$z['id']}</td>
                <td>{$z['cislo']}</td>
                <td>{$z['jmeno']}</td>
                <td>{$z['termin_datum']}</td>
                <td>{$z['created_at']}</td>
                <td style='background: #f8d7da;'>{$z['datum_dokonceni']}</td>
                <td style='background: #d4edda;'>{$spravneDatum}</td>
            </tr>";
        }
        echo "</table>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='warning'><strong>PROVADIM OPRAVU...</strong></div>";

            $opraveno = 0;
            foreach ($kOprave as $z) {
                $spravneDatum = $z['termin_datum'] ?: $z['created_at'];
                if ($spravneDatum) {
                    $spravneDatum = date('Y-m-d 17:00:00', strtotime($spravneDatum));

                    $stmtUpdate = $pdo->prepare("UPDATE wgs_reklamace SET datum_dokonceni = :datum WHERE id = :id");
                    $stmtUpdate->execute([':datum' => $spravneDatum, ':id' => $z['id']]);
                    $opraveno++;

                    echo "<div class='success'>Opraveno: {$z['cislo']} - datum_dokonceni = {$spravneDatum}</div>";
                }
            }

            echo "<div class='success'><strong>OPRAVENO {$opraveno} ZAKAZEK</strong></div>";
        } else {
            echo "<a href='?execute=1' class='btn btn-danger'>OPRAVIT DATUM_DOKONCENI</a>";
        }
    }

    echo "<br><a href='/statistiky.php' class='btn'>Zpet na statistiky</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
