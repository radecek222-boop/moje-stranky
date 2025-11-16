<?php
/**
 * ZOBRAZENÍ SKUTEČNÉ STRUKTURY DATABÁZE - WEB INTERFACE
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

    // Získat strukturu tabulky
    $stmt = $pdo->query('DESCRIBE wgs_reklamace');
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexy
    $stmt = $pdo->query("SHOW INDEX FROM wgs_reklamace");
    $indexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $indexyPrehled = [];
    foreach ($indexy as $index) {
        $indexyPrehled[$index['Key_name']][] = $index['Column_name'];
    }

    // Ukázka dat
    $stmt = $pdo->query('SELECT * FROM wgs_reklamace ORDER BY id DESC LIMIT 3');
    $zaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiky
    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_reklamace");
    $celkemReklamaci = $stmt->fetch()['pocet'];

    $stmt = $pdo->query("SELECT stav, COUNT(*) as pocet FROM wgs_reklamace GROUP BY stav");
    $stavyRozlozeni = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Chyba</title></head><body style="font-family: Poppins; padding: 40px;"><h1 style="color: #cc0000;">❌ CHYBA DATABÁZE</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struktura Databáze | WGS</title>
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
            max-width: 1400px;
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
        h2 {
            margin: 2rem 0 1rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #000;
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        h2:first-child {
            margin-top: 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        .stat-box {
            border: 2px solid #ddd;
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
            font-size: 0.8rem;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #000;
            color: #fff;
            font-size: 0.75rem;
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
        .check {
            color: #000;
            font-weight: 700;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗄️ SKUTEČNÁ STRUKTURA DATABÁZE</h1>
            <p style="margin-top: 0.5rem; opacity: 0.9; font-size: 0.95rem;">Tabulka: wgs_reklamace | Live Production Data</p>
        </div>

        <div class="content">
            <!-- STATISTIKY -->
            <h2>📊 Statistiky</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?php echo count($sloupce); ?></div>
                    <div class="stat-label">Celkem sloupců</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo count($indexyPrehled); ?></div>
                    <div class="stat-label">Celkem indexů</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $celkemReklamaci; ?></div>
                    <div class="stat-label">Celkem reklamací</div>
                </div>
            </div>

            <!-- ROZLOŽENÍ PODLE STAVU -->
            <?php if (!empty($stavyRozlozeni)): ?>
            <h2>📈 Rozložení podle stavu</h2>
            <div class="stats-grid">
                <?php foreach ($stavyRozlozeni as $stav): ?>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stav['pocet']; ?></div>
                    <div class="stat-label"><?php echo htmlspecialchars($stav['stav']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- STRUKTURA SLOUPCŮ -->
            <h2>📋 Struktura sloupců (<?php echo count($sloupce); ?>)</h2>
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
                        <?php $i = 1; foreach ($sloupce as $sloupec): ?>
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
            <h2>⚡ Indexy (<?php echo count($indexyPrehled); ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Název indexu</th>
                        <th>Sloupce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($indexyPrehled as $nazev => $sloupce): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($nazev); ?></strong></td>
                        <td><?php echo htmlspecialchars(implode(', ', $sloupce)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- KONTROLA KLÍČOVÝCH SLOUPCŮ -->
            <h2>✅ Kontrola klíčových sloupců</h2>
            <?php
            $klicoveSloupce = ['technik', 'prodejce', 'ulice', 'mesto', 'psc', 'castka', 'zeme'];
            $existujici = array_column($sloupce, 'Field');
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Sloupec</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($klicoveSloupce as $hledany): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($hledany); ?></strong></td>
                        <td>
                            <?php if (in_array($hledany, $existujici)): ?>
                                <span class="check">✅ EXISTUJE</span>
                            <?php else: ?>
                                <span style="color: #cc0000; font-weight: 700;">❌ CHYBÍ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- UKÁZKA DAT -->
            <?php if (!empty($zaznamy)): ?>
            <h2>📄 Ukázka dat (poslední <?php echo count($zaznamy); ?> záznamy)</h2>
            <div class="overflow-x">
                <table>
                    <thead>
                        <tr>
                            <?php foreach (array_keys($zaznamy[0]) as $klic): ?>
                            <th><?php echo htmlspecialchars($klic); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zaznamy as $zaznam): ?>
                        <tr>
                            <?php foreach ($zaznam as $hodnota): ?>
                            <td><?php echo htmlspecialchars($hodnota ?? 'NULL'); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="admin.php">← Zpět do Admin Panelu</a> |
            <a href="seznam.php">Seznam Reklamací</a> |
            <a href="FINAL_DDL_wgs_reklamace.sql" download>📄 Stáhnout SQL DDL</a>
        </div>
    </div>
</body>
</html>
