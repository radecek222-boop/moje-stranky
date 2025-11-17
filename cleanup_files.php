<?php
/**
 * CLEANUP PHP SOUBORŮ - Smazání zastaralých souborů
 * BEZPEČNOST: Pouze pro přihlášené administrátory
 * ⚠️ DŮLEŽITÉ: Před spuštěním udělej BACKUP souborů!
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

// Definice souborů k smazání (podle ANALYZA_ZASTARALYCH_SOUBORU.md)
$souboryKSmazani = [
    'diagnostic' => [
        'check_admin_hash.php' => 'Test admin hash',
        'check_all_control_files.php' => 'Kontrola souborů',
        'check_hotfix_status.php' => 'Kontrola hotfixů',
        'diagnose_geoapify.php' => 'Diagnostika Geoapify',
        'diagnose_system.php' => 'Systémová diagnostika',
        'find_geoapify_key.php' => 'Hledání API klíče',
        'find_syntax_error.php' => 'Hledání syntax chyb',
        'system_check.php' => 'Systémová kontrola',
        'validate_tools.php' => 'Validace nástrojů',
        'test_db_connection.php' => 'Test DB (už zabezpečený, ale nepotřebný)',
        'test_tile_response.php' => 'Test map tiles',
        'test_tile_simple.php' => 'Test map tiles simplified',
        'pure_db_test.php' => 'Pure DB test (už zabezpečený, ale nepotřebný)',
        'zjisti_constants.php' => 'Zobrazení PHP konstant',
        'zjisti_databazi.php' => 'Zjištění DB info',
        'zjisti_env.php' => 'Zobrazení .env',
        'zjisti_php_config.php' => 'PHP konfigurace',
        'zjisti_strukturu.php' => 'Struktura databáze',
        'show_file_content.php' => 'Zobrazení obsahu souboru',
        'zobraz_skutecnou_strukturu.php' => 'CLI verze (duplikát db_struktura.php)',
    ],
    'setup_migration' => [
        'add_indexes.php' => 'Přidání indexů (už provedeno)',
        'oprav_chybejici_sloupce.php' => 'Oprava sloupců (už provedeno)',
        'oprav_vse.php' => 'One-click oprava (už provedeno)',
        'oprava_databaze_2025_11_16.php' => 'Migrace z 16.11. (už provedeno)',
        'run_migration_simple.php' => 'Spuštění migrace',
        'smaz_lock.php' => 'Smazání lock souboru',
        'create_env.php' => 'Vytvoření .env (už provedeno)',
        'setup_env.php' => 'Setup .env (už provedeno)',
        'aktualizuj_databazi.php' => 'Aktualizace DB credentials (už provedeno)',
        'setup_actions_system.php' => 'Setup systému akcí',
        'add_optimization_tasks.php' => 'Přidání optimalizačních tasků',
    ],
    'cleanup' => [
        'cleanup_failed_emails.php' => 'Cleanup neúspěšných emailů',
        'cleanup_history_record.php' => 'Cleanup historie',
        'cleanup_logs_and_backup.php' => 'Cleanup logů a backupů',
        'quick_cleanup.php' => 'Rychlý cleanup',
        'verify_and_cleanup.php' => 'Verifikace a cleanup',
    ],
    'hotfix' => [
        'hotfix_csrf.php' => 'CSRF hotfix (už opraveno v kódu)',
        'fix_visibility.php' => 'Oprava viditelnosti (už opraveno)',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_phase'])) {
    $provedeno = true;
    $phase = $_POST['cleanup_phase'];

    if (!isset($souboryKSmazani[$phase])) {
        $chyby[] = "Neznámá fáze: $phase";
    } else {
        $soubory = $souboryKSmazani[$phase];

        foreach ($soubory as $nazev => $duvod) {
            $cesta = __DIR__ . '/' . $nazev;

            if (!file_exists($cesta)) {
                $vysledky[] = "⊙ Soubor '$nazev' už neexistuje - přeskakuji";
                continue;
            }

            // Smazat soubor
            if (unlink($cesta)) {
                $vysledky[] = "✅ Soubor '$nazev' smazán ($duvod)";
            } else {
                $chyby[] = "❌ Chyba při mazání '$nazev' - nemám oprávnění";
            }
        }

        $vysledky[] = "";
        $vysledky[] = "✅ Fáze '$phase' dokončena!";
    }
}

// Spočítat aktuální stav
$vsechnySoubory = glob(__DIR__ . '/*.php');
$pocetSouboru = count($vsechnySoubory);

// Kolik souborů zbývá smazat
$zbyvajiciKSmazani = [];
foreach ($souboryKSmazani as $kategorie => $soubory) {
    foreach ($soubory as $nazev => $duvod) {
        if (file_exists(__DIR__ . '/' . $nazev)) {
            $zbyvajiciKSmazani[$kategorie][] = $nazev;
        }
    }
}

$celkemKSmazani = 0;
foreach ($zbyvajiciKSmazani as $soubory) {
    $celkemKSmazani += count($soubory);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup PHP Souborů | WGS</title>
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
        .file-list {
            margin: 1rem 0;
            padding: 1rem;
            background: #f5f5f5;
            font-size: 0.85rem;
            line-height: 1.8;
            max-height: 300px;
            overflow-y: auto;
        }
        .file-list ul {
            list-style: none;
            padding: 0;
        }
        .file-list li {
            margin: 0.3rem 0;
            padding-left: 1.5rem;
            position: relative;
        }
        .file-list li:before {
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
            <h1>🧹 CLEANUP PHP SOUBORŮ</h1>
            <p style="margin-top: 0.5rem; opacity: 0.9;">Smazání zastaralých PHP souborů z root adresáře</p>
        </div>

        <div class="warning">
            <h2>⚠️ KRITICKÉ UPOZORNĚNÍ</h2>
            <p><strong>PŘED JAKÝMKOLIV SMAZÁNÍM UDĚLAJ BACKUP SOUBORŮ!</strong></p>
            <p style="margin-top: 1rem;">Tato akce je NEVRATNÁ. Smazané soubory nelze obnovit bez backupu nebo git.</p>
            <p style="margin-top: 0.5rem;">📋 <a href="ANALYZA_ZASTARALYCH_SOUBORU.md" style="color: #000; text-decoration: underline;">Přečti si ANALYZA_ZASTARALYCH_SOUBORU.md</a> před pokračováním!</p>
        </div>

        <div class="content">
            <!-- AKTUÁLNÍ STAV -->
            <h2 style="margin-bottom: 1.5rem; border-bottom: 2px solid #000; padding-bottom: 0.75rem;">📊 Aktuální stav</h2>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $pocetSouboru; ?></div>
                    <div class="stat-label">PHP souborů v root</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $celkemKSmazani; ?></div>
                    <div class="stat-label">Souborů ke smazání</div>
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

                <!-- Fáze 1: Diagnostic -->
                <div class="phase-section">
                    <div class="phase-header">
                        <div>
                            <div class="phase-title">Fáze 1: Diagnostic/Debug soubory</div>
                            <p style="color: #555; font-size: 0.9rem; margin-top: 0.5rem;">
                                <?php echo isset($zbyvajiciKSmazani['diagnostic']) ? count($zbyvajiciKSmazani['diagnostic']) : 0; ?> souborů ke smazání
                            </p>
                        </div>
                        <form method="POST" onsubmit="return confirm('Opravdu chceš smazat diagnostické soubory?')">
                            <input type="hidden" name="cleanup_phase" value="diagnostic">
                            <button type="submit" class="btn" <?php echo empty($zbyvajiciKSmazani['diagnostic']) ? 'disabled' : ''; ?>>
                                <?php echo empty($zbyvajiciKSmazani['diagnostic']) ? '✅ Hotovo' : 'Smazat diagnostic'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if (!empty($zbyvajiciKSmazani['diagnostic'])): ?>
                        <div class="file-list">
                            <ul>
                                <?php foreach ($zbyvajiciKSmazani['diagnostic'] as $soubor): ?>
                                    <li><strong><?php echo htmlspecialchars($soubor); ?></strong> - <?php echo htmlspecialchars($souboryKSmazani['diagnostic'][$soubor]); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="success">✅ Diagnostické soubory již byly smazány</div>
                    <?php endif; ?>
                </div>

                <!-- Fáze 2: Setup/Migration -->
                <div class="phase-section">
                    <div class="phase-header">
                        <div>
                            <div class="phase-title">Fáze 2: Setup/Migration soubory</div>
                            <p style="color: #555; font-size: 0.9rem; margin-top: 0.5rem;">
                                <?php echo isset($zbyvajiciKSmazani['setup_migration']) ? count($zbyvajiciKSmazani['setup_migration']) : 0; ?> souborů ke smazání
                            </p>
                        </div>
                        <form method="POST" onsubmit="return confirm('Opravdu chceš smazat setup/migration soubory?')">
                            <input type="hidden" name="cleanup_phase" value="setup_migration">
                            <button type="submit" class="btn" <?php echo empty($zbyvajiciKSmazani['setup_migration']) ? 'disabled' : ''; ?>>
                                <?php echo empty($zbyvajiciKSmazani['setup_migration']) ? '✅ Hotovo' : 'Smazat setup/migration'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if (!empty($zbyvajiciKSmazani['setup_migration'])): ?>
                        <div class="file-list">
                            <ul>
                                <?php foreach ($zbyvajiciKSmazani['setup_migration'] as $soubor): ?>
                                    <li><strong><?php echo htmlspecialchars($soubor); ?></strong> - <?php echo htmlspecialchars($souboryKSmazani['setup_migration'][$soubor]); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="success">✅ Setup/Migration soubory již byly smazány</div>
                    <?php endif; ?>
                </div>

                <!-- Fáze 3: Cleanup -->
                <div class="phase-section">
                    <div class="phase-header">
                        <div>
                            <div class="phase-title">Fáze 3: Cleanup soubory</div>
                            <p style="color: #555; font-size: 0.9rem; margin-top: 0.5rem;">
                                <?php echo isset($zbyvajiciKSmazani['cleanup']) ? count($zbyvajiciKSmazani['cleanup']) : 0; ?> souborů ke smazání
                            </p>
                        </div>
                        <form method="POST" onsubmit="return confirm('Opravdu chceš smazat cleanup soubory?')">
                            <input type="hidden" name="cleanup_phase" value="cleanup">
                            <button type="submit" class="btn" <?php echo empty($zbyvajiciKSmazani['cleanup']) ? 'disabled' : ''; ?>>
                                <?php echo empty($zbyvajiciKSmazani['cleanup']) ? '✅ Hotovo' : 'Smazat cleanup'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if (!empty($zbyvajiciKSmazani['cleanup'])): ?>
                        <div class="file-list">
                            <ul>
                                <?php foreach ($zbyvajiciKSmazani['cleanup'] as $soubor): ?>
                                    <li><strong><?php echo htmlspecialchars($soubor); ?></strong> - <?php echo htmlspecialchars($souboryKSmazani['cleanup'][$soubor]); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="success">✅ Cleanup soubory již byly smazány</div>
                    <?php endif; ?>
                </div>

                <!-- Fáze 4: Hotfix -->
                <div class="phase-section">
                    <div class="phase-header">
                        <div>
                            <div class="phase-title">Fáze 4: Hotfix soubory</div>
                            <p style="color: #555; font-size: 0.9rem; margin-top: 0.5rem;">
                                <?php echo isset($zbyvajiciKSmazani['hotfix']) ? count($zbyvajiciKSmazani['hotfix']) : 0; ?> souborů ke smazání
                            </p>
                        </div>
                        <form method="POST" onsubmit="return confirm('Opravdu chceš smazat hotfix soubory?')">
                            <input type="hidden" name="cleanup_phase" value="hotfix">
                            <button type="submit" class="btn danger" <?php echo empty($zbyvajiciKSmazani['hotfix']) ? 'disabled' : ''; ?>>
                                <?php echo empty($zbyvajiciKSmazani['hotfix']) ? '✅ Hotovo' : 'Smazat hotfix'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if (!empty($zbyvajiciKSmazani['hotfix'])): ?>
                        <div class="file-list">
                            <ul>
                                <?php foreach ($zbyvajiciKSmazani['hotfix'] as $soubor): ?>
                                    <li><strong><?php echo htmlspecialchars($soubor); ?></strong> - <?php echo htmlspecialchars($souboryKSmazani['hotfix'][$soubor]); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="success">✅ Hotfix soubory již byly smazány</div>
                    <?php endif; ?>
                </div>

                <?php if ($celkemKSmazani === 0): ?>
                    <div class="success" style="text-align: center; padding: 2rem;">
                        <h2 style="margin-bottom: 1rem;">🎉 HOTOVO!</h2>
                        <p>Všechny zastaralé soubory byly úspěšně smazány!</p>
                        <p style="margin-top: 1rem;">Root adresář je nyní čistý a přehledný.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="admin.php">← Zpět do Admin Panelu</a> |
            <a href="cleanup_database.php">Cleanup Databáze</a>
        </div>
    </div>
</body>
</html>
