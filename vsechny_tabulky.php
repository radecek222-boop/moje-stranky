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
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins; background: #fff; color: #000; padding: 40px; text-align: center;"><h1>❌ PŘÍSTUP ODEPŘEN</h1><p>Pouze pro administrátory!</p><p><a href="login.php" style="color: #000; border-bottom: 2px solid #000; text-decoration: none;">→ Přihlásit se</a></p></body></html>');
}

try {
    $pdo = getDbConnection();

    // Získat VŠECHNY tabulky v databázi
    $stmt = $pdo->query("SHOW TABLES");
    $vsechnyTabulky = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Pro každou tabulku získat detaily
    $detailyTabulek = [];
    foreach ($vsechnyTabulky as $tabulka) {
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
            'velikost' => $velikost
        ];
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
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Chyba</title></head><body style="font-family: Poppins; padding: 40px;"><h1 style="color: #cc0000;">❌ CHYBA DATABÁZE</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>');
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
            <h1>🗄️ KOMPLETNÍ PŘEHLED VŠECH SQL TABULEK</h1>
            <p style="margin-top: 0.5rem; opacity: 0.9; font-size: 0.95rem;">Databáze: <?php echo htmlspecialchars($_ENV['DB_NAME'] ?? 'wgs-servicecz01'); ?> | Live Production Data</p>
        </div>

        <div class="content">
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
                <h2>📑 Obsah</h2>
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

                <!-- STRUKTURA SLOUPCŮ -->
                <h2>📋 Struktura sloupců</h2>
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
                <h2>⚡ Indexy</h2>
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
                <h2>📄 Ukázka dat (max 3 záznamy)</h2>
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
</body>
</html>
