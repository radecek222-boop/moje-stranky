<?php
/**
 * Load Controller
 * Načítání reklamací, fotek a dokumentů pro seznam.php
 */

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/db_metadata.php';

header('Content-Type: application/json');

try {
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!isset($_SESSION['user_id']) && !$isAdmin) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'error' => 'Neautorizovaný přístup. Přihlaste se prosím.'
        ]);
        exit;
    }

    $status = $_GET['status'] ?? 'all';

    $pdo = getDbConnection();
    $columns = db_get_table_columns($pdo, 'wgs_reklamace');

    $whereParts = [];
    $params = [];

    if ($status !== 'all') {
        // DB používá anglické hodnoty: 'wait', 'open', 'done' (lowercase)
        // URL parametry také používají anglické hodnoty
        // NESMÍME mapovat na české hodnoty - SQL dotaz by nic nenašel!
        $statusValue = $status;

        if (in_array('stav', $columns, true)) {
            $whereParts[] = 'r.stav = :stav';
        } elseif (in_array('status', $columns, true)) {
            $whereParts[] = 'r.status = :stav';
        }
        $params[':stav'] = $statusValue;
    }

    // ŠKÁLOVATELNÁ LOGIKA PRO VÍCE PRODEJCŮ A TECHNIKŮ
    if (!$isAdmin) {
        $userId = $_SESSION['user_id'] ?? null;
        $userEmail = $_SESSION['user_email'] ?? null;  // SPRÁVNĚ: login_controller používá 'user_email'
        $userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));

        // Rozlišení podle role:
        // - 'prodejce' → vidí POUZE SVÉ reklamace (created_by = user_id)
        // - 'technik' → vidí VŠECHNY reklamace (žádný filtr)
        // - 'guest' → vidí pouze své (email match)

        $isProdejce = in_array($userRole, ['prodejce', 'user'], true);
        $isTechnik = in_array($userRole, ['technik', 'technician'], true);

        if ($isProdejce) {
            // PRODEJCE: Vidí pouze SVÉ reklamace
            // Filtrace podle created_by (ID prodejce který vytvořil reklamaci)
            if ($userId !== null && in_array('created_by', $columns, true)) {
                $whereParts[] = 'r.created_by = :created_by';
                $params[':created_by'] = $userId;
            } else {
                // Pokud nemá user_id nebo neexistuje sloupec created_by, nevidí nic
                $whereParts[] = '1 = 0';
            }
        } elseif ($isTechnik) {
            // TECHNIK: Vidí VŠECHNY reklamace (žádný filtr)
            // Technici mají přístup ke všem reklamacím pro diagnostiku a opravu
        } else {
            // GUEST nebo NEZNÁMÁ ROLE: Vidí pouze své (email match)
            $guestConditions = [];

            // Filter podle created_by
            if ($userId !== null && in_array('created_by', $columns, true)) {
                $guestConditions[] = 'r.created_by = :created_by';
                $params[':created_by'] = $userId;
            }

            // Filter podle customer email (case-insensitive)
            if ($userEmail && in_array('email', $columns, true)) {
                $guestConditions[] = 'LOWER(TRIM(r.email)) = LOWER(TRIM(:user_email))';
                $params[':user_email'] = $userEmail;
            }

            // Filter podle prodejce_email
            if ($userEmail && in_array('prodejce_email', $columns, true)) {
                $guestConditions[] = 'LOWER(TRIM(r.prodejce_email)) = LOWER(TRIM(:prodejce_email))';
                $params[':prodejce_email'] = $userEmail;
            }

            if (!empty($guestConditions)) {
                $whereParts[] = '(' . implode(' OR ', $guestConditions) . ')';
            } else {
                // Guest bez jakéhokoliv identifikátoru nevidí nic
                $whereParts[] = '1 = 0';
            }
        }
    }

    $whereClause = '';
    if (!empty($whereParts)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
    }

    // PERFORMANCE: Pagination - načíst jen stránku záznamu
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? min(200, max(10, (int)$_GET['per_page'])) : 50;
    $offset = ($page - 1) * $perPage;

    // Spočítat celkový počet záznamů
    $countSql = "SELECT COUNT(*) as total FROM wgs_reklamace r $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $sql = "
        SELECT
            r.*,
            r.id as claim_id,
            u.name as created_by_name
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.id
        $whereClause
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // PERFORMANCE FIX: N+1 Query problem - načíst všechny fotky a dokumenty najednou
    if (!empty($reklamace)) {
        // Extrahovat všechny reklamace_id a claim_id
        $reklamaceIds = array_column($reklamace, 'reklamace_id');
        $claimIds = array_column($reklamace, 'id');

        // Načíst VŠECHNY fotky najednou (místo N queries)
        $photoPlaceholders = implode(',', array_fill(0, count($reklamaceIds), '?'));
        $photoSql = "
            SELECT
                id, photo_id, reklamace_id, section_name,
                photo_path, file_path, file_name,
                photo_order, photo_type, uploaded_at
            FROM wgs_photos
            WHERE reklamace_id IN ($photoPlaceholders)
            ORDER BY photo_order ASC, uploaded_at ASC
        ";
        $photoStmt = $pdo->prepare($photoSql);
        $photoStmt->execute($reklamaceIds);
        $allPhotos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

        // Seskupit fotky podle reklamace_id
        $photosMap = [];
        foreach ($allPhotos as $photo) {
            $rekId = $photo['reklamace_id'];
            if (!isset($photosMap[$rekId])) {
                $photosMap[$rekId] = [];
            }
            $photosMap[$rekId][] = $photo;
        }

        // Načíst VŠECHNY dokumenty najednou (místo N queries)
        // POZNÁMKA: Pokud tabulka wgs_documents neexistuje, přeskočíme načítání dokumentů
        $documentsMap = [];
        try {
            $docPlaceholders = implode(',', array_fill(0, count($claimIds), '?'));
            $docSql = "
                SELECT
                    id, claim_id, document_name, document_path as file_path,
                    document_type, file_size, uploaded_by, uploaded_at
                FROM wgs_documents
                WHERE claim_id IN ($docPlaceholders)
                ORDER BY uploaded_at DESC
            ";
            $docStmt = $pdo->prepare($docSql);
            $docStmt->execute($claimIds);
            $allDocuments = $docStmt->fetchAll(PDO::FETCH_ASSOC);

            // Seskupit dokumenty podle claim_id
            foreach ($allDocuments as $doc) {
                $claimId = $doc['claim_id'];
                if (!isset($documentsMap[$claimId])) {
                    $documentsMap[$claimId] = [];
                }
                $documentsMap[$claimId][] = $doc;
            }
        } catch (PDOException $e) {
            // Tabulka wgs_documents neexistuje nebo je nedostupná
            // Pokračujeme bez dokumentů
            error_log("Varování: Nelze načíst dokumenty - " . $e->getMessage());
        }

        // Přiřadit fotky a dokumenty k reklamacím
        foreach ($reklamace as &$record) {
            $reklamaceId = $record['reklamace_id'] ?? $record['cislo'] ?? $record['id'];
            $claimId = $record['id'];

            $record['photos'] = $photosMap[$reklamaceId] ?? [];
            $record['documents'] = $documentsMap[$claimId] ?? [];
        }
    }

    // Vrácení dat s pagination metadata
    echo json_encode([
        'status' => 'success',
        'data' => $reklamace,
        'count' => count($reklamace),
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => ceil($totalRecords / $perPage)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
