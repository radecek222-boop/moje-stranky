<?php
// PSA data handler: serves and saves calculator data
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

$filePath = __DIR__ . '/../data/psa-employees.json';

function respond(string $status, array $payload = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status], $payload));
    exit;
}

function ensureDataDirectory(string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function requireAuth(): void
{
    $isAdmin = !empty($_SESSION['is_admin']);
    $rawRole = strtolower((string) ($_SESSION['role'] ?? ''));
    $isTechnik = strpos($rawRole, 'technik') !== false || strpos($rawRole, 'technician') !== false;

    if (!$isAdmin && !$isTechnik) {
        respond('error', ['message' => 'Neautorizovaný přístup'], 401);
    }
}

try {
    requireAuth();

    if ($method === 'GET') {
        if (!file_exists($filePath)) {
            respond('error', ['message' => 'Soubor s daty nebyl nalezen'], 404);
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            respond('error', ['message' => 'Nepodařilo se načíst data'], 500);
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            respond('error', ['message' => 'Soubor obsahuje neplatný JSON'], 500);
        }

        respond('success', ['data' => $decoded]);
    } elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            respond('error', ['message' => 'Neplatný JSON payload'], 400);
        }

        if (!validateCSRFToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            respond('error', ['message' => 'Neplatný CSRF token'], 403);
        }

        ensureDataDirectory($filePath);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            respond('error', ['message' => 'Nepodařilo se zapsat data'], 500);
        }

        $result = file_put_contents($filePath, $json, LOCK_EX);
        if ($result === false) {
            respond('error', ['message' => 'Zápis na disk selhal'], 500);
        }

        respond('success', ['message' => 'Data byla uložena']);
    } else {
        respond('error', ['message' => 'Metoda není podporována'], 405);
    }
} catch (Throwable $e) {
    respond('error', ['message' => 'Neočekávaná chyba: ' . $e->getMessage()], 500);
}
