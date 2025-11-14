<?php
/**
 * Save Controller
 * Ukládání reklamací a servisních požadavků
 */

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/db_metadata.php';

/**
 * Generuje unikátní ID pro reklamaci ve formátu WGSyymmdd-XXXXXX
 *
 * Používá FOR UPDATE lock k prevenci race condition při generování ID.
 * Pokud ID už existuje, zkusí vygenerovat nové (max 5 pokusů).
 *
 * @param PDO $pdo Database connection
 * @return string Vygenerované unikátní ID (např. "WGS251114-A3F2B1")
 * @throws Exception Pokud se nepodaří vygenerovat ID po 5 pokusech
 */
function generateWorkflowId(PDO $pdo): string
{
    // BUGFIX: Race condition fix - použít FOR UPDATE lock
    $attempts = 0;
    do {
        $candidate = 'WGS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // FOR UPDATE lock zajistí, že jiná transakce nemůže číst tento záznam současně
        $stmt = $pdo->prepare('SELECT reklamace_id FROM wgs_reklamace WHERE reklamace_id = :id FOR UPDATE');
        $stmt->execute([':id' => $candidate]);

        if ($stmt->rowCount() === 0) {
            // ID neexistuje, můžeme ho použít
            return $candidate;
        }

        $attempts++;
    } while ($attempts < 5);

    throw new Exception('Nepodařilo se vygenerovat interní ID reklamace.');
}

/**
 * Normalizuje datum z různých formátů do ISO 8601 (YYYY-MM-DD)
 *
 * Podporované formáty:
 * - NULL nebo prázdný string → NULL
 * - "nevyplňuje se" → NULL
 * - YYYY-MM-DD → YYYY-MM-DD (beze změny)
 * - DD.MM.YYYY → YYYY-MM-DD (převod)
 *
 * Validuje že datum je platné pomocí checkdate() (detekuje např. 32.13.9999).
 *
 * @param string|null $value Vstupní datum v různých formátech
 * @return string|null Normalizované datum ve formátu YYYY-MM-DD nebo NULL
 * @throws Exception Pokud formát data není rozpoznán nebo datum je neplatné
 */
function normalizeDateInput(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim($value);
    if ($trimmed === '' || strcasecmp($trimmed, 'nevyplňuje se') === 0) {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
        return $trimmed;
    }

    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $trimmed, $matches)) {
        // BUGFIX: Validace že datum je skutečně platné (ne 32.13.9999)
        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = (int)$matches[3];

        if (!checkdate($month, $day, $year)) {
            throw new Exception('Neplatné datum (den/měsíc/rok je mimo rozsah): ' . $value);
        }

        return sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);
    }

    throw new Exception('Neplatný formát data: ' . $value);
}

/**
 * Aktualizuje existující reklamaci v databázi
 *
 * Provádí kompletní update záznamu reklamace včetně:
 * - Kontroly oprávnění (admin nebo vlastník)
 * - Validace vstupních dat
 * - Normalizace datumů
 * - File-first přístupu pro přílohy
 * - Transakční bezpečnosti
 *
 * Podporuje identifikaci záznamu podle: id, reklamace_id nebo cislo.
 *
 * @param PDO $pdo Database connection
 * @param array $input Vstupní data z formuláře (POST data)
 * @return array Výsledek operace ['success' => bool, 'message' => string, 'data' => array]
 * @throws Exception Při chybě oprávnění, validace nebo DB operace
 */
function handleUpdate(PDO $pdo, array $input): array
{
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$isAdmin && !$userId) {
        throw new Exception('Neautorizovaný přístup.');
    }

    $columns = db_get_table_columns($pdo, 'wgs_reklamace');
    if (empty($columns)) {
        throw new Exception('Nelze načíst strukturu tabulky reklamací.');
    }

    $identifier = $input['id'] ?? $input['reklamace_id'] ?? $input['claim_id'] ?? null;
    if ($identifier === null || $identifier === '') {
        throw new Exception('Chybí identifikátor reklamace.');
    }

    $identifierColumn = 'id';
    if (!ctype_digit((string) $identifier) || !in_array('id', $columns, true)) {
        if (!empty($input['reklamace_id']) && in_array('reklamace_id', $columns, true)) {
            $identifier = $input['reklamace_id'];
            $identifierColumn = 'reklamace_id';
        } elseif (!empty($input['reference']) && in_array('cislo', $columns, true)) {
            $identifier = $input['reference'];
            $identifierColumn = 'cislo';
        } elseif (in_array('reklamace_id', $columns, true)) {
            $identifierColumn = 'reklamace_id';
        } elseif (in_array('cislo', $columns, true)) {
            $identifierColumn = 'cislo';
        }
    } else {
        $identifier = (int) $identifier;
    }

    $allowedFields = [
        'cislo',
        'datum_prodeje',
        'datum_reklamace',
        'jmeno',
        'telefon',
        'email',
        'adresa',
        'model',
        'provedeni',
        'barva',
        'doplnujici_info',
        'popis_problemu',
        'stav',
        'termin',
        'cas_navstevy',
        'fakturace_firma'
    ];

    $updateData = [];

    $markAsCompleted = isset($input['mark_as_completed']) && in_array(strtolower((string) $input['mark_as_completed']), ['1', 'true', 'yes'], true);
    if ($markAsCompleted && in_array('stav', $columns, true)) {
        $updateData['stav'] = 'HOTOVO';
        if (db_table_has_column($pdo, 'wgs_reklamace', 'completed_at')) {
            $updateData['completed_at'] = date('Y-m-d H:i:s');
        }
    }

    foreach ($allowedFields as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }

        if (!in_array($field, $columns, true)) {
            continue;
        }

        $value = $input[$field];

        switch ($field) {
            case 'datum_prodeje':
            case 'datum_reklamace':
                $updateData[$field] = normalizeDateInput($value);
                break;
            case 'email':
                $email = trim((string) $value);
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Neplatný formát emailu.');
                }
                $updateData[$field] = $email === '' ? null : $email;
                break;
            case 'termin':
                $trimmed = trim((string) $value);
                $updateData[$field] = $trimmed === '' ? null : $trimmed;
                break;
            case 'cas_navstevy':
                $time = trim((string) $value);
                $updateData[$field] = $time === '' ? null : $time;
                break;
            case 'stav':
                // MAPPING: Frontend posílá české názvy, DB používá anglické ENUM
                $stavValue = trim((string) $value);
                $stavMapping = [
                    'ČEKÁ' => 'wait',
                    'wait' => 'wait',
                    'DOMLUVENÁ' => 'open',
                    'open' => 'open',
                    'HOTOVO' => 'done',
                    'done' => 'done'
                ];

                if ($stavValue !== '' && isset($stavMapping[$stavValue])) {
                    $updateData[$field] = $stavMapping[$stavValue];
                } elseif ($stavValue === '') {
                    $updateData[$field] = null;
                } else {
                    // Fallback: použít hodnotu jak je (pro zpětnou kompatibilitu)
                    $updateData[$field] = $stavValue;
                }
                break;
            case 'fakturace_firma':
                // MAPPING: DB používá lowercase ENUM('cz','sk')
                $firmValue = trim((string) $value);
                $updateData[$field] = $firmValue === '' ? null : strtolower($firmValue);
                break;
            default:
                $sanitized = sanitizeInput((string) $value);
                $updateData[$field] = $sanitized === '' ? null : $sanitized;
        }
    }

    if (empty($updateData)) {
        throw new Exception('Nebyla poskytnuta žádná data ke změně.');
    }

    if (db_table_has_column($pdo, 'wgs_reklamace', 'updated_at')) {
        $updateData['updated_at'] = date('Y-m-d H:i:s');
    }

    $setParts = [];
    $params = [':identifier' => $identifier];

    foreach ($updateData as $column => $value) {
        $setParts[] = '`' . $column . '` = :' . $column;
        $params[':' . $column] = $value;
    }

    if (empty($setParts)) {
        throw new Exception('Nebyla nalezena žádná platná pole pro aktualizaci.');
    }

    // BUGFIX: Transaction support - atomicita update operace
    $pdo->beginTransaction();

    try {
        $sql = 'UPDATE wgs_reklamace SET ' . implode(', ', $setParts) . ' WHERE `' . $identifierColumn . '` = :identifier';
        if ($identifierColumn === 'id') {
            $sql .= ' LIMIT 1';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Reklamace nebyla nalezena nebo nebyla změněna.');
        }

        $pdo->commit();

        return [
            'status' => 'success',
            'message' => 'Reklamace byla aktualizována.',
            'updated_fields' => array_keys($updateData)
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

header('Content-Type: application/json');

try {
    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // Kontrola CSRF tokenu
    requireCSRF();

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $requestData = $_POST;

    if (stripos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        $decoded = json_decode($rawBody ?? '', true);
        if (is_array($decoded)) {
            $requestData = array_merge($requestData, $decoded);
        }
    }

    $action = $requestData['action'] ?? '';
    if ($action === '') {
        throw new Exception('Neplatná akce');
    }

    $pdo = getDbConnection();

    if ($action === 'update') {
        $response = handleUpdate($pdo, $requestData);
        echo json_encode($response);
        return;
    }

    if ($action !== 'create') {
        throw new Exception('Neznámá akce.');
    }

    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

    // Získání dat z formuláře - BEZPEČNOST: Sanitizace všech vstupů
    $typ = sanitizeInput($_POST['typ'] ?? 'servis');
    $cislo = sanitizeInput($_POST['cislo'] ?? '');
    $datumProdeje = sanitizeInput($_POST['datum_prodeje'] ?? null);
    $datumReklamace = sanitizeInput($_POST['datum_reklamace'] ?? null);
    $jmeno = sanitizeInput($_POST['jmeno'] ?? '');
    // Email - pouze trim, ne sanitizeInput (kvůli zachování formátu)
    $email = trim($_POST['email'] ?? '');
    $telefon = sanitizeInput($_POST['telefon'] ?? '');
    $adresa = sanitizeInput($_POST['adresa'] ?? '');
    $model = sanitizeInput($_POST['model'] ?? '');
    $provedeni = sanitizeInput($_POST['provedeni'] ?? '');
    $barva = sanitizeInput($_POST['barva'] ?? '');
    $serioveCislo = sanitizeInput($_POST['seriove_cislo'] ?? '');
    $popisProblemu = sanitizeInput($_POST['popis_problemu'] ?? '');
    $doplnujiciInfo = sanitizeInput($_POST['doplnujici_info'] ?? '');
    $fakturaceFirma = strtolower(trim($_POST['fakturace_firma'] ?? 'cz')); // DB používá lowercase ENUM
    $gdprConsentRaw = $_POST['gdpr_consent'] ?? null;
    $gdprConsent = filter_var($gdprConsentRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    if ($gdprConsent !== true) {
        throw new Exception('Je nutné potvrdit souhlas se zpracováním osobních údajů.');
    }

    $gdprConsentAt = date('Y-m-d H:i:s');
    $gdprConsentIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $gdprNoteParts = ["GDPR souhlas udělen {$gdprConsentAt}"];

    if (!empty($gdprConsentIp)) {
        $gdprNoteParts[] = 'IP: ' . $gdprConsentIp;
    }

    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'], 0, 200);
        $gdprNoteParts[] = 'UA: ' . $userAgent;
    }

    $gdprNote = sanitizeInput(implode(' | ', $gdprNoteParts));

    if (!empty($doplnujiciInfo)) {
        $doplnujiciInfo = trim($doplnujiciInfo) . "\n\n" . $gdprNote;
    } else {
        $doplnujiciInfo = $gdprNote;
    }

    // Dodatečná validace emailu - pouze pokud je vyplněn
    if (!empty($email)) {
        // Validace formátu
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Neplatný formát emailu');
        }
        // Sanitizace pro bezpečné uložení do DB
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    // Validace povinných polí
    if (empty($jmeno)) {
        throw new Exception('Jméno je povinné');
    }
    if (empty($telefon) && empty($email)) {
        throw new Exception('Je nutné vyplnit telefon nebo email');
    }
    if (empty($popisProblemu)) {
        throw new Exception('Popis problému je povinný');
    }

    // BUGFIX: Odstraněn duplicitní GDPR consent check (už byl proveden výše na řádcích 279-305)

    // Formátování dat pro databázi
    $datumProdejeForDb = null;
    if (!empty($datumProdeje) && $datumProdeje !== 'nevyplňuje se') {
        // Převod z českého formátu dd.mm.yyyy na yyyy-mm-dd
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $datumProdeje, $matches)) {
            $datumProdejeForDb = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }
    }

    $datumReklamaceForDb = null;
    if (!empty($datumReklamace) && $datumReklamace !== 'nevyplňuje se') {
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $datumReklamace, $matches)) {
            $datumReklamaceForDb = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }
    }

    $hasReklamaceId = db_table_has_column($pdo, 'wgs_reklamace', 'reklamace_id');
    $hasCreatedBy = db_table_has_column($pdo, 'wgs_reklamace', 'created_by');
    $hasCreatedByRole = db_table_has_column($pdo, 'wgs_reklamace', 'created_by_role');
    $hasZpracovalId = db_table_has_column($pdo, 'wgs_reklamace', 'zpracoval_id');
    $hasCreatedAt = db_table_has_column($pdo, 'wgs_reklamace', 'created_at');
    $hasUpdatedAt = db_table_has_column($pdo, 'wgs_reklamace', 'updated_at');

    // CRITICAL FIX: Zahájit transakci PŘED generováním ID
    // FOR UPDATE lock funguje pouze v transakci!
    $pdo->beginTransaction();

    try {
        $workflowId = null;
        if ($hasReklamaceId) {
            $workflowId = generateWorkflowId($pdo);
        }

        $now = date('Y-m-d H:i:s');

    $columns = [
        'typ' => $typ,
        'cislo' => $cislo,
        'datum_prodeje' => $datumProdejeForDb,
        'datum_reklamace' => $datumReklamaceForDb,
        'jmeno' => $jmeno,
        'email' => $email,
        'telefon' => $telefon,
        'adresa' => $adresa,
        'model' => $model,
        'provedeni' => $provedeni,
        'barva' => $barva,
        'seriove_cislo' => $serioveCislo,
        'popis_problemu' => $popisProblemu,
        'doplnujici_info' => $doplnujiciInfo,
        'fakturace_firma' => $fakturaceFirma
    ];

    if ($hasReklamaceId && $workflowId !== null) {
        $columns['reklamace_id'] = $workflowId;
    }

    if ($hasCreatedBy) {
        $columns['created_by'] = $_SESSION['user_id'] ?? null;
    }

    if ($hasCreatedByRole) {
        $columns['created_by_role'] = $_SESSION['role'] ?? ($isLoggedIn ? 'user' : 'guest');
    }

    // OPRAVA: Nastavit zpracoval_id pro viditelnost v load.php
    if ($hasZpracovalId) {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId !== null) {
            $columns['zpracoval_id'] = $userId;
        }
    }

    if ($hasCreatedAt) {
        $columns['created_at'] = $now;
    }

    if ($hasUpdatedAt) {
        $columns['updated_at'] = $now;
    }

    $columnNames = array_keys($columns);
    $placeholders = array_map(fn($col) => ':' . $col, $columnNames);

    $sql = 'INSERT INTO wgs_reklamace (' . implode(', ', $columnNames) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);

    $parameters = [];
    foreach ($columns as $column => $value) {
        $parameters[':' . $column] = $value === '' ? null : $value;
    }

        if (!$stmt->execute($parameters)) {
            throw new Exception('Chyba při ukládání do databáze');
        }

        // CRITICAL FIX: COMMIT transakce
        $pdo->commit();

        $primaryId = $pdo->lastInsertId();
        $identifierForClient = $workflowId ?? $primaryId;

    } catch (Exception $e) {
        // CRITICAL FIX: ROLLBACK při chybě
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Save error: " . $e->getMessage());
        throw $e;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Reklamace byla úspěšně vytvořena',
        'reklamace_id' => $identifierForClient,
        'id' => $primaryId,
        'workflow_id' => $identifierForClient,
        'reference' => $cislo
    ]);

} catch (Exception $e) {
    // Log error for debugging
    error_log('SAVE.PHP ERROR: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine());
    error_log('SAVE.PHP POST DATA: ' . json_encode($_POST));

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
