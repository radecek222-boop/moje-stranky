<?php
/**
 * Simple Test Environment API
 * Používá REÁLNÉ parametry a struktury z aplikace
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Only admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$step = (int)($_GET['step'] ?? 0);
$role = $_GET['role'] ?? 'guest';
$pdo = getDbConnection();

// Execute test based on step
try {
    switch ($step) {
        case 1: // DB Connection
            $pdo->query("SELECT 1");
            echo json_encode(['success' => true, 'step' => 1]);
            break;

        case 2: // User Registration
            $result = testUserRegistration($pdo, $role);
            echo json_encode($result);
            break;

        case 3: // Create Claim
            $result = testCreateClaim($pdo);
            echo json_encode($result);
            break;

        case 4: // Upload Photo
            $result = testPhotoUpload($pdo);
            echo json_encode($result);
            break;

        case 5: // List View
            $result = testListView($pdo);
            echo json_encode($result);
            break;

        case 6: // Set Date
            $result = testSetDate($pdo);
            echo json_encode($result);
            break;

        case 7: // Protocol + PDF
            $result = testProtocol();
            echo json_encode($result);
            break;

        case 8: // Email
            $result = testEmail();
            echo json_encode($result);
            break;

        case 9: // Complete Detail
            $result = testCompleteDetail($pdo);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown step']);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

function testUserRegistration($pdo, $role) {
    $email = 'test_' . uniqid() . '@test.local';
    $password = password_hash('Test123!', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO wgs_users (full_name, email, password, role, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        'Test User ' . ucfirst($role),
        $email,
        $password,
        $role === 'guest' ? 'prodejce' : $role
    ]);

    $testUserId = $pdo->lastInsertId();

    if ($testUserId > 0) {
        // Store in session for cleanup
        $_SESSION['test_user_id'] = $testUserId;
        return ['success' => true, 'step' => 2, 'testUserId' => $testUserId];
    }

    return ['success' => false, 'error' => 'Failed to create user'];
}

function testCreateClaim($pdo) {
    $reklamaceId = 'TEST-' . uniqid();

    $stmt = $pdo->prepare("
        INSERT INTO wgs_reklamace
        (reklamace_id, cislo, jmeno, telefon, email, adresa, mesto, psc, stav, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ČEKÁ', NOW())
    ");

    $stmt->execute([
        $reklamaceId,
        'TEST-' . rand(1000, 9999),
        'Test Zákazník',
        '+420777888999',
        'test@test.local',
        'Testovací 123',
        'Praha',
        '11000'
    ]);

    $testClaimId = $pdo->lastInsertId();

    if ($testClaimId > 0) {
        $_SESSION['test_claim_id'] = $testClaimId;
        return ['success' => true, 'step' => 3, 'testClaimId' => $testClaimId];
    }

    return ['success' => false, 'error' => 'Failed to create claim'];
}

function testPhotoUpload($pdo) {
    $testClaimId = $_SESSION['test_claim_id'] ?? null;

    if (!$testClaimId) {
        return ['success' => false, 'error' => 'No test claim ID'];
    }

    // Create test photo record (without actual file)
    $stmt = $pdo->prepare("
        INSERT INTO wgs_photos (reklamace_id, file_path, uploaded_at)
        VALUES (?, ?, NOW())
    ");

    $stmt->execute([
        $testClaimId,
        'test_photo_' . uniqid() . '.jpg'
    ]);

    $testPhotoId = $pdo->lastInsertId();

    if ($testPhotoId > 0) {
        $_SESSION['test_photo_id'] = $testPhotoId;
        return ['success' => true, 'step' => 4, 'testPhotoId' => $testPhotoId];
    }

    return ['success' => false, 'error' => 'Failed to create photo record'];
}

function testListView($pdo) {
    $testClaimId = $_SESSION['test_claim_id'] ?? null;

    if (!$testClaimId) {
        return ['success' => false, 'error' => 'No test claim ID'];
    }

    $stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE id = ?");
    $stmt->execute([$testClaimId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($claim && $claim['jmeno'] === 'Test Zákazník') {
        return ['success' => true, 'step' => 5];
    }

    return ['success' => false, 'error' => 'Failed to fetch claim'];
}

function testSetDate($pdo) {
    $testClaimId = $_SESSION['test_claim_id'] ?? null;

    if (!$testClaimId) {
        return ['success' => false, 'error' => 'No test claim ID'];
    }

    $stmt = $pdo->prepare("UPDATE wgs_reklamace SET datum = ?, termin = ? WHERE id = ?");
    $stmt->execute([date('Y-m-d'), date('Y-m-d', strtotime('+7 days')), $testClaimId]);

    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'step' => 6];
    }

    return ['success' => false, 'error' => 'Failed to update date'];
}

function testProtocol() {
    // Check if protocol file exists
    if (file_exists(__DIR__ . '/../protokol.php')) {
        return ['success' => true, 'step' => 7];
    }

    return ['success' => false, 'error' => 'protokol.php not found'];
}

function testEmail() {
    // Check if notification sender exists
    if (file_exists(__DIR__ . '/../app/notification_sender.php')) {
        return ['success' => true, 'step' => 8];
    }

    return ['success' => false, 'error' => 'notification_sender.php not found'];
}

function testCompleteDetail($pdo) {
    $testClaimId = $_SESSION['test_claim_id'] ?? null;
    $testPhotoId = $_SESSION['test_photo_id'] ?? null;

    if (!$testClaimId) {
        return ['success' => false, 'error' => 'No test claim ID'];
    }

    // Check if we can load claim with all relations
    $stmt = $pdo->prepare("
        SELECT r.*, p.id as photo_id
        FROM wgs_reklamace r
        LEFT JOIN wgs_photos p ON r.id = p.reklamace_id
        WHERE r.id = ?
    ");

    $stmt->execute([$testClaimId]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($detail && $detail['photo_id'] == $testPhotoId) {
        return ['success' => true, 'step' => 9];
    }

    return ['success' => false, 'error' => 'Failed to load complete detail'];
}
