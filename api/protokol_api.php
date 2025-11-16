<?php
/**
 * Protokol API
 * API pro ukládání PDF protokolů a práci s protokoly
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');
// ✅ PERFORMANCE: Cache-Control header (10 minut)
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

    // Rate limiting pouze pro POST operace (upload, save, send)
    if ($isPost && in_array($action, ['save_pdf_document', 'save_protokol', 'send_email'])) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimit = checkRateLimit("upload_pdf_$ip", 10, 3600); // 10 operací za hodinu

        if (!$rateLimit['allowed']) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Příliš mnoho požadavků. Zkuste to za ' . ceil($rateLimit['retry_after'] / 60) . ' minut.',
                'retry_after' => $rateLimit['retry_after']
            ]);
            exit;
        }

        // Zaznamenat pokus o upload
        recordLoginAttempt("upload_pdf_$ip");
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

        case 'save_pdf_document':
            $result = savePdfDocument($data);
            break;

        case 'ping':
            $result = ['status' => 'success', 'message' => 'pong', 'timestamp' => time()];
            break;

        default:
            throw new Exception('Neplatná akce: ' . $action);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Uložení PDF dokumentu
 */
function savePdfDocument($data) {
    $reklamaceId = $data['reklamace_id'] ?? null;
    $pdfBase64 = $data['pdf_base64'] ?? null;

    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    if (!$pdfBase64) {
        throw new Exception('Chybí PDF data');
    }

    // BEZPEČNOST: Kontrola velikosti base64 dat (max 15MB = ~11MB PDF)
    $base64Size = strlen($pdfBase64);
    $maxBase64Size = 15 * 1024 * 1024; // 15MB

    if ($base64Size > $maxBase64Size) {
        throw new Exception('PDF je příliš velké. Maximální velikost je 11 MB.');
    }

    // BEZPEČNOST: Validace reklamace_id - musí být pouze alfanumerické znaky
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
        throw new Exception('Neplatné ID reklamace');
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

    // Název souboru (basename pro extra bezpečnost)
    $filename = basename($reklamaceId) . '.pdf';
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

            $uploadedBy = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'system';

            $stmt->execute([
                ':claim_id' => $claimId,
                ':document_name' => "Protokol_{$reklamaceId}.pdf",
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
    $reklamaceId = $data['id'] ?? null;

    if (!$reklamaceId) {
        throw new Exception('Chybí ID reklamace');
    }

    // BEZPEČNOST: Validace ID
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
        throw new Exception('Neplatné ID reklamace');
    }

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
    $reklamaceId = $data['reklamace_id'] ?? null;
    $problemDescription = $data['problem_description'] ?? '';
    $repairProposal = $data['repair_proposal'] ?? '';
    $solved = $data['solved'] ?? '';

    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    // BEZPEČNOST: Validace ID
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
        throw new Exception('Neplatné ID reklamace');
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

    // Aktualizovat protokol data
    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET
            popis_problemu = :problem_description,
            navrh_reseni = :repair_proposal,
            vyreseno = :solved,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':problem_description' => $problemDescription,
        ':repair_proposal' => $repairProposal,
        ':solved' => $solved,
        ':id' => $reklamace['id']
    ]);

    return [
        'status' => 'success',
        'message' => 'Protokol uložen'
    ];
}

/**
 * Odeslání emailu zákazníkovi
 */
function sendEmailToCustomer($data) {
    $reklamaceId = $data['reklamace_id'] ?? null;
    $protocolPdf = $data['protokol_pdf'] ?? null;
    $photosPdf = $data['photos_pdf'] ?? null;

    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    // BEZPEČNOST: Validace ID
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
        throw new Exception('Neplatné ID reklamace');
    }

    // BEZPEČNOST: Kontrola velikosti base64 příloh (max 15MB každá = ~11MB PDF)
    $maxBase64Size = 15 * 1024 * 1024; // 15MB

    if ($protocolPdf) {
        $protocolSize = strlen($protocolPdf);
        if ($protocolSize > $maxBase64Size) {
            throw new Exception('Příloha protokolu je příliš velká. Maximální velikost je 11 MB.');
        }
    }

    if ($photosPdf) {
        $photosSize = strlen($photosPdf);
        if ($photosSize > $maxBase64Size) {
            throw new Exception('Příloha fotodokumentace je příliš velká. Maximální velikost je 11 MB.');
        }
    }

    // BEZPEČNOST: Celková velikost příloh max 30MB (2x 15MB)
    $totalSize = strlen($protocolPdf ?? '') + strlen($photosPdf ?? '');
    if ($totalSize > 30 * 1024 * 1024) {
        throw new Exception('Celková velikost příloh je příliš velká. Maximum je 22 MB celkem.');
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

    // Příprava emailu
    $subject = "Servisní protokol WGS - Reklamace č. {$reklamaceId}";
    $customerName = $reklamace['jmeno'] ?? $reklamace['zakaznik'] ?? 'Zákazník';

    $message = "
Dobrý den {$customerName},

zasíláme Vám servisní protokol k reklamaci č. {$reklamaceId}.

V příloze naleznete:
- Servisní protokol
" . ($photosPdf ? "- Fotodokumentace" : "") . "

V případě dotazů nás prosím kontaktujte.

S pozdravem,
White Glove Service
reklamace@wgs-service.cz
+420 725 965 826
";

    $headers = "From: White Glove Service <reklamace@wgs-service.cz>\r\n";
    $headers .= "Reply-To: reklamace@wgs-service.cz\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"WGS_BOUNDARY\"\r\n";

    $body = "--WGS_BOUNDARY\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $message . "\r\n\r\n";

    // Přiložit PDF protokolu
    if ($protocolPdf) {
        $body .= "--WGS_BOUNDARY\r\n";
        $body .= "Content-Type: application/pdf; name=\"Protokol_{$reklamaceId}.pdf\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"Protokol_{$reklamaceId}.pdf\"\r\n\r\n";
        $body .= chunk_split($protocolPdf) . "\r\n";
    }

    // Přiložit PDF fotek
    if ($photosPdf) {
        $body .= "--WGS_BOUNDARY\r\n";
        $body .= "Content-Type: application/pdf; name=\"Fotodokumentace_{$reklamaceId}.pdf\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"Fotodokumentace_{$reklamaceId}.pdf\"\r\n\r\n";
        $body .= chunk_split($photosPdf) . "\r\n";
    }

    $body .= "--WGS_BOUNDARY--";

    // Odeslat email
    $emailSent = mail($customerEmail, $subject, $body, $headers);

    if (!$emailSent) {
        throw new Exception('Nepodařilo se odeslat email');
    }

    // Aktualizovat stav reklamace
    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET db_stav = 'HOTOVO', updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':id' => $reklamace['id']]);

    return [
        'status' => 'success',
        'message' => 'Email byl úspěšně odeslán'
    ];
}
