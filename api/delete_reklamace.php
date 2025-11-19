<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/db_metadata.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena je pouze metoda POST.');
    }

    // DŮLEŽITÉ: Načíst JSON PŘED CSRF kontrolou
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $input = $_POST;

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?? '', true);
        if (is_array($decoded)) {
            $input = array_merge($input, $decoded);
            // CSRF token z JSON musíme přesunout do $_POST aby ho requireCSRF() našel
            if (isset($decoded['csrf_token'])) {
                $_POST['csrf_token'] = $decoded['csrf_token'];
            }
        }
    }

    // TEĎ kontrolujeme CSRF (token je už v $_POST)
    requireCSRF();

    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Mazání je povoleno pouze administrátorům.'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $reklamaceId = $input['reklamace_id'] ?? $input['id'] ?? null;
    $reference = $input['reference'] ?? null;

    if ($reklamaceId === null || $reklamaceId === '') {
        throw new Exception('Chybí identifikátor reklamace.');
    }

    $pdo = getDbConnection();
    $pdo->beginTransaction();

    $columns = db_get_table_columns($pdo, 'wgs_reklamace');
    if (empty($columns)) {
        throw new Exception('Nelze načíst strukturu tabulky reklamací.');
    }

    $identifierColumn = 'id';
    $identifierValue = $reklamaceId;

    if (!ctype_digit((string) $reklamaceId) || !in_array('id', $columns, true)) {
        if (in_array('reklamace_id', $columns, true)) {
            $identifierColumn = 'reklamace_id';
        } elseif (in_array('cislo', $columns, true)) {
            $identifierColumn = 'cislo';
        }
    } else {
        $identifierValue = (int) $reklamaceId;
    }

    // BEZPEČNOST: SQL Injection ochrana - whitelist povolených sloupců
    $allowedColumns = ['id', 'reklamace_id', 'cislo'];
    if (!in_array($identifierColumn, $allowedColumns, true)) {
        throw new Exception('Neplatný identifikátor sloupce.');
    }

    $stmt = $pdo->prepare('SELECT * FROM wgs_reklamace WHERE `' . $identifierColumn . '` = :identifier LIMIT 1');
    $stmt->execute([':identifier' => $identifierValue]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception('Reklamace nebyla nalezena.');
    }

    $primaryId = $record['id'] ?? null;
    $workflowId = $record['reklamace_id'] ?? null;
    $customerRef = $record['cislo'] ?? $reference ?? $reklamaceId;

    $filesToDelete = [];
    $deletedCounters = [
        'photos' => 0,
        'documents' => 0,
        'notes' => 0,
        'notifications' => 0
    ];

    if ($workflowId !== null && db_table_exists($pdo, 'wgs_photos')) {
        // Zjistit které sloupce existují
        $photoCols = db_get_table_columns($pdo, 'wgs_photos');
        $photoPathCols = [];
        if (in_array('file_path', $photoCols, true)) {
            $photoPathCols[] = 'file_path';
        }
        if (in_array('photo_path', $photoCols, true)) {
            $photoPathCols[] = 'photo_path';
        }

        if (!empty($photoPathCols)) {
            $selectCols = implode(', ', $photoPathCols);
            $stmt = $pdo->prepare('SELECT ' . $selectCols . ' FROM wgs_photos WHERE reklamace_id = :id');
            $stmt->execute([':id' => $workflowId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $photo) {
                if (!empty($photo['file_path'])) {
                    $filesToDelete[] = $photo['file_path'];
                } elseif (!empty($photo['photo_path'])) {
                    $filesToDelete[] = $photo['photo_path'];
                }
            }
        }

        $stmt = $pdo->prepare('DELETE FROM wgs_photos WHERE reklamace_id = :id');
        $stmt->execute([':id' => $workflowId]);
        $deletedCounters['photos'] = $stmt->rowCount();
    }

    if ($primaryId !== null && db_table_exists($pdo, 'wgs_documents')) {
        // Zjistit které sloupce existují
        $docCols = db_get_table_columns($pdo, 'wgs_documents');
        $docPathCols = [];
        if (in_array('file_path', $docCols, true)) {
            $docPathCols[] = 'file_path';
        }
        if (in_array('document_path', $docCols, true)) {
            $docPathCols[] = 'document_path';
        }

        if (!empty($docPathCols)) {
            $selectCols = implode(', ', $docPathCols);
            $stmt = $pdo->prepare('SELECT ' . $selectCols . ' FROM wgs_documents WHERE claim_id = :id');
            $stmt->execute([':id' => $primaryId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                if (!empty($doc['file_path'])) {
                    $filesToDelete[] = $doc['file_path'];
                } elseif (!empty($doc['document_path'])) {
                    $filesToDelete[] = $doc['document_path'];
                }
            }
        }

        $stmt = $pdo->prepare('DELETE FROM wgs_documents WHERE claim_id = :id');
        $stmt->execute([':id' => $primaryId]);
        $deletedCounters['documents'] = $stmt->rowCount();
    }

    // Smazat poznámky k reklamaci
    if ($primaryId !== null && db_table_exists($pdo, 'wgs_notes')) {
        $stmt = $pdo->prepare('DELETE FROM wgs_notes WHERE claim_id = :id');
        $stmt->execute([':id' => $primaryId]);
        $deletedCounters['notes'] = $stmt->rowCount();
    }

    // POZNÁMKA: Tabulka wgs_notifications je šablonová tabulka (templates),
    // nemá sloupec claim_id a neměla by se mazat při smazání reklamace
    // if ($primaryId !== null && db_table_exists($pdo, 'wgs_notifications')) {
    //     $stmt = $pdo->prepare('DELETE FROM wgs_notifications WHERE claim_id = :id');
    //     $stmt->execute([':id' => $primaryId]);
    //     $deletedCounters['notifications'] = $stmt->rowCount();
    // }

    $deleteStmt = $pdo->prepare('DELETE FROM wgs_reklamace WHERE `' . $identifierColumn . '` = :identifier LIMIT 1');
    $deleteStmt->execute([':identifier' => $identifierValue]);
    if ($deleteStmt->rowCount() === 0) {
        throw new Exception('Reklamaci se nepodařilo odstranit.');
    }

    if (db_table_exists($pdo, 'wgs_audit_log')) {
        $details = json_encode([
            'reklamace_id' => $workflowId,
            'reference' => $customerRef,
            'files_deleted' => array_sum($deletedCounters)
        ], JSON_UNESCAPED_UNICODE);

        $auditStmt = $pdo->prepare('INSERT INTO wgs_audit_log (user_id, action, details, created_at) VALUES (:user_id, :action, :details, NOW())');
        $auditStmt->execute([
            ':user_id' => $_SESSION['user_id'] ?? 'admin',
            ':action' => 'delete_reklamace',
            ':details' => $details
        ]);
    }

    $pdo->commit();

    $deletedFiles = cleanupUploadedFiles($workflowId, $filesToDelete);

    echo json_encode([
        'status' => 'success',
        'message' => 'Reklamace byla úspěšně smazána.',
        'deleted_records' => $deletedCounters,
        'deleted_files' => $deletedFiles
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * CleanupUploadedFiles
 *
 * @param string $workflowId WorkflowId
 * @param array $paths Paths
 */
function cleanupUploadedFiles(?string $workflowId, array $paths): int
{
    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    if ($uploadsRoot === false) {
        return 0;
    }

    $deleted = 0;

    foreach ($paths as $path) {
        if (empty($path)) {
            continue;
        }

        // BEZPEČNOST: Path Traversal ochrana - použít realpath()
        $normalized = str_replace(['\\', '..'], ['/', ''], $path);
        $normalized = ltrim($normalized, '/');
        if (strpos($normalized, 'uploads/') === 0) {
            $normalized = substr($normalized, 8);
        }

        $fullPath = $uploadsRoot . '/' . $normalized;

        // Ověřit že realpath je stále v uploads/ (ochrana proti ....// útokům)
        $realPath = realpath($fullPath);
        if ($realPath && strpos($realPath, $uploadsRoot) === 0 && is_file($realPath)) {
            if (unlink($realPath)) {
                $deleted++;
            } else {
                error_log('Failed to delete file: ' . $realPath);
            }
        }
    }

    if ($workflowId) {
        $dir = $uploadsRoot . '/reklamace_' . basename($workflowId);
        $deleted += removeDirectory($dir);
    }

    return $deleted;
}

/**
 * RemoveDirectory
 *
 * @param string $dir Dir
 */
function removeDirectory(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }

    $removed = 0;
    $items = scandir($dir);
    if ($items === false) {
        return 0;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $removed += removeDirectory($path);
        } elseif (file_exists($path) && unlink($path)) {
            $removed++;
        } else {
            error_log('Failed to delete file: ' . $path);
        }
    }

    if (is_dir($dir) && !rmdir($dir)) {
        error_log('Failed to remove directory: ' . $dir);
    }
    return $removed;
}
