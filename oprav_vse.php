<?php
/**
 * ONE-CLICK OPRAVA VŠEHO
 * - Test DB připojení
 * - Přidání chybějících sloupců
 * - Oprava API
 * Vše jedním kliknutím!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Načíst .env credentials
function nactiEnv() {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        return null;
    }

    $envVars = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $envVars[trim($key)] = trim($value);
        }
    }
    return $envVars;
}

$vysledky = [];
$chyby = [];
$provedeno = false;

// Pokud uživatel klikl na tlačítko
if (isset($_POST['opravit'])) {
    $provedeno = true;

    // Krok 1: Test DB připojení
    $vysledky[] = "🔍 KROK 1: Test databázového připojení...";

    $env = nactiEnv();
    if (!$env) {
        $chyby[] = "❌ .env soubor nebyl nalezen!";
    } else {
        $dbHost = $env['DB_HOST'] ?? '';
        $dbName = $env['DB_NAME'] ?? '';
        $dbUser = $env['DB_USER'] ?? '';
        $dbPass = $env['DB_PASS'] ?? '';

        $vysledky[] = "📋 Použité credentials:";
        $vysledky[] = "   • DB_HOST: {$dbHost}";
        $vysledky[] = "   • DB_NAME: {$dbName}";
        $vysledky[] = "   • DB_USER: {$dbUser}";
        $vysledky[] = "   • DB_PASS: " . str_repeat('•', strlen($dbPass));

        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            $vysledky[] = "✅ PŘIPOJENÍ K DATABÁZI ÚSPĚŠNÉ!";

            // Krok 2: Zkontrolovat tabulku wgs_reklamace
            $vysledky[] = "";
            $vysledky[] = "🔍 KROK 2: Kontrola tabulky wgs_reklamace...";

            $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_reklamace'");
            if ($stmt->rowCount() === 0) {
                $chyby[] = "❌ Tabulka wgs_reklamace neexistuje!";
            } else {
                $vysledky[] = "✅ Tabulka wgs_reklamace existuje";

                // Zjistit počet záznamů
                $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_reklamace");
                $pocet = $stmt->fetch()['pocet'];
                $vysledky[] = "   📊 Počet reklamací: {$pocet}";

                // Krok 3: Přidat chybějící sloupce
                $vysledky[] = "";
                $vysledky[] = "🔧 KROK 3: Přidávání chybějících sloupců...";

                $sloupce = [
                    ['nazev' => 'ulice', 'definice' => 'VARCHAR(255) DEFAULT NULL', 'komentar' => 'Ulice a číslo popisné', 'po' => 'adresa'],
                    ['nazev' => 'psc', 'definice' => 'VARCHAR(20) DEFAULT NULL', 'komentar' => 'PSČ', 'po' => 'mesto'],
                    ['nazev' => 'prodejce', 'definice' => 'VARCHAR(255) DEFAULT NULL', 'komentar' => 'Jméno prodejce', 'po' => 'zpracoval_id'],
                    ['nazev' => 'technik', 'definice' => 'VARCHAR(255) DEFAULT NULL', 'komentar' => 'Jméno technika', 'po' => 'prodejce'],
                    ['nazev' => 'castka', 'definice' => 'DECIMAL(10,2) DEFAULT NULL', 'komentar' => 'Částka (duplikát cena)', 'po' => 'technik'],
                    ['nazev' => 'zeme', 'definice' => 'VARCHAR(2) DEFAULT NULL', 'komentar' => 'CZ/SK (duplikát fakturace_firma)', 'po' => 'castka'],
                ];

                foreach ($sloupce as $sloupec) {
                    $nazev = $sloupec['nazev'];

                    // Zkontrolovat jestli sloupec už existuje
                    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE '{$nazev}'");

                    if ($stmt->rowCount() > 0) {
                        $vysledky[] = "   ⊙ Sloupec '{$nazev}' již existuje - přeskakuji";
                    } else {
                        try {
                            $sql = "ALTER TABLE wgs_reklamace ADD COLUMN {$nazev} {$sloupec['definice']} COMMENT '{$sloupec['komentar']}'";
                            if (!empty($sloupec['po'])) {
                                $sql .= " AFTER {$sloupec['po']}";
                            }

                            $pdo->exec($sql);
                            $vysledky[] = "   ✅ Sloupec '{$nazev}' přidán";

                        } catch (PDOException $e) {
                            $chyby[] = "   ❌ Chyba při přidání sloupce '{$nazev}': " . $e->getMessage();
                        }
                    }
                }

                // Krok 4: Vyplnit data
                $vysledky[] = "";
                $vysledky[] = "📝 KROK 4: Vyplnění dat do nových sloupců...";

                try {
                    // castka = cena
                    $pdo->exec("UPDATE wgs_reklamace SET castka = cena WHERE castka IS NULL AND cena IS NOT NULL");
                    $vysledky[] = "   ✅ Pole 'castka' vyplněno z 'cena'";

                    // zeme = fakturace_firma
                    $pdo->exec("UPDATE wgs_reklamace SET zeme = fakturace_firma WHERE zeme IS NULL AND fakturace_firma IS NOT NULL");
                    $vysledky[] = "   ✅ Pole 'zeme' vyplněno z 'fakturace_firma'";

                } catch (PDOException $e) {
                    $chyby[] = "   ⚠️ Chyba při vyplnění dat: " . $e->getMessage();
                }

                // Krok 5: Přidat indexy
                $vysledky[] = "";
                $vysledky[] = "⚡ KROK 5: Přidání indexů pro rychlost...";

                $indexy = [
                    ['nazev' => 'idx_technik', 'sloupec' => 'technik'],
                    ['nazev' => 'idx_prodejce', 'sloupec' => 'prodejce'],
                    ['nazev' => 'idx_zeme', 'sloupec' => 'zeme'],
                ];

                foreach ($indexy as $index) {
                    try {
                        $pdo->exec("ALTER TABLE wgs_reklamace ADD INDEX {$index['nazev']} ({$index['sloupec']})");
                        $vysledky[] = "   ✅ Index '{$index['nazev']}' přidán";
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                            $vysledky[] = "   ⊙ Index '{$index['nazev']}' již existuje";
                        } else {
                            $chyby[] = "   ❌ Chyba při vytváření indexu '{$index['nazev']}': " . $e->getMessage();
                        }
                    }
                }

                // Finální test
                $vysledky[] = "";
                $vysledky[] = "✅ HOTOVO!";
                $vysledky[] = "";
                $vysledky[] = "📋 Shrnutí:";
                $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
                $sloupce = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $vysledky[] = "   • Tabulka wgs_reklamace má nyní " . count($sloupce) . " sloupců";
                $vysledky[] = "   • Kontroluj admin panel - měl by fungovat!";
            }

        } catch (PDOException $e) {
            $chyby[] = "❌ CHYBA PŘIPOJENÍ K DATABÁZI:";
            $chyby[] = "   " . $e->getMessage();
            $chyby[] = "";
            $chyby[] = "💡 Zkontroluj credentials v .env souboru";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One-Click Oprava | WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #000 0%, #1a1a1a 100%);
            color: #0f0;
            padding: 2rem;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #000;
            border: 3px solid #0f0;
            box-shadow: 0 0 30px rgba(0, 255, 0, 0.3);
        }
        .header {
            background: #0f0;
            color: #000;
            padding: 2rem;
            text-align: center;
        }
        h1 {
            font-size: 2rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .subtitle {
            font-size: 0.9rem;
            margin-top: 0.5rem;
            opacity: 0.8;
        }
        .content {
            padding: 2rem;
        }
        .button-container {
            text-align: center;
            padding: 3rem 2rem;
            background: linear-gradient(180deg, #000 0%, #0a0a0a 100%);
        }
        .btn-main {
            background: #0f0;
            color: #000;
            border: none;
            padding: 1.5rem 3rem;
            font-size: 1.3rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(0, 255, 0, 0.4);
            font-family: 'Poppins', sans-serif;
        }
        .btn-main:hover {
            background: #00cc00;
            box-shadow: 0 8px 30px rgba(0, 255, 0, 0.6);
            transform: translateY(-2px);
        }
        .btn-main:active {
            transform: translateY(0);
        }
        .output {
            background: #0a0a0a;
            border: 2px solid #0f0;
            padding: 2rem;
            margin: 2rem 0;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            line-height: 1.8;
            max-height: 600px;
            overflow-y: auto;
        }
        .output div {
            margin: 0.3rem 0;
        }
        .error {
            color: #f00;
            background: rgba(255, 0, 0, 0.1);
            border-left: 4px solid #f00;
            padding: 0.5rem 1rem;
            margin: 0.5rem 0;
        }
        .success {
            color: #0f0;
        }
        .info {
            color: #0ff;
        }
        .warning {
            color: #ff0;
        }
        .footer {
            text-align: center;
            padding: 2rem;
            color: #888;
            font-size: 0.9rem;
        }
        .footer a {
            color: #0f0;
            text-decoration: none;
            border-bottom: 1px solid #0f0;
        }
        .footer a:hover {
            color: #00ff00;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 ONE-CLICK OPRAVA</h1>
            <div class="subtitle">Automatická oprava databáze a kontrola připojení</div>
        </div>

        <?php if (!$provedeno): ?>
            <div class="button-container">
                <form method="POST">
                    <button type="submit" name="opravit" class="btn-main">
                        ⚡ OPRAVIT VŠE NYNÍ
                    </button>
                </form>
                <div style="margin-top: 2rem; color: #888; font-size: 0.9rem;">
                    Tento skript provede:
                    <div style="margin-top: 1rem; text-align: left; max-width: 500px; margin-left: auto; margin-right: auto; color: #0f0;">
                        ✓ Test připojení k databázi<br>
                        ✓ Přidání chybějících sloupců (ulice, psc, prodejce, technik, castka, zeme)<br>
                        ✓ Vyplnění dat do nových sloupců<br>
                        ✓ Přidání indexů pro rychlost<br>
                        ✓ Kompletní diagnostiku
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="content">
                <div class="output">
                    <?php foreach ($vysledky as $vysledek): ?>
                        <div class="success"><?php echo htmlspecialchars($vysledek); ?></div>
                    <?php endforeach; ?>

                    <?php if (!empty($chyby)): ?>
                        <div style="margin-top: 2rem;">
                            <?php foreach ($chyby as $chyba): ?>
                                <div class="error"><?php echo htmlspecialchars($chyba); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="text-align: center; margin-top: 2rem;">
                    <form method="GET">
                        <button type="submit" class="btn-main" style="font-size: 1rem; padding: 1rem 2rem;">
                            🔄 SPUSTIT ZNOVU
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="footer">
            <a href="admin.php">← Zpět do Admin Panelu</a> |
            <a href="seznam.php">Seznam Reklamací</a> |
            <a href="show_env.php">Zobrazit .env</a>
        </div>
    </div>
</body>
</html>
