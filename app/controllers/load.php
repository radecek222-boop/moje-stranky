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
        $statusMap = [
            'wait' => 'ČEKÁ',
            'open' => 'DOMLUVENÁ',
            'done' => 'HOTOVO'
        ];
        $statusValue = $statusMap[$status] ?? $status;
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
        $userEmail = $_SESSION['user_email'] ?? null;
        $userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));

        // Rozlišení podle role:
        // - 'prodejce', 'user' → vidí VŠECHNY reklamace (vytvářejí pro zákazníky)
        // - 'technik' → vidí pouze přiřazené reklamace (zpracoval_id)
        // - 'guest' → vidí pouze své (email match)

        $isProdejce = in_array($userRole, ['prodejce', 'user'], true);
        $isTechnik = in_array($userRole, ['technik', 'technician'], true);

        if ($isProdejce) {
            // PRODEJCE: Vidí všechny reklamace (žádný filtr)
            // Prodejci vytvářejí reklamace pro zákazníky, takže potřebují vidět všechny
            // Žádné WHERE podmínky pro prodejce
        } elseif ($isTechnik) {
            // TECHNIK: Vidí pouze přiřazené reklamace
            if ($userId !== null) {
                $technikConditions = [];

                // Filtry pro technika
                if (in_array('zpracoval_id', $columns, true)) {
                    $technikConditions[] = 'r.zpracoval_id = :zpracoval_id';
                    $params[':zpracoval_id'] = $userId;
                }

                if (in_array('assigned_to', $columns, true)) {
                    $technikConditions[] = 'r.assigned_to = :assigned_to';
                    $params[':assigned_to'] = $userId;
                }

                if (!empty($technikConditions)) {
                    $whereParts[] = '(' . implode(' OR ', $technikConditions) . ')';
                } else {
                    // Pokud technik nemá žádný způsob filtrování, nevidí nic
                    $whereParts[] = '1 = 0';
                }
            } else {
                // Technik bez user_id nevidí nic
                $whereParts[] = '1 = 0';
            }
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

    $sql = "
        SELECT
            r.*,
            r.id as claim_id
        FROM wgs_reklamace r
        $whereClause
        ORDER BY r.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pro každou reklamaci načíst fotky a dokumenty
    foreach ($reklamace as &$record) {
        $reklamaceId = $record['reklamace_id'] ?? $record['cislo'] ?? $record['id'];

        // Načtení fotek
        $stmt = $pdo->prepare("
            SELECT
                id, photo_id, reklamace_id, section_name,
                photo_path, file_path, file_name,
                photo_order, photo_type, uploaded_at
            FROM wgs_photos
            WHERE reklamace_id = :reklamace_id
            ORDER BY photo_order ASC, uploaded_at ASC
        ");
        $stmt->execute([':reklamace_id' => $reklamaceId]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Přidat fotky k záznamu
        $record['photos'] = $photos;

        // Načtení dokumentů (PDF protokoly)
        $stmt = $pdo->prepare("
            SELECT
                id, claim_id, document_name, document_path as file_path,
                document_type, file_size, uploaded_by, uploaded_at
            FROM wgs_documents
            WHERE claim_id = :claim_id
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([':claim_id' => $record['id']]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Přidat dokumenty k záznamu
        $record['documents'] = $documents;
    }

    // Vrácení dat
    echo json_encode([
        'status' => 'success',
        'data' => $reklamace,
        'count' => count($reklamace)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
