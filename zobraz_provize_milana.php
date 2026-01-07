<?php
/**
 * Zobrazení provizí technika Milana Kolína
 *
 * Provize = 33% z celkové ceny zakázek přiřazených technikovi
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může zobrazit provize.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Provize technika Milan Kolín | WGS</title>
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap' rel='stylesheet'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #fff; color: #000; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; border: 2px solid #000; }
        .header { background: #000; color: #fff; padding: 2rem; }
        h1 { font-size: 1.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }
        .content { padding: 2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
        .stat-box { border: 2px solid #000; padding: 1.5rem; text-align: center; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #000; margin-bottom: 0.5rem; }
        .stat-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #555; }
        table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; font-size: 0.85rem; border: 2px solid #000; }
        th, td { padding: 0.75rem; text-align: left; border: 1px solid #ddd; }
        th { background: #000; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; }
        tr:hover { background: #f5f5f5; }
        .section { margin: 2rem 0; padding: 1.5rem; border: 2px solid #ddd; }
        h2 { font-size: 1.1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #000; }
        .highlight { background: #f5f5f5; padding: 1rem; border: 1px solid #ddd; margin: 1rem 0; }
        .footer { margin-top: 2rem; padding: 1.5rem 2rem; border-top: 2px solid #ddd; text-align: center; color: #555; font-size: 0.85rem; }
        .footer a { color: #000; text-decoration: none; border-bottom: 2px solid #000; }
        .mesic-tab { display: inline-block; padding: 0.5rem 1rem; margin: 0.25rem; border: 2px solid #ddd; cursor: pointer; }
        .mesic-tab.active { background: #000; color: #fff; border-color: #000; }
    </style>
</head>
<body>
<div class='container'>
    <div class='header'>
        <h1>Provize technika: Milan Kolín</h1>
        <p style='margin-top: 0.5rem; opacity: 0.9;'>Sazba provize: 33% z celkové ceny zakázky</p>
    </div>
    <div class='content'>";

try {
    $pdo = getDbConnection();

    // Získat ID Milana z tabulky users
    $stmtMilan = $pdo->prepare("
        SELECT id, name, email, user_id
        FROM wgs_users
        WHERE name LIKE '%Milan%' AND role = 'technik'
        LIMIT 1
    ");
    $stmtMilan->execute();
    $milan = $stmtMilan->fetch(PDO::FETCH_ASSOC);

    if (!$milan) {
        // Zkusit najít v textovém poli technik
        echo "<div class='highlight'><strong>POZOR:</strong> Milan není registrován jako technik v systému uživatelů. Hledám v textovém poli 'technik'...</div>";

        // Hledat podle textového pole
        $stmtTextTechnik = $pdo->prepare("
            SELECT
                COUNT(*) as pocet_zakazek,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as celkem_castka,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) * 0.33 as provize_celkem
            FROM wgs_reklamace
            WHERE technik LIKE '%Milan%'
        ");
        $stmtTextTechnik->execute();
        $statsCelkem = $stmtTextTechnik->fetch(PDO::FETCH_ASSOC);

    } else {
        echo "<div class='highlight'>";
        echo "<strong>Technik nalezen:</strong><br>";
        echo "ID: " . htmlspecialchars($milan['id']) . "<br>";
        echo "Jméno: " . htmlspecialchars($milan['name']) . "<br>";
        echo "Email: " . htmlspecialchars($milan['email'] ?? '-') . "<br>";
        echo "User ID: " . htmlspecialchars($milan['user_id'] ?? '-');
        echo "</div>";

        $milanId = $milan['id'];
        $milanName = $milan['name'];
    }

    // ========================================
    // CELKOVÉ STATISTIKY
    // ========================================
    echo "<h2>Celkové statistiky (všechny roky)</h2>";

    if (isset($milanId)) {
        // Hledat podle assigned_to NEBO textového pole technik
        $stmtCelkem = $pdo->prepare("
            SELECT
                COUNT(*) as pocet_zakazek,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as celkem_castka,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) * 0.33 as provize_celkem,
                SUM(CASE WHEN stav = 'done' THEN CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2)) * 0.33 ELSE 0 END) as provize_hotovo
            FROM wgs_reklamace
            WHERE assigned_to = :id OR technik LIKE :name
        ");
        $stmtCelkem->execute([
            'id' => $milanId,
            'name' => '%' . $milanName . '%'
        ]);
        $statsCelkem = $stmtCelkem->fetch(PDO::FETCH_ASSOC);
    } else {
        // Fallback - pouze textové pole
        $stmtCelkem = $pdo->prepare("
            SELECT
                COUNT(*) as pocet_zakazek,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as celkem_castka,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) * 0.33 as provize_celkem,
                SUM(CASE WHEN stav = 'done' THEN CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2)) * 0.33 ELSE 0 END) as provize_hotovo
            FROM wgs_reklamace
            WHERE technik LIKE '%Milan%'
        ");
        $stmtCelkem->execute();
        $statsCelkem = $stmtCelkem->fetch(PDO::FETCH_ASSOC);
    }

    $pocetZakazek = (int)($statsCelkem['pocet_zakazek'] ?? 0);
    $celkemCastka = (float)($statsCelkem['celkem_castka'] ?? 0);
    $provizeCelkem = (float)($statsCelkem['provize_celkem'] ?? 0);
    $provizeHotovo = (float)($statsCelkem['provize_hotovo'] ?? 0);

    echo "<div class='stats-grid'>
        <div class='stat-box'>
            <div class='stat-value'>{$pocetZakazek}</div>
            <div class='stat-label'>Celkem zakázek</div>
        </div>
        <div class='stat-box'>
            <div class='stat-value'>" . number_format($celkemCastka, 2, ',', ' ') . " €</div>
            <div class='stat-label'>Obrat celkem</div>
        </div>
        <div class='stat-box'>
            <div class='stat-value'>" . number_format($provizeCelkem, 2, ',', ' ') . " €</div>
            <div class='stat-label'>Provize celkem (33%)</div>
        </div>
        <div class='stat-box'>
            <div class='stat-value'>" . number_format($provizeHotovo, 2, ',', ' ') . " €</div>
            <div class='stat-label'>Provize (hotové zakázky)</div>
        </div>
    </div>";

    // ========================================
    // STATISTIKY PO MĚSÍCÍCH - AKTUÁLNÍ ROK
    // ========================================
    $aktualniRok = date('Y');
    echo "<h2>Provize po měsících - rok {$aktualniRok}</h2>";

    $mesice = [
        1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
        5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
        9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec'
    ];

    if (isset($milanId)) {
        $stmtMesice = $pdo->prepare("
            SELECT
                MONTH(created_at) as mesic,
                COUNT(*) as pocet,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as obrat,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) * 0.33 as provize,
                SUM(CASE WHEN stav = 'done' THEN 1 ELSE 0 END) as hotovo
            FROM wgs_reklamace
            WHERE (assigned_to = :id OR technik LIKE :name)
              AND YEAR(created_at) = :rok
            GROUP BY MONTH(created_at)
            ORDER BY MONTH(created_at)
        ");
        $stmtMesice->execute([
            'id' => $milanId,
            'name' => '%' . $milanName . '%',
            'rok' => $aktualniRok
        ]);
    } else {
        $stmtMesice = $pdo->prepare("
            SELECT
                MONTH(created_at) as mesic,
                COUNT(*) as pocet,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as obrat,
                SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) * 0.33 as provize,
                SUM(CASE WHEN stav = 'done' THEN 1 ELSE 0 END) as hotovo
            FROM wgs_reklamace
            WHERE technik LIKE '%Milan%'
              AND YEAR(created_at) = :rok
            GROUP BY MONTH(created_at)
            ORDER BY MONTH(created_at)
        ");
        $stmtMesice->execute(['rok' => $aktualniRok]);
    }

    $dataMesice = $stmtMesice->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>
        <thead>
            <tr>
                <th>Měsíc</th>
                <th>Zakázek</th>
                <th>Hotovo</th>
                <th>Obrat</th>
                <th>Provize (33%)</th>
            </tr>
        </thead>
        <tbody>";

    $celkemRok = 0;
    $celkemProvizeRok = 0;

    foreach ($dataMesice as $row) {
        $mesicCislo = (int)$row['mesic'];
        $mesicNazev = $mesice[$mesicCislo] ?? $mesicCislo;
        $obrat = (float)$row['obrat'];
        $provize = (float)$row['provize'];
        $celkemRok += $obrat;
        $celkemProvizeRok += $provize;

        echo "<tr>
            <td><strong>{$mesicNazev}</strong></td>
            <td>{$row['pocet']}</td>
            <td>{$row['hotovo']}</td>
            <td>" . number_format($obrat, 2, ',', ' ') . " €</td>
            <td><strong>" . number_format($provize, 2, ',', ' ') . " €</strong></td>
        </tr>";
    }

    echo "<tr style='background: #f5f5f5; font-weight: bold;'>
            <td>CELKEM {$aktualniRok}</td>
            <td>-</td>
            <td>-</td>
            <td>" . number_format($celkemRok, 2, ',', ' ') . " €</td>
            <td>" . number_format($celkemProvizeRok, 2, ',', ' ') . " €</td>
        </tr>";

    echo "</tbody></table>";

    // ========================================
    // SEZNAM ZAKÁZEK
    // ========================================
    echo "<h2>Posledních 20 zakázek</h2>";

    if (isset($milanId)) {
        $stmtZakazky = $pdo->prepare("
            SELECT
                cislo,
                model,
                adresa,
                stav,
                CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2)) as cena,
                CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2)) * 0.33 as provize,
                DATE_FORMAT(created_at, '%d.%m.%Y') as datum
            FROM wgs_reklamace
            WHERE assigned_to = :id OR technik LIKE :name
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmtZakazky->execute([
            'id' => $milanId,
            'name' => '%' . $milanName . '%'
        ]);
    } else {
        $stmtZakazky = $pdo->prepare("
            SELECT
                cislo,
                model,
                adresa,
                stav,
                CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2)) as cena,
                CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2)) * 0.33 as provize,
                DATE_FORMAT(created_at, '%d.%m.%Y') as datum
            FROM wgs_reklamace
            WHERE technik LIKE '%Milan%'
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmtZakazky->execute();
    }

    $zakazky = $stmtZakazky->fetchAll(PDO::FETCH_ASSOC);

    $stavyCS = [
        'wait' => 'Čeká',
        'open' => 'Domluvená',
        'done' => 'Hotovo'
    ];

    echo "<table>
        <thead>
            <tr>
                <th>Číslo</th>
                <th>Model</th>
                <th>Adresa</th>
                <th>Stav</th>
                <th>Cena</th>
                <th>Provize</th>
                <th>Datum</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($zakazky as $z) {
        $stavCS = $stavyCS[$z['stav']] ?? $z['stav'];
        echo "<tr>
            <td><strong>{$z['cislo']}</strong></td>
            <td>" . htmlspecialchars($z['model'] ?? '-') . "</td>
            <td>" . htmlspecialchars(substr($z['adresa'] ?? '-', 0, 40)) . "</td>
            <td>{$stavCS}</td>
            <td>" . number_format((float)$z['cena'], 2, ',', ' ') . " €</td>
            <td><strong>" . number_format((float)$z['provize'], 2, ',', ' ') . " €</strong></td>
            <td>{$z['datum']}</td>
        </tr>";
    }

    echo "</tbody></table>";

} catch (Exception $e) {
    echo "<div style='background: #fee; border: 2px solid #c00; padding: 1rem; margin: 1rem 0;'>";
    echo "<strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div>
    <div class='footer'>
        <a href='admin.php'>← Zpět do Admin Panelu</a> |
        <a href='statistiky.php'>Statistiky</a> |
        <a href='vsechny_tabulky.php'>SQL Tabulky</a>
    </div>
</div>
</body>
</html>";
?>
