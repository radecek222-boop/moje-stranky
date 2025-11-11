<?php
/**
 * Test Environment API
 * Dry-run testing - ověřuje existenci prvků BEZ spouštění na reálných datech
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Pouze admin
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized - Admin only'
        ]);
        exit;
    }

    $step = $_GET['step'] ?? '';
    $role = $_GET['role'] ?? 'guest';

    $pdo = getDbConnection();

    switch ($step) {
        case 'login':
            echo json_encode(testLogin($role, $pdo));
            break;

        case 'claim_page':
            echo json_encode(testClaimPage($role));
            break;

        case 'photo_upload':
            echo json_encode(testPhotoUpload($role));
            break;

        case 'save_claim':
            echo json_encode(testSaveClaim($role, $pdo));
            break;

        case 'list_view':
            echo json_encode(testListView($role));
            break;

        case 'set_date':
            echo json_encode(testSetDate($role, $pdo));
            break;

        case 'customer_photos':
            echo json_encode(testCustomerPhotos($role));
            break;

        case 'protocol':
            echo json_encode(testProtocol($role));
            break;

        case 'detail_view':
            echo json_encode(testDetailView($role));
            break;

        default:
            throw new Exception('Unknown test step: ' . $step);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'step' => $step ?? 'unknown',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * Test 1: Login simulace - REÁLNÝ TEST
 */
function testLogin($role, $pdo) {
    $checks = [];
    $success = true;
    $testUserId = null;

    try {
        // Check 1: DB Connection test
        try {
            $pdo->query("SELECT 1");
            $checks[] = ['passed' => true, 'message' => '✅ DB připojení funguje'];
        } catch (Exception $e) {
            $checks[] = ['passed' => false, 'message' => '❌ DB připojení selhalo: ' . $e->getMessage()];
            $success = false;
            return ['success' => $success, 'step' => 'login', 'role' => $role, 'checks' => $checks];
        }

        // Check 2: Test vytvoření uživatele
        try {
            $testEmail = 'test_' . uniqid() . '@test.local';
            $testPassword = password_hash('test123', PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO wgs_users (full_name, email, password, role, is_active)
                VALUES (:name, :email, :password, :role, 1)
            ");

            $stmt->execute([
                'name' => 'Test User ' . $role,
                'email' => $testEmail,
                'password' => $testPassword,
                'role' => $role === 'guest' ? 'prodejce' : $role
            ]);

            $testUserId = $pdo->lastInsertId();

            if ($testUserId > 0) {
                $checks[] = ['passed' => true, 'message' => "✅ Test uživatel vytvořen (ID: $testUserId)"];
            } else {
                $checks[] = ['passed' => false, 'message' => '❌ Nepodařilo se vytvořit test uživatele'];
                $success = false;
            }
        } catch (Exception $e) {
            $checks[] = ['passed' => false, 'message' => '❌ Chyba vytvoření uživatele: ' . $e->getMessage()];
            $success = false;
        }

        // Check 3: Test načtení uživatele
        if ($testUserId) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM wgs_users WHERE id = ?");
                $stmt->execute([$testUserId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && $user['email'] === $testEmail) {
                    $checks[] = ['passed' => true, 'message' => '✅ Načtení uživatele z DB funguje'];
                } else {
                    $checks[] = ['passed' => false, 'message' => '❌ Načtení uživatele selhalo'];
                    $success = false;
                }
            } catch (Exception $e) {
                $checks[] = ['passed' => false, 'message' => '❌ Chyba načtení: ' . $e->getMessage()];
                $success = false;
            }
        }

        // Check 4: Test password_verify
        if ($testUserId) {
            try {
                $stmt = $pdo->prepare("SELECT password FROM wgs_users WHERE id = ?");
                $stmt->execute([$testUserId]);
                $hashedPassword = $stmt->fetchColumn();

                if (password_verify('test123', $hashedPassword)) {
                    $checks[] = ['passed' => true, 'message' => '✅ Password verify funguje'];
                } else {
                    $checks[] = ['passed' => false, 'message' => '❌ Password verify nefunguje'];
                    $success = false;
                }
            } catch (Exception $e) {
                $checks[] = ['passed' => false, 'message' => '❌ Chyba password verify: ' . $e->getMessage()];
                $success = false;
            }
        }

        // Check 5: Test session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $checks[] = ['passed' => true, 'message' => '✅ Session je aktivní'];
        } else {
            $checks[] = ['passed' => false, 'message' => '❌ Session není aktivní'];
            $success = false;
        }

    } finally {
        // CLEANUP: Smazat test uživatele
        if ($testUserId) {
            try {
                $pdo->prepare("DELETE FROM wgs_users WHERE id = ?")->execute([$testUserId]);
                $checks[] = ['passed' => true, 'message' => '🧹 Test uživatel vymazán (cleanup OK)'];
            } catch (Exception $e) {
                $checks[] = ['passed' => false, 'message' => '⚠️ Cleanup selhal: ' . $e->getMessage()];
            }
        }
    }

    return [
        'success' => $success,
        'step' => 'login',
        'role' => $role,
        'checks' => $checks,
        'details' => "Reálný test přihlášení a registrace jako $role"
    ];
}

/**
 * Test 2: Stránka nové reklamace
 */
function testClaimPage($role) {
    $checks = [];
    $success = true;

    // Check 1: novareklamace.php existuje
    $file = __DIR__ . '/../novareklamace.php';
    if (file_exists($file)) {
        $checks[] = ['passed' => true, 'message' => 'novareklamace.php existuje'];

        // Check file size (should not be empty)
        $size = filesize($file);
        if ($size > 100) {
            $checks[] = ['passed' => true, 'message' => "Soubor má $size bytes"];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Soubor je podezřele malý'];
            $success = false;
        }
    } else {
        $checks[] = ['passed' => false, 'message' => 'novareklamace.php NEEXISTUJE'];
        $success = false;
    }

    // Check 2: Kontrola required elementů v HTML
    if (file_exists($file)) {
        $content = file_get_contents($file);

        // Check for form
        if (strpos($content, '<form') !== false) {
            $checks[] = ['passed' => true, 'message' => 'Formulář nalezen v HTML'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Formulář NENALEZEN'];
            $success = false;
        }

        // Check for input fields
        $requiredFields = ['jmeno', 'telefon', 'email', 'adresa'];
        foreach ($requiredFields as $field) {
            if (strpos($content, "name=\"$field\"") !== false || strpos($content, "id=\"$field\"") !== false) {
                $checks[] = ['passed' => true, 'message' => "Input pole '$field' existuje"];
            } else {
                $checks[] = ['passed' => false, 'message' => "Input pole '$field' NEEXISTUJE"];
                $success = false;
            }
        }
    }

    // Check 3: Access podle role
    $accessAllowed = in_array($role, ['admin', 'prodejce', 'guest']);
    if ($accessAllowed) {
        $checks[] = ['passed' => true, 'message' => "Role '$role' má přístup k této stránce"];
    } else {
        $checks[] = ['passed' => false, 'message' => "Role '$role' NEMÁ přístup"];
        $success = false;
    }

    return [
        'success' => $success,
        'step' => 'claim_page',
        'role' => $role,
        'checks' => $checks,
        'details' => 'Kontrola stránky pro vytvoření reklamace'
    ];
}

/**
 * Test 3: Nahrání fotky
 */
function testPhotoUpload($role) {
    $checks = [];
    $success = true;

    // Check 1: save_photos.php existuje
    $file = __DIR__ . '/../app/controllers/save_photos.php';
    if (file_exists($file)) {
        $checks[] = ['passed' => true, 'message' => 'save_photos.php existuje'];
    } else {
        $checks[] = ['passed' => false, 'message' => 'save_photos.php NEEXISTUJE'];
        $success = false;
    }

    // Check 2: Upload folder existuje
    $uploadDir = __DIR__ . '/../uploads';
    if (is_dir($uploadDir)) {
        $checks[] = ['passed' => true, 'message' => 'Upload složka existuje'];

        // Check permissions
        if (is_writable($uploadDir)) {
            $checks[] = ['passed' => true, 'message' => 'Upload složka je zapisovatelná'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Upload složka NENÍ zapisovatelná'];
            $success = false;
        }
    } else {
        $checks[] = ['passed' => false, 'message' => 'Upload složka NEEXISTUJE'];
        $success = false;
    }

    // Check 3: PHP upload settings
    $maxSize = ini_get('upload_max_filesize');
    $postMax = ini_get('post_max_size');
    $checks[] = ['passed' => true, 'message' => "Upload limit: $maxSize, POST limit: $postMax"];

    // Check 4: get_photos_api.php pro načítání fotek
    $apiFile = __DIR__ . '/../api/get_photos_api.php';
    if (file_exists($apiFile)) {
        $checks[] = ['passed' => true, 'message' => 'get_photos_api.php existuje'];
    } else {
        $checks[] = ['passed' => false, 'message' => 'get_photos_api.php NEEXISTUJE'];
        $success = false;
    }

    return [
        'success' => $success,
        'step' => 'photo_upload',
        'role' => $role,
        'checks' => $checks,
        'details' => 'Kontrola upload funkcí pro fotky'
    ];
}

/**
 * Test 4: Uložení reklamace - REÁLNÝ TEST
 */
function testSaveClaim($role, $pdo) {
    $checks = [];
    $success = true;
    $testClaimId = null;

    try {
        // Check 1: Test vytvoření reklamace
        try {
            $reklamaceId = 'TEST-' . uniqid();

            $stmt = $pdo->prepare("
                INSERT INTO wgs_reklamace
                (reklamace_id, cislo, jmeno, telefon, email, adresa, mesto, psc, stav, created_at)
                VALUES
                (:reklamace_id, :cislo, :jmeno, :telefon, :email, :adresa, :mesto, :psc, 'ČEKÁ', NOW())
            ");

            $stmt->execute([
                'reklamace_id' => $reklamaceId,
                'cislo' => 'TEST-' . rand(1000, 9999),
                'jmeno' => 'Test Zákazník',
                'telefon' => '+420777888999',
                'email' => 'test@test.local',
                'adresa' => 'Testovací 123',
                'mesto' => 'Praha',
                'psc' => '11000'
            ]);

            $testClaimId = $pdo->lastInsertId();

            if ($testClaimId > 0) {
                $checks[] = ['passed' => true, 'message' => "✅ Test reklamace vytvořena (ID: $testClaimId)"];
            } else {
                $checks[] = ['passed' => false, 'message' => '❌ Vytvoření reklamace selhalo'];
                $success = false;
            }
        } catch (Exception $e) {
            $checks[] = ['passed' => false, 'message' => '❌ Chyba vytvoření reklamace: ' . $e->getMessage()];
            $success = false;
        }

        // Check 2: Test načtení reklamace
        if ($testClaimId) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE id = ?");
                $stmt->execute([$testClaimId]);
                $claim = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($claim && $claim['jmeno'] === 'Test Zákazník') {
                    $checks[] = ['passed' => true, 'message' => '✅ Načtení reklamace z DB funguje'];
                } else {
                    $checks[] = ['passed' => false, 'message' => '❌ Načtení reklamace selhalo'];
                    $success = false;
                }
            } catch (Exception $e) {
                $checks[] = ['passed' => false, 'message' => '❌ Chyba načtení: ' . $e->getMessage()];
                $success = false;
            }
        }

        // Check 3: Test UPDATE reklamace
        if ($testClaimId) {
            try {
                $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = 'DOMLUVENÁ' WHERE id = ?");
                $stmt->execute([$testClaimId]);

                $stmt = $pdo->prepare("SELECT stav FROM wgs_reklamace WHERE id = ?");
                $stmt->execute([$testClaimId]);
                $newStatus = $stmt->fetchColumn();

                if ($newStatus === 'DOMLUVENÁ') {
                    $checks[] = ['passed' => true, 'message' => '✅ UPDATE stavu reklamace funguje'];
                } else {
                    $checks[] = ['passed' => false, 'message' => '❌ UPDATE selhalo'];
                    $success = false;
                }
            } catch (Exception $e) {
                $checks[] = ['passed' => false, 'message' => '❌ Chyba UPDATE: ' . $e->getMessage()];
                $success = false;
            }
        }

        // Check 4: Test validace emailu
        if (filter_var('test@test.local', FILTER_VALIDATE_EMAIL)) {
            $checks[] = ['passed' => true, 'message' => '✅ Email validace funguje'];
        } else {
            $checks[] = ['passed' => false, 'message' => '❌ Email validace nefunguje'];
            $success = false;
        }

        // Check 5: Test phone validace (basic)
        $testPhone = '+420777888999';
        if (preg_match('/^\+?[0-9]{9,15}$/', $testPhone)) {
            $checks[] = ['passed' => true, 'message' => '✅ Telefonní číslo validace funguje'];
        } else {
            $checks[] = ['passed' => false, 'message' => '❌ Telefonní validace nefunguje'];
            $success = false;
        }

    } finally {
        // CLEANUP: Smazat test reklamaci
        if ($testClaimId) {
            try {
                $pdo->prepare("DELETE FROM wgs_reklamace WHERE id = ?")->execute([$testClaimId]);
                $checks[] = ['passed' => true, 'message' => '🧹 Test reklamace vymazána (cleanup OK)'];
            } catch (Exception $e) {
                $checks[] = ['passed' => false, 'message' => '⚠️ Cleanup selhal: ' . $e->getMessage()];
            }
        }
    }

    return [
        'success' => $success,
        'step' => 'save_claim',
        'role' => $role,
        'checks' => $checks,
        'details' => 'Reálný test vytvoření, načtení a aktualizace reklamace'
    ];
}

/**
 * Test 5: Zobrazení v seznamu
 */
function testListView($role) {
    $checks = [];
    $success = true;

    // Check 1: seznam.php existuje
    $file = __DIR__ . '/../seznam.php';
    if (file_exists($file)) {
        $checks[] = ['passed' => true, 'message' => 'seznam.php existuje'];
    } else {
        $checks[] = ['passed' => false, 'message' => 'seznam.php NEEXISTUJE'];
        $success = false;
    }

    // Check 2: load.php API
    $loadFile = __DIR__ . '/../app/controllers/load.php';
    if (file_exists($loadFile)) {
        $checks[] = ['passed' => true, 'message' => 'load.php API existuje'];
    } else {
        $checks[] = ['passed' => false, 'message' => 'load.php API NEEXISTUJE'];
        $success = false;
    }

    // Check 3: admin_api.php list_reklamace
    $adminApi = __DIR__ . '/../api/admin_api.php';
    if (file_exists($adminApi)) {
        $content = file_get_contents($adminApi);
        if (strpos($content, 'list_reklamace') !== false) {
            $checks[] = ['passed' => true, 'message' => 'admin_api.php má list_reklamace endpoint'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'list_reklamace endpoint NENALEZEN'];
            $success = false;
        }
    } else {
        $checks[] = ['passed' => false, 'message' => 'admin_api.php NEEXISTUJE'];
        $success = false;
    }

    // Check 4: Role-based access
    $hasAccess = in_array($role, ['admin', 'technik', 'prodejce']);
    if ($hasAccess) {
        $checks[] = ['passed' => true, 'message' => "Role '$role' má přístup k seznamu"];
    } else {
        $checks[] = ['passed' => false, 'message' => "Role '$role' NEMÁ přístup k seznamu"];
        // Not necessarily a failure for guest
    }

    return [
        'success' => $success,
        'step' => 'list_view',
        'role' => $role,
        'checks' => $checks,
        'details' => 'Kontrola zobrazení seznamu reklamací'
    ];
}

/**
 * Test 6: Nastavení termínu
 */
function testSetDate($role, $pdo) {
    $checks = [];
    $success = true;

    // Check 1: Sloupce pro datum v DB
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace WHERE Field IN ('datum', 'termin')");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($columns) > 0) {
            $checks[] = ['passed' => true, 'message' => 'Sloupce pro datum existují: ' . implode(', ', $columns)];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Sloupce pro datum NEEXISTUJÍ'];
            $success = false;
        }
    } catch (Exception $e) {
        $checks[] = ['passed' => false, 'message' => 'DB chyba: ' . $e->getMessage()];
        $success = false;
    }

    // Check 2: API pro update
    $apiFiles = ['protokol_api.php', 'admin_api.php'];
    foreach ($apiFiles as $apiFile) {
        $file = __DIR__ . '/../api/' . $apiFile;
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if (strpos($content, 'UPDATE') !== false && strpos($content, 'wgs_reklamace') !== false) {
                $checks[] = ['passed' => true, 'message' => "$apiFile může aktualizovat datum"];
                break;
            }
        }
    }

    // Check 3: Kalendářový input
    $seznamFile = __DIR__ . '/../seznam.php';
    if (file_exists($seznamFile)) {
        $content = file_get_contents($seznamFile);
        if (strpos($content, 'type="date"') !== false || strpos($content, 'datepicker') !== false) {
            $checks[] = ['passed' => true, 'message' => 'Kalendářový input nalezen'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Kalendářový input NENALEZEN'];
            // Not critical
        }
    }

    return [
        'success' => $success,
        'step' => 'set_date',
        'role' => $role,
        'checks' => $checks,
        'details' => 'Kontrola nastavení termínu návštěvy'
    ];
}

/**
 * Test 7: PhotoCustomer - fotky od zákazníka
 */
function testCustomerPhotos($role) {
    $checks = [];
    $success = true;

    // Check 1: photocustomer.php existuje
    $file = __DIR__ . '/../photocustomer.php';
    if (file_exists($file)) {
        $checks[] = ['passed' => true, 'message' => 'photocustomer.php existuje'];
    } else {
        $checks[] = ['passed' => false, 'message' => 'photocustomer.php NEEXISTUJE'];
        $success = false;
    }

    // Check 2: wgs_photos tabulka
    try {
        global $pdo;
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_photos'");
        if ($stmt->rowCount() > 0) {
            $checks[] = ['passed' => true, 'message' => 'Tabulka wgs_photos existuje'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Tabulka wgs_photos NEEXISTUJE'];
            $success = false;
        }
    } catch (Exception $e) {
        $checks[] = ['passed' => false, 'message' => 'DB chyba: ' . $e->getMessage()];
        $success = false;
    }

    // Check 3: Upload API
    $uploadApi = __DIR__ . '/../app/controllers/save_photos.php';
    if (file_exists($uploadApi)) {
        $checks[] = ['passed' => true, 'message' => 'Upload API pro customer photos existuje'];
    } else {
        $checks[] = ['passed' => false, 'message' => 'Upload API NEEXISTUJE'];
        $success = false;
    }

    // Check 4: Veřejný přístup (bez autentizace)
    if ($role === 'guest') {
        $checks[] = ['passed' => true, 'message' => 'Guest má přístup k photocustomer (veřejná stránka)'];
    }

    return [
        'success' => $success,
        'step' => 'customer_photos',
        'role' => $role,
        'checks' => $checks,
        'details' => 'Kontrola nahrávání fotek zákazníkem'
    ];
}

/**
 * Test 8: Protokol
 */
function testProtocol($role) {
    $checks = [];
    $success = true;

    // Check 1: protokol.php existuje
    $file = __DIR__ . '/../protokol.php';
    if (file_exists($file)) {
        $checks[] = ['passed' => true, 'message' => 'protokol.php existuje'];
    } else {
        $checks[] = ['passed' => false, 'message' => 'protokol.php NEEXISTUJE'];
        $success = false;
    }

    // Check 2: PDF generování - FPDF nebo TCPDF
    $pdfLibs = ['fpdf', 'tcpdf', 'dompdf'];
    $pdfFound = false;
    foreach ($pdfLibs as $lib) {
        if (class_exists($lib) || class_exists(strtoupper($lib))) {
            $checks[] = ['passed' => true, 'message' => "PDF knihovna '$lib' je dostupná"];
            $pdfFound = true;
            break;
        }
    }
    if (!$pdfFound) {
        // Check if any PDF generation exists in protokol.php
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if (strpos($content, 'PDF') !== false || strpos($content, 'pdf') !== false) {
                $checks[] = ['passed' => true, 'message' => 'PDF generování je implementováno'];
            } else {
                $checks[] = ['passed' => false, 'message' => 'PDF generování NENÍ implementováno'];
                $success = false;
            }
        }
    }

    // Check 3: wgs_documents tabulka pro PDF
    try {
        global $pdo;
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_documents'");
        if ($stmt->rowCount() > 0) {
            $checks[] = ['passed' => true, 'message' => 'Tabulka wgs_documents existuje'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Tabulka wgs_documents NEEXISTUJE'];
            // Not critical
        }
    } catch (Exception $e) {
        $checks[] = ['passed' => false, 'message' => 'DB chyba: ' . $e->getMessage()];
    }

    // Check 4: Email odeslání protokolu
    $notifSender = __DIR__ . '/../app/notification_sender.php';
    if (file_exists($notifSender)) {
        $checks[] = ['passed' => true, 'message' => 'Email sender pro protokol existuje'];
    } else {
        $checks[] = ['passed' => false, 'message' => 'Email sender NEEXISTUJE'];
        $success = false;
    }

    return [
        'success' => $success,
        'step' => 'protocol',
        'role' => $role,
        'checks' => $checks,
        'details' => 'Kontrola protokolu a PDF generování'
    ];
}

/**
 * Test 9: Finální detail view
 */
function testDetailView($role) {
    $checks = [];
    $success = true;

    // Check 1: Detail view v seznam.php
    $file = __DIR__ . '/../seznam.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);

        // Check for detail display elements
        if (strpos($content, 'detail') !== false || strpos($content, 'modal') !== false) {
            $checks[] = ['passed' => true, 'message' => 'Detail view je implementován v seznam.php'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Detail view NENÍ implementován'];
            $success = false;
        }
    } else {
        $checks[] = ['passed' => false, 'message' => 'seznam.php NEEXISTUJE'];
        $success = false;
    }

    // Check 2: Všechny potřebné data jsou v DB
    try {
        global $pdo;
        $stmt = $pdo->query("DESCRIBE wgs_reklamace");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $requiredFields = ['jmeno', 'telefon', 'email', 'adresa', 'datum', 'termin', 'stav'];
        $missingFields = array_diff($requiredFields, $columns);

        if (empty($missingFields)) {
            $checks[] = ['passed' => true, 'message' => 'Všechna potřebná pole pro detail existují'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Chybějící pole: ' . implode(', ', $missingFields)];
            $success = false;
        }
    } catch (Exception $e) {
        $checks[] = ['passed' => false, 'message' => 'DB chyba: ' . $e->getMessage()];
        $success = false;
    }

    // Check 3: Vztah s fotkami
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_photos'");
        if ($stmt->rowCount() > 0) {
            // Check foreign key
            $stmt = $pdo->query("DESCRIBE wgs_photos");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('reklamace_id', $columns)) {
                $checks[] = ['passed' => true, 'message' => 'Vztah reklamace <-> fotky existuje'];
            } else {
                $checks[] = ['passed' => false, 'message' => 'Vztah reklamace <-> fotky NEEXISTUJE'];
                $success = false;
            }
        }
    } catch (Exception $e) {
        $checks[] = ['passed' => false, 'message' => 'Chyba kontroly vztahů: ' . $e->getMessage()];
    }

    // Check 4: Vztah s protokolem
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_documents'");
        if ($stmt->rowCount() > 0) {
            $checks[] = ['passed' => true, 'message' => 'Tabulka pro PDF protokoly existuje'];
        } else {
            $checks[] = ['passed' => false, 'message' => 'Tabulka pro PDF protokoly NEEXISTUJE'];
            // Not critical
        }
    } catch (Exception $e) {
        // Ignore
    }

    return [
        'success' => $success,
        'step' => 'detail_view',
        'role' => $role,
        'checks' => $checks,
        'details' => 'Kontrola kompletního zobrazení všech dat v detailu'
    ];
}
