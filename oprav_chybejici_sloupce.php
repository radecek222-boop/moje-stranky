<?php
/**
 * OPRAVA CHYBĚJÍCÍCH SLOUPCŮ - MESTO a PSC
 * Rychlá oprava pro sloupce které chybí
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Načíst .env
function nactiEnv() {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return null;

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

if (isset($_POST['opravit'])) {
    $provedeno = true;

    $env = nactiEnv();
    if (!$env) {
        $chyby[] = "❌ .env soubor nenalezen!";
    } else {
        try {
            $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
            $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            $vysledky[] = "✅ Připojení k databázi úspěšné";
            $vysledky[] = "";

            // Zjistit jaké sloupce existují
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
            $existujiciSloupce = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $vysledky[] = "📋 Aktuálně existujících sloupců: " . count($existujiciSloupce);
            $vysledky[] = "";

            // Sloupce které chceme přidat
            $noveSlopuce = [
                'mesto' => "VARCHAR(255) DEFAULT NULL COMMENT 'Město'",
                'psc' => "VARCHAR(20) DEFAULT NULL COMMENT 'PSČ'"
            ];

            foreach ($noveSlopuce as $nazev => $definice) {
                if (in_array($nazev, $existujiciSloupce)) {
                    $vysledky[] = "⊙ Sloupec '{$nazev}' již existuje - přeskakuji";
                } else {
                    try {
                        // Přidat BEZ AFTER - na konec tabulky
                        $sql = "ALTER TABLE wgs_reklamace ADD COLUMN {$nazev} {$definice}";
                        $pdo->exec($sql);
                        $vysledky[] = "✅ Přidán sloupec '{$nazev}'";
                    } catch (PDOException $e) {
                        $chyby[] = "❌ Chyba při přidání '{$nazev}': " . $e->getMessage();
                    }
                }
            }

            $vysledky[] = "";
            $vysledky[] = "✅ HOTOVO!";

            // Znovu zobrazit všechny sloupce
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
            $noveSlopuce = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $vysledky[] = "";
            $vysledky[] = "📊 Tabulka wgs_reklamace má nyní " . count($noveSlopuce) . " sloupců:";
            $vysledky[] = implode(', ', $noveSlopuce);

        } catch (PDOException $e) {
            $chyby[] = "❌ CHYBA: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oprava Chybějících Sloupců | WGS</title>
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
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #000;
        }
        .header {
            background: #000;
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .content {
            padding: 2rem;
        }
        .btn-main {
            background: #000;
            color: #fff;
            border: 2px solid #000;
            padding: 1.5rem 3rem;
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            width: 100%;
            font-family: 'Poppins', sans-serif;
            letter-spacing: 0.08em;
        }
        .btn-main:hover {
            background: #fff;
            color: #000;
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
        .output div {
            margin: 0.3rem 0;
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
            border-top: 2px solid #e0e0e0;
            color: #555;
            font-size: 0.9rem;
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
            <h1>🔧 Oprava Chybějících Sloupců</h1>
            <p style="margin-top: 0.5rem; font-size: 0.9rem; opacity: 0.9;">Přidání sloupců MESTO a PSC</p>
        </div>

        <div class="content">
            <?php if (!$provedeno): ?>
                <form method="POST">
                    <p style="margin-bottom: 2rem; color: #555; line-height: 1.8;">
                        Tento skript přidá chybějící sloupce <strong>mesto</strong> a <strong>psc</strong> do tabulky wgs_reklamace.
                    </p>
                    <button type="submit" name="opravit" class="btn-main">
                        ⚡ PŘIDAT CHYBĚJÍCÍ SLOUPCE
                    </button>
                </form>
            <?php else: ?>
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

                <form method="GET" style="margin-top: 2rem;">
                    <button type="submit" class="btn-main">← ZPĚT</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="admin.php">Admin Panel</a> |
            <a href="oprav_vse.php">One-Click Oprava</a> |
            <a href="show_table_structure.php">Zobrazit Strukturu</a>
        </div>
    </div>
</body>
</html>
