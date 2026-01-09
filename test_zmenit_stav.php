<?php
/**
 * Test přesného průběhu zmenit_stav API
 * Simuluje celý flow jako při volání z frontendu
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/db_metadata.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test zmenit_stav API</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #fff; padding: 20px; }
        .ok { color: #39ff14; }
        .error { color: #ff4444; }
        pre { background: #2a2a2a; padding: 15px; border-radius: 5px; }
        h2 { border-bottom: 1px solid #444; padding-bottom: 10px; margin-top: 30px; }
    </style>
</head>
<body>
<h1>Test zmenit_stav API - Kompletní simulace</h1>";

try {
    $pdo = getDbConnection();

    // Test 1: Kontrola funkce db_table_has_column
    echo "<h2>1. Test funkce db_table_has_column</h2><pre>";

    echo "function_exists('db_table_has_column'): ";
    echo function_exists('db_table_has_column') ? "<span class='ok'>ANO</span>\n" : "<span class='error'>NE!</span>\n";

    echo "function_exists('db_get_table_columns'): ";
    echo function_exists('db_get_table_columns') ? "<span class='ok'>ANO</span>\n" : "<span class='error'>NE!</span>\n";

    // Test volání
    echo "\nTest db_table_has_column('wgs_nabidky', 'cekame_nd_at'): ";
    try {
        $result = db_table_has_column($pdo, 'wgs_nabidky', 'cekame_nd_at');
        echo $result ? "<span class='ok'>TRUE</span>\n" : "<span class='error'>FALSE</span>\n";
    } catch (Throwable $e) {
        echo "<span class='error'>EXCEPTION: {$e->getMessage()}</span>\n";
    }

    echo "Test db_table_has_column('wgs_reklamace', 'updated_at'): ";
    try {
        $result = db_table_has_column($pdo, 'wgs_reklamace', 'updated_at');
        echo $result ? "<span class='ok'>TRUE</span>\n" : "<span class='error'>FALSE</span>\n";
    } catch (Throwable $e) {
        echo "<span class='error'>EXCEPTION: {$e->getMessage()}</span>\n";
    }

    echo "</pre>";

    // Test 2: Simulace změny stavu na "odsouhlasena"
    echo "<h2>2. Simulace změny na 'odsouhlasena' (cn_odsouhlasena)</h2><pre>";

    // Najít reklamaci s CN
    $stmt = $pdo->query("
        SELECT r.id, r.reklamace_id, r.email, r.stav, n.id as nabidka_id, n.stav as cn_stav
        FROM wgs_reklamace r
        JOIN wgs_nabidky n ON LOWER(n.zakaznik_email) = LOWER(r.email)
        LIMIT 1
    ");
    $testData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$testData) {
        echo "<span class='error'>Nenalezena žádná reklamace s CN pro test</span>\n";
    } else {
        echo "Testovací data:\n";
        echo "  Reklamace ID: {$testData['id']}\n";
        echo "  Číslo: {$testData['reklamace_id']}\n";
        echo "  Email: {$testData['email']}\n";
        echo "  Stav reklamace: {$testData['stav']}\n";
        echo "  Nabídka ID: {$testData['nabidka_id']}\n";
        echo "  Stav CN: {$testData['cn_stav']}\n\n";

        // Simulovat flow z API
        $reklamaceId = $testData['id'];
        $novyStav = 'cn_odsouhlasena';
        $zakaznikEmail = strtolower($testData['email']);

        echo "Simuluji změnu stavu na '{$novyStav}'...\n\n";

        // Krok 1: Kontrola existence sloupce
        echo "Krok 1: db_table_has_column('wgs_nabidky', 'cekame_nd_at')\n";
        $hasCekameNdAt = db_table_has_column($pdo, 'wgs_nabidky', 'cekame_nd_at');
        echo "  Výsledek: " . ($hasCekameNdAt ? 'TRUE' : 'FALSE') . "\n\n";

        // Krok 2: Najít CN
        echo "Krok 2: SELECT CN pro email '{$zakaznikEmail}'\n";
        if ($hasCekameNdAt) {
            $sql = "SELECT id, stav, cekame_nd_at FROM wgs_nabidky WHERE LOWER(zakaznik_email) = :email ORDER BY vytvoreno_at DESC LIMIT 1";
        } else {
            $sql = "SELECT id, stav FROM wgs_nabidky WHERE LOWER(zakaznik_email) = :email ORDER BY vytvoreno_at DESC LIMIT 1";
        }
        echo "  SQL: {$sql}\n";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => $zakaznikEmail]);
            $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "  <span class='ok'>OK</span> - Nalezeno: " . json_encode($nabidka) . "\n\n";
        } catch (PDOException $e) {
            echo "  <span class='error'>CHYBA: {$e->getMessage()}</span>\n\n";
        }

        // Krok 3: UPDATE CN
        echo "Krok 3: UPDATE wgs_nabidky (BEZ SKUTEČNÉ ZMĚNY - WHERE 1=0)\n";
        if ($hasCekameNdAt) {
            $sql = "UPDATE wgs_nabidky SET stav = 'potvrzena', cekame_nd_at = NULL WHERE 1=0";
        } else {
            $sql = "UPDATE wgs_nabidky SET stav = 'potvrzena' WHERE 1=0";
        }
        echo "  SQL: {$sql}\n";

        try {
            $pdo->exec($sql);
            echo "  <span class='ok'>OK</span>\n\n";
        } catch (PDOException $e) {
            echo "  <span class='error'>CHYBA: {$e->getMessage()}</span>\n\n";
        }

        // Krok 4: Kontrola updated_at
        echo "Krok 4: db_table_has_column('wgs_reklamace', 'updated_at')\n";
        $hasUpdatedAt = db_table_has_column($pdo, 'wgs_reklamace', 'updated_at');
        echo "  Výsledek: " . ($hasUpdatedAt ? 'TRUE' : 'FALSE') . "\n\n";

        // Krok 5: UPDATE reklamace (pokud by byl stav 'done')
        echo "Krok 5: UPDATE wgs_reklamace (BEZ SKUTEČNÉ ZMĚNY - WHERE 1=0)\n";
        if ($hasUpdatedAt) {
            $sql = "UPDATE wgs_reklamace SET stav = 'wait', updated_at = NOW() WHERE 1=0";
        } else {
            $sql = "UPDATE wgs_reklamace SET stav = 'wait' WHERE 1=0";
        }
        echo "  SQL: {$sql}\n";

        try {
            $pdo->exec($sql);
            echo "  <span class='ok'>OK</span>\n\n";
        } catch (PDOException $e) {
            echo "  <span class='error'>CHYBA: {$e->getMessage()}</span>\n\n";
        }
    }

    echo "</pre>";

    // Test 3: Zkusit skutečnou změnu na testovací reklamaci
    echo "<h2>3. SKUTEČNÝ TEST změny stavu</h2>";

    if ($testData && isset($_GET['execute'])) {
        echo "<pre>";
        echo "Provádím skutečnou změnu stavu CN na 'potvrzena'...\n\n";

        $pdo->beginTransaction();
        try {
            $nabidkaId = $testData['nabidka_id'];

            // Změnit stav CN
            $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'potvrzena', cekame_nd_at = NULL WHERE id = ?");
            $stmt->execute([$nabidkaId]);

            $affected = $stmt->rowCount();
            echo "UPDATE wgs_nabidky: {$affected} řádků změněno\n";

            $pdo->commit();
            echo "\n<span class='ok'>ÚSPĚCH! Stav CN změněn.</span>\n";
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "\n<span class='error'>CHYBA: {$e->getMessage()}</span>\n";
            echo "SQL State: " . $e->getCode() . "\n";
        }
        echo "</pre>";
    } elseif ($testData) {
        echo "<p>Pro provedení skutečné změny klikněte: <a href='?execute=1' style='color:#39ff14;'>PROVÉST ZMĚNU</a></p>";
        echo "<p style='color:#888;'>Změní stav CN nabídky ID {$testData['nabidka_id']} na 'potvrzena'</p>";
    }

    echo "<h2>Závěr</h2>";
    echo "<p>Pokud všechny testy prošly, problém je pravděpodobně v:</p>";
    echo "<ul>";
    echo "<li>Předávání dat z JavaScriptu (chybí email?)</li>";
    echo "<li>Cachování starého kódu na serveru</li>";
    echo "<li>Jiná verze API souboru</li>";
    echo "</ul>";

    echo "<p><a href='/seznam.php' style='color:#39ff14;'>Zpět na Seznam</a></p>";

} catch (Exception $e) {
    echo "<p class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
