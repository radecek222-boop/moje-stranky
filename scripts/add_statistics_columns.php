<?php
/**
 * Přidání sloupců pro statistiky reklamací
 *
 * Přidá sloupce: prodejce, technik, castka, zeme, mesto
 * Naplní data z existujících sloupců
 */

require_once __DIR__ . '/../init.php';

echo "═══════════════════════════════════════════════════════════════════\n";
echo "PŘIDÁNÍ SLOUPCŮ PRO STATISTIKY REKLAMACÍ\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = getDbConnection();

    // Začít transakci
    $pdo->beginTransaction();

    echo "📊 Přidávám sloupce do tabulky wgs_reklamace...\n";

    // 1. Přidat sloupce
    $pdo->exec("ALTER TABLE wgs_reklamace
        ADD COLUMN IF NOT EXISTS prodejce VARCHAR(255) NULL COMMENT 'Jméno prodejce' AFTER zpracoval,
        ADD COLUMN IF NOT EXISTS technik VARCHAR(255) NULL COMMENT 'Jméno technika' AFTER prodejce,
        ADD COLUMN IF NOT EXISTS castka DECIMAL(10,2) NULL COMMENT 'Částka za opravu (kopie z cena)' AFTER technik,
        ADD COLUMN IF NOT EXISTS zeme VARCHAR(2) NULL COMMENT 'Země (kopie z fakturace_firma)' AFTER castka,
        ADD COLUMN IF NOT EXISTS mesto VARCHAR(255) NULL COMMENT 'Město zákazníka' AFTER zeme
    ");

    echo "✅ Sloupce přidány\n\n";

    // 2. Vytvořit indexy
    echo "📊 Vytvářím indexy...\n";

    $indexes = [
        'idx_prodejce' => 'prodejce',
        'idx_technik' => 'technik',
        'idx_zeme' => 'zeme',
        'idx_mesto' => 'mesto'
    ];

    foreach ($indexes as $indexName => $column) {
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$indexName} ON wgs_reklamace({$column})");
            echo "  ✅ Index {$indexName} vytvořen\n";
        } catch (PDOException $e) {
            // Index už existuje, ignorovat
            echo "  ℹ️  Index {$indexName} již existuje\n";
        }
    }

    echo "\n";

    // 3. Naplnit data
    echo "📊 Naplňuji data z existujících sloupců...\n\n";

    // castka = cena
    $stmt = $pdo->exec("UPDATE wgs_reklamace
        SET castka = cena
        WHERE castka IS NULL OR castka = 0");
    echo "  ✅ castka: {$stmt} záznamů aktualizováno\n";

    // zeme = fakturace_firma
    $stmt = $pdo->exec("UPDATE wgs_reklamace
        SET zeme = fakturace_firma
        WHERE (zeme IS NULL OR zeme = '') AND fakturace_firma IS NOT NULL");
    echo "  ✅ zeme: {$stmt} záznamů aktualizováno\n";

    // prodejce = zpracoval
    $stmt = $pdo->exec("UPDATE wgs_reklamace
        SET prodejce = zpracoval
        WHERE (prodejce IS NULL OR prodejce = '')
          AND zpracoval IS NOT NULL
          AND zpracoval != ''");
    echo "  ✅ prodejce: {$stmt} záznamů aktualizováno\n";

    // mesto = extrahovat z adresa
    $stmt = $pdo->exec("UPDATE wgs_reklamace
        SET mesto = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(adresa, ',', -1), '\\n', 1))
        WHERE (mesto IS NULL OR mesto = '')
          AND adresa IS NOT NULL
          AND adresa != ''
          AND CHAR_LENGTH(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(adresa, ',', -1), '\\n', 1))) > 0
          AND CHAR_LENGTH(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(adresa, ',', -1), '\\n', 1))) < 100");
    echo "  ✅ mesto: {$stmt} záznamů aktualizováno\n";

    echo "\n";

    // Commit transakce
    $pdo->commit();

    // Zobrazit ukázku dat
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "UKÁZKA DAT (prvních 10 záznamů):\n";
    echo "═══════════════════════════════════════════════════════════════════\n\n";

    $stmt = $pdo->query("
        SELECT
            id,
            reklamace_id,
            jmeno,
            prodejce,
            technik,
            castka,
            cena,
            zeme,
            fakturace_firma,
            mesto
        FROM wgs_reklamace
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as $row) {
        echo "ID: {$row['id']} | {$row['reklamace_id']}\n";
        echo "  Zákazník: {$row['jmeno']}\n";
        echo "  Prodejce: " . ($row['prodejce'] ?: 'N/A') . "\n";
        echo "  Technik: " . ($row['technik'] ?: 'N/A') . "\n";
        echo "  Částka: {$row['castka']} € (cena: {$row['cena']} €)\n";
        echo "  Země: " . ($row['zeme'] ?: 'N/A') . " (fakturace: " . ($row['fakturace_firma'] ?: 'N/A') . ")\n";
        echo "  Město: " . ($row['mesto'] ?: 'N/A') . "\n";
        echo "  " . str_repeat('-', 65) . "\n";
    }

    echo "\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "✅ MIGRACE DOKONČENA ÚSPĚŠNĚ!\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "📊 Statistiky jsou nyní připraveny k použití.\n";
    echo "🔗 Otevřete: /admin/control_center.php → Statistiky\n";
    echo "\n";

} catch (Exception $e) {
    // Rollback při chybě
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "❌ CHYBA PŘI MIGRACI\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "Chyba: " . $e->getMessage() . "\n";
    echo "\n";

    exit(1);
}
