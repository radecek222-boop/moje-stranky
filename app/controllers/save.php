<?php
/**
 * Save Controller
 * Ukládání reklamací a servisních požadavků
 */

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/db_metadata.php';
require_once __DIR__ . '/../../includes/email_domain_validator.php';

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
/**
 * GenerateWorkflowId
 *
 * @param PDO $pdo Pdo
 */
function generateWorkflowId(PDO $pdo): string
{
    // Formát: WGS/YYYY/DD-MM/XXXXX
    // Příklad: WGS/2025/18-11/00001
    // Sekvenční číslo se resetuje každý den

    $rok = date('Y');        // 2025
    $denMesic = date('d-m'); // 18-11
    $prefix = "WGS/{$rok}/{$denMesic}/";

    // Najít nejvyšší číslo pro dnešní den
    $stmt = $pdo->prepare("
        SELECT reklamace_id FROM wgs_reklamace
        WHERE reklamace_id LIKE :prefix
        ORDER BY reklamace_id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([':prefix' => $prefix . '%']);
    $lastId = $stmt->fetchColumn();

    if ($lastId) {
        // Extrahovat číslo z konce (WGS/2025/18-11/00001 -> 00001)
        $parts = explode('/', $lastId);
        $cislo = (int)end($parts);
        $noveCislo = $cislo + 1;
    } else {
        // První reklamace pro dnešní den
        $noveCislo = 1;
    }

    // Formátovat číslo na 5 číslic (00001, 00002, ...)
    $candidate = sprintf('%s%05d', $prefix, $noveCislo);

    return $candidate;
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
/**
 * NormalizeDateInput
 *
 * @param string $value Value
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
/**
 * HandleUpdate
 *
 * @param PDO $pdo Pdo
 * @param array $input Input
 */
function handleUpdate(PDO $pdo, array $input): array
{
    // ⏱️ PERFORMANCE: Backend timing
    $t0 = microtime(true);
    error_log("⏱️ handleUpdate START");

    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$isAdmin && !$userId) {
        throw new Exception('Neautorizovaný přístup.');
    }

    $t1 = microtime(true);
    $columns = db_get_table_columns($pdo, 'wgs_reklamace');
    $t2 = microtime(true);
    error_log(sprintf("⏱️ db_get_table_columns: %.0fms", ($t2 - $t1) * 1000));
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
        'ulice',
        'mesto',
        'psc',
        'model',
        'provedeni',
        'barva',
        'doplnujici_info',
        'popis_problemu',
        'stav',
        'termin',
        'cas_navstevy',
        'fakturace_firma',
        'technik',
        'prodejce'
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
                if ($email !== '') {
                    // Validace formátu a kontrola existence domény
                    $emailValidation = validateAndSanitizeEmail($email, true);
                    if (!$emailValidation['valid']) {
                        throw new Exception($emailValidation['error']);
                    }
                    $email = $emailValidation['email'];
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
    $t3 = microtime(true);
    $pdo->beginTransaction();
    $t4 = microtime(true);
    error_log(sprintf("⏱️ beginTransaction: %.0fms", ($t4 - $t3) * 1000));

    try {
        $sql = 'UPDATE wgs_reklamace SET ' . implode(', ', $setParts) . ' WHERE `' . $identifierColumn . '` = :identifier';
        if ($identifierColumn === 'id') {
            $sql .= ' LIMIT 1';
        }

        $t5 = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $t6 = microtime(true);
        error_log(sprintf("⏱️ SQL UPDATE execute: %.0fms", ($t6 - $t5) * 1000));

        if ($stmt->rowCount() === 0) {
            throw new Exception('Reklamace nebyla nalezena nebo nebyla změněna.');
        }

        $t7 = microtime(true);
        $pdo->commit();
        $t8 = microtime(true);
        error_log(sprintf("⏱️ commit: %.0fms", ($t8 - $t7) * 1000));

        // ⏱️ CELKOVÝ ČAS
        $tTotal = microtime(true);
        error_log(sprintf("⏱️ ✅ handleUpdate TOTAL: %.0fms (%.1fs)", ($tTotal - $t0) * 1000, $tTotal - $t0));

        return [
            'status' => 'success',
            'message' => 'Reklamace byla aktualizována.',
            'updated_fields' => array_keys($updateData)
        ];
    } catch (Exception $e) {
        $pdo->rollBack();

        // ⏱️ Log času i při chybě
        $tError = microtime(true);
        error_log(sprintf("⏱️ ❌ Čas do chyby: %.0fms", ($tError - $t0) * 1000));

        throw $e;
    }
}

/**
 * Znovu otevře zakázku vytvořením klonu původní zakázky
 *
 * Funkce vytvoří úplnou kopii zakázky s novým ID a stavem ČEKÁ.
 * Původní zakázka zůstává HOTOVO pro správné statistiky.
 *
 * @param PDO $pdo Database connection
 * @param array $input Vstupní data (original_id)
 * @return array Výsledek operace
 * @throws Exception Při chybě oprávnění nebo DB operace
 */
function handleReopen(PDO $pdo, array $input): array
{
    error_log("⏱️ handleReopen START");
    $t0 = microtime(true);

    // Kontrola oprávnění
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$isAdmin && !$userId) {
        throw new Exception('Neautorizovaný přístup.');
    }

    // Získat ID původní zakázky
    $originalId = $input['original_id'] ?? $input['id'] ?? null;
    if ($originalId === null || $originalId === '') {
        throw new Exception('Chybí ID původní zakázky.');
    }

    // Načíst původní zakázku
    $stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $originalId]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        throw new Exception('Původní zakázka nebyla nalezena.');
    }

    // Kontrola stavu - musí být dokončená
    if ($original['stav'] !== 'done') {
        throw new Exception('Lze klonovat pouze dokončené zakázky (stav HOTOVO).');
    }

    // Zahájit transakci
    $pdo->beginTransaction();

    try {
        // Vygenerovat nové ID
        $newWorkflowId = generateWorkflowId($pdo);
        $now = date('Y-m-d H:i:s');

        // Připravit data pro klon
        $columns = [
            'reklamace_id' => $newWorkflowId,
            'original_reklamace_id' => $original['reklamace_id'] ?? $original['id'],
            'typ' => $original['typ'] ?? 'servis',
            'cislo' => $original['cislo'],
            'datum_prodeje' => $original['datum_prodeje'],
            'datum_reklamace' => null, // Nová reklamace
            'jmeno' => $original['jmeno'],
            'email' => $original['email'],
            'telefon' => $original['telefon'],
            'adresa' => $original['adresa'],
            'ulice' => $original['ulice'],
            'mesto' => $original['mesto'],
            'psc' => $original['psc'],
            'model' => $original['model'],
            'provedeni' => $original['provedeni'],
            'barva' => $original['barva'],
            'seriove_cislo' => $original['seriove_cislo'],
            'popis_problemu' => $original['popis_problemu'],
            'doplnujici_info' => $original['doplnujici_info'],
            'fakturace_firma' => $original['fakturace_firma'],
            'stav' => 'wait', // Nová zakázka
            'termin' => null,
            'cas_navstevy' => null,
            'datum_dokonceni' => null,
            'zpracoval_id' => $userId,
            'created_by' => $userId,
            'created_by_role' => $_SESSION['role'] ?? 'user',
            'created_at' => $now,
            'updated_at' => $now
        ];

        // Sestavit INSERT dotaz
        $columnNames = array_keys($columns);
        $placeholders = array_map(fn($col) => ':' . $col, $columnNames);

        $sql = 'INSERT INTO wgs_reklamace (' . implode(', ', $columnNames) . ')
                VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = $pdo->prepare($sql);

        // Připravit parametry
        $parameters = [];
        foreach ($columns as $column => $value) {
            $parameters[':' . $column] = $value === '' ? null : $value;
        }

        if (!$stmt->execute($parameters)) {
            throw new Exception('Chyba při vytváření klonu zakázky');
        }

        $newId = $pdo->lastInsertId();

        // Přidat poznámku do nové zakázky
        $noteTextNew = "🔄 Zakázka otevřena jako klon původní zakázky\n\n" .
                       "Původní zakázka: " . ($original['reklamace_id'] ?? $original['id']) . "\n" .
                       "Stav: NOVÁ (čeká na zpracování)\n" .
                       "Vytvořil: " . ($_SESSION['user_name'] ?? 'Uživatel') . "\n" .
                       "Datum: " . date('d.m.Y H:i');

        $stmtNote = $pdo->prepare("
            INSERT INTO wgs_notes (reklamace_id, note_text, created_by, created_at, is_read)
            VALUES (:reklamace_id, :note_text, :created_by, :created_at, 0)
        ");
        $stmtNote->execute([
            'reklamace_id' => $newId,
            'note_text' => $noteTextNew,
            'created_by' => $userId ?? 0,
            'created_at' => $now
        ]);

        // Přidat poznámku do původní zakázky
        $noteTextOriginal = "🔗 Založena nová zakázka (reklamace)\n\n" .
                            "Nová zakázka: " . $newWorkflowId . "\n" .
                            "Zákazník znovu nahlásil problém.\n" .
                            "Vytvořil: " . ($_SESSION['user_name'] ?? 'Uživatel') . "\n" .
                            "Datum: " . date('d.m.Y H:i');

        $stmtNote2 = $pdo->prepare("
            INSERT INTO wgs_notes (reklamace_id, note_text, created_by, created_at, is_read)
            VALUES (:reklamace_id, :note_text, :created_by, :created_at, 0)
        ");
        $stmtNote2->execute([
            'reklamace_id' => $originalId,
            'note_text' => $noteTextOriginal,
            'created_by' => $userId ?? 0,
            'created_at' => $now
        ]);

        // Commit transakce
        $pdo->commit();

        $t1 = microtime(true);
        error_log(sprintf("⏱️ handleReopen DONE: %.0fms", ($t1 - $t0) * 1000));

        return [
            'status' => 'success',
            'message' => 'Nová zakázka vytvořena jako klon',
            'new_id' => $newId,
            'new_workflow_id' => $newWorkflowId,
            'original_id' => $originalId
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $tError = microtime(true);
        error_log(sprintf("⏱️ ❌ handleReopen CHYBA: %.0fms - %s", ($tError - $t0) * 1000, $e->getMessage()));

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

    if ($action === 'reopen') {
        $response = handleReopen($pdo, $requestData);
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

    // ADRESA - buď jako celek nebo složená z ulice + mesto + psc
    $ulice = sanitizeInput($_POST['ulice'] ?? '');
    $mesto = sanitizeInput($_POST['mesto'] ?? '');
    $psc = sanitizeInput($_POST['psc'] ?? '');

    // Pokud není adresa přímo, složit ji z ulice + mesto + psc
    $adresa = sanitizeInput($_POST['adresa'] ?? '');
    if (empty($adresa) && (!empty($ulice) || !empty($mesto) || !empty($psc))) {
        $adresaParts = array_filter([$ulice, $mesto, $psc], fn($v) => !empty($v));
        $adresa = implode(', ', $adresaParts);
    }

    $model = sanitizeInput($_POST['model'] ?? '');
    $provedeni = sanitizeInput($_POST['provedeni'] ?? '');
    $barva = sanitizeInput($_POST['barva'] ?? '');
    $serioveCislo = sanitizeInput($_POST['seriove_cislo'] ?? '');
    $popisProblemu = sanitizeInput($_POST['popis_problemu'] ?? '');
    $doplnujiciInfo = sanitizeInput($_POST['doplnujici_info'] ?? '');
    $fakturaceFirma = strtolower(trim($_POST['fakturace_firma'] ?? 'cz')); // DB používá lowercase ENUM
    // GDPR souhlas - pouze pro neregistrované uživatele
    // Registrovaní uživatelé (prodejce, technik, admin) mají souhlas ošetřen ve smlouvách
    $gdprConsentRaw = $_POST['gdpr_consent'] ?? null;
    $gdprConsent = filter_var($gdprConsentRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    if (!$isLoggedIn) {
        // Neregistrovaný uživatel - vyžaduje explicitní souhlas
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
    } else {
        // Registrovaný uživatel - souhlas ošetřen smluvně
        $userId = $_SESSION['user_id'] ?? 'unknown';
        $userRole = $_SESSION['role'] ?? 'user';
        $gdprNote = "GDPR souhlas ošetřen smluvně (User ID: {$userId}, Role: {$userRole})";
    }

    if (!empty($doplnujiciInfo)) {
        $doplnujiciInfo = trim($doplnujiciInfo) . "\n\n" . $gdprNote;
    } else {
        $doplnujiciInfo = $gdprNote;
    }

    // Dodatečná validace emailu - pouze pokud je vyplněn
    if (!empty($email)) {
        // Validace formátu a kontrola existence domény (MX záznamy)
        $emailValidation = validateAndSanitizeEmail($email, true);

        if (!$emailValidation['valid']) {
            throw new Exception($emailValidation['error']);
        }

        // Použít sanitizovaný email
        $email = $emailValidation['email'];
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
        'ulice' => $ulice,
        'mesto' => $mesto,
        'psc' => $psc,
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

    // ✅ SECURITY FIX: Sanitizovat POST data před logováním (odstranit citlivá pole)
    $safePost = $_POST;
    $sensitiveKeys = ['password', 'csrf_token', 'credit_card', 'pin', 'ssn', 'card_number'];
    foreach ($sensitiveKeys as $key) {
        if (isset($safePost[$key])) {
            $safePost[$key] = '[REDACTED]';
        }
    }
    error_log('SAVE.PHP POST DATA: ' . json_encode($safePost));

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
