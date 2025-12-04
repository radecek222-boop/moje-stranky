<?php
/**
 * API endpoint pro správu seznamu zákazníků
 *
 * Operace:
 * - list_zakaznici: Seznam všech zákazníků s kontaktními údaji a počtem zakázek
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Kontrola admin přihlášení
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    if (!$isAdmin) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup - pouze pro adminy'
        ]));
    }

    $action = $_GET['action'] ?? 'list_zakaznici';
    $pdo = getDbConnection();

    switch ($action) {

        // ==================== SEZNAM ZÁKAZNÍKŮ ====================
        case 'list_zakaznici':
            // Volitelné vyhledávání
            $search = $_GET['search'] ?? '';

            // SQL dotaz pro získání unikátních zákazníků s počtem zakázek
            $sql = "
                SELECT
                    jmeno,
                    CONCAT_WS(', ', ulice, mesto, psc) as adresa,
                    telefon,
                    email,
                    COUNT(*) as pocet_zakazek,
                    MAX(created_at) as posledni_zakazka
                FROM wgs_reklamace
                WHERE 1=1
            ";

            $params = [];

            // Přidat vyhledávání pokud je zadáno
            if (!empty($search)) {
                $sql .= " AND (
                    jmeno LIKE :search
                    OR email LIKE :search
                    OR telefon LIKE :search
                    OR ulice LIKE :search
                    OR mesto LIKE :search
                )";
                $params['search'] = '%' . $search . '%';
            }

            // Seskupení podle zákazníka (kombinace jméno + email jako unikátní identifikátor)
            $sql .= " GROUP BY jmeno, email, telefon, ulice, mesto, psc";

            // Seřazení podle počtu zakázek (klesající) a pak podle jména
            $sql .= " ORDER BY pocet_zakazek DESC, jmeno ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $zakaznici = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'zakaznici' => $zakaznici,
                'count' => count($zakaznici)
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Neplatná akce'
            ]);
            break;
    }

} catch (PDOException $e) {
    error_log("Database error in zakaznici_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba při zpracování požadavku'
    ]);
} catch (Exception $e) {
    error_log("Error in zakaznici_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba serveru'
    ]);
}
?>
