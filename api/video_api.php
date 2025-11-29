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

// DEBUG: Zachytit všechny chyby
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Vlastní error handler pro zachycení všech chyb
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("video_api.php ERROR [$errno]: $errstr in $errfile:$errline");
    return false;
});

// Zachytit fatální chyby
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("video_api.php FATAL: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Interní chyba serveru: ' . $error['message'],
                'debug' => [
                    'file' => basename($error['file']),
                    'line' => $error['line']
                ]
            ]);
        }
    }
});

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// Debug log - request info
error_log("video_api.php: Request received - Method: " . $_SERVER['REQUEST_METHOD'] . ", Action: " . ($_POST['action'] ?? $_GET['action'] ?? 'none'));

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

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    $pdo = getDbConnection();

    switch ($action) {

        // ==================== NAHRÁT VIDEO ====================
        case 'upload_video':
            error_log("video_api.php: [1] Začátek upload_video");

            // CSRF validace (před session_write_close!)
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                error_log("video_api.php: CSRF validace selhala");
                http_response_code(403);
                die(json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']));
            }
            error_log("video_api.php: [2] CSRF OK");

            // PERFORMANCE: Uvolnění session zámku (až po CSRF validaci)
            session_write_close();

            $claimId = $_POST['claim_id'] ?? null;
            $rawUserId = $_SESSION['user_id'] ?? null;
            // uploaded_by je INT - pokud je user_id string (např. 'ADMIN001'), nastavit NULL
            $userId = (is_numeric($rawUserId)) ? (int)$rawUserId : null;
            error_log("video_api.php: [3] claimId=$claimId, rawUserId=$rawUserId, userId=" . ($userId ?? 'NULL'));

            if (!$claimId) {
                http_response_code(400);
                die(json_encode(['status' => 'error', 'message' => 'Chybí ID zakázky']));
            }

            // Kontrola zda zakázka existuje a získání čísla reklamace
            error_log("video_api.php: [4] Kontroluji zakázku v DB");
            $stmt = $pdo->prepare("SELECT id, reklamace_id, cislo FROM wgs_reklamace WHERE id = :id");
            $stmt->execute(['id' => $claimId]);
            $zakaz = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$zakaz) {
                error_log("video_api.php: Zakázka $claimId nenalezena");
                http_response_code(404);
                die(json_encode(['status' => 'error', 'message' => 'Zakázka nenalezena']));
            }
            error_log("video_api.php: [5] Zakázka nalezena: " . json_encode($zakaz));

            $reklamaceCislo = $zakaz['reklamace_id'] ?? $zakaz['cislo'] ?? 'video';

            // Kontrola nahraného souboru
            error_log("video_api.php: [6] Kontroluji FILES: " . json_encode($_FILES));
            if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $errorCode = $_FILES['video']['error'] ?? 'FILE_NOT_SET';
                error_log("video_api.php: Chyba souboru: $errorCode");
                http_response_code(400);
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Chyba při nahrávání videa: ' . $errorCode
                ]));
            }

            $videoFile = $_FILES['video'];
            $fileSize = $videoFile['size'];
            $fileName = basename($videoFile['name']);
            $tmpPath = $videoFile['tmp_name'];
            error_log("video_api.php: [7] Soubor: $fileName, velikost: $fileSize, tmp: $tmpPath");

            // Kontrola velikosti (max 500MB = 524288000 bytů)
            if ($fileSize > 524288000) {
                http_response_code(400);
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Video je příliš velké. Maximální velikost je 500 MB.'
                ]));
            }

            // Kontrola formátu (povolit všechny běžné video formáty)
            $allowedMimes = [
                'video/mp4',
                'video/quicktime',       // MOV
                'video/x-msvideo',       // AVI
                'video/webm',            // WebM
                'video/avi',             // AVI alternativní
                'video/x-matroska',      // MKV
                'video/3gpp',            // 3GP
                'video/3gpp2',           // 3G2
                'video/x-flv',           // FLV
                'video/x-ms-wmv',        // WMV
                'video/mpeg',            // MPEG
                'video/x-m4v',           // M4V
                'video/ogg',             // OGG
                'video/x-ms-asf',        // ASF
                'application/octet-stream' // Fallback pro neznámé video soubory
            ];

            // Povolené přípony souborů
            $allowedExtensions = ['mp4', 'mov', 'avi', 'webm', 'mkv', '3gp', '3g2', 'flv', 'wmv', 'mpg', 'mpeg', 'm4v', 'ogv', 'asf'];
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            error_log("video_api.php: [8] MIME: $mimeType, Extension: $extension");

            // Povolit pokud MIME odpovídá NEBO přípona je povolená video přípona
            $mimeOk = in_array($mimeType, $allowedMimes);
            $extOk = in_array($extension, $allowedExtensions);

            if (!$mimeOk && !$extOk) {
                error_log("video_api.php: Nepodporovaný formát - MIME: $mimeType, Ext: $extension");
                http_response_code(400);
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Nepodporovaný formát videa. Podporované formáty: MP4, MOV, AVI, WebM, MKV, WMV, MPEG, M4V, FLV, 3GP, OGG'
                ]));
            }
            error_log("video_api.php: [9] Formát OK");

            // Vytvořit složku pro videa (hlavní)
            $videosDir = __DIR__ . '/../uploads/videos';
            error_log("video_api.php: [10] Videos dir: $videosDir, exists: " . (is_dir($videosDir) ? 'yes' : 'no'));
            if (!is_dir($videosDir)) {
                if (!mkdir($videosDir, 0755, true)) {
                    error_log("video_api.php: Nelze vytvořit složku $videosDir");
                    http_response_code(500);
                    die(json_encode(['status' => 'error', 'message' => 'Chyba při vytváření složky pro videa']));
                }
            }

            // Vytvořit složku pro zakázku
            $uploadDir = $videosDir . '/' . $claimId;
            error_log("video_api.php: [11] Upload dir: $uploadDir, exists: " . (is_dir($uploadDir) ? 'yes' : 'no'));
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("video_api.php: Nelze vytvořit složku $uploadDir");
                    http_response_code(500);
                    die(json_encode(['status' => 'error', 'message' => 'Chyba při vytváření složky pro zakázku']));
                }
            }
            error_log("video_api.php: [12] Složky připraveny");

            // Generovat název souboru jako u fotek: reklamace_datum_vidX.ext
            // $extension je již definována výše při kontrole formátu
            $datum = date('Ymd'); // 20251128

            // Spočítat kolik už je videí pro tuto zakázku (aby byl unikátní index)
            error_log("video_api.php: [13] Počítám videa pro claim $claimId");
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM wgs_videos WHERE claim_id = :claim_id");
            $stmtCount->execute(['claim_id' => $claimId]);
            $videoCount = $stmtCount->fetchColumn() + 1;
            error_log("video_api.php: [14] Video count: $videoCount");

            // Bezpečný název reklamace (bez lomítek)
            $safeReklamace = str_replace('/', '-', $reklamaceCislo);

            $uniqueName = "{$safeReklamace}_{$datum}_vid{$videoCount}.{$extension}";
            $filePath = $uploadDir . '/' . $uniqueName;
            error_log("video_api.php: [15] Cílová cesta: $filePath");

            // Přesunout soubor
            error_log("video_api.php: [16] Přesouvám soubor z $tmpPath do $filePath");
            if (!move_uploaded_file($tmpPath, $filePath)) {
                error_log("video_api.php: Chyba při move_uploaded_file - tmp exists: " . (file_exists($tmpPath) ? 'yes' : 'no'));
                http_response_code(500);
                die(json_encode(['status' => 'error', 'message' => 'Chyba při ukládání videa']));
            }
            error_log("video_api.php: [17] Soubor přesunut úspěšně");

            // Získat délku videa (pokud je dostupné getID3 nebo ffprobe)
            $duration = null;
            // TODO: Implementovat detekci délky videa pomocí getID3 nebo ffprobe

            // Uložit do databáze
            $relativePath = '/uploads/videos/' . $claimId . '/' . $uniqueName;
            error_log("video_api.php: [18] Ukládám do DB - relativePath: $relativePath");

            $stmt = $pdo->prepare("
                INSERT INTO wgs_videos (claim_id, video_name, video_path, file_size, duration, uploaded_by)
                VALUES (:claim_id, :video_name, :video_path, :file_size, :duration, :uploaded_by)
            ");

            $params = [
                'claim_id' => $claimId,
                'video_name' => $uniqueName,
                'video_path' => $relativePath,
                'file_size' => $fileSize,
                'duration' => $duration,
                'uploaded_by' => $userId
            ];
            error_log("video_api.php: [19] DB params: " . json_encode($params));

            $stmt->execute($params);

            $videoId = $pdo->lastInsertId();
            error_log("video_api.php: [20] Video uloženo s ID: $videoId");

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
            error_log("video_api.php: [21] Upload dokončen úspěšně");
            break;

        // ==================== SEZNAM VIDEÍ ====================
        case 'list_videos':
            $claimId = $_GET['claim_id'] ?? null;

            if (!$claimId) {
                http_response_code(400);
                die(json_encode(['status' => 'error', 'message' => 'Chybí ID zakázky']));
            }

            // Načíst videa + info o zakázce
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
                    u.email as uploader_email,
                    r.jmeno as customer_name,
                    r.reklamace_id,
                    r.cislo
                FROM wgs_videos v
                LEFT JOIN wgs_users u ON v.uploaded_by = u.user_id
                LEFT JOIN wgs_reklamace r ON v.claim_id = r.id
                WHERE v.claim_id = :claim_id
                ORDER BY v.uploaded_at DESC
            ");

            $stmt->execute(['claim_id' => $claimId]);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Získat info o zakázce pro nadpis modalu
            $customerName = '';
            $reklamaceNum = '';
            if (!empty($videos)) {
                $customerName = $videos[0]['customer_name'] ?? '';
                $reklamaceNum = $videos[0]['reklamace_id'] ?? $videos[0]['cislo'] ?? '';
            } else {
                // Pokud nejsou videa, načíst info o zakázce samostatně
                $stmtClaim = $pdo->prepare("SELECT jmeno, reklamace_id, cislo FROM wgs_reklamace WHERE id = :id");
                $stmtClaim->execute(['id' => $claimId]);
                $claim = $stmtClaim->fetch(PDO::FETCH_ASSOC);
                $customerName = $claim['jmeno'] ?? '';
                $reklamaceNum = $claim['reklamace_id'] ?? $claim['cislo'] ?? '';
            }

            echo json_encode([
                'status' => 'success',
                'videos' => $videos,
                'count' => count($videos),
                'customer_name' => $customerName,
                'reklamace_cislo' => $reklamaceNum
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            break;

        // ==================== SMAZAT VIDEO ====================
        case 'delete_video':
            // CSRF validace (před session_write_close!)
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                die(json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']));
            }

            // PERFORMANCE: Uvolnění session zámku (až po CSRF validaci)
            session_write_close();

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
