<?php
/**
 * API pro správu interních dokumentů (PDF)
 *
 * Actions:
 * - seznam: Načte seznam dokumentů pro danou reklamaci
 * - nahrat: Nahraje nový interní dokument
 * - smazat: Smaže dokument (pouze admin)
 *
 * Dokumenty jsou interní - nikdy se neposílají zákazníkovi.
 * Viditelné pro všechny přihlášené uživatele s přístupem k reklamaci.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = getDbConnection();

    // Kontrola přihlášení
    $userId = $_SESSION['user_id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? null;
    $userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    $isLoggedIn = $userId || $isAdmin;

    if (!$isLoggedIn) {
        sendJsonError('Neautorizovaný přístup', 401);
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? 'seznam';

    switch ($action) {
        // ========================================
        // SEZNAM - Načte dokumenty pro reklamaci
        // ========================================
        case 'seznam':
            $reklamaceId = $_GET['reklamace_id'] ?? $_GET['id'] ?? null;

            if (!$reklamaceId) {
                sendJsonError('Chybí ID reklamace');
            }

            // Najít interní ID zakázky
            $stmtClaim = $pdo->prepare("
                SELECT r.id, r.created_by, u.email as vlastnik_email
                FROM wgs_reklamace r
                LEFT JOIN wgs_users u ON u.user_id = r.created_by
                WHERE r.reklamace_id = :reklamace_id OR r.id = :id
                LIMIT 1
            ");
            $stmtClaim->execute([
                'reklamace_id' => $reklamaceId,
                'id' => $reklamaceId
            ]);
            $claim = $stmtClaim->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                sendJsonError('Reklamace nenalezena', 404);
            }

            // Kontrola oprávnění
            $maOpravneni = kontrolaOpravneni($claim, $isAdmin, $userRole, $userId, $userEmail);
            if (!$maOpravneni) {
                sendJsonError('Nemáte oprávnění k této reklamaci', 403);
            }

            $claimId = $claim['id'];

            // Načíst všechny dokumenty
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    claim_id,
                    document_type,
                    document_path,
                    document_name,
                    file_size,
                    uploaded_at,
                    uploaded_by
                FROM wgs_documents
                WHERE claim_id = :claim_id
                ORDER BY uploaded_at DESC
            ");
            $stmt->execute(['claim_id' => $claimId]);
            $dokumenty = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formátovat dokumenty
            $formatovane = array_map(function ($dok) {
                // Zjistit typ dokumentu pro zobrazení
                $typPopis = 'Dokument';
                switch ($dok['document_type']) {
                    case 'nabidka_pdf':
                        $typPopis = 'Cenová nabídka';
                        break;
                    case 'internal_pdf':
                        $typPopis = 'Interní dokument';
                        break;
                    case 'protokol_pdf':
                        $typPopis = 'Protokol';
                        break;
                    case 'faktura_pdf':
                        $typPopis = 'Faktura';
                        break;
                }

                return [
                    'id' => $dok['id'],
                    'nazev' => $dok['document_name'],
                    'typ' => $dok['document_type'],
                    'typ_popis' => $typPopis,
                    'cesta' => $dok['document_path'],
                    'velikost' => $dok['file_size'],
                    'nahrano' => $dok['uploaded_at'],
                    'nahral' => $dok['uploaded_by'],
                    // Interní dokumenty se nikdy neposílají zákazníkovi
                    'interni' => in_array($dok['document_type'], ['internal_pdf', 'faktura_pdf'])
                ];
            }, $dokumenty);

            sendJsonSuccess('Dokumenty načteny', [
                'dokumenty' => $formatovane,
                'pocet' => count($formatovane)
            ]);
            break;

        // ========================================
        // NAHRAT - Nahraje nový interní dokument
        // ========================================
        case 'nahrat':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $reklamaceId = $_POST['reklamace_id'] ?? null;
            $nazevDokumentu = trim($_POST['nazev'] ?? '');

            if (!$reklamaceId) {
                sendJsonError('Chybí ID reklamace');
            }

            if (empty($nazevDokumentu)) {
                $nazevDokumentu = 'Interní dokument';
            }

            // Kontrola souboru
            if (!isset($_FILES['soubor']) || $_FILES['soubor']['error'] !== UPLOAD_ERR_OK) {
                $chybaUpload = $_FILES['soubor']['error'] ?? 'unknown';
                sendJsonError('Chyba při nahrávání souboru (kód: ' . $chybaUpload . ')');
            }

            $soubor = $_FILES['soubor'];

            // Kontrola typu - pouze PDF
            $povoleneTypy = ['application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detekovantyTyp = $finfo->file($soubor['tmp_name']);

            if (!in_array($detekovantyTyp, $povoleneTypy)) {
                sendJsonError('Povoleny jsou pouze PDF soubory');
            }

            // Kontrola velikosti (max 10MB)
            $maxVelikost = 10 * 1024 * 1024;
            if ($soubor['size'] > $maxVelikost) {
                sendJsonError('Soubor je příliš velký (max 10MB)');
            }

            // Najít interní ID zakázky
            $stmtClaim = $pdo->prepare("
                SELECT r.id, r.reklamace_id, r.created_by, u.email as vlastnik_email
                FROM wgs_reklamace r
                LEFT JOIN wgs_users u ON u.user_id = r.created_by
                WHERE r.reklamace_id = :reklamace_id OR r.id = :id
                LIMIT 1
            ");
            $stmtClaim->execute([
                'reklamace_id' => $reklamaceId,
                'id' => $reklamaceId
            ]);
            $claim = $stmtClaim->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                sendJsonError('Reklamace nenalezena', 404);
            }

            // Kontrola oprávnění
            $maOpravneni = kontrolaOpravneni($claim, $isAdmin, $userRole, $userId, $userEmail);
            if (!$maOpravneni) {
                sendJsonError('Nemáte oprávnění k této reklamaci', 403);
            }

            $claimId = $claim['id'];
            $reklamaceIdText = $claim['reklamace_id'];

            // Vytvořit adresář pro dokumenty
            $uploadDir = __DIR__ . '/../uploads/dokumenty';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Vygenerovat unikátní název souboru
            $timestamp = date('Ymd_His');
            $safeNazev = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nazevDokumentu);
            $filename = "interni_{$reklamaceIdText}_{$safeNazev}_{$timestamp}.pdf";
            $filePath = $uploadDir . '/' . $filename;
            $relativePathForDb = '/uploads/dokumenty/' . $filename;

            // Přesunout soubor
            if (!move_uploaded_file($soubor['tmp_name'], $filePath)) {
                sendJsonError('Nepodařilo se uložit soubor');
            }

            // Vložit záznam do databáze
            $stmt = $pdo->prepare("
                INSERT INTO wgs_documents (
                    claim_id, document_name, document_path, document_type,
                    file_size, uploaded_by, uploaded_at
                ) VALUES (
                    :claim_id, :document_name, :document_path, :document_type,
                    :file_size, :uploaded_by, NOW()
                )
            ");

            $stmt->execute([
                ':claim_id' => $claimId,
                ':document_name' => $nazevDokumentu,
                ':document_path' => $relativePathForDb,
                ':document_type' => 'internal_pdf',
                ':file_size' => $soubor['size'],
                ':uploaded_by' => $userId
            ]);

            $dokumentId = $pdo->lastInsertId();

            error_log("Interní dokument nahrán: {$filePath} pro reklamaci {$reklamaceIdText}");

            sendJsonSuccess('Dokument byl nahrán', [
                'dokument_id' => $dokumentId,
                'nazev' => $nazevDokumentu,
                'cesta' => $relativePathForDb
            ]);
            break;

        // ========================================
        // SMAZAT - Smaže dokument (pouze admin)
        // ========================================
        case 'smazat':
            if (!$isAdmin) {
                sendJsonError('Pouze admin může mazat dokumenty', 403);
            }

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $dokumentId = intval($_POST['dokument_id'] ?? 0);

            if (!$dokumentId) {
                sendJsonError('Chybí ID dokumentu');
            }

            // Najít dokument
            $stmt = $pdo->prepare("SELECT * FROM wgs_documents WHERE id = :id");
            $stmt->execute(['id' => $dokumentId]);
            $dokument = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dokument) {
                sendJsonError('Dokument nenalezen', 404);
            }

            // Smazat soubor z disku
            $fullPath = __DIR__ . '/..' . $dokument['document_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Smazat záznam z databáze
            $stmt = $pdo->prepare("DELETE FROM wgs_documents WHERE id = :id");
            $stmt->execute(['id' => $dokumentId]);

            error_log("Dokument smazán: ID {$dokumentId}, cesta: {$dokument['document_path']}");

            sendJsonSuccess('Dokument byl smazán');
            break;

        default:
            sendJsonError('Neznámá akce: ' . $action);
    }

} catch (PDOException $e) {
    error_log("Documents API PDO error: " . $e->getMessage());
    sendJsonError('Chyba databáze', 500);
} catch (Exception $e) {
    error_log("Documents API error: " . $e->getMessage());
    sendJsonError('Chyba serveru', 500);
}

/**
 * Kontrola oprávnění k reklamaci
 */
function kontrolaOpravneni($claim, $isAdmin, $userRole, $userId, $userEmail)
{
    // Admin a technik mají přístup vždy
    if ($isAdmin || in_array($userRole, ['admin', 'technik', 'technician'])) {
        return true;
    }

    // Prodejce vidí pouze své zakázky
    if (in_array($userRole, ['prodejce', 'user'])) {
        $vlastnikId = $claim['created_by'] ?? null;
        $vlastnikEmail = $claim['vlastnik_email'] ?? null;

        if (($userId && $vlastnikId && (string) $userId === (string) $vlastnikId) ||
            ($userEmail && $vlastnikEmail && strtolower($userEmail) === strtolower($vlastnikEmail))) {
            return true;
        }
    }

    return false;
}
