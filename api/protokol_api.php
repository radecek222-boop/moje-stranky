<?php
/**
 * Protokol API
 * API pro ukládání PDF protokolů a práci s protokoly
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/reklamace_id_validator.php';

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

            $uploadedBy = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'system';

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

    // Aktualizovat protokol data (včetně technika)
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

    // Přidat technika pokud je zadán
    if ($technik !== null) {
        $updateFields[] = 'technik = :technik';
        $params[':technik'] = $technik;
    }

    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET " . implode(', ', $updateFields) . "
        WHERE id = :id
    ");

    $stmt->execute($params);

    return [
        'status' => 'success',
        'message' => 'Protokol uložen'
    ];
}

/**
 * Odeslání emailu zákazníkovi pomocí PHPMailer
 */
function sendEmailToCustomer($data) {
    // Načíst PHPMailer
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception('PHPMailer není nainstalován. Spusťte Email System Installer na /admin/install_email_system.php');
    }
    require_once $autoloadPath;

    // Ověřit, že PHPMailer class existuje
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        throw new Exception('PHPMailer class nebyla nalezena. Přeinstalujte PHPMailer přes /admin/install_email_system.php');
    }

    $reklamaceId = sanitizeReklamaceId($data['reklamace_id'] ?? null, 'reklamace_id');
    $protocolPdf = $data['protokol_pdf'] ?? null;
    $photosPdf = $data['photos_pdf'] ?? null;

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

    // Vytvoření PHPMailer instance
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // SMTP konfigurace
        $mail->isSMTP();
        $mail->Host = $smtpSettings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpSettings['smtp_username'];
        $mail->Password = $smtpSettings['smtp_password'];
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

        // Obsah emailu
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;

        // Přiložit PDF protokolu
        if ($protocolPdf) {
            $protocolData = base64_decode($protocolPdf);
            $mail->addStringAttachment($protocolData, "Protokol_{$storageKey}.pdf", 'base64', 'application/pdf');
        }

        // Přiložit PDF fotek
        if ($photosPdf) {
            $photosData = base64_decode($photosPdf);
            $mail->addStringAttachment($photosData, "Fotodokumentace_{$storageKey}.pdf", 'base64', 'application/pdf');
        }

        // Odeslat
        $mail->send();

        // ✅ Uložit protokol_pdf do databáze (pokud existuje)
        if ($protocolPdf) {
            $protocolData = base64_decode($protocolPdf);

            // Vytvoření uploads/protokoly adresáře
            $uploadsDir = __DIR__ . '/../uploads/protokoly';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }

            // Název souboru pro protokol
            $filename = reklamaceStorageKey($reklamaceId) . '.pdf';
            $filePath = $uploadsDir . '/' . $filename;

            // Uložit soubor
            if (file_put_contents($filePath, $protocolData) !== false) {
                // Relativní cesta pro databázi
                $relativePathForDb = "uploads/protokoly/{$filename}";
                $fileSize = filesize($filePath);

                try {
                    // Kontrola zda už PDF protokolu existuje
                    $stmt = $pdo->prepare("
                        SELECT id FROM wgs_documents
                        WHERE claim_id = :claim_id AND document_type = 'protokol_pdf'
                        LIMIT 1
                    ");
                    $stmt->execute([':claim_id' => $reklamace['id']]);
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
                    } else {
                        // Vložení nového záznamu
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
                            ':claim_id' => $reklamace['id'],
                            ':document_name' => $filename,
                            ':document_path' => $relativePathForDb,
                            ':document_type' => 'protokol_pdf',
                            ':file_size' => $fileSize,
                            ':uploaded_by' => $uploadedBy
                        ]);
                    }
                } catch (PDOException $e) {
                    // Logovat chybu ale nepřerušovat odeslání emailu
                    error_log('Chyba při ukládání protokol_pdf do databáze: ' . $e->getMessage());
                }
            }
        }

        // ✅ Uložit photos_pdf do databáze (pokud existuje)
        if ($photosPdf) {
            $photosData = base64_decode($photosPdf);

            // Vytvoření uploads/protokoly adresáře (stejný jako pro protokol)
            $uploadsDir = __DIR__ . '/../uploads/protokoly';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }

            // Název souboru
            $filename = reklamaceStorageKey($reklamaceId) . '_fotky.pdf';
            $filePath = $uploadsDir . '/' . $filename;

            // Uložit soubor
            if (file_put_contents($filePath, $photosData) !== false) {
                // Relativní cesta pro databázi
                $relativePathForDb = "uploads/protokoly/{$filename}";
                $fileSize = filesize($filePath);

                try {
                    // Kontrola zda už PDF fotek existuje
                    $stmt = $pdo->prepare("
                        SELECT id FROM wgs_documents
                        WHERE claim_id = :claim_id AND document_type = 'photos_pdf'
                        LIMIT 1
                    ");
                    $stmt->execute([':claim_id' => $reklamace['id']]);
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
                    } else {
                        // Vložení nového záznamu
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
                            ':claim_id' => $reklamace['id'],
                            ':document_name' => $filename,
                            ':document_path' => $relativePathForDb,
                            ':document_type' => 'photos_pdf',
                            ':file_size' => $fileSize,
                            ':uploaded_by' => $uploadedBy
                        ]);
                    }
                } catch (PDOException $e) {
                    // Logovat chybu ale nepřerušovat odeslání emailu
                    error_log('Chyba při ukládání photos_pdf do databáze: ' . $e->getMessage());
                }
            }
        }

        // Aktualizovat stav reklamace
        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET stav = 'done', updated_at = NOW()
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
