<?php
/**
 * API pro klonování dokončených karet
 * Umožňuje prodejci vytvořit novou kartu z dokončené (zelené) karty
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    sendJsonError('Uživatel není přihlášen', 401);
}

try {
    $pdo = getDbConnection();

    $action = $_POST['action'] ?? '';

    if ($action === 'klonovat') {
        // Získat původní ID
        $puvodniId = $_POST['puvodni_id'] ?? null;

        if (!$puvodniId) {
            sendJsonError('Chybí ID původní reklamace');
        }

        // Načíst původní reklamaci
        $stmt = $pdo->prepare("
            SELECT * FROM wgs_reklamace
            WHERE id = :id OR reklamace_id = :reklamace_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => is_numeric($puvodniId) ? intval($puvodniId) : 0,
            ':reklamace_id' => $puvodniId
        ]);

        $puvodni = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$puvodni) {
            sendJsonError('Původní reklamace nenalezena');
        }

        // Kontrola oprávnění - pouze autor karty nebo admin
        $userId = $_SESSION['user_id'] ?? null;
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

        if (!$isAdmin && ($puvodni['created_by'] !== $userId)) {
            sendJsonError('Nemáte oprávnění klonovat tuto kartu', 403);
        }

        // Začít transakci
        $pdo->beginTransaction();

        try {
            // Vygenerovat nové číslo reklamace
            $rok = date('Y');
            $mesic = date('m');
            $typ = $puvodni['typ'] ?? 'REK';

            // Najít poslední číslo pro tento měsíc
            $stmtMax = $pdo->prepare("
                SELECT MAX(CAST(SUBSTRING_INDEX(reklamace_id, '/', -1) AS UNSIGNED)) as max_cislo
                FROM wgs_reklamace
                WHERE reklamace_id LIKE :pattern
            ");
            $pattern = "{$typ}/{$rok}-{$mesic}/%";
            $stmtMax->execute([':pattern' => $pattern]);
            $maxRow = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $posledniCislo = $maxRow['max_cislo'] ?? 0;
            $noveCislo = str_pad($posledniCislo + 1, 2, '0', STR_PAD_LEFT);

            $noveReklamaceId = "{$typ}/{$rok}-{$mesic}/{$noveCislo}";

            // Vytvořit novou reklamaci (naklonovat všechny údaje kromě ID, stavu, termínu)
            $insertStmt = $pdo->prepare("
                INSERT INTO wgs_reklamace (
                    reklamace_id, jmeno, telefon, email, adresa, model, typ,
                    popis_problemu, stav, created_by, created_by_role, typ_faktury,
                    nazev_produktu, created_at
                ) VALUES (
                    :reklamace_id, :jmeno, :telefon, :email, :adresa, :model, :typ,
                    :popis_problemu, 'wait', :created_by, :created_by_role, :typ_faktury,
                    :nazev_produktu, NOW()
                )
            ");

            $insertStmt->execute([
                ':reklamace_id' => $noveReklamaceId,
                ':jmeno' => $puvodni['jmeno'],
                ':telefon' => $puvodni['telefon'],
                ':email' => $puvodni['email'],
                ':adresa' => $puvodni['adresa'],
                ':model' => $puvodni['model'],
                ':typ' => $puvodni['typ'] ?? 'REK',
                ':popis_problemu' => $puvodni['popis_problemu'],
                ':created_by' => $userId,
                ':created_by_role' => $_SESSION['role'] ?? 'guest',
                ':typ_faktury' => $puvodni['typ_faktury'],
                ':nazev_produktu' => $puvodni['nazev_produktu']
            ]);

            $noveId = $pdo->lastInsertId();

            // Naklonovat fotografie (pokud existují)
            $stmtPhotos = $pdo->prepare("
                SELECT * FROM wgs_photos
                WHERE reklamace_id = :reklamace_id
            ");
            $stmtPhotos->execute([':reklamace_id' => $puvodni['reklamace_id']]);
            $photos = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

            foreach ($photos as $photo) {
                $insertPhotoStmt = $pdo->prepare("
                    INSERT INTO wgs_photos (
                        reklamace_id, photo_path, file_path, file_name,
                        section_name, photo_type, photo_order, uploaded_at
                    ) VALUES (
                        :reklamace_id, :photo_path, :file_path, :file_name,
                        :section_name, :photo_type, :photo_order, NOW()
                    )
                ");

                $insertPhotoStmt->execute([
                    ':reklamace_id' => $noveReklamaceId,
                    ':photo_path' => $photo['photo_path'],
                    ':file_path' => $photo['file_path'],
                    ':file_name' => $photo['file_name'],
                    ':section_name' => $photo['section_name'],
                    ':photo_type' => $photo['photo_type'],
                    ':photo_order' => $photo['photo_order']
                ]);
            }

            $pdo->commit();

            // Zalogovat akci
            error_log("KLONOVÁNÍ: Uživatel {$userId} naklonoval reklamaci {$puvodni['reklamace_id']} -> {$noveReklamaceId}");

            sendJsonSuccess('Nová karta úspěšně vytvořena', [
                'nova_reklamace_id' => $noveId,
                'nova_reklamace_cislo' => $noveReklamaceId,
                'puvodni_reklamace_id' => $puvodni['reklamace_id']
            ]);

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Chyba při klonování reklamace: " . $e->getMessage());
            sendJsonError('Chyba při vytváření nové karty');
        }

    } else {
        sendJsonError('Neplatná akce');
    }

} catch (Exception $e) {
    error_log("KLONOVÁNÍ API - Chyba: " . $e->getMessage());
    sendJsonError('Chyba serveru');
}
?>
