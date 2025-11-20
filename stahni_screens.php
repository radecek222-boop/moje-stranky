<?php
/**
 * Skript pro stažení všech souborů z uploads/screens/ do JSON formátu
 * Pro import do GitHub repozitáře
 */

$secretKey = 'wgs2024screens';
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die(json_encode(['error' => 'Přístup odepřen']));
}

header('Content-Type: application/json; charset=utf-8');

$screensDir = __DIR__ . '/uploads/screens';

if (!is_dir($screensDir)) {
    die(json_encode(['error' => 'Složka neexistuje', 'path' => $screensDir]));
}

$files = scandir($screensDir);
$files = array_diff($files, ['.', '..', '.gitignore']);

$result = [
    'success' => true,
    'path' => $screensDir,
    'count' => count($files),
    'files' => []
];

foreach ($files as $file) {
    $filePath = $screensDir . '/' . $file;

    if (is_file($filePath)) {
        $fileData = [
            'name' => $file,
            'size' => filesize($filePath),
            'date' => date('Y-m-d H:i:s', filemtime($filePath)),
            'extension' => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            'url' => 'https://www.wgs-service.cz/uploads/screens/' . rawurlencode($file)
        ];

        // Pro malé soubory (< 500KB) přidat base64
        if ($fileData['size'] < 512000) {
            $fileData['base64'] = base64_encode(file_get_contents($filePath));
            $fileData['mime'] = mime_content_type($filePath);
        }

        $result['files'][] = $fileData;
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
