<?php
/**
 * Create Test Claim API
 * Vytvoří testovací reklamaci pro interaktivní testing
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Only admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDbConnection();

try {
    // Get form data
    $jmeno = trim($_POST['jmeno'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $popis = trim($_POST['popis'] ?? '');

    // Validate
    if (empty($jmeno) || empty($email)) {
        throw new Exception('Jméno a email jsou povinné');
    }

    // Generate unique reklamace_id
    $reklamaceId = 'TEST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    // Get current user info
    $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? 'admin';

    // Insert claim
    $stmt = $pdo->prepare("INSERT INTO wgs_reklamace
        (reklamace_id, jmeno, email, telefon, popis, stav, created_by, created_by_role, created_at)
        VALUES
        (:reklamace_id, :jmeno, :email, :telefon, :popis, 'ČEKÁ', :created_by, :created_by_role, NOW())
    ");

    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':jmeno' => $jmeno,
        ':email' => $email,
        ':telefon' => $telefon,
        ':popis' => $popis,
        ':created_by' => $userId,
        ':created_by_role' => $userRole
    ]);

    $claimId = $pdo->lastInsertId();

    // Handle photo upload if present
    $photoId = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'test_' . time() . '_' . basename($_FILES['photo']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            // Insert into wgs_photos
            $stmt = $pdo->prepare("INSERT INTO wgs_photos
                (reklamace_id, file_path, file_name, uploaded_at)
                VALUES
                (:reklamace_id, :file_path, :file_name, NOW())
            ");

            $stmt->execute([
                ':reklamace_id' => $claimId,
                ':file_path' => 'uploads/' . $fileName,
                ':file_name' => $fileName
            ]);

            $photoId = $pdo->lastInsertId();
        }
    }

    echo json_encode([
        'success' => true,
        'claim_id' => $claimId,
        'reklamace_id' => $reklamaceId,
        'photo_id' => $photoId,
        'message' => 'Testovací reklamace vytvořena'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
