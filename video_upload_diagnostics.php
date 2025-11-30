<?php
// Jednorázový diagnostický skript pro zjištění přesné chyby při uploadu videa.
// Spouštějte z prohlížeče jako přihlášený admin/technik. Skript nic nemění trvale;
// testovací INSERT se vrací zpět a soubory se ukládají jen do temp a následně smažou.

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/api_response.php';
require_once __DIR__ . '/includes/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

$results = [];

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
    addResult($results, 'Volání API /video_api.php?action=upload_video', function () {
        // Vytvoříme požadavek podobný frontendu, ale s testovacím claim_id
        $pdo = getDbConnection();
        $claim = $pdo->query('SELECT id FROM wgs_reklamace ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$claim) {
            throw new RuntimeException('Chybí zakázka pro upload.');
        }

        $claimId = (int)$claim['id'];
        $_POST['action'] = 'upload_video';
        $_POST['claim_id'] = $claimId;

        // Vytvoříme platný CSRF token podle helperu
        $token = generateCSRFToken();
        $_POST['csrf_token'] = $token;

        // Předelegujeme na původní endpoint a zachytíme výstup
        ob_start();
        include __DIR__ . '/api/video_api.php';
        $response = ob_get_clean();

        return [
            'claim_id' => $claimId,
            'api_response' => json_decode($response, true) ?? $response,
        ];
    });
}

// Výstup
http_response_code(200);
echo json_encode([
    'status' => 'done',
    'checks' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>
