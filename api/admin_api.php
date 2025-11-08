<?php
/**
 * Admin API
 * Správa registračních klíčů, uživatelů a notifikací
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Kontrola admin přihlášení
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Přístup odepřen. Pouze pro administrátory.'
        ]);
        exit;
    }

    // Získání akce
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Routing podle akce
    switch ($action) {
        case 'list_keys':
            handleListKeys();
            break;

        case 'create_key':
            handleCreateKey();
            break;

        case 'delete_key':
            handleDeleteKey();
            break;

        default:
            throw new Exception('Neplatná akce: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * LIST KEYS - Zobrazit všechny registrační klíče
 */
function handleListKeys() {
    $pdo = getDbConnection();

    // Vytvoření tabulky pokud neexistuje
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_registration_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            key_code VARCHAR(100) UNIQUE NOT NULL,
            key_type VARCHAR(20) NOT NULL,
            status ENUM('active', 'used', 'revoked') DEFAULT 'active',
            created_by VARCHAR(50) DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used_by INT DEFAULT NULL,
            used_at TIMESTAMP NULL,
            INDEX idx_key_code (key_code),
            INDEX idx_status (status)
        )
    ");

    // Načíst všechny klíče
    $stmt = $pdo->query("
        SELECT
            k.*,
            u.name as used_by_name,
            u.email as used_by_email
        FROM wgs_registration_keys k
        LEFT JOIN wgs_users u ON k.used_by = u.id
        ORDER BY k.created_at DESC
    ");

    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'keys' => $keys
    ]);
}

/**
 * CREATE KEY - Vytvořit nový registrační klíč
 */
function handleCreateKey() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Pouze POST metoda');
    }

    // Načíst data z JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $keyType = $input['key_type'] ?? '';

    // Validace typu
    $allowedTypes = ['admin', 'technik', 'prodejce', 'partner'];
    if (!in_array($keyType, $allowedTypes)) {
        throw new Exception('Neplatný typ klíče. Povolené: ' . implode(', ', $allowedTypes));
    }

    // Vygenerovat náhodný klíč
    $keyCode = generateSecureKey();

    $pdo = getDbConnection();

    // Vložit klíč do databáze
    $stmt = $pdo->prepare("
        INSERT INTO wgs_registration_keys (key_code, key_type, status, created_by)
        VALUES (:key_code, :key_type, 'active', :created_by)
    ");

    $createdBy = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'admin';

    $result = $stmt->execute([
        ':key_code' => $keyCode,
        ':key_type' => $keyType,
        ':created_by' => $createdBy
    ]);

    if (!$result) {
        throw new Exception('Chyba při vytváření klíče');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Klíč vytvořen',
        'key' => [
            'id' => $pdo->lastInsertId(),
            'key_code' => $keyCode,
            'key_type' => $keyType,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

/**
 * DELETE KEY - Smazat/zneplatnit registrační klíč
 */
function handleDeleteKey() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Pouze DELETE nebo POST metoda');
    }

    $keyCode = $_GET['key_code'] ?? $_POST['key_code'] ?? '';

    if (empty($keyCode)) {
        throw new Exception('Chybí key_code');
    }

    $pdo = getDbConnection();

    // Zkontrolovat, zda klíč existuje
    $stmt = $pdo->prepare("SELECT id, status FROM wgs_registration_keys WHERE key_code = :key_code");
    $stmt->execute([':key_code' => $keyCode]);
    $key = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$key) {
        throw new Exception('Klíč nenalezen');
    }

    if ($key['status'] === 'used') {
        throw new Exception('Nelze smazat použitý klíč');
    }

    // Místo mazání označíme jako revoked (lepší audit trail)
    $deleteStmt = $pdo->prepare("
        UPDATE wgs_registration_keys
        SET status = 'revoked'
        WHERE key_code = :key_code
    ");

    $result = $deleteStmt->execute([':key_code' => $keyCode]);

    if (!$result) {
        throw new Exception('Chyba při mazání klíče');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Klíč zneplatněn'
    ]);
}

/**
 * Generovat bezpečný náhodný klíč
 */
function generateSecureKey() {
    // Generovat 32 bytů náhodných dat
    $randomBytes = random_bytes(32);

    // Převést na base64 a odstranit znaky, které nejsou uživatelsky přívětivé
    $key = str_replace(['+', '/', '='], ['', '', ''], base64_encode($randomBytes));

    // Zkrátit na 40 znaků
    $key = substr($key, 0, 40);

    // Přidat prefix pro lepší rozpoznání
    return 'WGS-' . strtoupper($key);
}
?>
