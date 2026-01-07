<?php
/**
 * API endpoint pro načtení provizí technika
 *
 * Vrací provize aktuálně přihlášeného technika za aktuální měsíc
 * Provize = 33% z celkové ceny zakázky
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Kontrola přihlášení
    $isLoggedIn = isset($_SESSION['user_id']);

    if (!$isLoggedIn) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]));
    }

    // PERFORMANCE: Uvolnění session zámku
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? null;

    session_write_close();

    // Kontrola že uživatel je technik
    if ($userRole !== 'technik') {
        http_response_code(403);
        die(json_encode([
            'status' => 'error',
            'message' => 'Pouze pro techniky'
        ]));
    }

    $pdo = getDbConnection();

    // Získat numerické ID technika z tabulky wgs_users
    // $_SESSION['user_id'] je textový user_id, ale assigned_to je INT (wgs_users.id)
    $stmtUser = $pdo->prepare("
        SELECT id, name FROM wgs_users WHERE user_id = :user_id LIMIT 1
    ");
    $stmtUser->execute(['user_id' => $userId]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $numericUserId = $userRow['id'] ?? null;
    $userName = $userRow['name'] ?? '';

    // Získat aktuální měsíc a rok
    $aktualniRok = date('Y');
    $aktualniMesic = date('m');

    // České názvy měsíců
    $mesiceCS = [
        '01' => 'leden',
        '02' => 'únor',
        '03' => 'březen',
        '04' => 'duben',
        '05' => 'květen',
        '06' => 'červen',
        '07' => 'červenec',
        '08' => 'srpen',
        '09' => 'září',
        '10' => 'říjen',
        '11' => 'listopad',
        '12' => 'prosinec'
    ];

    $nazevMesice = $mesiceCS[$aktualniMesic] ?? 'neznámý';

    // Spočítat provizi za aktuální měsíc
    // Počítáme podle dokonceno_kym - kdo zakázku dokončil (odeslal protokol)
    // DŮLEŽITÉ: Provize patří tomu, kdo byl přihlášen při dokončení

    // Zjistit zda existuje sloupec dokonceno_kym
    $stmtColumns = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'dokonceno_kym'");
    $hasDokoncenokym = $stmtColumns->rowCount() > 0;

    // Zjistit zda existuje sloupec datum_dokonceni
    $stmtColumns2 = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'datum_dokonceni'");
    $hasDatumDokonceni = $stmtColumns2->rowCount() > 0;

    // Použít datum_dokonceni pokud existuje, jinak updated_at
    $datumSloupec = $hasDatumDokonceni ? 'COALESCE(r.datum_dokonceni, r.updated_at)' : 'r.updated_at';

    // Podmínka pro technika - priorita: dokonceno_kym, pak assigned_to, pak technik text
    if ($hasDokoncenokym) {
        // Nový systém - podle kdo dokončil
        $whereCondition = "(r.dokonceno_kym = :numeric_id OR (r.dokonceno_kym IS NULL AND (r.assigned_to = :numeric_id2 OR r.technik LIKE :user_name)))";
        $params = [
            'numeric_id' => $numericUserId,
            'numeric_id2' => $numericUserId,
            'user_name' => '%' . $userName . '%',
            'rok' => $aktualniRok,
            'mesic' => $aktualniMesic
        ];
    } else {
        // Starý systém - fallback
        $whereCondition = "(r.assigned_to = :numeric_id OR r.technik LIKE :user_name)";
        $params = [
            'numeric_id' => $numericUserId,
            'user_name' => '%' . $userName . '%',
            'rok' => $aktualniRok,
            'mesic' => $aktualniMesic
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as pocet_zakazek,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) as celkem_castka,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) * 0.33 as provize_celkem
        FROM wgs_reklamace r
        WHERE {$whereCondition}
          AND YEAR({$datumSloupec}) = :rok
          AND MONTH({$datumSloupec}) = :mesic
          AND r.stav = 'done'
    ");

    $stmt->execute($params);

    $vysledek = $stmt->fetch(PDO::FETCH_ASSOC);

    $pocetZakazek = (int)($vysledek['pocet_zakazek'] ?? 0);
    $celkemCastka = (float)($vysledek['celkem_castka'] ?? 0);
    $provizeCelkem = (float)($vysledek['provize_celkem'] ?? 0);

    echo json_encode([
        'status' => 'success',
        'mesic' => $nazevMesice,
        'rok' => $aktualniRok,
        'pocet_zakazek' => $pocetZakazek,
        'celkem_castka' => number_format($celkemCastka, 2, '.', ''),
        'provize_celkem' => number_format($provizeCelkem, 2, '.', '')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log("Database error in tech_provize_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba při načítání provizí'
    ]);
} catch (Exception $e) {
    error_log("Error in tech_provize_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba serveru'
    ]);
}
?>
