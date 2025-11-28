<?php
/**
 * API endpoint pro video archiv zakázek
 *
 * Operace:
 * - upload_video: Nahrát nové video (s kompresí)
 * - list_videos: Seznam videí pro zakázku
 * - delete_video: Smazat video
 * - get_video: Získat video pro přehrání
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Kontrola přihlášení
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

    if (!$isLoggedIn) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]));
    }

    // PERFORMANCE: Uvolnění session zámku
    session_write_close();

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    $pdo = getDbConnection();

    switch ($action) {

        // ==================== NAHRÁT VIDEO ====================
        case 'upload_video':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                die(json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']));
            }

            $claimId = $_POST['claim_id'] ?? null;
            $userId = $_SESSION['user_id'] ?? null;

            if (!$claimId) {
                http_response_code(400);
                die(json_encode(['status' => 'error', 'message' => 'Chybí ID zakázky']));
            }

            // Kontrola zda zakázka existuje
            $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE id = :id");
            $stmt->execute(['id' => $claimId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                die(json_encode(['status' => 'error', 'message' => 'Zakázka nenalezena']));
            }

            // Kontrola nahraného souboru
            if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Chyba při nahrávání videa: ' . ($_FILES['video']['error'] ?? 'Soubor nebyl nahrán')
                ]));
            }

            $videoFile = $_FILES['video'];
            $fileSize = $videoFile['size'];
            $fileName = basename($videoFile['name']);
            $tmpPath = $videoFile['tmp_name'];

            // Kontrola velikosti (max 500MB = 524288000 bytů)
            if ($fileSize > 524288000) {
                http_response_code(400);
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Video je příliš velké. Maximální velikost je 500 MB.'
                ]));
            }

            // Kontrola formátu (povolit běžné video formáty)
            $allowedMimes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/avi'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimes)) {
                http_response_code(400);
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Nepodporovaný formát videa. Podporované formáty: MP4, MOV, AVI, WebM'
                ]));
            }

            // Vytvořit složku pro zakázku
            $uploadDir = __DIR__ . '/../uploads/videos/' . $claimId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generovat unikátní název souboru
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
            $uniqueName = $safeName . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . '/' . $uniqueName;

            // Přesunout soubor
            if (!move_uploaded_file($tmpPath, $filePath)) {
                http_response_code(500);
                die(json_encode(['status' => 'error', 'message' => 'Chyba při ukládání videa']));
            }

            // Získat délku videa (pokud je dostupné getID3 nebo ffprobe)
            $duration = null;
            // TODO: Implementovat detekci délky videa pomocí getID3 nebo ffprobe

            // Uložit do databáze
            $relativePath = '/uploads/videos/' . $claimId . '/' . $uniqueName;

            $stmt = $pdo->prepare("
                INSERT INTO wgs_videos (claim_id, video_name, video_path, file_size, duration, uploaded_by)
                VALUES (:claim_id, :video_name, :video_path, :file_size, :duration, :uploaded_by)
            ");

            $stmt->execute([
                'claim_id' => $claimId,
                'video_name' => $fileName,
                'video_path' => $relativePath,
                'file_size' => $fileSize,
                'duration' => $duration,
                'uploaded_by' => $userId
            ]);

            $videoId = $pdo->lastInsertId();

            echo json_encode([
                'status' => 'success',
                'message' => 'Video bylo úspěšně nahráno',
                'video' => [
                    'id' => $videoId,
                    'name' => $fileName,
                    'path' => $relativePath,
                    'size' => $fileSize,
                    'uploaded_at' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            break;

        // ==================== SEZNAM VIDEÍ ====================
        case 'list_videos':
            $claimId = $_GET['claim_id'] ?? null;

            if (!$claimId) {
                http_response_code(400);
                die(json_encode(['status' => 'error', 'message' => 'Chybí ID zakázky']));
            }

            $stmt = $pdo->prepare("
                SELECT
                    v.id,
                    v.claim_id,
                    v.video_name,
                    v.video_path,
                    v.file_size,
                    v.duration,
                    v.thumbnail_path,
                    v.uploaded_at,
                    v.uploaded_by,
                    u.email as uploader_email
                FROM wgs_videos v
                LEFT JOIN wgs_users u ON v.uploaded_by = u.user_id
                WHERE v.claim_id = :claim_id
                ORDER BY v.uploaded_at DESC
            ");

            $stmt->execute(['claim_id' => $claimId]);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'videos' => $videos,
                'count' => count($videos)
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            break;

        // ==================== SMAZAT VIDEO ====================
        case 'delete_video':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                die(json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']));
            }

            $videoId = $_POST['video_id'] ?? null;

            if (!$videoId) {
                http_response_code(400);
                die(json_encode(['status' => 'error', 'message' => 'Chybí ID videa']));
            }

            // Načíst video z databáze
            $stmt = $pdo->prepare("SELECT video_path FROM wgs_videos WHERE id = :id");
            $stmt->execute(['id' => $videoId]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                http_response_code(404);
                die(json_encode(['status' => 'error', 'message' => 'Video nenalezeno']));
            }

            // Smazat soubor
            $fullPath = __DIR__ . '/..' . $video['video_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Smazat z databáze
            $stmt = $pdo->prepare("DELETE FROM wgs_videos WHERE id = :id");
            $stmt->execute(['id' => $videoId]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Video bylo smazáno'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Neplatná akce'
            ]);
            break;
    }

} catch (PDOException $e) {
    error_log("Database error in video_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba při zpracování požadavku'
    ]);
} catch (Exception $e) {
    error_log("Error in video_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba serveru'
    ]);
}
?>
