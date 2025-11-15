<?php
/**
 * Test Cleanup API
 * Smaže všechna test data z databáze
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Only admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Get test IDs from session or request
    $testUserId = $_SESSION['test_user_id'] ?? null;
    $testClaimId = $_SESSION['test_claim_id'] ?? null;
    $testPhotoId = $_SESSION['test_photo_id'] ?? null;

    // If IDs are provided in request, use them
    $json = file_get_contents('php://input');
    if ($json) {
        $data = json_decode($json, true);
        if (isset($data['testUserId'])) $testUserId = $data['testUserId'];
        if (isset($data['testClaimId'])) $testClaimId = $data['testClaimId'];
        if (isset($data['testPhotoId'])) $testPhotoId = $data['testPhotoId'];
    }

    $deletedCount = 0;

    // Delete test photo
    if ($testPhotoId) {
        $stmt = $pdo->prepare("DELETE FROM wgs_photos WHERE id = ?");
        $stmt->execute([$testPhotoId]);
        $deletedCount += $stmt->rowCount();
    }

    // Delete test claim
    if ($testClaimId) {
        // Delete photos for this claim
        $stmt = $pdo->prepare("DELETE FROM wgs_photos WHERE reklamace_id = ?");
        $stmt->execute([$testClaimId]);

        // Delete claim
        $stmt = $pdo->prepare("DELETE FROM wgs_reklamace WHERE id = ?");
        $stmt->execute([$testClaimId]);
        $deletedCount += $stmt->rowCount();
    }

    // Delete test user
    if ($testUserId) {
        $stmt = $pdo->prepare("DELETE FROM wgs_users WHERE id = ?");
        $stmt->execute([$testUserId]);
        $deletedCount += $stmt->rowCount();
    }

    // Clear session
    unset($_SESSION['test_user_id']);
    unset($_SESSION['test_claim_id']);
    unset($_SESSION['test_photo_id']);

    echo json_encode([
        'success' => true,
        'deleted_count' => $deletedCount,
        'message' => "Smazáno $deletedCount záznamů"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
