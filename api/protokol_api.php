<?php
/**
 * Protokol API
 * API pro ukládání PDF protokolů a práci s protokoly
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/reklamace_id_validator.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');
// PERFORMANCE: Cache-Control header (10 minut)
// Protokoly (PDF dokumenty) se nemění často
header('Cache-Control: private, max-age=600'); // 10 minut

try {
    // BEZPEČNOST: Kontrola přihlášení (admin nebo technik)
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Neautorizovaný přístup. Přihlaste se prosím.'
        ]);
        exit;
    }

    // PERFORMANCE FIX: Načíst session data a uvolnit zámek
    // Audit 2025-11-24: PDF generation (1-3s) blokuje ostatní requesty
    $uploadedBy = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'system';

    // KRITICKÉ: Uvolnit session lock pro paralelní zpracování
    session_write_close();

    // Získání akce
    $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
    $isGet = $_SERVER['REQUEST_METHOD'] === 'GET';

    if ($isPost) {
        // Načtení JSON dat PŘED CSRF kontrolou
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);

        if (!$data) {
            throw new Exception('Neplatná JSON data');
        }

        // BEZPEČNOST: CSRF ochrana pro POST operace
        // Ensure CSRF token is a string, not an array (security)
        $csrfToken = $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
        if (is_array($csrfToken)) {
            $csrfToken = ''; // Reject arrays
        }
        if (!validateCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Neplatný CSRF token. Obnovte stránku a zkuste znovu.'
            ]);
            exit;
        }

        $action = $data['action'] ?? '';
    } elseif ($isGet) {
        // Pro GET požadavky (load_reklamace)
        $action = $_GET['action'] ?? '';
        $data = $_GET;
    } else {
        throw new Exception('Povolena pouze POST nebo GET metoda');
    }

    // FIX 9: Databázový rate limiting pouze pro POST operace (upload, save, send)
    if ($isPost && in_array($action, ['save_pdf_document', 'save_protokol', 'send_email'])) {
        $pdo = getDbConnection();
        $rateLimiter = new RateLimiter($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $rateCheck = $rateLimiter->checkLimit(
            $ip,
            'pdf_upload',
            ['max_attempts' => 10, 'window_minutes' => 60, 'block_minutes' => 120]
        );

        if (!$rateCheck['allowed']) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => $rateCheck['message'],
                'retry_after' => strtotime($rateCheck['reset_at']) - time()
            ]);
            exit;
        }

        // FIX 9: RateLimiter již zaznamenal pokus automaticky v checkLimit()
    }

    switch ($action) {
        case 'load_reklamace':
            $result = loadReklamace($data);
            break;

        case 'save_protokol':
            $result = saveProtokolData($data);
            break;

        case 'send_email':
            $result = sendEmailToCustomer($data);
            break;

        case 'save_pdf_only':
            $result = savePdfOnly($data);
            break;

        case 'save_pdf_document':
            $result = savePdfDocument($data);
            break;

        case 'ping':
            $result = ['status' => 'success', 'message' => 'pong', 'timestamp' => time()];
            break;

        case 'save_kalkulace':
            $result = saveKalkulaceData($data);
            break;

        default:
            throw new Exception('Neplatná akce: ' . $action);
    }

    echo json_encode($result);

} catch (Exception $e) {
    // DETAILNÍ DEBUGGING - logovat podrobnosti chyby
    $errorDetails = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'action' => $action ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    error_log('=== PROTOKOL API ERROR ===');
    error_log('Timestamp: ' . $errorDetails['timestamp']);
    error_log('Action: ' . $errorDetails['action']);
    error_log('Method: ' . $errorDetails['method']);
    error_log('IP: ' . $errorDetails['ip']);
    error_log('Message: ' . $errorDetails['message']);
    error_log('File: ' . $errorDetails['file'] . ':' . $errorDetails['line']);
    error_log('Trace: ' . $e->getTraceAsString());
    error_log('=========================');

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'action' => $errorDetails['action'],
            'timestamp' => $errorDetails['timestamp']
        ]
    ]);
}

/**
 * Uložení kompletního PDF reportu (protokol + fotky) do databáze
 * Používá se při EXPORT PDF, aby se uložil stejný PDF jako při odeslání emailem
 */
function savePdfOnly($data) {
    $reklamaceId = sanitizeReklamaceId($data['reklamace_id'] ?? null, 'reklamace_id');
    $completePdf = $data['complete_pdf'] ?? null;

    if (!$completePdf) {
        throw new Exception('Chybí PDF dokument');
    }

    // BEZPEČNOST: Kontrola velikosti base64 přílohy (max 30MB = ~22MB PDF)
    $maxBase64Size = 30 * 1024 * 1024; // 30MB
    $pdfSize = strlen($completePdf);

    if ($pdfSize > $maxBase64Size) {
        throw new Exception('Příloha PDF je příliš velká. Maximální velikost je 22 MB.');
    }

    $pdo = getDbConnection();

    // Načtení reklamace
    $stmt = $pdo->prepare("
        SELECT * FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id OR cislo = :cislo
        LIMIT 1
    ");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    // Dekódování PDF
    $pdfData = base64_decode($completePdf);

    // Vytvoření uploads/protokoly adresáře
    $uploadsDir = __DIR__ . '/../uploads/protokoly';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Název souboru pro kompletní report
    $storageKey = reklamaceStorageKey($reklamaceId);
    $filename = $storageKey . '_report.pdf';
    $filePath = $uploadsDir . '/' . $filename;

    // Uložit soubor
    if (file_put_contents($filePath, $pdfData) !== false) {
        // Relativní cesta pro databázi
        $relativePathForDb = "uploads/protokoly/{$filename}";
        $fileSize = filesize($filePath);

        try {
            // Smazat staré záznamy (protokol_pdf a photos_pdf) pokud existují
            $pdo->prepare("
                DELETE FROM wgs_documents
                WHERE claim_id = :claim_id AND document_type IN ('protokol_pdf', 'photos_pdf')
            ")->execute([':claim_id' => $reklamace['id']]);

            // Vložení nového kompletního reportu
            $stmt = $pdo->prepare("
                INSERT INTO wgs_documents (
                    claim_id, document_name, document_path, document_type,
                    file_size, uploaded_by, uploaded_at
                ) VALUES (
                    :claim_id, :document_name, :document_path, :document_type,
                    :file_size, :uploaded_by, NOW()
                )
            ");

            global $uploadedBy; // Načteno v hlavním scope (řádek 31)

            $stmt->execute([
                ':claim_id' => $reklamace['id'],
                ':document_name' => $filename,
                ':document_path' => $relativePathForDb,
                ':document_type' => 'complete_report',
                ':file_size' => $fileSize,
                ':uploaded_by' => $uploadedBy
            ]);

            error_log("PDF report uložen přes EXPORT: {$filename} ({$fileSize} bytes)");

            return [
                'status' => 'success',
                'message' => 'PDF úspěšně uložen do databáze',
                'file_path' => $relativePathForDb,
                'file_size' => $fileSize
            ];

        } catch (PDOException $e) {
            error_log('Chyba při ukládání PDF do databáze: ' . $e->getMessage());
            throw new Exception('Chyba při ukládání PDF do databáze');
        }
    } else {
        throw new Exception('Nepodařilo se uložit PDF soubor');
    }
}

/**
 * Uložení PDF dokumentu
 */
function savePdfDocument($data) {
    $reklamaceId = sanitizeReklamaceId($data['reklamace_id'] ?? null, 'reklamace_id');
    $pdfBase64 = $data['pdf_base64'] ?? null;

    if (!$pdfBase64) {
        throw new Exception('Chybí PDF data');
    }

    // BEZPEČNOST: Kontrola velikosti base64 dat (max 15MB = ~11MB PDF)
    $base64Size = strlen($pdfBase64);
    $maxBase64Size = 15 * 1024 * 1024; // 15MB

    if ($base64Size > $maxBase64Size) {
        throw new Exception('PDF je příliš velké. Maximální velikost je 11 MB.');
    }

    // Databázové připojení
    $pdo = getDbConnection();

    // BEZPEČNOST: Ověření existence reklamace PŘED zápisem souboru
    $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    $claimId = $reklamace['id'];

    // Dekódování base64
    $pdfData = base64_decode($pdfBase64);
    if ($pdfData === false) {
        throw new Exception('Nepodařilo se dekódovat PDF');
    }

    // Vytvoření uploads/protokoly adresáře
    $uploadsDir = __DIR__ . '/../uploads/protokoly';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Název souboru (bezpečný klíč bez lomítek)
    $filename = reklamaceStorageKey($reklamaceId) . '.pdf';
    $filePath = $uploadsDir . '/' . $filename;

    // CRITICAL FIX: FILE-FIRST APPROACH
    // Krok 1: Uložení souboru na disk
    if (file_put_contents($filePath, $pdfData) === false) {
        throw new Exception('Nepodařilo se uložit PDF soubor');
    }

    // Relativní cesta pro databázi
    $relativePathForDb = "uploads/protokoly/{$filename}";

    // Velikost souboru
    $fileSize = filesize($filePath);

    try {
        // Krok 2: Kontrola zda už PDF existuje a uložení do databáze
        $stmt = $pdo->prepare("
            SELECT id FROM wgs_documents
            WHERE claim_id = :claim_id AND document_type = 'protokol_pdf'
            LIMIT 1
        ");
        $stmt->execute([':claim_id' => $claimId]);
        $existingDoc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingDoc) {
            // Update existujícího záznamu
            $stmt = $pdo->prepare("
                UPDATE wgs_documents
                SET document_path = :document_path,
                    file_size = :file_size,
                    uploaded_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':document_path' => $relativePathForDb,
                ':file_size' => $fileSize,
                ':id' => $existingDoc['id']
            ]);

            $documentId = $existingDoc['id'];
        } else {
            // Vložení nového záznamu do databáze
            $stmt = $pdo->prepare("
                INSERT INTO wgs_documents (
                    claim_id, document_name, document_path, document_type,
                    file_size, uploaded_by, uploaded_at
                ) VALUES (
                    :claim_id, :document_name, :document_path, :document_type,
                    :file_size, :uploaded_by, NOW()
                )
            ");

            global $uploadedBy; // Načteno v hlavním scope (řádek 31)

            $stmt->execute([
                ':claim_id' => $claimId,
                ':document_name' => $filename, // Používá už reklamaceStorageKey()
                ':document_path' => $relativePathForDb,
                ':document_type' => 'protokol_pdf',
                ':file_size' => $fileSize,
                ':uploaded_by' => $uploadedBy
            ]);

            $documentId = $pdo->lastInsertId();
        }

    } catch (PDOException $e) {
        // CRITICAL FIX: ROLLBACK - Smazat soubor pokud DB operace selhala
        if (file_exists($filePath)) {
    if (!unlink($filePath)) {
        error_log('Failed to delete file: ' . $filePath);
    }
}
        throw new Exception('Chyba při ukládání PDF do databáze: ' . $e->getMessage());
    }

    return [
        'success' => true,
        'message' => 'PDF úspěšně uloženo',
        'path' => $relativePathForDb,
        'document_id' => $documentId,
        'file_size' => $fileSize
    ];
}

/**
 * Načtení dat reklamace
 */
function loadReklamace($data) {
    $reklamaceId = sanitizeReklamaceId($data['id'] ?? null, 'ID reklamace');

    $pdo = getDbConnection();

    // Načtení reklamace
    $stmt = $pdo->prepare("
        SELECT * FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id OR cislo = :cislo OR id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId,
        ':id' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    return [
        'status' => 'success',
        'reklamace' => $reklamace
    ];
}

/**
 * Uložení dat protokolu
 */
function saveProtokolData($data) {
    $reklamaceId = sanitizeReklamaceId($data['reklamace_id'] ?? null, 'reklamace_id');
    $problemDescription = $data['problem_description'] ?? '';
    $repairProposal = $data['repair_proposal'] ?? '';
    $solved = $data['solved'] ?? '';
    $technik = $data['technician'] ?? null;
    $dealer = $data['dealer'] ?? 'NE'; // Nutné vyjádření prodejce

    // Cenové údaje
    $pocetDilu = isset($data['pocet_dilu']) ? (int)$data['pocet_dilu'] : null;
    $cenaPrace = isset($data['cena_prace']) ? (float)$data['cena_prace'] : null;
    $cenaMaterial = isset($data['cena_material']) ? (float)$data['cena_material'] : null;
    $cenaDruhyTechnik = isset($data['cena_druhy_technik']) ? (float)$data['cena_druhy_technik'] : null;
    $cenaDoprava = isset($data['cena_doprava']) ? (float)$data['cena_doprava'] : null;
    $cenaCelkem = isset($data['cena_celkem']) ? (float)$data['cena_celkem'] : null;

    $pdo = getDbConnection();

    // Najít reklamaci
    $stmt = $pdo->prepare("
        SELECT id FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id OR cislo = :cislo
        LIMIT 1
    ");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    // Kontrola zda sloupec ceka_na_prodejce existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'ceka_na_prodejce'");
    $maSloupecDealer = $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;

    // Aktualizovat protokol data (včetně technika a cenových údajů)
    $updateFields = [
        'popis_problemu = :problem_description',
        'popis_opravy = :repair_proposal',
        'vyreseno = :solved',
        'datum_protokolu = NOW()',
        'updated_at = NOW()'
    ];
    $params = [
        ':problem_description' => $problemDescription,
        ':repair_proposal' => $repairProposal,
        ':solved' => $solved,
        ':id' => $reklamace['id']
    ];

    // Přidat ceka_na_prodejce pokud sloupec existuje
    if ($maSloupecDealer) {
        $updateFields[] = 'ceka_na_prodejce = :ceka_na_prodejce';
        $params[':ceka_na_prodejce'] = ($dealer === 'ANO') ? 1 : 0;
    }

    // Přidat technika pokud je zadán
    if ($technik !== null) {
        $updateFields[] = 'technik = :technik';
        $params[':technik'] = $technik;
    }

    // Přidat cenové údaje pokud jsou zadány
    if ($pocetDilu !== null) {
        $updateFields[] = 'pocet_dilu = :pocet_dilu';
        $params[':pocet_dilu'] = $pocetDilu;
    }
    if ($cenaPrace !== null) {
        $updateFields[] = 'cena_prace = :cena_prace';
        $params[':cena_prace'] = $cenaPrace;
    }
    if ($cenaMaterial !== null) {
        $updateFields[] = 'cena_material = :cena_material';
        $params[':cena_material'] = $cenaMaterial;
    }
    if ($cenaDruhyTechnik !== null) {
        $updateFields[] = 'cena_druhy_technik = :cena_druhy_technik';
        $params[':cena_druhy_technik'] = $cenaDruhyTechnik;
    }
    if ($cenaDoprava !== null) {
        $updateFields[] = 'cena_doprava = :cena_doprava';
        $params[':cena_doprava'] = $cenaDoprava;
    }
    if ($cenaCelkem !== null) {
        $updateFields[] = 'cena_celkem = :cena_celkem';
        $params[':cena_celkem'] = $cenaCelkem;
    }

    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET " . implode(', ', $updateFields) . "
        WHERE id = :id
    ");

    $stmt->execute($params);

    // Odeslat notifikaci pokud dealer = ANO (nutné vyjádření prodejce)
    $notifikaceOdeslana = false;
    if ($dealer === 'ANO' && $maSloupecDealer) {
        try {
            // Načíst data reklamace pro šablonu
            // BUGFIX: Sloupec r.prodejce neexistuje - použít pouze u.name
            $stmtRekl = $pdo->prepare("
                SELECT r.jmeno, r.email, r.telefon, COALESCE(u.name, 'Neznámý') as prodejce, r.reklamace_id
                FROM wgs_reklamace r
                LEFT JOIN wgs_users u ON r.created_by = u.user_id
                WHERE r.id = :id
            ");
            $stmtRekl->execute([':id' => $reklamace['id']]);
            $reklData = $stmtRekl->fetch(PDO::FETCH_ASSOC);

            if ($reklData && !empty($reklData['email'])) {
                // Načíst emailovou šablonu
                $stmtSablona = $pdo->prepare("
                    SELECT * FROM wgs_notifications
                    WHERE id = 'waiting_dealer_response' AND active = 1
                ");
                $stmtSablona->execute();
                $sablona = $stmtSablona->fetch(PDO::FETCH_ASSOC);

                if ($sablona) {
                    // Nahradit proměnné v šabloně
                    $predmet = str_replace(
                        ['{{order_id}}'],
                        [$reklData['reklamace_id']],
                        $sablona['subject']
                    );
                    $telo = str_replace(
                        ['{{customer_name}}', '{{order_id}}', '{{dealer_name}}'],
                        [$reklData['jmeno'], $reklData['reklamace_id'], $reklData['prodejce'] ?? 'Prodejce'],
                        $sablona['template']
                    );

                    // Vložit do email fronty
                    $stmtQueue = $pdo->prepare("
                        INSERT INTO wgs_email_queue
                        (recipient_email, recipient_name, subject, body, status, notification_id, created_at)
                        VALUES (:email, :name, :subject, :body, 'pending', :notification_id, NOW())
                    ");
                    $stmtQueue->execute([
                        ':email' => $reklData['email'],
                        ':name' => $reklData['jmeno'],
                        ':subject' => $predmet,
                        ':body' => $telo,
                        ':notification_id' => 'waiting_dealer_response'
                    ]);
                    $notifikaceOdeslana = true;

                    error_log("Protokol API: Email 'ceka_na_prodejce' zařazen do fronty pro {$reklData['email']}");
                }
            }
        } catch (Exception $e) {
            error_log("Protokol API: Chyba při odesílání notifikace ceka_na_prodejce: " . $e->getMessage());
        }
    }

    return [
        'status' => 'success',
        'message' => 'Protokol uložen' . ($notifikaceOdeslana ? ' (email zařazen do fronty)' : '')
    ];
}

/**
 * Odeslání emailu zákazníkovi pomocí PHPMailer
 */
function sendEmailToCustomer($data) {
    // BEZPEČNÉ LOGOVÁNÍ - pouze metadata (bez PDF payloadu)
    $pdfSize = isset($data['complete_pdf']) ? strlen($data['complete_pdf']) : 0;
    error_log(sprintf(
        'Email send: reklamace_id=%s, pdf_size=%d bytes (%.2f MB)',
        $data['reklamace_id'] ?? 'UNDEFINED',
        $pdfSize,
        $pdfSize / (1024 * 1024)
    ));

    // Načíst PHPMailer - zkusit lib/autoload.php nebo vendor/autoload.php
    $libAutoload = __DIR__ . '/../lib/autoload.php';
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';

    if (file_exists($libAutoload)) {
        require_once $libAutoload;
    } elseif (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        throw new Exception('PHPMailer není nainstalován. Spusťte Email System Installer na /admin/install_email_system.php');
    }

    // Ověřit, že PHPMailer class existuje
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        throw new Exception('PHPMailer class nebyla nalezena. Přeinstalujte PHPMailer přes /admin/install_email_system.php');
    }

    $reklamaceId = sanitizeReklamaceId($data['reklamace_id'] ?? null, 'reklamace_id');
    $completePdf = $data['complete_pdf'] ?? null;

    if (!$completePdf) {
        throw new Exception('Chybí PDF dokument');
    }

    // BEZPEČNOST: Kontrola velikosti base64 přílohy (max 30MB = ~22MB PDF)
    $maxBase64Size = 30 * 1024 * 1024; // 30MB
    $pdfSize = strlen($completePdf);

    if ($pdfSize > $maxBase64Size) {
        throw new Exception('Příloha PDF je příliš velká. Maximální velikost je 22 MB.');
    }

    $pdo = getDbConnection();

    // Načtení reklamace a zákazníka
    $stmt = $pdo->prepare("
        SELECT * FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id OR cislo = :cislo
        LIMIT 1
    ");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    $customerEmail = $reklamace['email'] ?? null;
    if (!$customerEmail || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Neplatná emailová adresa zákazníka');
    }

    // Kontrola jestli existuji videa pro tuto zakazku
    $videoDownloadUrl = null;
    $stmt = $pdo->prepare("SELECT COUNT(*) as pocet FROM wgs_videos WHERE claim_id = :claim_id");
    $stmt->execute([':claim_id' => $reklamace['id']]);
    $videaCount = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

    if ($videaCount > 0) {
        // Vytvorit token pro stahovani videi
        $videoToken = bin2hex(random_bytes(32)); // 64 znaku
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        $stmt = $pdo->prepare("
            INSERT INTO wgs_video_tokens (token, claim_id, expires_at, customer_email)
            VALUES (:token, :claim_id, :expires_at, :email)
        ");
        $stmt->execute([
            ':token' => $videoToken,
            ':claim_id' => $reklamace['id'],
            ':expires_at' => $expiresAt,
            ':email' => $customerEmail
        ]);

        // Sestavit URL pro stahovani
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'www.wgs-service.cz');
        $videoDownloadUrl = $baseUrl . '/api/video_download.php?token=' . $videoToken;

        error_log("Video token vytvoren pro reklamaci {$reklamaceId}: {$videoToken}");
    }

    // Načtení SMTP nastavení z databáze
    $stmt = $pdo->query("
        SELECT * FROM wgs_smtp_settings
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smtpSettings) {
        throw new Exception('SMTP nastavení není nakonfigurováno. Spusťte Email System Installer na /admin/install_email_system.php nebo zkontrolujte konfiguraci na /diagnoza_smtp.php');
    }

    // Příprava emailu
    $storageKey = reklamaceStorageKey($reklamaceId);
    // Pouzit zakaznicke cislo reklamace (cislo), ne interni WGS cislo (reklamace_id)
    $cisloReklamace = $reklamace['cislo'] ?? $reklamace['reklamace_id'] ?? $reklamaceId;
    $customerName = $reklamace['jmeno'] ?? $reklamace['zakaznik'] ?? 'Zákazník';
    // Předmět vždy začíná jménem zákazníka a číslem reklamace
    $subject = "{$customerName} - Reklamace č. {$cisloReklamace} - Servisní protokol WGS";

    // Sestavit sekci videodokumentace pokud existuji videa
    $videoSection = '';
    if ($videoDownloadUrl) {
        $videoSection = "
<br>
<table cellpadding='0' cellspacing='0' border='0' style='margin: 20px 0; background: #f5f5f5; border-radius: 8px; width: 100%;'>
    <tr>
        <td style='padding: 20px;'>
            <p style='margin: 0 0 12px 0; font-weight: bold; color: #333;'>Videodokumentace</p>
            <p style='margin: 0 0 12px 0; color: #666;'>K této zakázce je k dispozici videodokumentace ({$videaCount} " . ($videaCount == 1 ? 'video' : 'videí') . ").</p>
            <a href='{$videoDownloadUrl}' style='display: inline-block; background: #333; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Stáhnout videodokumentaci</a>
            <p style='margin: 12px 0 0 0; font-size: 12px; color: #999;'>Odkaz je platný 7 dní</p>
        </td>
    </tr>
</table>
";
    }

    // HTML email zpráva
    $message = "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <p>Dobrý den {$customerName},</p>

    <p>zasíláme Vám kompletní servisní report k reklamaci č. <strong>{$cisloReklamace}</strong>.</p>

    <p><strong>V příloze naleznete:</strong></p>
    <ul>
        <li>Servisní protokol s fotodokumentací (PDF)</li>
    </ul>
    {$videoSection}
    <p>V případě dotazů nás prosím kontaktujte.</p>

    <p style='margin-top: 30px;'>
        S pozdravem,<br>
        <strong>White Glove Service</strong><br>
        <a href='mailto:reklamace@wgs-service.cz' style='color: #333;'>reklamace@wgs-service.cz</a><br>
        +420 725 965 826
    </p>
</body>
</html>
";

    // Textová verze pro klienty bez HTML
    $messageText = "Dobrý den {$customerName},

zasíláme Vám kompletní servisní report k reklamaci č. {$cisloReklamace}.

V příloze naleznete:
- Servisní protokol s fotodokumentací (PDF)
" . ($videoDownloadUrl ? "
VIDEODOKUMENTACE
K této zakázce je k dispozici videodokumentace ({$videaCount} videí).
Ke stažení: {$videoDownloadUrl}
Odkaz je platný 7 dní.
" : "") . "
V případě dotazů nás prosím kontaktujte.

S pozdravem,
White Glove Service
reklamace@wgs-service.cz
+420 725 965 826
";

    // Vytvoření PHPMailer instance
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // SMTP konfigurace
        $mail->isSMTP();
        $mail->Host = $smtpSettings['smtp_host'];

        // SMTP Authentication - pouze pokud jsou zadány credentials
        // Pro WebSMTP port 25 může být autentizace doménová (bez hesla)
        if (!empty($smtpSettings['smtp_username']) && !empty($smtpSettings['smtp_password'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtpSettings['smtp_username'];
            $mail->Password = $smtpSettings['smtp_password'];
        } else {
            $mail->SMTPAuth = false;
        }

        $mail->Port = $smtpSettings['smtp_port'];
        $mail->CharSet = 'UTF-8';

        // Šifrování
        if ($smtpSettings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpSettings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Odesílatel a příjemce
        $mail->setFrom($smtpSettings['smtp_from_email'], $smtpSettings['smtp_from_name'] ?? 'White Glove Service');
        $mail->addAddress($customerEmail, $customerName);
        $mail->addReplyTo('reklamace@wgs-service.cz', 'White Glove Service');

        // Obsah emailu - HTML s textovou alternativou
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = $messageText; // Textová verze pro klienty bez HTML

        // Přiložit kompletní PDF (protokol + fotodokumentace)
        // Nazev prilohy pouziva zakaznicke cislo reklamace + jmeno zakaznika
        $pdfData = base64_decode($completePdf);
        $safeCustomerName = preg_replace('/[^a-zA-Z0-9_-]/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $customerName));
        $safeCustomerName = preg_replace('/-+/', '-', trim($safeCustomerName, '-'));
        $attachmentName = "Protokol_" . reklamaceStorageKey($cisloReklamace) . "_" . $safeCustomerName . ".pdf";
        $mail->addStringAttachment($pdfData, $attachmentName, 'base64', 'application/pdf');

        // Odeslat
        $mail->send();

        // Uložit kompletní PDF do databáze (protokol + fotodokumentace)
        // Vytvoření uploads/protokoly adresáře
        $uploadsDir = __DIR__ . '/../uploads/protokoly';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        // Název souboru pro kompletní report
        $filename = reklamaceStorageKey($reklamaceId) . '_report.pdf';
        $filePath = $uploadsDir . '/' . $filename;

        // Uložit soubor
        if (file_put_contents($filePath, $pdfData) !== false) {
            // Relativní cesta pro databázi
            $relativePathForDb = "uploads/protokoly/{$filename}";
            $fileSize = filesize($filePath);

            try {
                // Smazat staré záznamy (protokol_pdf a photos_pdf) pokud existují
                $pdo->prepare("
                    DELETE FROM wgs_documents
                    WHERE claim_id = :claim_id AND document_type IN ('protokol_pdf', 'photos_pdf')
                ")->execute([':claim_id' => $reklamace['id']]);

                // Vložení nového kompletního reportu
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_documents (
                        claim_id, document_name, document_path, document_type,
                        file_size, uploaded_by, uploaded_at
                    ) VALUES (
                        :claim_id, :document_name, :document_path, :document_type,
                        :file_size, :uploaded_by, NOW()
                    )
                ");

                global $uploadedBy; // Načteno v hlavním scope (řádek 31)

                $stmt->execute([
                    ':claim_id' => $reklamace['id'],
                    ':document_name' => $filename,
                    ':document_path' => $relativePathForDb,
                    ':document_type' => 'complete_report',
                    ':file_size' => $fileSize,
                    ':uploaded_by' => $uploadedBy
                ]);

                error_log("Kompletní PDF report uložen: {$filename} ({$fileSize} bytes)");

            } catch (PDOException $e) {
                // Logovat chybu ale nepřerušovat odeslání emailu
                error_log('Chyba při ukládání complete_report do databáze: ' . $e->getMessage());
            }
        }

        // Aktualizovat stav reklamace a datum dokončení
        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET stav = 'done', updated_at = NOW(), datum_dokonceni = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $reklamace['id']]);

        return [
            'status' => 'success',
            'message' => 'Email byl úspěšně odeslán'
        ];

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        throw new Exception('Chyba při odesílání emailu: ' . $mail->ErrorInfo);
    }
}

/**
 * Uložení dat z kalkulačky k reklamaci
 *
 * @param array $data Data z POST requestu
 * @return array Výsledek operace
 * @throws Exception Pokud reklamace neexistuje nebo nastane chyba
 */
function saveKalkulaceData($data) {
    $reklamaceId = sanitizeReklamaceId($data['reklamace_id'] ?? null, 'reklamace_id');
    $kalkulaceData = $data['kalkulace_data'] ?? null;

    if (!$kalkulaceData) {
        throw new Exception('Chybí data kalkulace');
    }

    $pdo = getDbConnection();

    // Najít reklamaci
    $stmt = $pdo->prepare("
        SELECT id FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id OR cislo = :cislo
        LIMIT 1
    ");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    // Převést kalkulace data na JSON
    $kalkulaceJson = json_encode($kalkulaceData, JSON_UNESCAPED_UNICODE);

    if ($kalkulaceJson === false) {
        throw new Exception('Chyba při převodu dat kalkulace do JSON');
    }

    // Uložit kalkulaci do databáze
    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET kalkulace_data = :kalkulace_data,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':kalkulace_data' => $kalkulaceJson,
        ':id' => $reklamace['id']
    ]);

    return [
        'status' => 'success',
        'message' => 'Kalkulace byla úspěšně uložena',
        'reklamace_id' => $reklamaceId
    ];
}
