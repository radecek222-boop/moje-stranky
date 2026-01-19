<?php
/**
 * API endpoint pro načtení provizí technika
 *
 * Vrací provize aktuálně přihlášeného technika za aktuální měsíc
 * Provize = individuální % (dle nastavení technika) z celkové ceny zakázky
 * Výchozí hodnota: 33%
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

    // FIX: Převést textové user_id na numerické id z wgs_users
    // assigned_to obsahuje wgs_users.id (numerické), ne user_id (textové)
    // TAKÉ načíst provizi technika
    $stmtGetId = $pdo->prepare("SELECT id, CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name, COALESCE(provize_procent, 33) as provize_procent FROM wgs_users WHERE user_id = :user_id LIMIT 1");
    $stmtGetId->execute([':user_id' => $userId]);
    $userRow = $stmtGetId->fetch(PDO::FETCH_ASSOC);
    $numericUserId = $userRow['id'] ?? null;
    $userName = trim($userRow['full_name'] ?? '');
    $provizeProcent = (float)($userRow['provize_procent'] ?? 33);

    if (!$numericUserId) {
        // Fallback - zkusit jestli userId není už numerické
        if (is_numeric($userId)) {
            $numericUserId = (int)$userId;
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Nepodařilo se najít ID uživatele'
            ]);
            exit;
        }
    }

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

    // === 1. REKLAMACE (s created_by) - individuální provize technika ===
    $provizeKoeficient = $provizeProcent / 100;

    $stmtReklamace = $pdo->prepare("
        SELECT
            COUNT(*) as pocet_zakazek,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) as celkem_castka,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) * :provize_koeficient as provize_celkem
        FROM wgs_reklamace r
        WHERE {$whereCondition}
          AND YEAR({$datumSloupec}) = :rok
          AND MONTH({$datumSloupec}) = :mesic
          AND r.stav = 'done'
          AND (r.created_by IS NOT NULL AND r.created_by != '')
    ");

    $paramsReklamace = $params;
    $paramsReklamace['provize_koeficient'] = $provizeKoeficient;
    $stmtReklamace->execute($paramsReklamace);
    $vysledekReklamace = $stmtReklamace->fetch(PDO::FETCH_ASSOC);

    $pocetReklamace = (int)($vysledekReklamace['pocet_zakazek'] ?? 0);
    $castkaReklamace = (float)($vysledekReklamace['celkem_castka'] ?? 0);
    $provizeReklamace = (float)($vysledekReklamace['provize_celkem'] ?? 0);

    // === 2. POZ (bez created_by) - fixní 50% provize ===
    $stmtPoz = $pdo->prepare("
        SELECT
            COUNT(*) as pocet_zakazek,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) as celkem_castka,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) * 0.5 as provize_celkem
        FROM wgs_reklamace r
        WHERE {$whereCondition}
          AND YEAR({$datumSloupec}) = :rok
          AND MONTH({$datumSloupec}) = :mesic
          AND r.stav = 'done'
          AND (r.created_by IS NULL OR r.created_by = '')
    ");

    $stmtPoz->execute($params);
    $vysledekPoz = $stmtPoz->fetch(PDO::FETCH_ASSOC);

    $pocetPoz = (int)($vysledekPoz['pocet_zakazek'] ?? 0);
    $castkaPoz = (float)($vysledekPoz['celkem_castka'] ?? 0);
    $provizePoz = (float)($vysledekPoz['provize_celkem'] ?? 0);

    // === CELKEM ===
    $pocetCelkem = $pocetReklamace + $pocetPoz;
    $castkaCelkem = $castkaReklamace + $castkaPoz;
    $provizeCelkem = $provizeReklamace + $provizePoz;

    echo json_encode([
        'status' => 'success',
        'mesic' => $nazevMesice,
        'rok' => $aktualniRok,
        'pocet_zakazek' => $pocetCelkem,
        'celkem_castka' => number_format($castkaCelkem, 2, '.', ''),
        'provize_celkem' => number_format($provizeCelkem, 2, '.', ''),
        // Nové - oddělené hodnoty
        'provize_reklamace' => number_format($provizeReklamace, 2, '.', ''),
        'provize_poz' => number_format($provizePoz, 2, '.', ''),
        'pocet_reklamace' => $pocetReklamace,
        'pocet_poz' => $pocetPoz
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
