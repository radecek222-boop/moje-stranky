<?php
/**
 * KOMPLETNÍ PŘEHLED VŠECH SQL TABULEK
 * Zobrazí VŠECHNY tabulky, jejich strukturu, data a statistiky
 * BEZPEČNOST: Pouze pro přihlášené administrátory
 */

require_once __DIR__ . '/init.php';

// KRITICKÉ: Vyžadovat admin session BEZ BYPASSU
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins; background: #fff; color: #000; padding: 40px; text-align: center;"><h1>PŘÍSTUP ODEPŘEN</h1><p>Pouze pro administrátory!</p><p><a href="login.php" style="color: #000; border-bottom: 2px solid #000; text-decoration: none;">Přihlásit se</a></p></body></html>');
}

try {
    $pdo = getDbConnection();

    // Získat VŠECHNY tabulky v databázi
    $stmt = $pdo->query("SHOW TABLES");
    $vsechnyTabulky = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Pro každou tabulku získat detaily
    $detailyTabulek = [];
    $chyboveTabulky = [];

    foreach ($vsechnyTabulky as $tabulka) {
        try {
            // Struktura
            $stmt = $pdo->query("DESCRIBE `$tabulka`");
            $struktura = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Počet záznamů
            $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM `$tabulka`");
            $pocet = $stmt->fetch()['pocet'];

            // Ukázka dat (max 3 záznamy)
            $stmt = $pdo->query("SELECT * FROM `$tabulka` LIMIT 3");
            $ukazkaZaznamu = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Indexy
            $stmt = $pdo->query("SHOW INDEX FROM `$tabulka`");
            $indexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $indexyPrehled = [];
            foreach ($indexy as $index) {
                $indexyPrehled[$index['Key_name']][] = $index['Column_name'];
            }

            // CREATE TABLE DDL
            $stmt = $pdo->query("SHOW CREATE TABLE `$tabulka`");
            $createTableDDL = $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'] ?? null;

            // Velikost tabulky
            $stmt = $pdo->query("
                SELECT
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) AS velikost_kb
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '$tabulka'
            ");
            $velikostInfo = $stmt->fetch();
            $velikost = $velikostInfo['velikost_kb'] ?? 0;

            $detailyTabulek[] = [
                'nazev' => $tabulka,
                'pocet' => $pocet,
                'struktura' => $struktura,
                'ukazka' => $ukazkaZaznamu,
                'indexy' => $indexyPrehled,
                'velikost' => $velikost,
                'ddl' => $createTableDDL
            ];

        } catch (PDOException $e) {
            // Přeskočit tabulky/VIEW které nelze načíst (např. neplatné VIEW)
            $chyboveTabulky[] = [
                'nazev' => $tabulka,
                'chyba' => $e->getMessage()
            ];
        }
    }

    // Celková statistika databáze
    $stmt = $pdo->query("
        SELECT
            SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 AS velikost_mb
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
    ");
    $celkovaVelikost = round($stmt->fetch()['velikost_mb'], 2);

    $celkovyPocetZaznamu = array_sum(array_column($detailyTabulek, 'pocet'));

} catch (Exception $e) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Chyba</title></head><body style="font-family: Poppins; padding: 40px;"><h1 style="color: #cc0000;">CHYBA DATABÁZE</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Všechny SQL Tabulky | WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #000;
            padding: 2rem;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            border: 2px solid #000;
        }
        .header {
            background: #000;
            color: #fff;
            padding: 2rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .content {
            padding: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .stat-box {
            border: 2px solid #000;
            padding: 1.5rem;
            text-align: center;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #555;
        }
        .tabulka-section {
            margin: 3rem 0;
            border: 2px solid #ddd;
            padding: 2rem;
        }
        .tabulka-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #000;
        }
        .tabulka-nazev {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .tabulka-info {
            display: flex;
            gap: 2rem;
            font-size: 0.9rem;
        }
        .tabulka-info span {
            background: #f5f5f5;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
        }
        h2 {
            margin: 2rem 0 1rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #ddd;
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.85rem;
            border: 2px solid #000;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #000;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #000;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge.primary {
            background: #000;
        }
        .badge.index {
            background: #555;
        }
        .badge.null {
            background: #ccc;
            color: #000;
        }
        code {
            background: #f5f5f5;
            padding: 0.2rem 0.5rem;
            font-family: monospace;
            border: 1px solid #ddd;
            font-size: 0.85em;
        }
        .overflow-x {
            overflow-x: auto;
        }
        .footer {
            margin-top: 2rem;
            padding: 1.5rem 2rem;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #555;
            font-size: 0.85rem;
        }
        .footer a {
            color: #000;
            text-decoration: none;
            border-bottom: 2px solid #000;
        }
        .toc {
            background: #f5f5f5;
            border: 2px solid #ddd;
            padding: 2rem;
            margin: 2rem 0;
        }
        .toc h2 {
            margin-top: 0;
            border: none;
        }
        .toc ul {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
            column-count: 3;
            column-gap: 2rem;
        }
        .toc li {
            margin: 0.5rem 0;
        }
        .toc a {
            color: #000;
            text-decoration: none;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }
        .toc a:hover {
            border-bottom-width: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>KOMPLETNÍ PŘEHLED VŠECH SQL TABULEK</h1>
            <p style="margin-top: 0.5rem; opacity: 0.9; font-size: 0.95rem;">Databáze: <?php echo htmlspecialchars($_ENV['DB_NAME'] ?? 'wgs-servicecz01'); ?> | Live Production Data</p>
            <div style="margin-top: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                <button onclick="exportAllDDL()" style="padding: 0.75rem 1.5rem; background: #fff; color: #000; border: 2px solid #fff; cursor: pointer; font-family: Poppins; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.08em;">Stáhnout všechny DDL</button>
                <button onclick="window.print()" style="padding: 0.75rem 1.5rem; background: transparent; color: #fff; border: 2px solid #fff; cursor: pointer; font-family: Poppins; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.08em;">Tisk</button>
                <button onclick="window.location.reload()" style="padding: 0.75rem 1.5rem; background: transparent; color: #fff; border: 2px solid #fff; cursor: pointer; font-family: Poppins; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.08em;">Obnovit aktuální SQL</button>
            </div>
        </div>

        <div class="content">
            <!-- UPOZORNĚNÍ NA CHYBOVÉ TABULKY/VIEW -->
            <?php if (!empty($chyboveTabulky)): ?>
            <div style="background: #fff3cd; border: 2px solid #fbbf24; padding: 2rem; margin: 2rem 0;">
                <h2 style="margin-top: 0; padding-bottom: 0.75rem; border-bottom: 2px solid #fbbf24; font-size: 1.1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #92400e;">
                    Neplatné VIEW/Tabulky
                </h2>
                <p style="margin: 1rem 0; color: #78350f; font-size: 0.9rem;">
                    <strong>VAROVÁNÍ:</strong> Následující tabulky/VIEW nelze načíst kvůli chybám. Pravděpodobně jde o neplatné VIEW které odkazují na smazané sloupce.
                </p>
                <?php foreach ($chyboveTabulky as $chyba): ?>
                <div style="background: white; border: 1px solid #fbbf24; padding: 1rem; margin: 0.5rem 0; border-radius: 5px;">
                    <strong style="color: #92400e;"><?php echo htmlspecialchars($chyba['nazev']); ?></strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: #78350f;">
                        Chyba: <?php echo htmlspecialchars($chyba['chyba']); ?>
                    </p>
                    <?php if (strpos($chyba['nazev'], 'provize') !== false): ?>
                    <a href="oprav_view_provize.php" target="_blank" style="display: inline-block; margin-top: 0.5rem; padding: 0.5rem 1rem; background: #92400e; color: #fff; text-decoration: none; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; border-radius: 3px;">
                        Opravit VIEW
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- NÁSTROJE PRO SPRÁVU DATABÁZE -->
            <div style="background: #f5f5f5; border: 2px solid #000; padding: 2rem; margin: 2rem 0;">
                <h2 style="margin-top: 0; padding-bottom: 0.75rem; border-bottom: 2px solid #000; font-size: 1.1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;">
                    Nástroje pro správu databáze
                </h2>
                <p style="margin: 1rem 0; color: #555; font-size: 0.9rem;">
                    <strong>DŮLEŽITÉ:</strong> Všechny změny SQL struktury provádějte pouze přes tyto nástroje. Nikdy neměňte strukturu ručně!
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-top: 1.5rem;">

                    <!-- Oprava DB credentials -->
                    <div style="background: white; border: 2px solid #000; padding: 1.5rem;">
                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600;">
                            Aktualizovat DB credentials
                        </h3>
                        <p style="margin: 0.5rem 0; font-size: 0.85rem; color: #666;">
                            Oprava databázových credentials v .env souboru. Použij pokud se změní DB heslo.
                        </p>
                        <a href="aktualizuj_databazi.php" style="display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer;">
                            Otevřít nástroj
                        </a>
                    </div>

                    <!-- Čištění emailové fronty -->
                    <div style="background: white; border: 2px solid #000; padding: 1.5rem;">
                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600;">
                            Vyčistit emailovou frontu
                        </h3>
                        <p style="margin: 0.5rem 0; font-size: 0.85rem; color: #666;">
                            Smazání selhavších emailů z tabulky wgs_email_queue pro čištění databáze.
                        </p>
                        <a href="vycisti_emailovou_frontu.php" style="display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer;">
                            Otevřít nástroj
                        </a>
                    </div>

                    <!-- Migrace wgs_videos -->
                    <div style="background: white; border: 2px solid #000; padding: 1.5rem;">
                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600;">
                            Migrace wgs_videos (jen pokud chybí)
                        </h3>
                        <p style="margin: 0.5rem 0; font-size: 0.85rem; color: #666;">
                            Spusťte jen pokud tabulka není v databázi nebo potřebujete znovu založit složku uploads/videos. Na produkci už tabulka existuje.
                        </p>
                        <a href="migrations/2025_12_01_pridej_tabulku_wgs_videos.php" style="display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer;">
                            Otevřít migraci
                        </a>
                    </div>

                </div>

                <div style="margin-top: 2rem; padding: 1rem; background: #fffbea; border: 2px solid #fbbf24;">
                    <h3 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; font-weight: 600; color: #92400e;">
                        Přidat nový SQL nástroj (pro AI)
                    </h3>
                    <p style="margin: 0 0 1rem 0; font-size: 0.85rem; color: #78350f;">
                        Když AI vytvoří nový migrační skript pro změny v databázi, uloží jej do root složky projektu
                        s názvem který začíná <code>pridej_</code>, <code>kontrola_</code>, <code>migrace_</code> nebo <code>vycisti_</code>.
                    </p>
                    <p style="margin: 0 0 1rem 0; font-size: 0.85rem; color: #78350f;">
                        <strong>Formát názvu:</strong> <code>pridej_nazev_sloupce.php</code>, <code>kontrola_nazev.php</code>, <code>migrace_nazev.php</code>, <code>vycisti_nazev.php</code>
                    </p>
                    <p style="margin: 0; font-size: 0.85rem; color: #78350f;">
                        <strong>Po vytvoření skriptu:</strong> Přidej odkaz ručně do sekce "Nástroje pro správu databáze" v tomto souboru (vsechny_tabulky.php).
                    </p>
                </div>
            </div>

            <!-- CELKOVÁ STATISTIKA -->
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?php echo count($detailyTabulek); ?></div>
                    <div class="stat-label">Celkem tabulek</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo number_format($celkovyPocetZaznamu); ?></div>
                    <div class="stat-label">Celkem záznamů</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $celkovaVelikost; ?> MB</div>
                    <div class="stat-label">Velikost databáze</div>
                </div>
            </div>

            <!-- OBSAH -->
            <div class="toc">
                <h2>Obsah</h2>
                <ul>
                    <?php foreach ($detailyTabulek as $detail): ?>
                    <li>
                        <a href="#tabulka-<?php echo htmlspecialchars($detail['nazev']); ?>">
                            <?php echo htmlspecialchars($detail['nazev']); ?>
                            <small>(<?php echo $detail['pocet']; ?>)</small>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- DETAILY JEDNOTLIVÝCH TABULEK -->
            <?php foreach ($detailyTabulek as $detail): ?>
            <div class="tabulka-section" id="tabulka-<?php echo htmlspecialchars($detail['nazev']); ?>">
                <div class="tabulka-header">
                    <div class="tabulka-nazev"><?php echo htmlspecialchars($detail['nazev']); ?></div>
                    <div class="tabulka-info">
                        <span><strong><?php echo $detail['pocet']; ?></strong> záznamů</span>
                        <span><strong><?php echo $detail['velikost']; ?> KB</strong></span>
                        <span><strong><?php echo count($detail['struktura']); ?></strong> sloupců</span>
                        <span><strong><?php echo count($detail['indexy']); ?></strong> indexů</span>
                    </div>
                </div>

                <!-- CREATE TABLE DDL -->
                <?php if (!empty($detail['ddl'])): ?>
                <h2>CREATE TABLE (SQL DDL)</h2>
                <details style="margin: 1rem 0; border: 2px solid #ddd; padding: 1rem; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: 600; user-select: none;">Zobrazit CREATE TABLE příkaz</summary>
                    <pre style="background: #f5f5f5; padding: 1rem; margin-top: 1rem; overflow-x: auto; border: 1px solid #ddd; border-radius: 3px; font-size: 0.85rem; line-height: 1.5;"><code><?php echo htmlspecialchars($detail['ddl']); ?></code></pre>
                    <button onclick="navigator.clipboard.writeText(<?php echo htmlspecialchars(json_encode($detail['ddl']), ENT_QUOTES); ?>); this.textContent='Zkopírováno!'; setTimeout(() => this.textContent='Kopírovat do schránky', 2000);" style="margin-top: 0.5rem; padding: 0.5rem 1rem; background: #000; color: #fff; border: none; cursor: pointer; font-family: Poppins; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">Kopírovat do schránky</button>
                </details>
                <?php endif; ?>

                <!-- STRUKTURA SLOUPCŮ -->
                <h2>Struktura sloupců</h2>
                <div class="overflow-x">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Sloupec</th>
                                <th>Typ</th>
                                <th>Null</th>
                                <th>Key</th>
                                <th>Default</th>
                                <th>Extra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($detail['struktura'] as $sloupec): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($sloupec['Field']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($sloupec['Type']); ?></code></td>
                                <td>
                                    <?php if ($sloupec['Null'] === 'YES'): ?>
                                        <span class="badge null">YES</span>
                                    <?php else: ?>
                                        <span class="badge">NO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sloupec['Key'])): ?>
                                        <?php if ($sloupec['Key'] === 'PRI'): ?>
                                            <span class="badge primary">PRIMARY</span>
                                        <?php elseif ($sloupec['Key'] === 'MUL'): ?>
                                            <span class="badge index">INDEX</span>
                                        <?php else: ?>
                                            <span class="badge"><?php echo htmlspecialchars($sloupec['Key']); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($sloupec['Default'] ?? 'NULL'); ?></td>
                                <td><?php echo htmlspecialchars($sloupec['Extra']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- INDEXY -->
                <?php if (!empty($detail['indexy'])): ?>
                <h2>Indexy</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Název indexu</th>
                            <th>Sloupce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail['indexy'] as $nazev => $sloupce): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($nazev); ?></strong></td>
                            <td><?php echo htmlspecialchars(implode(', ', $sloupce)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- UKÁZKA DAT -->
                <?php if (!empty($detail['ukazka'])): ?>
                <h2>Ukázka dat (max 3 záznamy)</h2>
                <div class="overflow-x">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach (array_keys($detail['ukazka'][0]) as $klic): ?>
                                <th><?php echo htmlspecialchars($klic); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail['ukazka'] as $zaznam): ?>
                            <tr>
                                <?php foreach ($zaznam as $hodnota): ?>
                                <td><?php echo htmlspecialchars(substr($hodnota ?? 'NULL', 0, 100)); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="color: #888; font-style: italic; margin: 1rem 0;">Tabulka je prázdná - žádné záznamy.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <a href="admin.php">← Zpět do Admin Panelu</a> |
            <a href="db_struktura.php">Struktura wgs_reklamace</a> |
            <a href="#top">↑ Nahoru</a>
        </div>
    </div>

    <script>
    // Export všech DDL do jednoho SQL souboru
    function exportAllDDL() {
        const allDDL = <?php echo json_encode(array_map(function($t) {
            return [
                'nazev' => $t['nazev'],
                'ddl' => $t['ddl']
            ];
        }, $detailyTabulek)); ?>;

        let sqlContent = "-- WGS Database Export\n";
        sqlContent += "-- Databáze: <?php echo htmlspecialchars($_ENV['DB_NAME'] ?? 'wgs-servicecz01'); ?>\n";
        sqlContent += "-- Vygenerováno: " + new Date().toLocaleString('cs-CZ') + "\n";
        sqlContent += "-- Celkem tabulek: " + allDDL.length + "\n";
        sqlContent += "-- ============================================\n\n";

        allDDL.forEach((table, index) => {
            sqlContent += "-- ============================================\n";
            sqlContent += "-- Tabulka #" + (index + 1) + ": " + table.nazev + "\n";
            sqlContent += "-- ============================================\n\n";
            sqlContent += "DROP TABLE IF EXISTS `" + table.nazev + "`;\n\n";
            sqlContent += table.ddl + ";\n\n\n";
        });

        // Vytvořit blob a stáhnout
        const blob = new Blob([sqlContent], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'wgs_database_structure_' + new Date().toISOString().split('T')[0] + '.sql';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>
