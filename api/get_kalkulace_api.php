<?php
/**
 * API pro načtení kalkulace z databáze
 *
 * Načítá kalkulace_data z wgs_reklamace
 * Používá se v protokolu pro generování PDF PRICELIST
 *
 * SECURITY FIX: Přidána IDOR ochrana, odstraněny debug logy
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola přihlášení
$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['user_email'] ?? null;
$userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$userId && !$isAdmin) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Uživatel není přihlášen'
    ]);
    exit;
}

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Získat reklamace_id z GET parametru
    $reklamaceId = $_GET['reklamace_id'] ?? null;

    if (!$reklamaceId) {
        throw new Exception('Chybí parametr reklamace_id');
    }

    // Sanitizace
    $reklamaceId = trim($reklamaceId);

    // Najít reklamaci v databázi včetně informace o vlastníkovi
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.reklamace_id,
            r.jmeno,
            r.adresa,
            r.telefon,
            r.email,
            r.model,
            r.kalkulace_data,
            r.created_by,
            u.email as vlastnik_email
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON (u.user_id = r.created_by OR u.id = r.created_by)
        WHERE r.reklamace_id = :reklamace_id OR r.cislo = :cislo OR r.id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId,
        ':id' => is_numeric($reklamaceId) ? intval($reklamaceId) : 0
    ]);

    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        echo json_encode([
            'success' => false,
            'error' => 'Reklamace nenalezena'
        ]);
        exit;
    }

    // SECURITY: IDOR ochrana - kontrola oprávnění k přístupu
    // Admin a technik vidí vše, prodejce jen své reklamace
    $maOpravneni = false;
    if ($isAdmin || in_array($userRole, ['admin', 'technik', 'technician'])) {
        $maOpravneni = true;
    } elseif (in_array($userRole, ['prodejce', 'user'])) {
        // Prodejce vidí jen své reklamace
        $vlastnikId = $reklamace['created_by'] ?? null;
        $vlastnikEmail = $reklamace['vlastnik_email'] ?? null;
        if (($userId && $vlastnikId && (string)$userId === (string)$vlastnikId) ||
            ($userEmail && $vlastnikEmail && strtolower($userEmail) === strtolower($vlastnikEmail))) {
            $maOpravneni = true;
        }
    }

    if (!$maOpravneni) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Nemáte oprávnění k této reklamaci'
        ]);
        exit;
    }

    // Pokud kalkulace_data je null nebo prázdné
    if (empty($reklamace['kalkulace_data'])) {
        echo json_encode([
            'success' => true,
            'has_kalkulace' => false,
            'reklamace_id' => $reklamaceId,
            'message' => 'Kalkulace nebyla nalezena'
        ]);
        exit;
    }

    // Dekódovat JSON kalkulace
    $kalkulaceData = json_decode($reklamace['kalkulace_data'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Chyba při dekódování kalkulace: ' . json_last_error_msg());
    }

    // Vrátit úspěšnou odpověď
    echo json_encode([
        'success' => true,
        'has_kalkulace' => true,
        'kalkulace' => $kalkulaceData,
        'zakaznik' => [
            'jmeno' => $reklamace['jmeno'],
            'adresa' => $reklamace['adresa'],
            'telefon' => $reklamace['telefon'],
            'email' => $reklamace['email'],
            'model' => $reklamace['model']
        ],
        'reklamace_id' => $reklamaceId
    ]);

} catch (PDOException $e) {
    // SECURITY: Log detaily server-side, klientovi vrátit pouze generickou zprávu
    error_log("GET_KALKULACE API - Database error: " . $e->getMessage());
    error_log("GET_KALKULACE API - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Chyba při načítání dat z databáze'
    ]);

} catch (Exception $e) {
    // SECURITY: Log detaily server-side, klientovi vrátit pouze generickou zprávu
    error_log("GET_KALKULACE API - Error: " . $e->getMessage());
    error_log("GET_KALKULACE API - Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Chyba při zpracování požadavku'
    ]);
}
?>
