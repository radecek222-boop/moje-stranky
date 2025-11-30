<?php
// Jednorázový diagnostický skript pro zjištění přesné chyby při uploadu videa.
// Spouštějte z prohlížeče jako přihlášený admin/technik. Skript nic nemění trvale;
// testovací INSERT se vrací zpět a soubory se ukládají jen do temp a následně smažou.

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/api_response.php';
require_once __DIR__ . '/includes/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

$results = [];
$phpErrors = [];
$outputSent = false;

set_error_handler(function ($severity, $message, $file, $line) use (&$phpErrors) {
    $phpErrors[] = [
        'type' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ];

    // Pokračuj v běžném toku (nepřevádíme na výjimku, chceme pokračovat)
    return true;
});

set_exception_handler(function (Throwable $e) use (&$phpErrors, &$outputSent) {
    if ($outputSent) {
        return;
    }

    $phpErrors[] = [
        'type' => 'UNCAUGHT_EXCEPTION',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ];

    http_response_code(500);
    echo json_encode([
        'status' => 'fatal_error',
        'message' => 'Nezachycená výjimka při diagnostice',
        'php_errors' => $phpErrors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $outputSent = true;
});

register_shutdown_function(function () use (&$phpErrors, &$outputSent) {
    $lastError = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if ($outputSent) {
        return;
    }

    if ($lastError && in_array($lastError['type'], $fatalTypes, true)) {
        $phpErrors[] = $lastError;
        http_response_code(500);
        echo json_encode([
            'status' => 'fatal_error',
            'message' => 'Skript skončil fatální chybou – viz php_errors',
            'php_errors' => $phpErrors,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $outputSent = true;
    }
});

function sendOutput(array $payload, int $status, bool &$outputSent): void
{
    if ($outputSent) {
        return;
    }

    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $outputSent = true;
}

function addResult(array &$results, string $title, callable $fn): void {
    try {
        $results[] = [
            'step' => $title,
            'status' => 'ok',
            'details' => $fn()
        ];
    } catch (Throwable $e) {
        $results[] = [
            'step' => $title,
            'status' => 'error',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// 1) Kontrola přihlášení
addResult($results, 'Session a role', function () {
    $userId = $_SESSION['user_id'] ?? null;
    $isAdmin = $_SESSION['is_admin'] ?? false;

    if (!$userId && !$isAdmin) {
        throw new RuntimeException('Nepřihlášený uživatel – přihlaste se a spusťte znovu.');
    }

    return [
        'user_id' => $userId,
        'is_admin' => (bool)$isAdmin,
    ];
});

// 2) Připojení k DB
addResult($results, 'DB připojení', function () {
    $pdo = getDbConnection();
    $pdo->query('SELECT 1');
    return 'Připojeno';
});

// 3) Struktura tabulky wgs_videos
addResult($results, 'Kontrola tabulky wgs_videos', function () {
    $pdo = getDbConnection();
    $stmt = $pdo->query("DESCRIBE wgs_videos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $columns;
});

// 4) Kontrola cesty uploads/videos a oprávnění
addResult($results, 'Složky pro upload', function () {
    $base = __DIR__ . '/uploads';
    $videos = $base . '/videos';
    $info = [];

    $info['uploads_exists'] = is_dir($base);
    $info['videos_exists'] = is_dir($videos);
    $info['uploads_writable'] = is_writable($base);
    $info['videos_writable'] = is_dir($videos) ? is_writable($videos) : false;

    // Pokus o vytvoření a smazání testovacího souboru
    if (!is_dir($videos)) {
        throw new RuntimeException('Složka uploads/videos neexistuje.');
    }

    $testFile = $videos . '/.write_test_' . uniqid() . '.tmp';
    if (@file_put_contents($testFile, 'test') === false) {
        throw new RuntimeException('Do složky uploads/videos nelze zapisovat.');
    }
    unlink($testFile);

    return $info;
});

// 5) PHP limity pro upload (užitečné pro 500 chyby)
addResult($results, 'PHP upload limity', function () {
    return [
        'file_uploads' => ini_get('file_uploads'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
    ];
});

// 6) Testovací INSERT do wgs_videos (vrátíme zpět)
addResult($results, 'Test INSERT (bez souboru)', function () {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // Najít libovolnou existující zakázku
    $claim = $pdo->query('SELECT id, reklamace_id, cislo FROM wgs_reklamace ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$claim) {
        throw new RuntimeException('V tabulce wgs_reklamace není žádná zakázka, nelze otestovat INSERT.');
    }

    $claimId = (int)$claim['id'];
    $fakeName = 'diagnosticky_test_' . date('Ymd_His') . '.mp4';
    $fakePath = '/uploads/videos/' . $claimId . '/' . $fakeName;

    $stmt = $pdo->prepare('INSERT INTO wgs_videos (claim_id, video_name, video_path, file_size, duration, uploaded_by) VALUES (:claim_id, :video_name, :video_path, :file_size, :duration, :uploaded_by)');
    $stmt->execute([
        'claim_id' => $claimId,
        'video_name' => $fakeName,
        'video_path' => $fakePath,
        'file_size' => 1234,
        'duration' => null,
        'uploaded_by' => null,
    ]);

    $newId = $pdo->lastInsertId();
    $pdo->rollBack();

    return [
        'claim_id' => $claimId,
        'insert_id' => $newId,
        'note' => 'INSERT byl vrácen zpět (ROLLBACK)',
    ];
});

// 7) Volitelný test s reálným uploadem přes API (spustí se jen pokud je přiložen soubor video)
if (!empty($_FILES['video'])) {
    addResult($results, 'Volání API /api/video_api.php (curl)', function () {
        $pdo = getDbConnection();
        $claim = $pdo->query('SELECT id FROM wgs_reklamace ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$claim) {
            throw new RuntimeException('Chybí zakázka pro upload.');
        }

        $claimId = (int)$claim['id'];
        $token = generateCSRFToken();

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $apiUrl = $scheme . '://' . $host . '/api/video_api.php';

        $cookieHeader = $_SERVER['HTTP_COOKIE'] ?? (session_name() . '=' . session_id());

        $postFields = [
            'action' => 'upload_video',
            'claim_id' => $claimId,
            'csrf_token' => $token,
            'video' => new CURLFile(
                $_FILES['video']['tmp_name'],
                $_FILES['video']['type'] ?? 'application/octet-stream',
                $_FILES['video']['name'] ?? 'diagnostics_video.mp4'
            ),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Cookie: ' . $cookieHeader,
            ],
        ]);

        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $err);
        }

        $info = curl_getinfo($ch);
        curl_close($ch);

        $headerSize = $info['header_size'] ?? 0;
        $responseHeadersRaw = substr($rawResponse, 0, $headerSize);
        $responseBody = substr($rawResponse, $headerSize);
        $decoded = json_decode($responseBody, true);

        return [
            'url' => $apiUrl,
            'claim_id' => $claimId,
            'http_code' => $info['http_code'] ?? null,
            'response_headers' => $responseHeadersRaw,
            'response_json' => $decoded ?: null,
            'response_body' => $decoded ? null : $responseBody,
        ];
    });
}

// Výstup
sendOutput([
    'status' => 'done',
    'checks' => $results,
    'php_errors' => $phpErrors,
], 200, $outputSent);

?>
