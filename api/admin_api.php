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

// ✅ PERFORMANCE FIX: Načíst session data a uvolnit zámek
// Audit 2025-11-24: Admin operations - různé délky operací
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 'admin';
$createdBy = $userId; // Pro použití v řádku 217

// KRITICKÉ: Uvolnit session lock pro paralelní zpracování
session_write_close();

// HIGH PRIORITY FIX: Rate limiting na admin API
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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

        case 'update_email_recipients':
            handleUpdateEmailRecipients($pdo, $payload);
            break;

        case 'send_invitations':
            handleSendInvitations($pdo, $payload);
            break;

        case 'get_keys':
            handleListKeys($pdo);
            break;

        case 'get_invitation_template':
            handleGetInvitationTemplate($pdo);
            break;

        case 'save_invitation_template':
            handleSaveInvitationTemplate($pdo, $payload);
            break;

        case 'get_invitation_texts':
            handleGetInvitationTexts($pdo);
            break;

        case 'save_invitation_texts':
            handleSaveInvitationTexts($pdo, $payload);
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
    $allowedTypes = ['technik', 'prodejce'];

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

    // $createdBy již načteno výše (řádek 29)
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

    // ✅ OPRAVA: Fyzické smazání místo soft delete (is_active = 0)
    // Důvod: UI zobrazuje i neaktivní klíče, ale UPDATE ... SET is_active = 0
    // nefunguje pro klíče které už jsou neaktivní (rowCount = 0)
    // Řešení: DELETE fyzicky odstraní klíč z databáze
    $stmt = $pdo->prepare('DELETE FROM wgs_registration_keys WHERE key_code = :key_code');
    $stmt->execute([':key_code' => $keyCode]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Klíč nebyl nalezen v databázi.');
    }

    respondSuccess(['key_code' => $keyCode, 'message' => 'Klíč byl úspěšně smazán']);
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
            ulice, mesto, psc, adresa, model, provedeni, barva,
            popis_problemu, doplnujici_info, termin, cas_navstevy,
            stav, created_at as datum_vytvoreni, datum_dokonceni, prodejce as jmeno_prodejce,
            typ, technik, castka, fakturace_firma,
            pocet_dilu, cena_prace, cena_material, cena_druhy_technik, cena_doprava, cena_celkem
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
            'nazev' => 'Reklamace vytvořena',
            'popis' => 'Zákazník vytvořil novou reklamaci',
            'datum' => $reklamace['datum_vytvoreni'],
            'user' => $reklamace['jmeno_prodejce'] ?: 'Systém'
        ];
    }

    // 2. Domluvení termínu (pokud existuje)
    if ($reklamace['termin']) {
        $timeline[] = [
            'typ' => 'termin',
            'nazev' => 'Termín domluven',
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
                'nazev' => 'Fotodokumentace - ' . $sekce,
                'popis' => 'Nahrán počet fotografií: ' . count($fotkySekce) . $fotkyHtml,
                'datum' => $fotkySekce[0]['created_at'] ?: $reklamace['datum_vytvoreni'],
                'user' => 'Technik'
            ];
        }
    }

    // 4. Protokoly (PDFy) - pouze pokud tabulka existuje
    try {
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
            $protokolyHtml .= '<span style="font-size: 1.5rem; font-weight: 600; color: #dc3545;">PDF</span>';
            $protokolyHtml .= '<div>';
            $protokolyHtml .= '<div style="font-weight: 600;">' . htmlspecialchars($protokol['original_filename']) . '</div>';
            $protokolyHtml .= '<div style="font-size: 0.75rem; color: #666;">Klikněte pro zobrazení PDF</div>';
            $protokolyHtml .= '</div>';
            $protokolyHtml .= '</a>';
            $protokolyHtml .= '</div>';

            $timeline[] = [
                'typ' => 'document',
                'nazev' => 'Protokol PDF',
                'popis' => 'Vytvořen servisní protokol' . $protokolyHtml,
                'datum' => $protokol['created_at'],
                'user' => 'Technik'
            ];
        }
    } catch (PDOException $e) {
        // Tabulka wgs_documents neexistuje - přeskočit
        error_log("wgs_documents table doesn't exist or query failed: " . $e->getMessage());
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
            'nazev' => 'Email odeslán',
            'popis' => 'Předmět: ' . $email['subject'],
            'datum' => $email['sent_at'] ?: $email['created_at'],
            'user' => 'Systém'
        ];
    }

    // 6. Dokončení (pokud je hotovo)
    if ($reklamace['stav'] === 'done' && $reklamace['datum_dokonceni']) {
        $timeline[] = [
            'typ' => 'done',
            'nazev' => 'Reklamace vyřízena',
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

/**
 * Aktualizovat příjemce email šablony
 */
function handleUpdateEmailRecipients(PDO $pdo, array $payload): void
{
    $templateId = $payload['template_id'] ?? null;
    $recipients = $payload['recipients'] ?? null;

    if (!$templateId) {
        throw new InvalidArgumentException('Chybí ID šablony');
    }

    if (!is_array($recipients)) {
        throw new InvalidArgumentException('Příjemci musí být pole');
    }

    // Validace typu (to/cc/bcc)
    $validTypes = ['to', 'cc', 'bcc'];
    $validateType = function($type) use ($validTypes) {
        return in_array($type, $validTypes) ? $type : 'to';
    };

    // Validace struktury recipients
    $validatedRecipients = [
        'customer' => [
            'enabled' => isset($recipients['customer']['enabled']) ? (bool)$recipients['customer']['enabled'] : false,
            'type' => $validateType($recipients['customer']['type'] ?? 'to')
        ],
        'seller' => [
            'enabled' => isset($recipients['seller']['enabled']) ? (bool)$recipients['seller']['enabled'] : false,
            'type' => $validateType($recipients['seller']['type'] ?? 'cc')
        ],
        'technician' => [
            'enabled' => isset($recipients['technician']['enabled']) ? (bool)$recipients['technician']['enabled'] : false,
            'type' => $validateType($recipients['technician']['type'] ?? 'cc')
        ],
        'importer' => [
            'enabled' => isset($recipients['importer']['enabled']) ? (bool)$recipients['importer']['enabled'] : false,
            'email' => isset($recipients['importer']['email']) ? trim($recipients['importer']['email']) : '',
            'type' => $validateType($recipients['importer']['type'] ?? 'cc')
        ],
        'other' => [
            'enabled' => isset($recipients['other']['enabled']) ? (bool)$recipients['other']['enabled'] : false,
            'email' => isset($recipients['other']['email']) ? trim($recipients['other']['email']) : '',
            'type' => $validateType($recipients['other']['type'] ?? 'cc')
        ]
    ];

    // Validace emailů pokud jsou enabled
    if ($validatedRecipients['importer']['enabled'] && !empty($validatedRecipients['importer']['email'])) {
        if (!filter_var($validatedRecipients['importer']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Neplatná emailová adresa výrobce');
        }
    }

    if ($validatedRecipients['other']['enabled'] && !empty($validatedRecipients['other']['email'])) {
        if (!filter_var($validatedRecipients['other']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Neplatná emailová adresa v poli "Jiné"');
        }
    }

    // Uložit do databáze jako JSON
    $recipientsJson = json_encode($validatedRecipients, JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("
        UPDATE wgs_notifications
        SET recipients = :recipients,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'recipients' => $recipientsJson,
        'id' => $templateId
    ]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Šablona nebyla nalezena nebo nebyla změněna');
    }

    respondSuccess([
        'message' => 'Příjemci byli úspěšně aktualizováni',
        'template_id' => $templateId,
        'recipients' => $validatedRecipients
    ]);
}

/**
 * Odeslat pozvánky na registraci
 */
function handleSendInvitations(PDO $pdo, array $payload): void
{
    $typ = strtolower(trim($payload['typ'] ?? ''));
    $klic = trim($payload['klic'] ?? '');
    $emaily = $payload['emaily'] ?? [];

    // Validace typu
    if (!in_array($typ, ['technik', 'prodejce'], true)) {
        throw new InvalidArgumentException('Neplatny typ pozvanky');
    }

    // Validace emailů
    if (!is_array($emaily) || count($emaily) === 0) {
        throw new InvalidArgumentException('Zadejte alespon jeden email');
    }

    if (count($emaily) > 30) {
        throw new InvalidArgumentException('Maximalne 30 emailu najednou');
    }

    // Filtrovat a validovat emaily
    $platneEmaily = [];
    foreach ($emaily as $email) {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $platneEmaily[] = $email;
        }
    }

    if (count($platneEmaily) === 0) {
        throw new InvalidArgumentException('Zadny z emailu neni platny');
    }

    // Získat nebo vytvořit klíč
    $pouzityKlic = '';
    if ($klic === 'auto' || $klic === '') {
        // Vytvořit nový klíč
        $prefix = strtoupper(substr($typ, 0, 3));
        $pouzityKlic = generateRegistrationKey($prefix);

        $stmt = $pdo->prepare(
            'INSERT INTO wgs_registration_keys (key_code, key_type, max_usage, usage_count, is_active, created_at)
             VALUES (:key_code, :key_type, NULL, 0, 1, NOW())'
        );
        $stmt->execute([
            ':key_code' => $pouzityKlic,
            ':key_type' => $typ
        ]);
    } else {
        // Ověřit že klíč existuje a je aktivní
        $stmt = $pdo->prepare('SELECT key_code, key_type, is_active FROM wgs_registration_keys WHERE key_code = :klic');
        $stmt->execute([':klic' => $klic]);
        $klicData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$klicData) {
            throw new InvalidArgumentException('Registracni klic nebyl nalezen');
        }
        if (!$klicData['is_active']) {
            throw new InvalidArgumentException('Registracni klic neni aktivni');
        }

        $pouzityKlic = $klicData['key_code'];
    }

    // Připravit email šablonu
    $appUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'www.wgs-service.cz');
    $rok = date('Y');

    // Načíst vlastní texty z databáze
    $vlastniTexty = null;
    try {
        $stmtTexty = $pdo->prepare("
            SELECT config_value FROM wgs_system_config
            WHERE config_key = 'invitation_template_texts' LIMIT 1
        ");
        $stmtTexty->execute();
        $resultTexty = $stmtTexty->fetch(PDO::FETCH_ASSOC);
        if ($resultTexty && !empty($resultTexty['config_value'])) {
            $vlastniTexty = json_decode($resultTexty['config_value'], true);
        }
    } catch (PDOException $e) {
        error_log("Chyba nacitani textu pro pozvanku: " . $e->getMessage());
    }

    // Předmět emailu - z vlastních textů nebo výchozí
    $roleNazev = $typ === 'technik' ? 'Servisni technik' : 'Prodejce';
    if ($vlastniTexty && !empty($vlastniTexty['predmetEmailu'])) {
        $predmet = str_replace('{ROLE}', $roleNazev, $vlastniTexty['predmetEmailu']);
    } else {
        $predmet = 'Pozvanka do systemu WGS - ' . $roleNazev;
    }

    $htmlSablona = vytvorPozvankovouSablonu($typ, $pouzityKlic, $appUrl, $rok, $vlastniTexty);

    // Odeslat emaily
    $odeslanoPocet = 0;
    $chyby = [];

    foreach ($platneEmaily as $email) {
        try {
            // Použít email queue
            $stmt = $pdo->prepare("
                INSERT INTO wgs_email_queue (to_email, subject, body, status, created_at, scheduled_at)
                VALUES (:to_email, :subject, :body, 'pending', NOW(), NOW())
            ");
            $stmt->execute([
                ':to_email' => $email,
                ':subject' => $predmet,
                ':body' => $htmlSablona
            ]);
            $odeslanoPocet++;
        } catch (Exception $e) {
            $chyby[] = $email . ': ' . $e->getMessage();
            error_log("Chyba odeslani pozvanky na {$email}: " . $e->getMessage());
        }
    }

    respondSuccess([
        'sent_count' => $odeslanoPocet,
        'key_code' => $pouzityKlic,
        'errors' => $chyby
    ]);
}

/**
 * Načíst uložená nastavení šablony pozvánky
 */
function handleGetInvitationTemplate(PDO $pdo): void
{
    // Výchozí nastavení
    $vychoziNastaveni = [
        'datumSpusteni' => '1. ledna 2026',
        'telefonPodpora' => '+420 725 965 826',
        'emailPodpora' => 'info@wgs-service.cz',
        'textSkoleni' => 'Radi vas proskolime po telefonu nebo osobne. Staci se nam ozvat a domluvime se.',
        'dobaSkoleni' => '15-30 minut',
        'nazevFirmy' => 'White Glove Service',
        'popisFirmy' => 'Autorizovany servis Natuzzi pro CR a SR',
        'webFirmy' => 'www.wgs-service.cz'
    ];

    try {
        // Zkusit načíst z databáze
        $stmt = $pdo->prepare("
            SELECT config_value
            FROM wgs_system_config
            WHERE config_key = 'invitation_template_settings'
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty($result['config_value'])) {
            $ulozeneNastaveni = json_decode($result['config_value'], true);
            if (is_array($ulozeneNastaveni)) {
                // Sloučit s výchozími (pro případ nových polí)
                $nastaveni = array_merge($vychoziNastaveni, $ulozeneNastaveni);
                respondSuccess(['settings' => $nastaveni, 'source' => 'database']);
                return;
            }
        }
    } catch (PDOException $e) {
        error_log("Chyba načítání nastavení šablony: " . $e->getMessage());
    }

    // Vrátit výchozí nastavení
    respondSuccess(['settings' => $vychoziNastaveni, 'source' => 'default']);
}

/**
 * Uložit nastavení šablony pozvánky
 */
function handleSaveInvitationTemplate(PDO $pdo, array $payload): void
{
    $nastaveni = $payload['settings'] ?? null;

    if (!is_array($nastaveni)) {
        respondError('Chybí nastavení šablony.');
        return;
    }

    // Validace povolených polí
    $povolenaPole = [
        'datumSpusteni',
        'telefonPodpora',
        'emailPodpora',
        'textSkoleni',
        'dobaSkoleni',
        'nazevFirmy',
        'popisFirmy',
        'webFirmy'
    ];

    $filtrovanaNastaveni = [];
    foreach ($povolenaPole as $pole) {
        if (isset($nastaveni[$pole])) {
            $filtrovanaNastaveni[$pole] = trim((string)$nastaveni[$pole]);
        }
    }

    $jsonNastaveni = json_encode($filtrovanaNastaveni, JSON_UNESCAPED_UNICODE);

    try {
        // Zkusit UPDATE, pokud neexistuje, tak INSERT
        $stmt = $pdo->prepare("
            INSERT INTO wgs_system_config (config_key, config_value, config_type, updated_at)
            VALUES ('invitation_template_settings', ?, 'json', NOW())
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
        ");
        $stmt->execute([$jsonNastaveni]);

        respondSuccess(['message' => 'Nastavení šablony uloženo.', 'settings' => $filtrovanaNastaveni]);

    } catch (PDOException $e) {
        error_log("Chyba ukládání nastavení šablony: " . $e->getMessage());
        respondError('Chyba při ukládání nastavení.');
    }
}

/**
 * Vytvořit HTML šablonu pro pozvánku - SUPER JEDNODUCHÉ pro netechnické uživatele
 * S DETAILNÍMI NÁVODY PRO KAŽDOU ROLI
 *
 * @param string $typ Typ role (technik/prodejce)
 * @param string $klic Registrační klíč
 * @param string $appUrl URL aplikace
 * @param string $rok Aktuální rok
 * @param array|null $vlastniTexty Vlastní texty z databáze
 * @return string HTML šablona emailu
 */
function vytvorPozvankovouSablonu(string $typ, string $klic, string $appUrl, string $rok, ?array $vlastniTexty = null): string
{
    $roleNazev = $typ === 'technik' ? 'servisni technik' : 'prodejce';
    $roleVelke = $typ === 'technik' ? 'TECHNIK' : 'PRODEJCE';

    // Helper pro převod **text** na <strong>text</strong>
    $formatujText = function($text) {
        return preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', htmlspecialchars($text));
    };

    // Uvítací text - z vlastních textů nebo výchozí
    $uvitaciText = 'Byli jste pozvani jako <strong>' . $roleNazev . '</strong> do systemu White Glove Service pro spravu servisnich zakazek Natuzzi.';
    if ($vlastniTexty && !empty($vlastniTexty['uvitaciText'])) {
        $uvitaciText = str_replace('{ROLE}', $roleNazev, $formatujText($vlastniTexty['uvitaciText']));
    }

    // Specifické funkce a návody podle role
    $funkce = '';
    $navodHlavni = '';

    if ($typ === 'technik') {
        // ============================================
        // NÁVOD PRO TECHNIKY
        // ============================================

        // Seznam funkcí - z vlastních textů nebo výchozí
        $funkceSeznam = '';
        if ($vlastniTexty && !empty($vlastniTexty['funkceTechnik'])) {
            $radky = explode("\n", $vlastniTexty['funkceTechnik']);
            foreach ($radky as $radek) {
                $radek = trim($radek);
                if (!empty($radek)) {
                    $funkceSeznam .= '<li>' . $formatujText($radek) . '</li>';
                }
            }
        } else {
            $funkceSeznam = '
                    <li>Videt sve <strong>prirazene zakazky</strong> v prehlednem seznamu</li>
                    <li>Menit <strong>stav zakazky</strong> (Ceka / Domluvena / Hotovo)</li>
                    <li>Vyplnovat <strong>servisni protokoly</strong> s automatickym prekladem</li>
                    <li>Nahravat <strong>fotky pred a po oprave</strong></li>
                    <li>Videt <strong>adresu zakaznika na mape</strong> s navigaci</li>
                    <li>Nechat zakaznika <strong>elektronicky podepsat</strong> protokol</li>
                    <li>Exportovat protokol do <strong>PDF</strong> a poslat zakaznikovi</li>
            ';
        }

        $funkce = '
            <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #333; margin-top: 0; font-size: 16px;">Co budete moct delat v systemu:</h3>
                <ul style="color: #555; line-height: 2; margin: 0; padding-left: 20px;">
                    ' . $funkceSeznam . '
                </ul>
            </div>
        ';

        // DETAILNÍ NÁVOD PRO TECHNIKY
        $navodHlavni = '
            <!-- NÁVOD: PŘEHLED ZAKÁZEK -->
            <div style="border: 2px solid #333; border-radius: 8px; padding: 25px; margin: 30px 0;">
                <h3 style="color: #333; margin: 0 0 20px; font-size: 18px; border-bottom: 2px solid #333; padding-bottom: 10px;">
                    1. PREHLED VASICH ZAKAZEK
                </h3>

                <p style="color: #555; font-size: 14px; margin: 0 0 20px;">
                    Po prihlaseni uvidite <strong>seznam vsech vasich prirazenych zakazek</strong>. Kazda zakazka je zobrazena jako karta.
                </p>

                <!-- CO VIDÍTE NA KARTĚ -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">CO VIDITE NA KARTE ZAKAZKY:</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Cislo zakazky</strong> - napr. NCN-000768</li>
                        <li><strong>Barevny indikator stavu</strong>:
                            <ul style="margin: 5px 0; padding-left: 15px;">
                                <li><span style="color: #f5a623; font-weight: bold;">ZLUTA</span> = NOVA/CEKA</li>
                                <li><span style="color: #2196f3; font-weight: bold;">MODRA</span> = V RESENI</li>
                                <li><span style="color: #4caf50; font-weight: bold;">ZELENA</span> = HOTOVO</li>
                            </ul>
                        </li>
                        <li><strong>Jmeno zakaznika</strong> a <strong>adresa</strong> - kam pojedete</li>
                        <li><strong>Datum vytvoreni</strong></li>
                    </ul>
                </div>

                <!-- FILTROVÁNÍ -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">FILTROVANI ZAKAZEK:</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>VSECHNY</strong> - vsechny vase zakazky</li>
                        <li><strong>CEKAJICI</strong> - bez domluveneho terminu</li>
                        <li><strong>V RESENI</strong> - s domluvenym terminem</li>
                        <li><strong>VYRIZENE</strong> - dokoncene</li>
                    </ul>
                </div>

                <!-- PO KLIKNUTÍ NA KARTU -->
                <div style="background: #e8f4fd; padding: 15px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #333;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">PO KLIKNUTI NA KARTU - AKCNI TLACITKA:</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>"ZAHAJIT NAVSTEVU"</strong> - spusti servisni navstevu, stav se zmeni na V RESENI</li>
                        <li><strong>"NAPLANOVAT TERMIN"</strong> - otevre kalendar pro vyber data a casu</li>
                        <li><strong>"KONTAKTOVAT"</strong> - telefon a email zakaznika</li>
                        <li><strong>"DETAIL ZAKAZNIKA"</strong> - kompletni informace ze servisniho formulare</li>
                        <li><strong>"VIDEOTEKA"</strong> - knihovna servisnich videi</li>
                        <li><strong>"ZAVRIT"</strong> - zpet na seznam</li>
                    </ul>
                </div>

                <!-- DETAIL ZÁKAZNÍKA -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">DETAIL ZAKAZNIKA OBSAHUJE:</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Identifikacni udaje</strong> - cislo objednavky, zadavatel, fakturace, datumy</li>
                        <li><strong>Kontaktni udaje</strong> - jmeno, telefon, email, adresa</li>
                        <li><strong>Informace o produktu</strong> - model, provedeni, barva</li>
                        <li><strong>Popis problemu</strong> - od zakaznika i od prodejce</li>
                        <li><strong>Fotografie</strong> - vsechny nahrane fotky zavady</li>
                    </ul>
                </div>
            </div>

            <!-- NÁVOD: SERVISNÍ PROTOKOL -->
            <div style="border: 2px solid #333; border-radius: 8px; padding: 25px; margin: 30px 0;">
                <h3 style="color: #333; margin: 0 0 20px; font-size: 18px; border-bottom: 2px solid #333; padding-bottom: 10px;">
                    2. JAK VYPLNIT SERVISNI PROTOKOL (protokol.php)
                </h3>

                <p style="color: #555; font-size: 14px; margin: 0 0 20px;">
                    Protokol se <strong>automaticky predvyplni</strong> udaji ze zakazky. Vy doplnite pouze to, co jste zjistili a opravili.
                </p>

                <!-- HLAVIČKA PROTOKOLU -->
                <div style="background: #e8f4fd; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">HLAVICKA (predvyplneno automaticky):</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Cislo objednavky</strong> - interni WGS cislo</li>
                        <li><strong>Cislo reklamace</strong> - cislo od prodejce</li>
                        <li><strong>Zakaznik</strong> - jmeno, telefon, email, adresa</li>
                        <li><strong>Zadavatel</strong> - kdo zakazku vytvoril (prodejce)</li>
                        <li><strong>Model</strong> - jaky nabytek</li>
                        <li><strong>Technik</strong> - vyberte sve jmeno z rozbalovaci nabidky</li>
                    </ul>
                </div>

                <!-- POLE K VYPLNĚNÍ -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">CO VYPLNUJETE VY:</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Zakaznik reklamuje</strong> - predvyplneno, muzete upravit</li>
                        <li><strong>Problem zjisteny technikem</strong> - CO jste skutecne nasli</li>
                        <li><strong>Navrh opravy</strong> - CO jste udelali nebo doporucujete</li>
                    </ul>
                    <p style="color: #888; margin: 10px 0 0; font-size: 12px;">
                        TIP: Text se automaticky prelozi do anglictiny pro Natuzzi.
                    </p>
                </div>

                <!-- ÚČTOVÁNÍ -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">UCTOVANI A STAV:</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Uctovano za servis</strong> - kliknete a otevre se kalkulacka ceny</li>
                        <li><strong>Plati zakaznik?</strong> - ANO/NE (u reklamace obvykle NE)</li>
                        <li><strong>Datum podpisu</strong> - dnesni datum</li>
                        <li><strong>Vyreseno?</strong> - ANO pokud je vse opraveno</li>
                        <li><strong>Nutne vyjadreni prodejce</strong> - pokud potrebujete schvaleni</li>
                        <li><strong>Poskozeni technikem?</strong> - pokud jste neco poskodili</li>
                    </ul>
                </div>

                <!-- PODPIS -->
                <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">PODPIS ZAKAZNIKA:</p>
                    <ol style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li>Kliknete na tlacitko <strong>"Podepsat protokol"</strong></li>
                        <li>Otevre se souhrn pro zakaznika - ukazete mu, co jste udelali</li>
                        <li>Zakaznik se <strong>podepise prstem</strong> na displeji (nebo mysi na PC)</li>
                        <li>Kliknete na <strong>"Potvrdit podpis"</strong></li>
                    </ol>
                </div>

                <!-- EXPORT -->
                <div style="background: #d4edda; padding: 15px; border-radius: 6px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">EXPORT A ODESLANI:</p>
                    <ul style="color: #155724; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Pridat fotky</strong> - nahrajte fotky pred/po oprave</li>
                        <li><strong>Export do PDF</strong> - stazeni protokolu jako PDF</li>
                        <li><strong>Odeslat zakaznikovi</strong> - automaticky posle email s protokolem</li>
                    </ul>
                </div>
            </div>
        ';

    } else {
        // ============================================
        // NÁVOD PRO PRODEJCE
        // ============================================

        // Seznam funkcí - z vlastních textů nebo výchozí
        $funkceSeznam = '';
        if ($vlastniTexty && !empty($vlastniTexty['funkceProdejce'])) {
            $radky = explode("\n", $vlastniTexty['funkceProdejce']);
            foreach ($radky as $radek) {
                $radek = trim($radek);
                if (!empty($radek)) {
                    $funkceSeznam .= '<li>' . $formatujText($radek) . '</li>';
                }
            }
        } else {
            $funkceSeznam = '
                    <li>Zadavat <strong>nove reklamace</strong> pro vase zakazniky</li>
                    <li>Sledovat <strong>stav vasich zakazek</strong> v realnem case</li>
                    <li>Videt <strong>historii vsech reklamaci</strong> ktere jste zadali</li>
                    <li>Nahravat <strong>dokumenty a fotky</strong> k zakazkam</li>
                    <li>Pridavat <strong>poznamky</strong> pro techniky</li>
                    <li>Videt kdy technik <strong>navstivi zakaznika</strong></li>
            ';
        }

        $funkce = '
            <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #333; margin-top: 0; font-size: 16px;">Co budete moct delat v systemu:</h3>
                <ul style="color: #555; line-height: 2; margin: 0; padding-left: 20px;">
                    ' . $funkceSeznam . '
                </ul>
            </div>
        ';

        // DETAILNÍ NÁVOD PRO PRODEJCE
        $navodHlavni = '
            <!-- NÁVOD: OBJEDNAT SERVIS -->
            <div style="border: 2px solid #333; border-radius: 8px; padding: 25px; margin: 30px 0;">
                <h3 style="color: #333; margin: 0 0 20px; font-size: 18px; border-bottom: 2px solid #333; padding-bottom: 10px;">
                    1. JAK OBJEDNAT SERVIS PRO ZAKAZNIKA
                </h3>

                <p style="color: #555; font-size: 14px; margin: 0 0 20px;">
                    Po prihlaseni kliknete na <strong>"Objednat servis"</strong> v menu. Otevre se formular s nasledujicimi sekcemi:
                </p>

                <!-- POVĚŘENÍ K REKLAMACI -->
                <div style="background: #e8f4fd; padding: 15px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #333;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">0. POVERENI K REKLAMACI (volitelne)</p>
                    <p style="color: #555; margin: 0; font-size: 13px; line-height: 1.6;">
                        Pokud reklamaci podavate za zakaznika, <strong>nahrajte poverovaci dokument</strong> ve formatu PDF.
                        Kliknete na tlacitko "VYBRAT PDF SOUBOR" a vyberte dokument z vaseho zarizeni.
                    </p>
                </div>

                <!-- ČÁST 1 -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">1. ZAKLADNI UDAJE</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Cislo objednavky/reklamace</strong> - vase interni cislo z prodejny (povinne)</li>
                        <li><strong>Fakturace</strong> - vyberte CZ nebo SK podle zeme fakturace</li>
                        <li><strong>Datum prodeje</strong> - kdy zakaznik nabytek koupil (pro vypocet zaruky)</li>
                        <li><strong>Datum reklamace</strong> - kdy byla reklamace nahlasena</li>
                    </ul>
                    <p style="color: #888; margin: 10px 0 0; font-size: 12px;">
                        TIP: System automaticky spocita, jestli je nabytek v zaruce (2 roky od prodeje).
                    </p>
                </div>

                <!-- ČÁST 2 -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">2. KONTAKTNI UDAJE ZAKAZNIKA</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Jmeno zakaznika</strong> - cele jmeno a prijmeni (povinne)</li>
                        <li><strong>E-mail</strong> - zakaznik dostane potvrzeni a protokol emailem (povinne)</li>
                        <li><strong>Telefon</strong> - vyberte predvolbu (+420 CZ, +421 SK, +39 IT...) a zadejte cislo (povinne)</li>
                    </ul>
                </div>

                <!-- ČÁST 3 -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">3. ADRESA ZAKAZNIKA</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Ulice a cislo popisne</strong> - napr. "Vinohradska 50"</li>
                        <li><strong>Mesto</strong> - napr. "Praha"</li>
                        <li><strong>PSC</strong> - napr. "120 00"</li>
                    </ul>
                    <p style="color: #888; margin: 10px 0 0; font-size: 12px;">
                        TIP: Pod formularem se zobrazi interaktivni mapa - zkontrolujte spravnost adresy!
                    </p>
                </div>

                <!-- ČÁST 4 -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">4. INFORMACE O PRODUKTU</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Model</strong> - nazev nebo kod modelu (napr. "B845")</li>
                        <li><strong>Provedeni</strong> - kliknete VYBRAT a zvolte: Latka / Kuze / Kombinace</li>
                        <li><strong>Oznaceni barvy</strong> - kod barvy z katalogu (napr. "BF12")</li>
                        <li><strong>Doplnujici informace</strong> - pristup k domu, kod do branky, kontakt na domovnika apod.</li>
                    </ul>
                </div>

                <!-- ČÁST 5 -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">5. POPIS PROBLEMU</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Popis problemu od zakaznika</strong> - popiste detailne co je spatne (povinne)</li>
                    </ul>
                </div>

                <!-- ČÁST 6 -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">6. FOTODOKUMENTACE</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li>Kliknete na <strong>"VYBRAT FOTOGRAFIE"</strong></li>
                        <li>Muzete nahrat az <strong>10 fotografii</strong> zavady</li>
                        <li>System provede <strong>automatickou kompresi</strong> - neni treba fotky upravovat</li>
                    </ul>
                    <p style="color: #888; margin: 10px 0 0; font-size: 12px;">
                        TIP: Vice fotek = rychlejsi diagnostika technikem!
                    </p>
                </div>

                <!-- ODESLÁNÍ -->
                <div style="background: #e8e8e8; border: 2px solid #333; border-radius: 6px; padding: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">7. ODESLANI POZADAVKU</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li>Zkontrolujte vsechna pole (povinne jsou oznacene <strong>*</strong>)</li>
                        <li>Kliknete na <strong>"ODESLAT POZADAVEK"</strong></li>
                        <li>Zakaznik dostane automaticky email s potvrzenim</li>
                        <li>Vy uvidite zakazku v sekci "Moje reklamace"</li>
                    </ul>
                </div>
            </div>

            <!-- NÁVOD: MOJE REKLAMACE -->
            <div style="border: 2px solid #333; border-radius: 8px; padding: 25px; margin: 30px 0;">
                <h3 style="color: #333; margin: 0 0 20px; font-size: 18px; border-bottom: 2px solid #333; padding-bottom: 10px;">
                    2. JAK SLEDOVAT VASE ZAKAZKY (Moje reklamace)
                </h3>

                <p style="color: #555; font-size: 14px; margin: 0 0 20px;">
                    V menu kliknete na <strong>"Moje reklamace"</strong> - uvidite prehled vsech servisnich pozadavku.
                </p>

                <!-- PŘEHLED KARET -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">CO VIDITE NA KARTE ZAKAZKY:</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Cislo zakazky</strong> - napr. NCN-000768 (vase cislo z prodejny)</li>
                        <li><strong>Barevny indikator stavu</strong>:
                            <ul style="margin: 5px 0; padding-left: 15px;">
                                <li><span style="color: #f5a623; font-weight: bold;">ZLUTA</span> = NOVA/CEKA na zpracovani</li>
                                <li><span style="color: #2196f3; font-weight: bold;">MODRA</span> = V RESENI (termin domluven)</li>
                                <li><span style="color: #4caf50; font-weight: bold;">ZELENA</span> = HOTOVO</li>
                            </ul>
                        </li>
                        <li><strong>Jmeno zakaznika</strong> a <strong>adresa</strong></li>
                        <li><strong>Kod technika</strong> - kdo ma zakazku prirazenu</li>
                        <li><strong>Datum vytvoreni</strong></li>
                    </ul>
                </div>

                <!-- FILTROVÁNÍ -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">FILTROVANI ZAKAZEK (tlacitka nahoře):</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>VSECHNY (pocet)</strong> - celkovy pocet zakazek</li>
                        <li><strong>CEKAJICI (pocet)</strong> - jeste nezpracovane</li>
                        <li><strong>V RESENI (pocet)</strong> - probiha servis</li>
                        <li><strong>VYRIZENE (pocet)</strong> - dokoncene</li>
                    </ul>
                </div>

                <!-- VYHLEDÁVÁNÍ -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">VYHLEDAVANI:</p>
                    <p style="color: #555; margin: 0; font-size: 13px;">
                        Pouzijte <strong>vyhledavaci pole</strong> nahoře - hledejte podle jmena, cisla zakazky, adresy nebo cehokoli dalsiho.
                    </p>
                </div>

                <!-- DETAIL KARTY -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">PO KLIKNUTI NA KARTU - ZAKLADNI PREHLED:</p>
                    <p style="color: #555; margin: 0 0 10px; font-size: 13px;">
                        Zobrazi se okno s informacemi o zakaznikovi a tlacitky:
                    </p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Jmeno</strong>, <strong>adresa</strong>, <strong>termin</strong>, <strong>stav</strong></li>
                        <li>Tlacitko <strong>"DETAIL ZAKAZNIKA"</strong> - otevre kompletni informace</li>
                        <li>Tlacitko <strong>"ZAVRIT"</strong> - zpet na seznam</li>
                    </ul>
                </div>

                <!-- KOMPLETNÍ DETAIL -->
                <div style="background: #e8e8e8; padding: 15px; border-radius: 6px;">
                    <p style="color: #333; margin: 0 0 10px; font-weight: bold;">DETAIL ZAKAZNIKA - KOMPLETNI INFORMACE:</p>
                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                        <li><strong>Zakladni udaje</strong> - cislo objednavky, zadavatel, fakturace, datumy</li>
                        <li><strong>Kontaktni udaje</strong> - jmeno, telefon, email, adresa</li>
                        <li><strong>Informace o produktu</strong> - model, provedeni, barva</li>
                        <li><strong>Doplnujici informace od prodejce</strong></li>
                        <li><strong>Popis problemu</strong> - od zakaznika i od prodejce</li>
                        <li><strong>Fotografie</strong> - vsechny nahrane fotky</li>
                        <li><strong>PDF protokolu</strong> - po dokonceni servisu ke stazeni</li>
                        <li><strong>GDPR souhlas</strong> - jak byl osteren</li>
                    </ul>
                </div>
            </div>
        ';
    }

    return '
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background: #f0f0f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f0f0f0; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">

                    <!-- HLAVICKA -->
                    <tr>
                        <td style="background: #1a1a1a; padding: 30px; text-align: center;">
                            <h1 style="color: #fff; margin: 0; font-size: 28px; font-weight: bold;">WGS</h1>
                            <p style="color: #888; margin: 10px 0 0; font-size: 14px;">White Glove Service</p>
                        </td>
                    </tr>

                    <!-- OBSAH -->
                    <tr>
                        <td style="padding: 40px 30px;">

                            <!-- UVITANI -->
                            <h2 style="color: #333; margin: 0 0 20px; font-size: 22px;">Dobry den!</h2>

                            <p style="color: #555; font-size: 16px; line-height: 1.6; margin: 0 0 20px;">
                                ' . $uvitaciText . '
                            </p>

                            <!-- DATUM SPUSTENI -->
                            <div style="background: #e8f4fd; border: 2px solid #333; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
                                <p style="color: #333; margin: 0; font-size: 16px;">
                                    <strong>System bude spusten od 1. ledna 2026</strong>
                                </p>
                                <p style="color: #555; margin: 10px 0 0; font-size: 14px;">
                                    Zaregistrujte se prosim predem, abyste byli pripraveni.
                                </p>
                            </div>

                            <!-- REGISTRACNI KLIC - VELKY A ZRETELNY -->
                            <div style="background: #1a1a1a; padding: 25px; border-radius: 8px; text-align: center; margin: 30px 0;">
                                <p style="color: #888; margin: 0 0 10px; font-size: 14px;">VAS REGISTRACNI KLIC:</p>
                                <div style="color: #fff; font-size: 28px; font-weight: bold; letter-spacing: 3px; font-family: monospace;">
                                    ' . htmlspecialchars($klic) . '
                                </div>
                                <p style="color: #666; margin: 15px 0 0; font-size: 12px;">Zkopirujte tento klic - budete ho potrebovat pri registraci</p>
                            </div>

                            <!-- NAVOD KROK ZA KROKEM -->
                            <h3 style="color: #333; margin: 30px 0 20px; font-size: 18px; border-bottom: 2px solid #333; padding-bottom: 10px;">
                                JAK SE ZAREGISTROVAT (3 jednoduche kroky)
                            </h3>

                            <!-- KROK 1 -->
                            <div style="display: flex; margin-bottom: 25px; align-items: flex-start;">
                                <div style="background: #1a1a1a; color: #fff; width: 35px; height: 35px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; flex-shrink: 0;">1</div>
                                <div style="margin-left: 15px; flex: 1;">
                                    <p style="color: #333; margin: 0 0 5px; font-weight: bold; font-size: 16px;">Otevrete stranku registrace</p>
                                    <p style="color: #555; margin: 0 0 10px; font-size: 14px;">Kliknete na tlacitko nize nebo zkopirujte odkaz do prohlizece:</p>
                                    <a href="' . $appUrl . '/registration.php" style="display: inline-block; background: #333; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;">OTEVRIT REGISTRACI</a>
                                    <p style="color: #888; margin: 10px 0 0; font-size: 12px; word-break: break-all;">' . $appUrl . '/registration.php</p>
                                </div>
                            </div>

                            <!-- KROK 2 -->
                            <div style="display: flex; margin-bottom: 25px; align-items: flex-start;">
                                <div style="background: #1a1a1a; color: #fff; width: 35px; height: 35px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; flex-shrink: 0;">2</div>
                                <div style="margin-left: 15px; flex: 1;">
                                    <p style="color: #333; margin: 0 0 5px; font-weight: bold; font-size: 16px;">Vyplnte formular</p>
                                    <ul style="color: #555; margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8;">
                                        <li><strong>Registracni klic</strong> - vlozte klic z tohoto emailu (viz vyse)</li>
                                        <li><strong>Jmeno a prijmeni</strong> - vase cele jmeno</li>
                                        <li><strong>Email</strong> - vase emailova adresa</li>
                                        <li><strong>Telefon</strong> - vase telefonni cislo</li>
                                        <li><strong>Heslo</strong> - vymyslete si heslo (minimalne 12 znaku)</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- KROK 3 -->
                            <div style="display: flex; margin-bottom: 25px; align-items: flex-start;">
                                <div style="background: #1a1a1a; color: #fff; width: 35px; height: 35px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; flex-shrink: 0;">3</div>
                                <div style="margin-left: 15px; flex: 1;">
                                    <p style="color: #333; margin: 0 0 5px; font-weight: bold; font-size: 16px;">Prihlaste se</p>
                                    <p style="color: #555; margin: 0 0 10px; font-size: 14px;">Po uspesne registraci se muzete prihlasit:</p>
                                    <a href="' . $appUrl . '/login.php" style="display: inline-block; background: #666; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px;">PRIHLASIT SE</a>
                                </div>
                            </div>

                            <!-- FUNKCE PODLE ROLE -->
                            ' . $funkce . '

                            <!-- DETAILNI NAVOD PODLE ROLE -->
                            ' . $navodHlavni . '

                            <!-- DULEZITE UPOZORNENI -->
                            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 15px; margin: 25px 0;">
                                <p style="color: #856404; margin: 0; font-size: 14px;">
                                    <strong>Dulezite:</strong> Registracni klic je urcen pouze pro vas. Prosim, nesdílejte ho s nikym dalsim.
                                </p>
                            </div>

                            <!-- SKOLENI A PODPORA -->
                            <div style="background: #f5f5f5; border-radius: 8px; padding: 25px; margin: 25px 0;">
                                <h3 style="color: #333; margin: 0 0 15px; font-size: 16px; text-align: center;">Potrebujete vysvetlit, jak system funguje?</h3>

                                <p style="color: #555; font-size: 14px; text-align: center; margin: 0 0 20px; line-height: 1.6;">
                                    Zadny problem! Radi vas <strong>proskolime po telefonu</strong> nebo <strong>osobne</strong>.<br>
                                    Staci se nam ozvat a domluvime se.
                                </p>

                                <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
                                    <div style="text-align: center;">
                                        <p style="color: #333; margin: 0 0 5px; font-weight: bold; font-size: 14px;">Telefon</p>
                                        <a href="tel:+420777123456" style="color: #333; font-size: 16px; text-decoration: none;">+420 777 123 456</a>
                                    </div>
                                    <div style="text-align: center;">
                                        <p style="color: #333; margin: 0 0 5px; font-weight: bold; font-size: 14px;">Email</p>
                                        <a href="mailto:info@wgs-service.cz" style="color: #333; font-size: 16px; text-decoration: none;">info@wgs-service.cz</a>
                                    </div>
                                </div>

                                <p style="color: #888; font-size: 12px; text-align: center; margin: 20px 0 0;">
                                    Skoleni je zdarma a trva priblizne 15-30 minut.
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- PATICKA -->
                    <tr>
                        <td style="background: #1a1a1a; padding: 30px; text-align: center;">
                            <p style="color: #fff; margin: 0 0 5px; font-size: 16px; font-weight: bold;">White Glove Service</p>
                            <p style="color: #888; margin: 0 0 15px; font-size: 13px;">Autorizovany servis Natuzzi pro CR a SR</p>
                            <p style="color: #666; margin: 0; font-size: 12px;">
                                <a href="' . $appUrl . '" style="color: #888; text-decoration: none;">www.wgs-service.cz</a>
                            </p>
                            <p style="color: #555; margin: 20px 0 0; font-size: 11px;">&copy; ' . $rok . ' WGS Service. Vsechna prava vyhrazena.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
    ';
}

/**
 * Načíst texty šablony pozvánky z databáze
 */
function handleGetInvitationTexts(PDO $pdo): void
{
    // Výchozí texty
    $vychoziTexty = [
        'predmetEmailu' => 'Pozvanka do systemu WGS - {ROLE}',
        'uvitaciText' => 'Byli jste pozvani jako **{ROLE}** do systemu White Glove Service pro spravu servisnich zakazek Natuzzi.',
        'funkceProdejce' => "Zadavat **nove reklamace** pro vase zakazniky\nSledovat **stav vasich zakazek** v realnem case\nVidet **historii vsech reklamaci** ktere jste zadali\nNahravat **dokumenty a fotky** k zakazkam\nPridavat **poznamky** pro techniky\nVidet kdy technik **navstivi zakaznika**",
        'funkceTechnik' => "Videt sve **prirazene zakazky** v prehlednem seznamu\nMenit **stav zakazky** (Ceka / Domluvena / Hotovo)\nVyplnovat **servisni protokoly** s automatickym prekladem\nNahravat **fotky pred a po oprave**\nVidet **adresu zakaznika na mape** s navigaci\nNechat zakaznika **elektronicky podepsat** protokol\nExportovat protokol do **PDF** a poslat zakaznikovi",
        'navodProdejce' => "# JAK OBJEDNAT SERVIS PRO ZAKAZNIKA\n\nPo prihlaseni kliknete na **\"Objednat servis\"** v menu. Formular ma 5 casti:\n\n## 1. ZAKLADNI UDAJE\n- Cislo objednavky/reklamace - vase interni cislo\n- Fakturace - CZ nebo SK\n- Datum prodeje a reklamace\n\n## 2. KONTAKTNI UDAJE\n- Jmeno a prijmeni zakaznika\n- Email a telefon\n\n## 3. ADRESA\n- Ulice, mesto, PSC\n- Po zadani se zobrazi mapa\n\n## 4. PRODUKT\n- Model, provedeni, barva\n\n## 5. PROBLEM\n- Popis zavady + fotky",
        'navodTechnik' => "# PREHLED VASICH ZAKAZEK\n\nPo prihlaseni uvidite seznam vsech prirazenych zakazek.\n\n## Co vidite na karte:\n- Cislo zakazky (napr. WGS-2025-001)\n- Barevny stav (zluta/modra/zelena)\n- Jmeno a adresa zakaznika\n\n## Filtrovani:\n- Vsechny / Cekajici / V reseni / Vyrizene\n\n# SERVISNI PROTOKOL\n\nProtokol se predvyplni automaticky. Vy doplnite:\n- Problem zjisteny technikem\n- Navrh opravy\n- Uctovani\n\nPo dokonceni nechte zakaznika podepsat a exportujte PDF."
    ];

    try {
        // Zkusit načíst z databáze
        $stmt = $pdo->prepare("
            SELECT config_value
            FROM wgs_system_config
            WHERE config_key = 'invitation_template_texts'
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty($result['config_value'])) {
            $ulozeneTexty = json_decode($result['config_value'], true);
            if (is_array($ulozeneTexty)) {
                // Sloučit s výchozími (pro případ nových polí)
                $texty = array_merge($vychoziTexty, $ulozeneTexty);
                respondSuccess(['texts' => $texty, 'source' => 'database']);
                return;
            }
        }
    } catch (PDOException $e) {
        error_log("Chyba nacitani textu sablony: " . $e->getMessage());
    }

    // Vrátit výchozí texty
    respondSuccess(['texts' => $vychoziTexty, 'source' => 'default']);
}

/**
 * Uložit texty šablony pozvánky
 */
function handleSaveInvitationTexts(PDO $pdo, array $payload): void
{
    $texty = $payload['texts'] ?? null;

    if (!is_array($texty)) {
        respondError('Chybi texty sablony.');
        return;
    }

    // Validace povolených polí
    $povolenaPole = [
        'predmetEmailu',
        'uvitaciText',
        'funkceProdejce',
        'funkceTechnik',
        'navodProdejce',
        'navodTechnik'
    ];

    $filtrovanetexty = [];
    foreach ($povolenaPole as $pole) {
        if (isset($texty[$pole])) {
            $filtrovanetexty[$pole] = trim((string)$texty[$pole]);
        }
    }

    $jsonTexty = json_encode($filtrovanetexty, JSON_UNESCAPED_UNICODE);

    try {
        // Upsert - INSERT nebo UPDATE
        $stmt = $pdo->prepare("
            INSERT INTO wgs_system_config (config_key, config_value, config_group, updated_at)
            VALUES ('invitation_template_texts', ?, 'templates', NOW())
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
        ");
        $stmt->execute([$jsonTexty]);

        respondSuccess(['message' => 'Texty sablony ulozeny.', 'texts' => $filtrovanetexty]);

    } catch (PDOException $e) {
        error_log("Chyba ukladani textu sablony: " . $e->getMessage());
        respondError('Chyba pri ukladani textu.');
    }
}

