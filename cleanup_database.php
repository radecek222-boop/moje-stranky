<?php
/**
 * CLEANUP DATABÁZE - Smazání zbytečných tabulek
 * BEZPEČNOST: Pouze pro přihlášené administrátory
 * ⚠️ DŮLEŽITÉ: Před spuštěním udělej BACKUP databáze!
 */

require_once __DIR__ . '/init.php';

// KRITICKÉ: Vyžadovat admin session BEZ BYPASSU
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins; background: #fff; color: #000; padding: 40px; text-align: center;"><h1>❌ PŘÍSTUP ODEPŘEN</h1><p>Pouze pro administrátory!</p><p><a href="login.php" style="color: #000; border-bottom: 2px solid #000; text-decoration: none;">→ Přihlásit se</a></p></body></html>');
}

$provedeno = false;
$vysledky = [];
$chyby = [];

// Definice tabulek k smazání
$tabulkyKSmazani = [
    'duplicitni' => [
        'registration_keys' => 'Duplicita wgs_registration_keys',
        'users' => 'Duplicita wgs_users',
    ],
    'prazdne_wgs' => [
        'wgs_analytics_visits' => 'Návštěvy se nesledují',
        'wgs_audit_log' => 'Audit log není aktivní',
        'wgs_claims' => 'Claims system není implementován',
        'wgs_content_texts' => 'Editovatelné texty nejsou použity',
        'wgs_documents' => 'Upload dokumentů není implementován',
        'wgs_github_webhooks' => 'GitHub webhooks nepoužívány',
        'wgs_notes' => 'Poznámky nejsou implementovány',
        'wgs_provize_technici' => 'Provize se nepočítají',
        'wgs_sessions' => 'Používají se PHP sessions',
    ],
    'wordpress' => [
        'wp_commentmeta' => 'Starý WordPress',
        'wp_comments' => 'Starý WordPress',
        'wp_e_events' => 'Starý Elementor',
        'wp_links' => 'Starý WordPress',
        'wp_options' => 'Starý WordPress',
        'wp_postmeta' => 'Starý WordPress',
        'wp_posts' => 'Starý WordPress',
        'wp_term_relationships' => 'Starý WordPress',
        'wp_term_taxonomy' => 'Starý WordPress',
        'wp_termmeta' => 'Starý WordPress',
        'wp_terms' => 'Starý WordPress',
        'wp_usermeta' => 'Starý WordPress',
        'wp_users' => 'Starý WordPress',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_phase'])) {
    $provedeno = true;
    $phase = $_POST['cleanup_phase'];

    try {
        $pdo = getDbConnection();

        if (!isset($tabulkyKSmazani[$phase])) {
            $chyby[] = "Neznámá fáze: $phase";
        } else {
            $tabulky = $tabulkyKSmazani[$phase];

            foreach ($tabulky as $nazev => $duvod) {
                try {
                    // Zkontrolovat jestli tabulka existuje
                    $stmt = $pdo->query("SHOW TABLES LIKE '$nazev'");
                    if ($stmt->rowCount() === 0) {
                        $vysledky[] = "⊙ Tabulka '$nazev' už neexistuje - přeskakuji";
                        continue;
                    }

                    // Smazat tabulku
                    $pdo->exec("DROP TABLE IF EXISTS `$nazev`");
                    $vysledky[] = "✅ Tabulka '$nazev' smazána ($duvod)";

                } catch (PDOException $e) {
                    $chyby[] = "❌ Chyba při mazání '$nazev': " . $e->getMessage();
                }
            }

            $vysledky[] = "";
            $vysledky[] = "✅ Fáze '$phase' dokončena!";
        }

    } catch (Exception $e) {
        $chyby[] = "❌ KRITICKÁ CHYBA: " . $e->getMessage();
    }
}

// Spočítat aktuální stav databáze
try {
    $pdo = getDbConnection();

    $stmt = $pdo->query("SHOW TABLES");
    $vsechnyTabulky = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $pocetTabulek = count($vsechnyTabulky);

    $stmt = $pdo->query("
        SELECT
            SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 AS velikost_mb
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
    ");
    $velikost = round($stmt->fetch()['velikost_mb'], 2);

    // Kolik tabulek zbývá smazat
    $zbyvajiciKSmazani = [];
    foreach ($tabulkyKSmazani as $kategorie => $tabulky) {
        foreach ($tabulky as $nazev => $duvod) {
            if (in_array($nazev, $vsechnyTabulky)) {
                $zbyvajiciKSmazani[$kategorie][] = $nazev;
            }
        }
    }

} catch (Exception $e) {
    $pocetTabulek = '?';
    $velikost = '?';
    $zbyvajiciKSmazani = [];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Databáze | WGS</title>
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
            max-width: 1000px;
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
        .warning {
            background: #ff0;
            color: #000;
            padding: 1.5rem;
            margin: 2rem;
            border: 3px solid #000;
            font-weight: 600;
        }
        .warning h2 {
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        .content {
            padding: 2rem;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .stat-box {
            border: 2px solid #ddd;
            padding: 1.5rem;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #555;
            text-transform: uppercase;
        }
        .phase-section {
            margin: 2rem 0;
            border: 2px solid #ddd;
            padding: 1.5rem;
        }
        .phase-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #000;
        }
        .phase-title {
            font-size: 1.2rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .btn {
            background: #000;
            color: #fff;
            border: 2px solid #000;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }
        .btn:hover {
            background: #fff;
            color: #000;
        }
        .btn.danger {
            background: #cc0000;
            border-color: #cc0000;
        }
        .btn.danger:hover {
            background: #fff;
            color: #cc0000;
        }
        .btn:disabled {
            background: #ccc;
            border-color: #ccc;
            color: #666;
            cursor: not-allowed;
        }
        .table-list {
            margin: 1rem 0;
            padding: 1rem;
            background: #f5f5f5;
            font-size: 0.9rem;
            line-height: 1.8;
        }
        .table-list ul {
            list-style: none;
            padding: 0;
        }
        .table-list li {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }
        .table-list li:before {
            content: "→";
            position: absolute;
            left: 0;
        }
        .output {
            background: #f5f5f5;
            border-left: 4px solid #000;
            padding: 1.5rem;
            margin: 2rem 0;
            font-family: monospace;
            font-size: 0.9rem;
            line-height: 1.8;
        }
        .error {
            color: #cc0000;
            background: #fff0f0;
            border-left: 4px solid #cc0000;
            padding: 1rem;
            margin: 0.5rem 0;
        }
        .footer {
            text-align: center;
            padding: 2rem;
            border-top: 2px solid #ddd;
            color: #555;
        }
        .footer a {
            color: #000;
            text-decoration: none;
            border-bottom: 2px solid #000;
        }
        .success {
            background: #e8f5e9;
            border-left: 4px solid #000;
            padding: 1.5rem;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧹 CLEANUP DATABÁZE</h1>
            <p style="margin-top: 0.5rem; opacity: 0.9;">Smazání zbytečných tabulek z databáze</p>
        </div>

        <div class="warning">
            <h2>⚠️ KRITICKÉ UPOZORNĚNÍ</h2>
            <p><strong>PŘED JAKÝMKOLIV SMAZÁNÍM UDĚLEJ BACKUP DATABÁZE!</strong></p>
            <p style="margin-top: 1rem;">Tato akce je NEVRATNÁ. Smazané tabulky nelze obnovit bez backupu.</p>
            <p style="margin-top: 0.5rem;">📋 <a href="ANALYZA_SQL_TABULEK.md" style="color: #000; text-decoration: underline;">Přečti si ANALYZA_SQL_TABULEK.md</a> před pokračováním!</p>
        </div>

        <div class="content">
            <!-- AKTUÁLNÍ STAV -->
            <h2 style="margin-bottom: 1.5rem; border-bottom: 2px solid #000; padding-bottom: 0.75rem;">📊 Aktuální stav databáze</h2>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $pocetTabulek; ?></div>
                    <div class="stat-label">Tabulek celkem</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $velikost; ?> MB</div>
                    <div class="stat-label">Velikost</div>
                </div>
            </div>

            <?php if ($provedeno): ?>
                <!-- VÝSLEDKY -->
                <div class="output">
                    <?php foreach ($vysledky as $vysledek): ?>
                        <div><?php echo htmlspecialchars($vysledek); ?></div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($chyby)): ?>
                    <?php foreach ($chyby as $chyba): ?>
                        <div class="error"><?php echo htmlspecialchars($chyba); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 2rem;">
                    <a href="?" class="btn">← Zpět</a>
                </div>
            <?php else: ?>
                <!-- FÁZE CLEANUP -->

                <!-- Fáze 1: Duplicitní -->
                <div class="phase-section">
                    <div class="phase-header">
                        <div>
                            <div class="phase-title">Fáze 1: Duplicitní tabulky</div>
                            <p style="color: #555; font-size: 0.9rem; margin-top: 0.5rem;">
                                <?php echo isset($zbyvajiciKSmazani['duplicitni']) ? count($zbyvajiciKSmazani['duplicitni']) : 0; ?> tabulek ke smazání
                            </p>
                        </div>
                        <form method="POST" onsubmit="return confirm('Opravdu chceš smazat duplicitní tabulky?')">
                            <input type="hidden" name="cleanup_phase" value="duplicitni">
                            <button type="submit" class="btn" <?php echo empty($zbyvajiciKSmazani['duplicitni']) ? 'disabled' : ''; ?>>
                                <?php echo empty($zbyvajiciKSmazani['duplicitni']) ? '✅ Hotovo' : 'Smazat duplicity'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if (!empty($zbyvajiciKSmazani['duplicitni'])): ?>
                        <div class="table-list">
                            <ul>
                                <?php foreach ($zbyvajiciKSmazani['duplicitni'] as $tabulka): ?>
                                    <li><strong><?php echo htmlspecialchars($tabulka); ?></strong> - <?php echo htmlspecialchars($tabulkyKSmazani['duplicitni'][$tabulka]); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="success">✅ Duplicitní tabulky již byly smazány</div>
                    <?php endif; ?>
                </div>

                <!-- Fáze 2: Prázdné WGS -->
                <div class="phase-section">
                    <div class="phase-header">
                        <div>
                            <div class="phase-title">Fáze 2: Prázdné WGS tabulky</div>
                            <p style="color: #555; font-size: 0.9rem; margin-top: 0.5rem;">
                                <?php echo isset($zbyvajiciKSmazani['prazdne_wgs']) ? count($zbyvajiciKSmazani['prazdne_wgs']) : 0; ?> tabulek ke smazání
                            </p>
                        </div>
                        <form method="POST" onsubmit="return confirm('Opravdu chceš smazat prázdné WGS tabulky?')">
                            <input type="hidden" name="cleanup_phase" value="prazdne_wgs">
                            <button type="submit" class="btn" <?php echo empty($zbyvajiciKSmazani['prazdne_wgs']) ? 'disabled' : ''; ?>>
                                <?php echo empty($zbyvajiciKSmazani['prazdne_wgs']) ? '✅ Hotovo' : 'Smazat prázdné'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if (!empty($zbyvajiciKSmazani['prazdne_wgs'])): ?>
                        <div class="table-list">
                            <ul>
                                <?php foreach ($zbyvajiciKSmazani['prazdne_wgs'] as $tabulka): ?>
                                    <li><strong><?php echo htmlspecialchars($tabulka); ?></strong> - <?php echo htmlspecialchars($tabulkyKSmazani['prazdne_wgs'][$tabulka]); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="success">✅ Prázdné WGS tabulky již byly smazány</div>
                    <?php endif; ?>
                </div>

                <!-- Fáze 3: WordPress -->
                <div class="phase-section">
                    <div class="phase-header">
                        <div>
                            <div class="phase-title">Fáze 3: WordPress tabulky</div>
                            <p style="color: #555; font-size: 0.9rem; margin-top: 0.5rem;">
                                <?php echo isset($zbyvajiciKSmazani['wordpress']) ? count($zbyvajiciKSmazani['wordpress']) : 0; ?> tabulek ke smazání
                            </p>
                        </div>
                        <form method="POST" onsubmit="return confirm('POZOR: Smažeš 13 WordPress tabulek! Máš backup? Opravdu pokračovat?')">
                            <input type="hidden" name="cleanup_phase" value="wordpress">
                            <button type="submit" class="btn danger" <?php echo empty($zbyvajiciKSmazani['wordpress']) ? 'disabled' : ''; ?>>
                                <?php echo empty($zbyvajiciKSmazani['wordpress']) ? '✅ Hotovo' : 'Smazat WordPress'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if (!empty($zbyvajiciKSmazani['wordpress'])): ?>
                        <div class="table-list">
                            <ul>
                                <?php foreach ($zbyvajiciKSmazani['wordpress'] as $tabulka): ?>
                                    <li><strong><?php echo htmlspecialchars($tabulka); ?></strong> - <?php echo htmlspecialchars($tabulkyKSmazani['wordpress'][$tabulka]); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="success">✅ WordPress tabulky již byly smazány</div>
                    <?php endif; ?>
                </div>

                <?php if (empty($zbyvajiciKSmazani['duplicitni']) && empty($zbyvajiciKSmazani['prazdne_wgs']) && empty($zbyvajiciKSmazani['wordpress'])): ?>
                    <div class="success" style="text-align: center; padding: 2rem;">
                        <h2 style="margin-bottom: 1rem;">🎉 HOTOVO!</h2>
                        <p>Všechny zbytečné tabulky byly úspěšně smazány!</p>
                        <p style="margin-top: 1rem;">Databáze je nyní čistá a optimalizovaná.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="vsechny_tabulky.php">Zobrazit všechny tabulky</a> |
            <a href="admin.php">← Zpět do Admin Panelu</a>
        </div>
    </div>
</body>
</html>
