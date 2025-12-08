<?php
/**
 * AUDIT DATABÁZE - Analýza struktury a použití sloupců
 *
 * Tento skript analyzuje:
 * 1. Všechny sloupce v tabulce wgs_reklamace
 * 2. Které sloupce se používají v kódu
 * 3. Duplicitní/nekonzistentní názvy
 * 4. Návrh na sjednocení
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Audit databaze - WGS</title>
    <style>
        body { font-family: 'Poppins', sans-serif; max-width: 1400px; margin: 20px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        h1 { border-bottom: 2px solid #39ff14; padding-bottom: 10px; }
        h2 { color: #39ff14; margin-top: 2rem; }
        h3 { color: #ccc; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; background: #222; }
        th, td { padding: 0.5rem; text-align: left; border: 1px solid #444; font-size: 0.85rem; }
        th { background: #333; color: #39ff14; text-transform: uppercase; }
        .used { color: #39ff14; }
        .unused { color: #ff4444; }
        .duplicate { background: #442200; }
        .legacy { background: #333; color: #888; }
        .recommend { background: #1a3a1a; }
        .warning { background: #3a1a1a; }
        .info-box { background: #333; padding: 1rem; border-radius: 5px; margin: 1rem 0; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0; }
        .stat { background: #333; padding: 1rem; border-radius: 5px; text-align: center; }
        .stat-value { font-size: 2rem; color: #39ff14; }
        .stat-label { color: #888; font-size: 0.8rem; text-transform: uppercase; }
        pre { background: #111; padding: 1rem; overflow-x: auto; font-size: 0.8rem; }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #39ff14; color: #000; text-decoration: none; border-radius: 3px; margin: 0.25rem; font-weight: 600; }
        .btn:hover { background: #2dd10f; }
    </style>
</head>
<body>
<h1>Audit databaze WGS</h1>";

try {
    $pdo = getDbConnection();

    // === 1. STRUKTURA TABULKY wgs_reklamace ===
    echo "<h2>1. Struktura tabulky wgs_reklamace</h2>";

    $stmt = $pdo->query("DESCRIBE wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Definice očekávaných sloupců a jejich účelu
    $columnDefinitions = [
        // IDENTIFIKÁTORY
        'id' => ['skupina' => 'ID', 'ucel' => 'Interní auto-increment ID', 'status' => 'PONECHAT'],
        'reklamace_id' => ['skupina' => 'ID', 'ucel' => 'Workflow ID (WGS-YYYYMMDD-XXX)', 'status' => 'PONECHAT'],
        'cislo' => ['skupina' => 'ID', 'ucel' => 'Číslo objednávky od zákazníka', 'status' => 'PONECHAT'],
        'original_reklamace_id' => ['skupina' => 'ID', 'ucel' => 'Reference na původní zakázku (klonování)', 'status' => 'ZVÁŽIT ODSTRANĚNÍ'],

        // ZÁKAZNÍK
        'jmeno' => ['skupina' => 'Zákazník', 'ucel' => 'Jméno zákazníka', 'status' => 'PONECHAT'],
        'email' => ['skupina' => 'Zákazník', 'ucel' => 'Email zákazníka', 'status' => 'PONECHAT'],
        'telefon' => ['skupina' => 'Zákazník', 'ucel' => 'Telefon zákazníka', 'status' => 'PONECHAT'],

        // ADRESA
        'adresa' => ['skupina' => 'Adresa', 'ucel' => 'Kompletní adresa (legacy)', 'status' => 'LEGACY'],
        'ulice' => ['skupina' => 'Adresa', 'ucel' => 'Ulice a číslo', 'status' => 'PONECHAT'],
        'mesto' => ['skupina' => 'Adresa', 'ucel' => 'Město', 'status' => 'PONECHAT'],
        'psc' => ['skupina' => 'Adresa', 'ucel' => 'PSČ', 'status' => 'PONECHAT'],

        // PRODUKT
        'model' => ['skupina' => 'Produkt', 'ucel' => 'Model produktu', 'status' => 'PONECHAT'],
        'provedeni' => ['skupina' => 'Produkt', 'ucel' => 'Provedení (látka/kůže)', 'status' => 'PONECHAT'],
        'barva' => ['skupina' => 'Produkt', 'ucel' => 'Barva', 'status' => 'PONECHAT'],
        'seriove_cislo' => ['skupina' => 'Produkt', 'ucel' => 'Sériové číslo', 'status' => 'PONECHAT'],

        // POPIS PROBLÉMU
        'popis_problemu' => ['skupina' => 'Popis', 'ucel' => 'Popis problému', 'status' => 'PONECHAT'],
        'doplnujici_info' => ['skupina' => 'Popis', 'ucel' => 'Doplňující informace', 'status' => 'PONECHAT'],

        // STAV A TERMÍNY
        'stav' => ['skupina' => 'Stav', 'ucel' => 'Stav zakázky (wait/open/done)', 'status' => 'PONECHAT'],
        'typ' => ['skupina' => 'Stav', 'ucel' => 'Typ zakázky (reklamace/servis)', 'status' => 'PONECHAT'],
        'termin' => ['skupina' => 'Termín', 'ucel' => 'Datum návštěvy', 'status' => 'PONECHAT'],
        'cas_navstevy' => ['skupina' => 'Termín', 'ucel' => 'Čas návštěvy', 'status' => 'PONECHAT'],

        // DATUMY
        'datum_prodeje' => ['skupina' => 'Datumy', 'ucel' => 'Datum prodeje produktu', 'status' => 'PONECHAT'],
        'datum_reklamace' => ['skupina' => 'Datumy', 'ucel' => 'Datum podání reklamace', 'status' => 'PONECHAT'],
        'created_at' => ['skupina' => 'Datumy', 'ucel' => 'Datum vytvoření záznamu', 'status' => 'PONECHAT'],
        'updated_at' => ['skupina' => 'Datumy', 'ucel' => 'Datum poslední aktualizace', 'status' => 'PONECHAT'],
        'datum_dokonceni' => ['skupina' => 'Datumy', 'ucel' => 'Datum dokončení zakázky', 'status' => 'PONECHAT'],

        // ZADAVATEL (KDO VYTVOŘIL)
        'created_by' => ['skupina' => 'Zadavatel', 'ucel' => 'User ID zadavatele (VARCHAR)', 'status' => 'PONECHAT - PRIMÁRNÍ'],
        'created_by_role' => ['skupina' => 'Zadavatel', 'ucel' => 'Role zadavatele při vytvoření', 'status' => 'PONECHAT'],
        'prodejce' => ['skupina' => 'Zadavatel', 'ucel' => 'LEGACY: Jméno prodejce (TEXT)', 'status' => 'ODSTRANIT - DUPLICITA'],
        'zpracoval_id' => ['skupina' => 'Zadavatel', 'ucel' => 'LEGACY: ID zpracovatele', 'status' => 'ZVÁŽIT ODSTRANĚNÍ'],

        // TECHNIK
        'technik' => ['skupina' => 'Technik', 'ucel' => 'Jméno technika (VARCHAR)', 'status' => 'PONECHAT - PRIMÁRNÍ'],
        'assigned_to' => ['skupina' => 'Technik', 'ucel' => 'INT ID přiřazeného technika', 'status' => 'ZVÁŽIT - DUPLICITA'],

        // FINANCE
        'castka' => ['skupina' => 'Finance', 'ucel' => 'LEGACY: Částka (starý systém)', 'status' => 'LEGACY'],
        'cena' => ['skupina' => 'Finance', 'ucel' => 'LEGACY: Cena', 'status' => 'LEGACY'],
        'cena_prace' => ['skupina' => 'Finance', 'ucel' => 'Cena práce', 'status' => 'PONECHAT'],
        'cena_material' => ['skupina' => 'Finance', 'ucel' => 'Cena materiálu', 'status' => 'PONECHAT'],
        'cena_doprava' => ['skupina' => 'Finance', 'ucel' => 'Cena dopravy', 'status' => 'PONECHAT'],
        'cena_druhy_technik' => ['skupina' => 'Finance', 'ucel' => 'Cena druhého technika', 'status' => 'PONECHAT'],
        'cena_celkem' => ['skupina' => 'Finance', 'ucel' => 'Celková cena', 'status' => 'PONECHAT'],
        'pocet_dilu' => ['skupina' => 'Finance', 'ucel' => 'Počet dílů', 'status' => 'PONECHAT'],
        'fakturace_firma' => ['skupina' => 'Finance', 'ucel' => 'Země fakturace (CZ/SK)', 'status' => 'PONECHAT'],

        // PROTOKOL
        'protokol_data' => ['skupina' => 'Protokol', 'ucel' => 'JSON data protokolu', 'status' => 'PONECHAT'],
        'kalkulace_data' => ['skupina' => 'Protokol', 'ucel' => 'JSON data kalkulace', 'status' => 'PONECHAT'],
        'dealer' => ['skupina' => 'Protokol', 'ucel' => 'Čeká na vyjádření dealera', 'status' => 'PONECHAT'],

        // GPS
        'latitude' => ['skupina' => 'GPS', 'ucel' => 'GPS šířka', 'status' => 'PONECHAT'],
        'longitude' => ['skupina' => 'GPS', 'ucel' => 'GPS délka', 'status' => 'PONECHAT'],
    ];

    // Statistiky
    $totalColumns = count($columns);
    $definedColumns = 0;
    $undefinedColumns = [];

    echo "<div class='stats'>
        <div class='stat'><div class='stat-value'>{$totalColumns}</div><div class='stat-label'>Celkem sloupcu</div></div>
    </div>";

    echo "<table>
        <tr>
            <th>Sloupec</th>
            <th>Typ</th>
            <th>Skupina</th>
            <th>Ucel</th>
            <th>Status</th>
            <th>Null</th>
            <th>Default</th>
        </tr>";

    foreach ($columns as $col) {
        $name = $col['Field'];
        $def = $columnDefinitions[$name] ?? null;

        if ($def) {
            $definedColumns++;
            $statusClass = match($def['status']) {
                'PONECHAT', 'PONECHAT - PRIMÁRNÍ' => 'used',
                'ODSTRANIT - DUPLICITA' => 'warning',
                'ZVÁŽIT ODSTRANĚNÍ', 'ZVÁŽIT - DUPLICITA' => 'duplicate',
                'LEGACY' => 'legacy',
                default => ''
            };
            echo "<tr class='{$statusClass}'>
                <td><strong>{$name}</strong></td>
                <td>{$col['Type']}</td>
                <td>{$def['skupina']}</td>
                <td>{$def['ucel']}</td>
                <td>{$def['status']}</td>
                <td>{$col['Null']}</td>
                <td>{$col['Default']}</td>
            </tr>";
        } else {
            $undefinedColumns[] = $name;
            echo "<tr class='warning'>
                <td><strong>{$name}</strong></td>
                <td>{$col['Type']}</td>
                <td>???</td>
                <td>NEZNÁMÝ SLOUPEC</td>
                <td>PROZKOUMAT</td>
                <td>{$col['Null']}</td>
                <td>{$col['Default']}</td>
            </tr>";
        }
    }
    echo "</table>";

    if (!empty($undefinedColumns)) {
        echo "<div class='info-box warning'>
            <strong>Neznámé sloupce:</strong> " . implode(', ', $undefinedColumns) . "
        </div>";
    }

    // === 2. DUPLICITNÍ SLOUPCE ===
    echo "<h2>2. Duplicitni a konfliktni sloupce</h2>";

    $duplicates = [
        [
            'nazev' => 'ZADAVATEL (kdo vytvoril zakazku)',
            'sloupce' => ['created_by', 'prodejce', 'zpracoval_id'],
            'doporuceni' => 'Ponechat pouze CREATED_BY (VARCHAR s user_id). Sloupec PRODEJCE odstranit - je to legacy TEXT s jménem.',
            'akce' => 'Migrovat data z PRODEJCE do wgs_users pokud chybí, pak PRODEJCE smazat.'
        ],
        [
            'nazev' => 'TECHNIK (kdo provadi servis)',
            'sloupce' => ['technik', 'assigned_to'],
            'doporuceni' => 'TECHNIK obsahuje jméno (VARCHAR), ASSIGNED_TO obsahuje INT ID. Sjednotit na jeden.',
            'akce' => 'Buď používat pouze TECHNIK (jméno) nebo pouze ASSIGNED_TO (ID s JOINem).'
        ],
        [
            'nazev' => 'CENA/CASTKA',
            'sloupce' => ['castka', 'cena', 'cena_celkem'],
            'doporuceni' => 'CASTKA a CENA jsou legacy. Používat pouze CENA_CELKEM.',
            'akce' => 'Ověřit že CENA_CELKEM má správné hodnoty, pak legacy sloupce odstranit.'
        ],
        [
            'nazev' => 'ADRESA',
            'sloupce' => ['adresa', 'ulice', 'mesto', 'psc'],
            'doporuceni' => 'ADRESA je legacy kompletní string. ULICE+MESTO+PSC jsou strukturované.',
            'akce' => 'Ověřit že strukturované sloupce jsou vyplněné, pak ADRESA může zůstat jako cache.'
        ]
    ];

    foreach ($duplicates as $dup) {
        echo "<div class='info-box'>
            <h3>{$dup['nazev']}</h3>
            <p><strong>Sloupce:</strong> " . implode(', ', $dup['sloupce']) . "</p>
            <p><strong>Doporučení:</strong> {$dup['doporuceni']}</p>
            <p><strong>Akce:</strong> {$dup['akce']}</p>
        </div>";
    }

    // === 3. ANALÝZA DAT ===
    echo "<h2>3. Analyza dat</h2>";

    // created_by vs prodejce
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN created_by IS NOT NULL AND created_by != '' THEN 1 ELSE 0 END) as has_created_by,
            SUM(CASE WHEN prodejce IS NOT NULL AND prodejce != '' THEN 1 ELSE 0 END) as has_prodejce,
            SUM(CASE WHEN (created_by IS NULL OR created_by = '') AND (prodejce IS NOT NULL AND prodejce != '') THEN 1 ELSE 0 END) as only_prodejce,
            SUM(CASE WHEN technik IS NOT NULL AND technik != '' THEN 1 ELSE 0 END) as has_technik,
            SUM(CASE WHEN assigned_to IS NOT NULL AND assigned_to > 0 THEN 1 ELSE 0 END) as has_assigned_to
        FROM wgs_reklamace
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>Metrika</th><th>Hodnota</th><th>Procento</th></tr>
        <tr><td>Celkem reklamací</td><td>{$stats['total']}</td><td>100%</td></tr>
        <tr><td>Má CREATED_BY</td><td>{$stats['has_created_by']}</td><td>" . round($stats['has_created_by']/$stats['total']*100, 1) . "%</td></tr>
        <tr><td>Má PRODEJCE (legacy)</td><td>{$stats['has_prodejce']}</td><td>" . round($stats['has_prodejce']/$stats['total']*100, 1) . "%</td></tr>
        <tr class='warning'><td>Má POUZE PRODEJCE (bez created_by)</td><td>{$stats['only_prodejce']}</td><td>" . round($stats['only_prodejce']/$stats['total']*100, 1) . "%</td></tr>
        <tr><td>Má TECHNIK (jméno)</td><td>{$stats['has_technik']}</td><td>" . round($stats['has_technik']/$stats['total']*100, 1) . "%</td></tr>
        <tr><td>Má ASSIGNED_TO (ID)</td><td>{$stats['has_assigned_to']}</td><td>" . round($stats['has_assigned_to']/$stats['total']*100, 1) . "%</td></tr>
    </table>";

    // === 4. NÁVRH NA SJEDNOCENÍ ===
    echo "<h2>4. Navrh na sjednoceni nazvoslovi</h2>";

    echo "<div class='info-box recommend'>
        <h3>DOPORUCENA STRUKTURA</h3>
        <table>
            <tr><th>Koncept</th><th>Sloupec</th><th>Typ</th><th>Popis</th></tr>
            <tr><td>Zadavatel</td><td><strong>created_by</strong></td><td>VARCHAR(20)</td><td>User ID zadavatele (PRO..., TCH..., ADM...)</td></tr>
            <tr><td>Technik</td><td><strong>technik</strong></td><td>VARCHAR(100)</td><td>Jméno technika (pro zobrazení)</td></tr>
            <tr><td>Technik ID</td><td><strong>assigned_to</strong></td><td>INT</td><td>ID technika pro JOIN (volitelné)</td></tr>
            <tr><td>Cena</td><td><strong>cena_celkem</strong></td><td>DECIMAL(10,2)</td><td>Celková cena zakázky</td></tr>
        </table>
    </div>";

    echo "<div class='info-box warning'>
        <h3>SLOUPCE K ODSTRANENI</h3>
        <ul>
            <li><strong>prodejce</strong> - Legacy TEXT, nahrazeno created_by + JOIN</li>
            <li><strong>castka</strong> - Legacy, nahrazeno cena_celkem</li>
            <li><strong>cena</strong> - Legacy, nahrazeno cena_celkem</li>
            <li><strong>zpracoval_id</strong> - Nepoužívá se</li>
            <li><strong>original_reklamace_id</strong> - Funkce znovuotevření byla odstraněna</li>
        </ul>
    </div>";

    // === 5. AKČNÍ TLAČÍTKA ===
    echo "<h2>5. Dalsi kroky</h2>";
    echo "<p>
        <a href='/smaz_klony_zakazek.php' class='btn'>Smazat klony zakazek</a>
        <a href='/vsechny_tabulky.php' class='btn'>Zobrazit SQL strukturu</a>
    </p>";

} catch (Exception $e) {
    echo "<div class='warning'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
