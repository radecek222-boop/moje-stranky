<?php
/**
 * Load Controller
 * Načítání reklamací, fotek a dokumentů pro seznam.php
 */

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/db_metadata.php';

header('Content-Type: application/json');
// Zakázat cachování pro PWA
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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
        // - 'prodejce' → vidí SVÉ reklamace + reklamace SUPERVIZOVANÝCH prodejců
        // - 'technik' → vidí VŠECHNY reklamace (žádný filtr)
        // - 'guest' → vidí pouze své (email match)

        $isProdejce = in_array($userRole, ['prodejce', 'user'], true);
        $isTechnik = in_array($userRole, ['technik', 'technician'], true);

        if ($isProdejce) {
            // PRODEJCE: Vidí SVÉ reklamace + reklamace SUPERVIZOVANÝCH prodejců
            if ($userId !== null && in_array('created_by', $columns, true)) {
                // Načíst supervizované uživatele
                $supervisedUserIds = [];
                try {
                    // Zjistit strukturu tabulky wgs_users
                    $stmtCols = $pdo->query("SHOW COLUMNS FROM wgs_users");
                    $userColumns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
                    $idCol = in_array('user_id', $userColumns) ? 'user_id' : 'id';
                    $numericIdCol = 'id';

                    // Získat numerické ID aktuálního uživatele
                    $currentNumericId = $userId;
                    if (!is_numeric($userId)) {
                        $stmtNum = $pdo->prepare("SELECT id FROM wgs_users WHERE user_id = :user_id LIMIT 1");
                        $stmtNum->execute([':user_id' => $userId]);
                        $numericId = $stmtNum->fetchColumn();
                        if ($numericId) {
                            $currentNumericId = $numericId;
                        }
                    }

                    // Načíst user_id supervizovaných prodejců
                    $stmtSup = $pdo->prepare("
                        SELECT u.{$idCol}
                        FROM wgs_supervisor_assignments sa
                        JOIN wgs_users u ON u.{$numericIdCol} = sa.salesperson_user_id
                        WHERE sa.supervisor_user_id = :user_id
                    ");
                    $stmtSup->execute([':user_id' => $currentNumericId]);
                    $supervisedUserIds = $stmtSup->fetchAll(PDO::FETCH_COLUMN);
                } catch (Exception $e) {
                    // Tabulka možná neexistuje - pokračovat bez supervizorů
                    error_log("Supervisor assignments check: " . $e->getMessage());
                }

                // Vytvořit WHERE podmínku - vlastní NEBO supervizované
                if (!empty($supervisedUserIds)) {
                    // Prodejce má přiřazené supervizované prodejce
                    $allUserIds = array_merge([$userId], $supervisedUserIds);
                    $placeholders = [];
                    foreach ($allUserIds as $idx => $supUserId) {
                        $paramName = ':user_id_' . $idx;
                        $placeholders[] = $paramName;
                        $params[$paramName] = $supUserId;
                    }
                    $whereParts[] = 'r.created_by IN (' . implode(', ', $placeholders) . ')';
                } else {
                    // Prodejce nemá supervizované - vidí jen své
                    $whereParts[] = 'r.created_by = :created_by';
                    $params[':created_by'] = $userId;
                }
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
    $perPage = isset($_GET['per_page']) ? min(9999, max(10, (int)$_GET['per_page'])) : 9999;
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
            CASE
                WHEN r.created_by IS NULL OR r.created_by = '' THEN
                    CASE r.created_by_role
                        WHEN 'admin' THEN 'Administrátor'
                        WHEN 'technik' THEN COALESCE(r.technik, 'Technik')
                        ELSE NULL
                    END
                ELSE u.name
            END as created_by_name,
            u.name as zadavatel_jmeno,
            u.email as created_by_email,
            t.name as technik_jmeno,
            t.email as technik_email,
            t.phone as technik_telefon,
            n.odeslano_at as cn_odeslano_at
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        LEFT JOIN wgs_users t ON r.assigned_to = t.id
        LEFT JOIN wgs_nabidky n ON LOWER(TRIM(r.email)) = LOWER(TRIM(n.zakaznik_email))
            AND n.stav IN ('odeslana', 'potvrzena')
        $whereClause
        ORDER BY
            -- Priorita podle stavu/typu (1=žluté, 2=modré, 3=CN, 4=POZ, 5=zelené)
            CASE
                WHEN r.stav = 'wait' AND (n.odeslano_at IS NULL) THEN 1
                WHEN r.stav = 'open' THEN 2
                WHEN n.odeslano_at IS NOT NULL AND r.stav != 'done' THEN 3
                WHEN r.created_by IS NULL OR r.created_by = '' THEN 4
                WHEN r.stav = 'done' THEN 5
                ELSE 6
            END ASC,
            -- Pro DOMLUVENÁ: řadit podle termínu (nejbližší první)
            CASE
                WHEN r.stav = 'open' THEN
                    CASE
                        WHEN r.termin IS NULL THEN '9999-12-31'
                        ELSE DATE(STR_TO_DATE(r.termin, '%d.%m.%Y'))
                    END
                ELSE NULL
            END ASC,
            -- Pro DOMLUVENÁ: řadit také podle času návštěvy (nejdřívější první)
            CASE
                WHEN r.stav = 'open' THEN
                    CASE
                        WHEN r.cas_navstevy IS NULL OR r.cas_navstevy = '' THEN '23:59'
                        ELSE r.cas_navstevy
                    END
                ELSE NULL
            END ASC,
            -- Pro CN: řadit podle data odeslání CN (nejnovější první)
            CASE
                WHEN n.odeslano_at IS NOT NULL AND r.stav != 'done' THEN n.odeslano_at
                ELSE NULL
            END DESC,
            -- Pro POZ: řadit podle data vytvoření (nejnovější první)
            CASE
                WHEN r.created_by IS NULL OR r.created_by = '' THEN r.created_at
                ELSE NULL
            END DESC,
            -- Pro ČEKÁ (bez CN): řadit podle data zadání (nejnovější první)
            CASE
                WHEN r.stav = 'wait' AND (n.odeslano_at IS NULL) THEN r.created_at
                ELSE NULL
            END DESC,
            -- Pro HOTOVO: řadit podle data vytvoření (nejnovější první)
            CASE
                WHEN r.stav = 'done' THEN r.created_at
                ELSE NULL
            END DESC
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
