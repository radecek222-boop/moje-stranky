<?php
/**
 * Admin API – správa registračních klíčů a souvisejících entit
 *
 * Očekávané parametry:
 *   action=list_keys            (GET)
 *   action=create_key           (POST JSON {key_type, max_usage?, csrf_token})
 *   action=delete_key           (POST JSON {key_code, csrf_token})
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Přístup odepřen. Pouze pro administrátory.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// HIGH PRIORITY FIX: Rate limiting na admin API
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 'admin';
$identifier = "admin_api_{$ip}_{$userId}";

$rateLimiter = new RateLimiter(getDbConnection());
$rateCheck = $rateLimiter->checkLimit($identifier, 'admin_api', [
    'max_attempts' => 100,
    'window_minutes' => 10,
    'block_minutes' => 30
]);

if (!$rateCheck['allowed']) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => $rateCheck['message'],
        'retry_after' => $rateCheck['reset_at']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$payload = [];

// Načtení JSON payloadu (pro POST požadavky)
if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                respondError('Neplatný JSON payload.', 400);
            }
        }
    } else {
        $payload = $_POST;
    }

    $csrfToken = $payload['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    // SECURITY: Ensure CSRF token is a string, not an array
    if (is_array($csrfToken)) {
        $csrfToken = '';
    }
    if (!validateCSRFToken($csrfToken)) {
        respondError('Neplatný CSRF token. Obnovte stránku a zkuste to znovu.', 403);
    }
}

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    respondError('Nepodařilo se připojit k databázi.', 500);
}

try {
    switch ($action) {
        case 'list_keys':
            handleListKeys($pdo);
            break;

        case 'create_key':
            handleCreateKey($pdo, $payload);
            break;

        case 'delete_key':
            handleDeleteKey($pdo, $payload);
            break;

        case 'list_users':
            handleListUsers($pdo);
            break;

        case 'list_reklamace':
            handleListReklamace($pdo);
            break;

        case 'ping':
            echo json_encode(['status' => 'success', 'message' => 'pong', 'timestamp' => time()]);
            break;

        default:
            respondError('Neznámá akce.', 400);
    }
} catch (InvalidArgumentException $e) {
    respondError($e->getMessage(), 400);
} catch (PDOException $e) {
    error_log('Admin API DB error: ' . $e->getMessage());
    respondError('Chyba databáze.', 500);
} catch (Throwable $e) {
    error_log('Admin API error: ' . $e->getMessage());
    respondError('Neočekávaná chyba.', 500);
}

/**
 * Vrátí seznam registračních klíčů
 */
function handleListKeys(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'SELECT id, key_code, key_type, max_usage, usage_count, is_active, created_at
         FROM wgs_registration_keys
         ORDER BY created_at DESC'
    );
    $stmt->execute();

    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    respondSuccess([
        'keys' => array_map(static function (array $key): array {
            return [
                'id' => (int) ($key['id'] ?? 0),
                'key_code' => $key['key_code'] ?? '',
                'key_type' => $key['key_type'] ?? 'unknown',
                'max_usage' => isset($key['max_usage']) ? (int) $key['max_usage'] : null,
                'usage_count' => isset($key['usage_count']) ? (int) $key['usage_count'] : 0,
                'is_active' => isset($key['is_active']) ? (bool) $key['is_active'] : true,
                'created_at' => $key['created_at'] ?? null,
            ];
        }, $keys)
    ]);
}

/**
 * Vytvoří nový registrační klíč
 */
function handleCreateKey(PDO $pdo, array $payload): void
{
    $keyType = strtolower(trim($payload['key_type'] ?? ''));
    $allowedTypes = ['admin', 'technik', 'prodejce', 'partner'];

    if (!in_array($keyType, $allowedTypes, true)) {
        throw new InvalidArgumentException('Neplatný typ klíče.');
    }

    $maxUsage = null;
    if (isset($payload['max_usage']) && $payload['max_usage'] !== '') {
        $maxUsage = max(1, (int) $payload['max_usage']);
    }

    $prefix = strtoupper(substr($keyType, 0, 3));
    $keyCode = generateRegistrationKey($prefix);

    $stmt = $pdo->prepare(
        'INSERT INTO wgs_registration_keys (key_code, key_type, max_usage, usage_count, is_active, created_at, created_by)
         VALUES (:key_code, :key_type, :max_usage, 0, 1, NOW(), :created_by)'
    );

    $stmt->bindValue(':key_code', $keyCode, PDO::PARAM_STR);
    $stmt->bindValue(':key_type', $keyType, PDO::PARAM_STR);
    if ($maxUsage === null) {
        $stmt->bindValue(':max_usage', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':max_usage', $maxUsage, PDO::PARAM_INT);
    }

    $createdBy = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    if ($createdBy !== null && is_numeric($createdBy)) {
        $stmt->bindValue(':created_by', (int) $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }

    $stmt->execute();

    respondSuccess([
        'key_code' => $keyCode,
        'key_type' => $keyType,
    ]);
}

/**
 * Deaktivuje zadaný klíč
 */
function handleDeleteKey(PDO $pdo, array $payload): void
{
    $keyCode = trim($payload['key_code'] ?? '');
    if ($keyCode === '') {
        throw new InvalidArgumentException('Chybí kód klíče.');
    }

    $stmt = $pdo->prepare('UPDATE wgs_registration_keys SET is_active = 0 WHERE key_code = :key_code');
    $stmt->execute([':key_code' => $keyCode]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Klíč nebyl nalezen nebo již byl deaktivován.');
    }

    respondSuccess(['key_code' => $keyCode]);
}

/**
 * Helper pro úspěšnou odpověď
 */
function respondSuccess(array $data = []): void
{
    echo json_encode(array_merge(['status' => 'success'], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// respondError() je nyní poskytována z includes/api_response.php

/**
 * Vrátí seznam uživatelů
 */
function handleListUsers(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, role, is_active, created_at
         FROM wgs_users
         WHERE is_active = 1
         ORDER BY name ASC'
    );
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    respondSuccess([
        'status' => 'success',
        'data' => array_map(static function (array $user): array {
            return [
                'id' => (int) ($user['id'] ?? 0),
                'user_id' => (int) ($user['id'] ?? 0),
                'name' => $user['name'] ?? '',
                'email' => $user['email'] ?? '',
                'role' => $user['role'] ?? 'prodejce',
                'is_active' => isset($user['is_active']) ? (bool) $user['is_active'] : true,
                'created_at' => $user['created_at'] ?? null,
            ];
        }, $users)
    ]);
}

/**
 * Vrátí seznam reklamací
 */
function handleListReklamace(PDO $pdo): void
{
    $sql = "
        SELECT
            r.*,
            r.id as claim_id
        FROM wgs_reklamace r
        ORDER BY r.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // PERFORMANCE FIX: N+1 Query problem - načíst všechny fotky a dokumenty najednou
    if (!empty($reklamace)) {
        // Extrahovat všechny reklamace_id a claim_id
        $reklamaceIds = array_column($reklamace, 'reklamace_id');
        $claimIds = array_column($reklamace, 'id');

        // Načíst VŠECHNY fotky najednou (místo N queries)
        $photoPlaceholders = implode(',', array_fill(0, count($reklamaceIds), '?'));
        $photoSql = "
            SELECT
                id, photo_id, reklamace_id, section_name,
                photo_path, file_path, file_name,
                photo_order, photo_type, uploaded_at
            FROM wgs_photos
            WHERE reklamace_id IN ($photoPlaceholders)
            ORDER BY photo_order ASC, uploaded_at ASC
        ";
        $photoStmt = $pdo->prepare($photoSql);
        $photoStmt->execute($reklamaceIds);
        $allPhotos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

        // Seskupit fotky podle reklamace_id
        $photosMap = [];
        foreach ($allPhotos as $photo) {
            $rekId = $photo['reklamace_id'];
            if (!isset($photosMap[$rekId])) {
                $photosMap[$rekId] = [];
            }
            $photosMap[$rekId][] = $photo;
        }

        // Načíst VŠECHNY dokumenty najednou (místo N queries)
        $docPlaceholders = implode(',', array_fill(0, count($claimIds), '?'));
        $docSql = "
            SELECT
                id, claim_id, document_name, document_path as file_path,
                document_type, file_size, uploaded_by, uploaded_at
            FROM wgs_documents
            WHERE claim_id IN ($docPlaceholders)
            ORDER BY uploaded_at DESC
        ";
        $docStmt = $pdo->prepare($docSql);
        $docStmt->execute($claimIds);
        $allDocuments = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        // Seskupit dokumenty podle claim_id
        $documentsMap = [];
        foreach ($allDocuments as $doc) {
            $claimId = $doc['claim_id'];
            if (!isset($documentsMap[$claimId])) {
                $documentsMap[$claimId] = [];
            }
            $documentsMap[$claimId][] = $doc;
        }

        // Přiřadit fotky a dokumenty k reklamacím
        foreach ($reklamace as &$record) {
            $reklamaceId = $record['reklamace_id'] ?? $record['cislo'] ?? $record['id'];
            $claimId = $record['id'];

            $record['photos'] = $photosMap[$reklamaceId] ?? [];
            $record['documents'] = $documentsMap[$claimId] ?? [];
        }
    }

    respondSuccess([
        'status' => 'success',
        'data' => $reklamace,
        'reklamace' => $reklamace,
        'count' => count($reklamace)
    ]);
}

