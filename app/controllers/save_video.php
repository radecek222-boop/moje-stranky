<?php
/**
 * Save Video Controller
 * Ukládání videa z formuláře objednatservis.php
 */

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/safe_file_operations.php';
require_once __DIR__ . '/../../includes/reklamace_id_validator.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // CSRF ochrana
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (is_array($csrfToken)) $csrfToken = '';
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']);
        exit;
    }

    $pdo = getDbConnection();

    // Rate limiting
    $rateLimiter = new RateLimiter($pdo);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateCheck = $rateLimiter->checkLimit(
        $ip,
        'video_upload',
        ['max_attempts' => 10, 'window_minutes' => 60, 'block_minutes' => 60]
    );
    if (!$rateCheck['allowed']) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Příliš mnoho pokusů. Zkuste to za chvíli.']);
        exit;
    }

    // Validace reklamace ID
    $reklamaceId = sanitizeReklamaceId($_POST['reklamace_id'] ?? null, 'reklamace_id');
    $reklamaceStorageKey = reklamaceStorageKey($reklamaceId);

    // Kontrola existence reklamace
    $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
    $stmt->execute([':reklamace_id' => $reklamaceId, ':cislo' => $reklamaceId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    // Kontrola uploadu
    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $chybaKod = $_FILES['video']['error'] ?? -1;
        throw new Exception("Chyba při nahrávání videa (kód: $chybaKod)");
    }

    $soubor = $_FILES['video'];

    // MIME validace
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $soubor['tmp_name']);
    finfo_close($finfo);

    $povoleneTypy = ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo'];
    if (!in_array($mimeType, $povoleneTypy, true)) {
        throw new Exception("Nepodporovaný typ souboru ($mimeType). Povolené: MP4, MOV, WebM.");
    }

    // Velikost max 500 MB
    $maxVelikost = 524288000;
    if ($soubor['size'] > $maxVelikost) {
        throw new Exception('Video je příliš velké. Maximum je 500 MB.');
    }

    // Přípona podle MIME
    $priponaMapa = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'video/x-msvideo' => 'avi'
    ];
    $pripona = $priponaMapa[$mimeType] ?? 'mp4';

    // Vytvoření adresáře
    $uploadsDir = __DIR__ . '/../../uploads';
    if (!is_dir($uploadsDir)) safeMkdir($uploadsDir, 0755, true);
    $reklamaceDir = $uploadsDir . '/reklamace_' . $reklamaceStorageKey;
    if (!is_dir($reklamaceDir)) safeMkdir($reklamaceDir, 0755, true);

    // Název souboru
    $timestamp = time();
    $nahodny = bin2hex(random_bytes(4));
    $nazevSouboru = "video_{$reklamaceStorageKey}_{$timestamp}_{$nahodny}.{$pripona}";
    $cesta = $reklamaceDir . '/' . $nazevSouboru;

    if (!move_uploaded_file($soubor['tmp_name'], $cesta)) {
        throw new Exception('Nepodařilo se uložit video na disk');
    }

    $relativniCesta = "uploads/reklamace_{$reklamaceStorageKey}/{$nazevSouboru}";

    try {
        $stmt = $pdo->prepare("
            INSERT INTO wgs_photos (
                reklamace_id, section_name, photo_path, file_path, file_name,
                photo_type, created_at
            ) VALUES (
                :reklamace_id, :section_name, :photo_path, :file_path, :file_name,
                :photo_type, NOW()
            )
        ");
        $stmt->execute([
            ':reklamace_id' => $reklamaceId,
            ':section_name' => 'problem',
            ':photo_path' => $relativniCesta,
            ':file_path' => $relativniCesta,
            ':file_name' => $nazevSouboru,
            ':photo_type' => 'video'
        ]);
    } catch (PDOException $e) {
        safeFileDelete($cesta);
        throw new Exception('Chyba při ukládání videa do databáze');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Video úspěšně nahráno',
        'path' => $relativniCesta,
        'filename' => $nazevSouboru
    ]);

} catch (Exception $e) {
    error_log('[save_video] Chyba: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
