<?php
/**
 * PŘIDÁNÍ CHYBĚJÍCÍCH INDEXŮ PRO ZRYCHLENÍ QUERIES
 * Podle diagnostiky chybí 11 indexů na často používaných sloupcích
 * BEZPEČNOST: Pouze pro přihlášené administrátory
 */

require_once __DIR__ . '/init.php';

// KRITICKÉ: Vyžadovat admin session
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins; background: #fff; color: #000; padding: 40px; text-align: center;"><h1>❌ PŘÍSTUP ODEPŘEN</h1><p>Pouze pro administrátory!</p></body></html>');
}

try {
    $pdo = getDbConnection();

    // Seznam indexů k přidání
    $indexy = [
        ['tabulka' => 'wgs_photos', 'sloupec' => 'created_at', 'nazev' => 'idx_photos_created'],
        ['tabulka' => 'wgs_photos', 'sloupec' => 'updated_at', 'nazev' => 'idx_photos_updated'],
        ['tabulka' => 'wgs_registration_keys', 'sloupec' => 'created_at', 'nazev' => 'idx_regkeys_created'],
        ['tabulka' => 'wgs_reklamace', 'sloupec' => 'email', 'nazev' => 'idx_reklamace_email'],
        ['tabulka' => 'wgs_reklamace', 'sloupec' => 'updated_at', 'nazev' => 'idx_reklamace_updated'],
        ['tabulka' => 'wgs_settings', 'sloupec' => 'updated_at', 'nazev' => 'idx_settings_updated'],
        ['tabulka' => 'wgs_smtp_settings', 'sloupec' => 'created_at', 'nazev' => 'idx_smtp_created'],
        ['tabulka' => 'wgs_smtp_settings', 'sloupec' => 'updated_at', 'nazev' => 'idx_smtp_updated'],
        ['tabulka' => 'wgs_technici', 'sloupec' => 'created_at', 'nazev' => 'idx_technici_created'],
        ['tabulka' => 'wgs_tokens', 'sloupec' => 'created_at', 'nazev' => 'idx_tokens_created'],
        ['tabulka' => 'wgs_tokens', 'sloupec' => 'expires_at', 'nazev' => 'idx_tokens_expires'],
    ];

    $vysledky = [];
    $pridano = 0;
    $preskoceno = 0;

    foreach ($indexy as $index) {
        $tabulka = $index['tabulka'];
        $sloupec = $index['sloupec'];
        $nazev = $index['nazev'];

        // Zkontrolovat zda index už neexistuje
        $stmt = $pdo->query("SHOW INDEX FROM `$tabulka` WHERE Key_name = '$nazev'");
        $existuje = $stmt->rowCount() > 0;

        if ($existuje) {
            $vysledky[] = [
                'status' => 'skip',
                'tabulka' => $tabulka,
                'sloupec' => $sloupec,
                'nazev' => $nazev,
                'zprava' => 'Index již existuje'
            ];
            $preskoceno++;
            continue;
        }

        // Přidat index
        try {
            $sql = "ALTER TABLE `$tabulka` ADD INDEX `$nazev` (`$sloupec`)";
            $pdo->exec($sql);

            $vysledky[] = [
                'status' => 'success',
                'tabulka' => $tabulka,
                'sloupec' => $sloupec,
                'nazev' => $nazev,
                'zprava' => 'Index úspěšně přidán'
            ];
            $pridano++;
        } catch (PDOException $e) {
            $vysledky[] = [
                'status' => 'error',
                'tabulka' => $tabulka,
                'sloupec' => $sloupec,
                'nazev' => $nazev,
                'zprava' => 'Chyba: ' . $e->getMessage()
            ];
        }
    }

} catch (Exception $e) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Chyba</title></head><body style="font-family: Poppins; padding: 40px;"><h1 style="color: #cc0000;">❌ CHYBA</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přidání chybějících indexů | WGS</title>
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
            max-width: 1200px;
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            margin-bottom: 0.5rem;
        }
        .stat-value.success { color: #22c55e; }
        .stat-value.skip { color: #888; }
        .stat-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            border: 2px solid #000;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #000;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.85rem;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 2px;
        }
        .badge.success {
            background: #22c55e;
            color: #fff;
        }
        .badge.skip {
            background: #888;
            color: #fff;
        }
        .badge.error {
            background: #ef4444;
            color: #fff;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 PŘIDÁNÍ CHYBĚJÍCÍCH INDEXŮ</h1>
            <p style="margin-top: 0.5rem; opacity: 0.9;">Zrychlení databázových dotazů přidáním indexů na často používané sloupce</p>
        </div>

        <div class="content">
            <!-- STATISTIKA -->
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-value success"><?php echo $pridano; ?></div>
                    <div class="stat-label">Indexů přidáno</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value skip"><?php echo $preskoceno; ?></div>
                    <div class="stat-label">Indexů přeskočeno</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo count($indexy); ?></div>
                    <div class="stat-label">Celkem kontrolováno</div>
                </div>
            </div>

            <!-- DETAILNÍ VÝSLEDKY -->
            <h2 style="margin: 2rem 0 1rem 0; padding-bottom: 0.75rem; border-bottom: 2px solid #000; font-size: 1.1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;">📊 Detailní výsledky</h2>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>Tabulka</th>
                        <th>Sloupec</th>
                        <th>Název indexu</th>
                        <th>Zpráva</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($vysledky as $vysledek): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td>
                            <span class="badge <?php echo $vysledek['status']; ?>">
                                <?php
                                    if ($vysledek['status'] === 'success') echo '✅ SUCCESS';
                                    elseif ($vysledek['status'] === 'skip') echo '⏭️ SKIP';
                                    else echo '❌ ERROR';
                                ?>
                            </span>
                        </td>
                        <td><strong><?php echo htmlspecialchars($vysledek['tabulka']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($vysledek['sloupec']); ?></code></td>
                        <td><code><?php echo htmlspecialchars($vysledek['nazev']); ?></code></td>
                        <td><?php echo htmlspecialchars($vysledek['zprava']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pridano > 0): ?>
            <div style="background: #f0fdf4; border: 2px solid #22c55e; padding: 1.5rem; margin: 2rem 0;">
                <strong style="color: #15803d;">✅ ÚSPĚCH!</strong><br>
                Bylo přidáno <strong><?php echo $pridano; ?></strong> nových indexů. Databázové dotazy by měly být nyní rychlejší.
            </div>
            <?php endif; ?>

            <?php if ($pridano === 0 && $preskoceno === count($indexy)): ?>
            <div style="background: #f5f5f5; border: 2px solid #888; padding: 1.5rem; margin: 2rem 0;">
                <strong>ℹ️ INFORMACE</strong><br>
                Všechny indexy již existují. Není potřeba přidávat nic nového.
            </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="admin.php">← Zpět do Admin Panelu</a> |
            <a href="vsechny_tabulky.php">Zobrazit SQL tabulky</a>
        </div>
    </div>
</body>
</html>
