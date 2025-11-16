<?php
/**
 * MIGRACE DATABÁZE: Oprava chybějících sloupců v wgs_reklamace
 * Datum: 2025-11-16
 * Účel: Přidat chybějící sloupce (technik, prodejce, ulice, psc, castka, zeme)
 *
 * BEZPEČNOST:
 * - Pouze pro přihlášené adminy
 * - Jednorázové spuštění (vytvoří lock soubor)
 * - CSRF ochrana
 * - Detailní logování
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// =====================================================
// BEZPEČNOSTNÍ KONTROLA
// =====================================================

// Kontrola admin přístupu
$jeAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$jeAdmin) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center;"><h1 style="color: #000;">❌ PŘÍSTUP ODEPŘEN</h1><p>Pouze administrátor může spustit tento migrační skript.</p><p><a href="/admin.php" style="color: #000; border-bottom: 2px solid #000;">← Zpět do admin panelu</a></p></body></html>');
}

// Lock soubor - zamezit opakovanému spuštění
$lockFile = __DIR__ . '/migrations/.lock_oprava_2025_11_16';
$uzSpusteno = file_exists($lockFile);

// Pokud je GET request, zobrazit formulář s CSRF tokenem
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $csrfToken = generateCSRFToken();
    ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oprava databáze WGS | 2025-11-16</title>
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
            max-width: 900px;
            margin: 0 auto;
            border: 2px solid #000;
            background: #fff;
        }
        .header {
            background: #000;
            color: #fff;
            padding: 2rem;
            border-bottom: 2px solid #000;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
            font-weight: 300;
        }
        .content {
            padding: 2rem;
        }
        .section {
            margin-bottom: 2rem;
        }
        h2 {
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
            border-bottom: 2px solid #000;
            padding-bottom: 0.5rem;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }
        li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #000;
            font-weight: 700;
        }
        .warning {
            background: #fff9e6;
            border-left: 4px solid #ff9900;
            padding: 1rem;
            margin: 1rem 0;
        }
        .success {
            background: #f0fff0;
            border-left: 4px solid #006600;
            padding: 1rem;
            margin: 1rem 0;
        }
        .error {
            background: #fff0f0;
            border-left: 4px solid #cc0000;
            padding: 1rem;
            margin: 1rem 0;
        }
        .btn {
            background: #000;
            color: #fff;
            border: 2px solid #000;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        .btn:hover {
            background: #fff;
            color: #000;
        }
        .btn:disabled {
            background: #999;
            border-color: #999;
            cursor: not-allowed;
            opacity: 0.5;
        }
        .btn-secondary {
            background: #fff;
            color: #000;
            border: 2px solid #000;
        }
        .btn-secondary:hover {
            background: #000;
            color: #fff;
        }
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        code {
            background: #f5f5f5;
            padding: 0.2rem 0.5rem;
            border: 1px solid #ddd;
            font-family: monospace;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Oprava databáze WGS</h1>
            <p class="subtitle">Migrace: Přidání chybějících sloupců (2025-11-16)</p>
        </div>

        <div class="content">
            <?php if ($uzSpusteno): ?>
                <div class="success">
                    <h2 style="border: none; margin-bottom: 0.5rem;">✓ MIGRACE JIŽ BYLA PROVEDENA</h2>
                    <p>Tento migrační skript byl již jednou spuštěn.</p>
                    <p style="margin-top: 0.5rem;"><strong>Lock soubor:</strong> <code><?php echo htmlspecialchars($lockFile); ?></code></p>
                </div>

                <div class="actions">
                    <a href="admin.php" class="btn-secondary" style="display: inline-block; text-decoration: none; text-align: center;">← ZPĚT DO ADMIN PANELU</a>
                </div>
            <?php else: ?>
                <div class="section">
                    <h2>Co se provede</h2>
                    <p>Tento skript přidá následující chybějící sloupce do tabulky <code>wgs_reklamace</code>:</p>

                    <ul>
                        <li><code>ulice</code> VARCHAR(255) - Ulice a číslo popisné</li>
                        <li><code>psc</code> VARCHAR(20) - PSČ</li>
                        <li><code>prodejce</code> VARCHAR(255) - Jméno prodejce (pro statistiky)</li>
                        <li><code>technik</code> VARCHAR(255) - Jméno technika (KRITICKÉ!)</li>
                        <li><code>castka</code> DECIMAL(10,2) - Částka (duplikát cena)</li>
                        <li><code>zeme</code> VARCHAR(2) - CZ/SK (duplikát fakturace_firma)</li>
                    </ul>

                    <p style="margin-top: 1rem;">Vytvoří také indexy pro rychlejší vyhledávání.</p>
                </div>

                <div class="section">
                    <h2>Proč je to potřeba</h2>
                    <ul>
                        <li>Bez sloupce <code>technik</code> nefungují protokoly</li>
                        <li>Bez sloupců <code>prodejce</code>, <code>castka</code>, <code>zeme</code> nefungují statistiky</li>
                        <li>Bez sloupců <code>ulice</code>, <code>psc</code> se ztrácejí data z formuláře</li>
                    </ul>
                </div>

                <div class="warning">
                    <h2 style="border: none; margin-bottom: 0.5rem;">⚠️ DŮLEŽITÉ</h2>
                    <ul>
                        <li>Tato operace upravuje strukturu databáze</li>
                        <li>Doporučujeme mít zálohu databáze</li>
                        <li>Skript lze spustit pouze jednou (vytvoří lock soubor)</li>
                        <li>Operace je BEZPEČNÁ - pouze přidává sloupce, nemazaže data</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="potvrzeni" value="ano">

                    <div class="actions">
                        <button type="submit" class="btn">SPUSTIT MIGRACI</button>
                        <a href="admin.php" class="btn-secondary" style="display: inline-block; text-decoration: none; text-align: center; line-height: 1rem; padding-top: 1.2rem;">ZRUŠIT</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// =====================================================
// POST REQUEST - PROVÉST MIGRACI
// =====================================================

// CSRF kontrola
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Neplatný CSRF token. Obnovte stránku a zkuste znovu.');
}

// Kontrola potvrzení
if (($_POST['potvrzeni'] ?? '') !== 'ano') {
    http_response_code(400);
    die('Chybí potvrzení.');
}

// Kontrola lock souboru
if ($uzSpusteno) {
    http_response_code(400);
    die('Migrace již byla provedena. Skript nelze spustit podruhé.');
}

// =====================================================
// SPUŠTĚNÍ MIGRACE
// =====================================================

$vysledky = [];
$chyby = [];
$uspech = true;

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Log začátku
    $vysledky[] = "✓ Připojení k databázi úspěšné";

    // ==========================================
    // KROK 1: Přidat sloupce
    // ==========================================

    $sloupce = [
        ['nazev' => 'ulice', 'definice' => 'VARCHAR(255) DEFAULT NULL', 'komentar' => 'Ulice a číslo popisné', 'po' => 'adresa'],
        ['nazev' => 'psc', 'definice' => 'VARCHAR(20) DEFAULT NULL', 'komentar' => 'PSČ', 'po' => 'mesto'],
        ['nazev' => 'prodejce', 'definice' => 'VARCHAR(255) DEFAULT NULL', 'komentar' => 'Jméno prodejce', 'po' => 'zpracoval_id'],
        ['nazev' => 'technik', 'definice' => 'VARCHAR(255) DEFAULT NULL', 'komentar' => 'Jméno technika', 'po' => 'prodejce'],
        ['nazev' => 'castka', 'definice' => 'DECIMAL(10,2) DEFAULT NULL', 'komentar' => 'Částka (duplikát cena)', 'po' => 'technik'],
        ['nazev' => 'zeme', 'definice' => 'VARCHAR(2) DEFAULT NULL', 'komentar' => 'CZ/SK (duplikát fakturace_firma)', 'po' => 'castka'],
    ];

    foreach ($sloupce as $sloupec) {
        try {
            // Zkontrolovat jestli sloupec už existuje
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE '{$sloupec['nazev']}'");
            $existuje = $stmt->rowCount() > 0;

            if ($existuje) {
                $vysledky[] = "⊙ Sloupec '{$sloupec['nazev']}' už existuje - přeskočeno";
            } else {
                // Přidat sloupec
                $sql = "ALTER TABLE wgs_reklamace
                        ADD COLUMN {$sloupec['nazev']} {$sloupec['definice']}
                        COMMENT '{$sloupec['komentar']}'
                        AFTER {$sloupec['po']}";

                $pdo->exec($sql);
                $vysledky[] = "✓ Přidán sloupec '{$sloupec['nazev']}' ({$sloupec['definice']})";
            }
        } catch (PDOException $e) {
            $chyby[] = "✗ Chyba při přidávání sloupce '{$sloupec['nazev']}': " . $e->getMessage();
            $uspech = false;
        }
    }

    // ==========================================
    // KROK 2: Vytvořit indexy
    // ==========================================

    $indexy = [
        ['nazev' => 'idx_prodejce', 'sloupec' => 'prodejce'],
        ['nazev' => 'idx_technik', 'sloupec' => 'technik'],
        ['nazev' => 'idx_zeme', 'sloupec' => 'zeme'],
        ['nazev' => 'idx_ulice', 'sloupec' => 'ulice'],
    ];

    foreach ($indexy as $index) {
        try {
            // Zkontrolovat jestli index už existuje
            $stmt = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = '{$index['nazev']}'");
            $existuje = $stmt->rowCount() > 0;

            if ($existuje) {
                $vysledky[] = "⊙ Index '{$index['nazev']}' už existuje - přeskočeno";
            } else {
                $sql = "CREATE INDEX {$index['nazev']} ON wgs_reklamace({$index['sloupec']})";
                $pdo->exec($sql);
                $vysledky[] = "✓ Vytvořen index '{$index['nazev']}' pro sloupec '{$index['sloupec']}'";
            }
        } catch (PDOException $e) {
            $chyby[] = "✗ Chyba při vytváření indexu '{$index['nazev']}': " . $e->getMessage();
            // Neoznačovat jako chybu - indexy nejsou kritické
        }
    }

    // ==========================================
    // KROK 3: Naplnit data z existujících sloupců
    // ==========================================

    try {
        // castka = cena
        $stmt = $pdo->exec("UPDATE wgs_reklamace SET castka = cena WHERE castka IS NULL AND cena IS NOT NULL");
        $vysledky[] = "✓ Naplněno {$stmt} záznamů: castka = cena";
    } catch (PDOException $e) {
        $chyby[] = "✗ Chyba při naplňování castka: " . $e->getMessage();
    }

    try {
        // zeme = UPPER(fakturace_firma)
        $stmt = $pdo->exec("UPDATE wgs_reklamace SET zeme = UPPER(fakturace_firma) WHERE zeme IS NULL AND fakturace_firma IS NOT NULL");
        $vysledky[] = "✓ Naplněno {$stmt} záznamů: zeme = fakturace_firma";
    } catch (PDOException $e) {
        $chyby[] = "✗ Chyba při naplňování zeme: " . $e->getMessage();
    }

    try {
        // prodejce = zpracoval
        $stmt = $pdo->exec("UPDATE wgs_reklamace SET prodejce = zpracoval WHERE prodejce IS NULL AND zpracoval IS NOT NULL AND zpracoval != ''");
        $vysledky[] = "✓ Naplněno {$stmt} záznamů: prodejce = zpracoval";
    } catch (PDOException $e) {
        $chyby[] = "✗ Chyba při naplňování prodejce: " . $e->getMessage();
    }

    // ==========================================
    // KROK 4: Vytvořit lock soubor
    // ==========================================

    if ($uspech && empty($chyby)) {
        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        $lockContent = json_encode([
            'datum' => date('Y-m-d H:i:s'),
            'admin' => $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'vysledky' => $vysledky
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        file_put_contents($lockFile, $lockContent);
        $vysledky[] = "✓ Vytvořen lock soubor: {$lockFile}";
    }

    // ==========================================
    // KROK 5: Ověření výsledku
    // ==========================================

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as celkem,
            COUNT(technik) as ma_technika,
            COUNT(prodejce) as ma_prodejce,
            COUNT(mesto) as ma_mesto,
            COUNT(ulice) as ma_ulici,
            COUNT(castka) as ma_castku
        FROM wgs_reklamace
    ");
    $statistika = $stmt->fetch(PDO::FETCH_ASSOC);

    $vysledky[] = "✓ Ověření: {$statistika['celkem']} celkových záznamů";
    $vysledky[] = "  - {$statistika['ma_technika']} má vyplněného technika";
    $vysledky[] = "  - {$statistika['ma_prodejce']} má vyplněného prodejce";
    $vysledky[] = "  - {$statistika['ma_mesto']} má vyplněné město";
    $vysledky[] = "  - {$statistika['ma_ulici']} má vyplněnou ulici";
    $vysledky[] = "  - {$statistika['ma_castku']} má vyplněnou částku";

} catch (Exception $e) {
    $chyby[] = "✗ KRITICKÁ CHYBA: " . $e->getMessage();
    $uspech = false;
}

// =====================================================
// ZOBRAZENÍ VÝSLEDKU
// =====================================================
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $uspech && empty($chyby) ? 'Migrace úspěšná' : 'Chyba migrace'; ?> | WGS</title>
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
            max-width: 900px;
            margin: 0 auto;
            border: 2px solid #000;
            background: #fff;
        }
        .header {
            background: <?php echo $uspech && empty($chyby) ? '#006600' : '#cc0000'; ?>;
            color: #fff;
            padding: 2rem;
            border-bottom: 2px solid #000;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }
        .content {
            padding: 2rem;
        }
        h2 {
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 2rem 0 1rem 0;
            border-bottom: 2px solid #000;
            padding-bottom: 0.5rem;
        }
        h2:first-child {
            margin-top: 0;
        }
        .log {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            max-height: 400px;
            overflow-y: auto;
            line-height: 1.6;
        }
        .log-item {
            margin: 0.3rem 0;
        }
        .error-log {
            background: #fff0f0;
            border-color: #cc0000;
        }
        .btn {
            background: #000;
            color: #fff;
            border: 2px solid #000;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-decoration: none;
            display: inline-block;
            margin-top: 2rem;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #fff;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $uspech && empty($chyby) ? '✓ MIGRACE ÚSPĚŠNÁ' : '✗ CHYBA MIGRACE'; ?></h1>
            <p><?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <div class="content">
            <?php if (!empty($vysledky)): ?>
                <h2>Výsledky operací</h2>
                <div class="log">
                    <?php foreach ($vysledky as $vysledek): ?>
                        <div class="log-item"><?php echo htmlspecialchars($vysledek); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($chyby)): ?>
                <h2>Chyby</h2>
                <div class="log error-log">
                    <?php foreach ($chyby as $chyba): ?>
                        <div class="log-item"><?php echo htmlspecialchars($chyba); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a href="admin.php" class="btn">← ZPĚT DO ADMIN PANELU</a>
        </div>
    </div>
</body>
</html>
