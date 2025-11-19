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

        case 'get_users':
            handleGetUsers($pdo);
            break;

        case 'change_admin_password':
            handleChangeAdminPassword($pdo, $payload);
            break;

        case 'reset_user_password':
            handleResetUserPassword($pdo, $payload);
            break;

        case 'update_api_key':
            handleUpdateApiKey($pdo, $payload);
            break;

        case 'get_api_keys':
            handleGetApiKeys($pdo);
            break;

        case 'ping':
            echo json_encode(['status' => 'success', 'message' => 'pong', 'timestamp' => time()]);
            break;

        case 'change_reklamace_status':
            handleChangeReklamaceStatus($pdo, $payload);
            break;

        case 'get_reklamace_detail':
            handleGetReklamaceDetail($pdo);
            break;

        case 'update_email_template':
            handleUpdateEmailTemplate($pdo, $payload);
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

/**
 * Vrátí VŠECHNY uživatele (včetně neaktivních) - pro security tab
 */
function handleGetUsers(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, role, is_active, created_at, last_login
         FROM wgs_users
         ORDER BY created_at DESC'
    );
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    respondSuccess([
        'users' => array_map(static function (array $user): array {
            return [
                'id' => (int) ($user['id'] ?? 0),
                'name' => $user['name'] ?? '',
                'email' => $user['email'] ?? '',
                'role' => $user['role'] ?? 'prodejce',
                'is_active' => isset($user['is_active']) ? (bool) $user['is_active'] : true,
                'created_at' => $user['created_at'] ?? null,
                'last_login' => $user['last_login'] ?? null,
            ];
        }, $users)
    ]);
}

/**
 * Změna admin hesla
 */
function handleChangeAdminPassword(PDO $pdo, array $payload): void
{
    $aktualniHeslo = trim($payload['current_password'] ?? '');
    $noveHeslo = trim($payload['new_password'] ?? '');

    if ($aktualniHeslo === '' || $noveHeslo === '') {
        throw new InvalidArgumentException('Chybí aktuální nebo nové heslo.');
    }

    // Ověření aktuálního hesla
    $aktualniHash = hash('sha256', $aktualniHeslo);
    $ocekavanyHash = defined('ADMIN_KEY_HASH') ? ADMIN_KEY_HASH : getenv('ADMIN_KEY_HASH');

    if ($aktualniHash !== $ocekavanyHash) {
        throw new InvalidArgumentException('Aktuální heslo je nesprávné.');
    }

    // Validace síly nového hesla
    $silneHeslo = isStrongPassword($noveHeslo);
    if ($silneHeslo !== true) {
        throw new InvalidArgumentException('Nové heslo není dostatečně silné: ' . implode(', ', $silneHeslo));
    }

    // Generování nového hashe
    $novyHash = hash('sha256', $noveHeslo);

    // Uložení do .env souboru
    $envPath = __DIR__ . '/../.env';
    if (!file_exists($envPath)) {
        throw new InvalidArgumentException('.env soubor nebyl nalezen.');
    }

    $envContent = file_get_contents($envPath);
    if ($envContent === false) {
        throw new InvalidArgumentException('Nepodařilo se načíst .env soubor.');
    }

    // Nahradit admin hash
    $envContent = preg_replace(
        '/^ADMIN_KEY_HASH=.*/m',
        'ADMIN_KEY_HASH=' . $novyHash,
        $envContent
    );

    if (file_put_contents($envPath, $envContent, LOCK_EX) === false) {
        throw new InvalidArgumentException('Nepodařilo se uložit nové heslo do .env souboru.');
    }

    respondSuccess(['message' => 'Admin heslo bylo úspěšně změněno.']);
}

/**
 * Reset uživatelského hesla
 */
function handleResetUserPassword(PDO $pdo, array $payload): void
{
    $userId = (int) ($payload['user_id'] ?? 0);
    $noveHeslo = trim($payload['new_password'] ?? '');

    if ($userId <= 0) {
        throw new InvalidArgumentException('Neplatné ID uživatele.');
    }

    if ($noveHeslo === '') {
        throw new InvalidArgumentException('Nové heslo nesmí být prázdné.');
    }

    // Validace síly hesla
    $silneHeslo = isStrongPassword($noveHeslo);
    if ($silneHeslo !== true) {
        throw new InvalidArgumentException('Heslo není dostatečně silné: ' . implode(', ', $silneHeslo));
    }

    // Hashování hesla
    $passwordHash = password_hash($noveHeslo, PASSWORD_DEFAULT);

    // Aktualizace hesla v databázi
    $stmt = $pdo->prepare(
        'UPDATE wgs_users SET password_hash = :password_hash WHERE id = :id'
    );
    $stmt->execute([
        ':password_hash' => $passwordHash,
        ':id' => $userId
    ]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Uživatel nebyl nalezen.');
    }

    respondSuccess(['message' => 'Heslo bylo úspěšně resetováno.']);
}

/**
 * Aktualizace API klíče
 */
function handleUpdateApiKey(PDO $pdo, array $payload): void
{
    $nazevKlice = trim($payload['key_name'] ?? '');
    $hodnotaKlice = trim($payload['key_value'] ?? '');

    if ($nazevKlice === '') {
        throw new InvalidArgumentException('Chybí název klíče.');
    }

    if ($hodnotaKlice === '') {
        throw new InvalidArgumentException('Hodnota klíče nesmí být prázdná.');
    }

    // Povolené klíče (whitelist)
    $povoleneKlice = [
        'GEOAPIFY_API_KEY',
        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_USER',
        'SMTP_PASS'
    ];

    if (!in_array($nazevKlice, $povoleneKlice, true)) {
        throw new InvalidArgumentException('Nepovolený název klíče.');
    }

    // Uložení do .env souboru
    $envPath = __DIR__ . '/../.env';
    if (!file_exists($envPath)) {
        throw new InvalidArgumentException('.env soubor nebyl nalezen.');
    }

    $envContent = file_get_contents($envPath);
    if ($envContent === false) {
        throw new InvalidArgumentException('Nepodařilo se načíst .env soubor.');
    }

    // Escapovat hodnotu pro .env (pokud obsahuje mezery, obalit uvozovkami)
    $escapedValue = $hodnotaKlice;
    if (strpos($hodnotaKlice, ' ') !== false || strpos($hodnotaKlice, '#') !== false) {
        $escapedValue = '"' . str_replace('"', '\\"', $hodnotaKlice) . '"';
    }

    // Kontrola zda klíč už v .env existuje
    if (preg_match('/^' . preg_quote($nazevKlice, '/') . '=/m', $envContent)) {
        // Nahradit existující hodnotu
        $envContent = preg_replace(
            '/^' . preg_quote($nazevKlice, '/') . '=.*/m',
            $nazevKlice . '=' . $escapedValue,
            $envContent
        );
    } else {
        // Přidat nový klíč na konec souboru
        $envContent .= "\n" . $nazevKlice . '=' . $escapedValue . "\n";
    }

    if (file_put_contents($envPath, $envContent, LOCK_EX) === false) {
        throw new InvalidArgumentException('Nepodařilo se uložit API klíč do .env souboru.');
    }

    respondSuccess(['message' => 'API klíč byl úspěšně uložen.']);
}

/**
 * Načtení aktuálních hodnot API klíčů
 */
function handleGetApiKeys(PDO $pdo): void
{
    // Načíst hodnoty z prostředí (.env)
    $keys = [
        'GEOAPIFY_API_KEY' => getEnvValue('GEOAPIFY_API_KEY') ?: getEnvValue('GEOAPIFY_KEY') ?: '',
        'SMTP_HOST' => getEnvValue('SMTP_HOST') ?: '',
        'SMTP_PORT' => getEnvValue('SMTP_PORT') ?: '',
        'SMTP_USER' => getEnvValue('SMTP_USER') ?: '',
        'SMTP_PASS' => getEnvValue('SMTP_PASS') ?: '',
    ];

    respondSuccess(['keys' => $keys]);
}

/**
 * Změna stavu reklamace
 */
function handleChangeReklamaceStatus(PDO $pdo, array $payload): void
{
    $reklamaceId = $payload['reklamace_id'] ?? null;
    $newStatus = $payload['new_status'] ?? null;

    if (!$reklamaceId || !$newStatus) {
        throw new InvalidArgumentException('Chybí reklamace_id nebo new_status.');
    }

    // Whitelist povolených stavů
    $allowedStatuses = ['wait', 'open', 'done'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        throw new InvalidArgumentException('Neplatný stav. Povolené hodnoty: wait, open, done');
    }

    // Aktualizovat stav
    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET stav = :stav,
            datum_dokonceni = CASE WHEN :stav_check = 'done' THEN NOW() ELSE datum_dokonceni END
        WHERE reklamace_id = :reklamace_id
    ");

    $stmt->execute([
        'stav' => $newStatus,
        'stav_check' => $newStatus,
        'reklamace_id' => $reklamaceId
    ]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Reklamace nebyla nalezena nebo stav nebyl změněn.');
    }

    respondSuccess(['message' => 'Stav reklamace byl změněn.']);
}

/**
 * Načtení detailu reklamace + timeline historie
 */
function handleGetReklamaceDetail(PDO $pdo): void
{
    $reklamaceId = $_GET['reklamace_id'] ?? null;

    if (!$reklamaceId) {
        throw new InvalidArgumentException('Chybí reklamace_id.');
    }

    // Načíst detail reklamace
    $stmt = $pdo->prepare("
        SELECT
            reklamace_id, cislo, jmeno, telefon, email,
            ulice, mesto, psc, model, provedeni, barva,
            popis_problemu, doplnujici_info, termin, cas_navstevy,
            stav, created_at as datum_vytvoreni, datum_dokonceni, prodejce as jmeno_prodejce,
            typ, technik, castka, fakturace_firma
        FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id
        LIMIT 1
    ");

    $stmt->execute(['reklamace_id' => $reklamaceId]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new InvalidArgumentException('Reklamace nebyla nalezena.');
    }

    // Vytvořit timeline historii
    $timeline = [];

    // 1. Vytvoření reklamace
    if ($reklamace['datum_vytvoreni']) {
        $timeline[] = [
            'typ' => 'system',
            'nazev' => '✅ Reklamace vytvořena',
            'popis' => 'Zákazník vytvořil novou reklamaci',
            'datum' => $reklamace['datum_vytvoreni'],
            'user' => $reklamace['jmeno_prodejce'] ?: 'Systém'
        ];
    }

    // 2. Domluvení termínu (pokud existuje)
    if ($reklamace['termin']) {
        $timeline[] = [
            'typ' => 'termin',
            'nazev' => '📅 Termín domluven',
            'popis' => 'Termín návštěvy: ' . date('d.m.Y', strtotime($reklamace['termin'])) . ' v ' . $reklamace['cas_navstevy'],
            'datum' => $reklamace['termin'] . ' ' . $reklamace['cas_navstevy'],
            'user' => 'Technik'
        ];
    }

    // 3. Fotodokumentace (pokud existují fotky)
    $fotoStmt = $pdo->prepare("
        SELECT id, photo_path, file_path, file_name, section_name, created_at
        FROM wgs_photos
        WHERE reklamace_id = :id
        ORDER BY created_at ASC
    ");
    $fotoStmt->execute(['id' => $reklamaceId]);
    $fotky = $fotoStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($fotky)) {
        // Seskupit fotky podle sekce
        $fotkyPodleSekci = [];
        foreach ($fotky as $fotka) {
            $sekce = $fotka['section_name'] ?: 'Ostatní';
            if (!isset($fotkyPodleSekci[$sekce])) {
                $fotkyPodleSekci[$sekce] = [];
            }
            $fotkyPodleSekci[$sekce][] = $fotka;
        }

        // Přidat do timeline pro každou sekci
        foreach ($fotkyPodleSekci as $sekce => $fotkySekce) {
            $fotkyHtml = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 10px;">';
            foreach ($fotkySekce as $fotka) {
                $cesta = $fotka['file_path'] ?: $fotka['photo_path'];
                $fotkyHtml .= '<a href="/' . htmlspecialchars($cesta) . '" target="_blank" style="display: block; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; transition: transform 0.2s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">';
                $fotkyHtml .= '<img src="/' . htmlspecialchars($cesta) . '" style="width: 100%; height: 120px; object-fit: cover;" alt="' . htmlspecialchars($fotka['file_name']) . '">';
                $fotkyHtml .= '</a>';
            }
            $fotkyHtml .= '</div>';

            $timeline[] = [
                'typ' => 'photo',
                'nazev' => '📸 Fotodokumentace - ' . $sekce,
                'popis' => 'Nahrán počet fotografií: ' . count($fotkySekce) . $fotkyHtml,
                'datum' => $fotkySekce[0]['created_at'] ?: $reklamace['datum_vytvoreni'],
                'user' => 'Technik'
            ];
        }
    }

    // 4. Protokoly (PDFy)
    $protokolStmt = $pdo->prepare("
        SELECT id, file_path, original_filename, document_type, created_at
        FROM wgs_documents
        WHERE claim_id = :id AND document_type = 'protokol_pdf'
        ORDER BY created_at ASC
    ");
    $protokolStmt->execute(['id' => $reklamaceId]);
    $protokoly = $protokolStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($protokoly as $protokol) {
        $protokolyHtml = '<div style="margin-top: 10px;">';
        $protokolyHtml .= '<a href="/' . htmlspecialchars($protokol['file_path']) . '" target="_blank" style="display: inline-flex; align-items: center; gap: 10px; padding: 10px 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #000; transition: background 0.2s;" onmouseover="this.style.background=\'#e5e5e5\'" onmouseout="this.style.background=\'#f5f5f5\'">';
        $protokolyHtml .= '<span style="font-size: 2rem;">📄</span>';
        $protokolyHtml .= '<div>';
        $protokolyHtml .= '<div style="font-weight: 600;">' . htmlspecialchars($protokol['original_filename']) . '</div>';
        $protokolyHtml .= '<div style="font-size: 0.75rem; color: #666;">Klikněte pro zobrazení PDF</div>';
        $protokolyHtml .= '</div>';
        $protokolyHtml .= '</a>';
        $protokolyHtml .= '</div>';

        $timeline[] = [
            'typ' => 'document',
            'nazev' => '📋 Protokol PDF',
            'popis' => 'Vytvořen servisní protokol' . $protokolyHtml,
            'datum' => $protokol['created_at'],
            'user' => 'Technik'
        ];
    }

    // 5. Odeslané emaily
    $emailStmt = $pdo->prepare("
        SELECT subject, created_at, sent_at
        FROM wgs_email_queue
        WHERE to_email = :email
        ORDER BY created_at ASC
    ");
    $emailStmt->execute(['email' => $reklamace['email']]);
    $emaily = $emailStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($emaily as $email) {
        $timeline[] = [
            'typ' => 'email',
            'nazev' => '📧 Email odeslán',
            'popis' => 'Předmět: ' . $email['subject'],
            'datum' => $email['sent_at'] ?: $email['created_at'],
            'user' => 'Systém'
        ];
    }

    // 6. Dokončení (pokud je hotovo)
    if ($reklamace['stav'] === 'done' && $reklamace['datum_dokonceni']) {
        $timeline[] = [
            'typ' => 'done',
            'nazev' => '✅ Reklamace vyřízena',
            'popis' => 'Zakázka byla úspěšně dokončena',
            'datum' => $reklamace['datum_dokonceni'],
            'user' => 'Technik'
        ];
    }

    // Seřadit timeline chronologicky
    usort($timeline, function($a, $b) {
        return strtotime($a['datum']) - strtotime($b['datum']);
    });

    respondSuccess([
        'reklamace' => $reklamace,
        'timeline' => $timeline
    ]);
}

/**
 * Aktualizovat email šablonu
 */
function handleUpdateEmailTemplate(PDO $pdo, array $payload): void
{
    $templateId = $payload['template_id'] ?? null;
    $subject = trim($payload['subject'] ?? '');
    $template = trim($payload['template'] ?? '');
    $active = isset($payload['active']) ? (bool)$payload['active'] : false;

    if (!$templateId) {
        throw new InvalidArgumentException('Chybí ID šablony');
    }

    if (empty($subject)) {
        throw new InvalidArgumentException('Předmět emailu nesmí být prázdný');
    }

    if (empty($template)) {
        throw new InvalidArgumentException('Obsah šablony nesmí být prázdný');
    }

    // Aktualizovat šablonu v databázi
    $stmt = $pdo->prepare("
        UPDATE wgs_notifications
        SET subject = :subject,
            template = :template,
            active = :active,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'subject' => $subject,
        'template' => $template,
        'active' => $active ? 1 : 0,
        'id' => $templateId
    ]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Šablona nebyla nalezena nebo nebyla změněna');
    }

    respondSuccess([
        'message' => 'Šablona byla úspěšně aktualizována',
        'template_id' => $templateId
    ]);
}

