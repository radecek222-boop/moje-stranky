<?php
/**
 * ZOBRAZENÍ SKUTEČNÉ STRUKTURY DATABÁZE
 * Tento skript se připojí k databázi a zobrazí skutečnou strukturu tabulky wgs_reklamace
 */

// Načíst .env
$envFile = __DIR__ . '/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $envVars[trim($key)] = trim($value);
        }
    }
}

$dbHost = $envVars['DB_HOST'] ?? '127.0.0.1';
$dbName = $envVars['DB_NAME'] ?? 'wgs-servicecz01';
$dbUser = $envVars['DB_USER'] ?? 'wgs-servicecz002';
$dbPass = $envVars['DB_PASS'] ?? '';

echo "Připojuji se k databázi...\n";
echo "Host: $dbHost\n";
echo "DB: $dbName\n";
echo "User: $dbUser\n\n";

try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "✅ Připojení úspěšné!\n\n";

    echo str_repeat('═', 100) . "\n";
    echo "SKUTEČNÁ STRUKTURA TABULKY wgs_reklamace\n";
    echo str_repeat('═', 100) . "\n\n";

    // Získat seznam všech sloupců
    $stmt = $pdo->query('DESCRIBE wgs_reklamace');
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Celkem sloupců: " . count($sloupce) . "\n\n";

    // Vypsat všechny sloupce s detaily
    printf("%-35s %-25s %-8s %-8s %-20s\n", 'SLOUPEC', 'TYP', 'NULL', 'KEY', 'DEFAULT');
    echo str_repeat('─', 100) . "\n";

    foreach ($sloupce as $sloupec) {
        printf("%-35s %-25s %-8s %-8s %-20s\n",
            $sloupec['Field'],
            $sloupec['Type'],
            $sloupec['Null'],
            $sloupec['Key'] ?: '-',
            $sloupec['Default'] ?: 'NULL'
        );
    }

    echo "\n";
    echo str_repeat('═', 100) . "\n";
    echo "KONTROLA KLÍČOVÝCH SLOUPCŮ\n";
    echo str_repeat('═', 100) . "\n\n";

    $klicoveSloupce = ['technik', 'prodejce', 'ulice', 'mesto', 'psc', 'castka', 'zeme'];
    $existujici = array_column($sloupce, 'Field');

    foreach ($klicoveSloupce as $hledany) {
        if (in_array($hledany, $existujici)) {
            echo "✅ Sloupec '$hledany' EXISTUJE\n";
        } else {
            echo "❌ Sloupec '$hledany' CHYBÍ!\n";
        }
    }

    // Všechny indexy
    echo "\n";
    echo str_repeat('═', 100) . "\n";
    echo "INDEXY V TABULCE\n";
    echo str_repeat('═', 100) . "\n\n";

    $stmt = $pdo->query("SHOW INDEX FROM wgs_reklamace");
    $indexy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $indexyPrehled = [];
    foreach ($indexy as $index) {
        $indexyPrehled[$index['Key_name']][] = $index['Column_name'];
    }

    foreach ($indexyPrehled as $nazev => $sloupce) {
        echo "  • $nazev: " . implode(', ', $sloupce) . "\n";
    }

    // Ukázka dat
    echo "\n";
    echo str_repeat('═', 100) . "\n";
    echo "UKÁZKA DAT (poslední 2 záznamy)\n";
    echo str_repeat('═', 100) . "\n\n";

    $stmt = $pdo->query('SELECT id, reklamace_id, jmeno, email, stav, technik, prodejce, ulice, mesto, psc FROM wgs_reklamace ORDER BY id DESC LIMIT 2');
    $zaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($zaznamy)) {
        echo "(Žádné záznamy v tabulce)\n";
    } else {
        foreach ($zaznamy as $zaznam) {
            echo "ID: " . $zaznam['id'] . "\n";
            echo "  Reklamace ID: " . $zaznam['reklamace_id'] . "\n";
            echo "  Jméno: " . $zaznam['jmeno'] . "\n";
            echo "  Email: " . $zaznam['email'] . "\n";
            echo "  Stav: " . $zaznam['stav'] . "\n";
            echo "  Technik: " . ($zaznam['technik'] ?: 'NULL') . "\n";
            echo "  Prodejce: " . ($zaznam['prodejce'] ?: 'NULL') . "\n";
            echo "  Ulice: " . ($zaznam['ulice'] ?: 'NULL') . "\n";
            echo "  Město: " . ($zaznam['mesto'] ?: 'NULL') . "\n";
            echo "  PSČ: " . ($zaznam['psc'] ?: 'NULL') . "\n";
            echo "\n";
        }
    }

    // Celkový počet záznamů
    echo str_repeat('═', 100) . "\n";
    echo "STATISTIKY\n";
    echo str_repeat('═', 100) . "\n\n";

    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_reklamace");
    $pocet = $stmt->fetch()['pocet'];
    echo "Celkový počet reklamací v databázi: $pocet\n";

    $stmt = $pdo->query("SELECT stav, COUNT(*) as pocet FROM wgs_reklamace GROUP BY stav");
    $stavy = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nRozložení podle stavu:\n";
    foreach ($stavy as $stav) {
        echo "  • {$stav['stav']}: {$stav['pocet']}x\n";
    }

} catch (PDOException $e) {
    echo "❌ CHYBA: " . $e->getMessage() . "\n";
}
?>
