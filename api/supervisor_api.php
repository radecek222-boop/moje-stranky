<?php
/**
 * Supervisor API
 * API pro správu přiřazení prodejců pod supervizory
 *
 * Pouze admin může měnit přiřazení.
 * Supervizor vidí zakázky přiřazených prodejců + své vlastní.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // BEZPEČNOST: Kontrola přihlášení admina
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    $isLoggedIn = isset($_SESSION['user_id']) || $isAdmin;

    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup. Přihlaste se prosím.'
        ]);
        exit;
    }

    // Načíst session data
    $currentUserId = $_SESSION['user_id'] ?? null;
    $adminId = $_SESSION['admin_id'] ?? null;

    // Uvolnit session lock
    session_write_close();

    // Zjištění akce
    $action = '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        // GET může pouze číst
        $readOnlyActions = ['getAssignments', 'getSalespersons', 'getMySupervisedUsers'];
        if (!in_array($action, $readOnlyActions, true)) {
            throw new Exception('Tato akce vyžaduje POST metodu s CSRF tokenem.');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // CSRF ochrana pro POST
        requireCSRF();

        // Pouze admin může měnit přiřazení
        if (!$isAdmin) {
            throw new Exception('Pouze administrátor může měnit přiřazení supervizorů.');
        }
    } else {
        throw new Exception('Povolena pouze GET nebo POST metoda');
    }

    $pdo = getDbConnection();

    switch ($action) {

        // === NAČTENÍ PŘIŘAZENÝCH PRODEJCŮ PRO SUPERVIZORA ===
        case 'getAssignments':
            $supervisorIdParam = $_GET['user_id'] ?? 0;

            // Zjistit strukturu tabulky wgs_users
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Zjistit sloupec pro jméno
            $nameCol = in_array('name', $columns) ? 'name' : (in_array('jmeno', $columns) ? 'jmeno' : 'email');

            // Konvertovat user_id parametr na numerické ID (pokud je VARCHAR)
            $supervisorId = $supervisorIdParam;
            if (!is_numeric($supervisorIdParam)) {
                $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE user_id = :user_id LIMIT 1");
                $stmt->execute([':user_id' => $supervisorIdParam]);
                $numericId = $stmt->fetchColumn();
                if ($numericId) {
                    $supervisorId = intval($numericId);
                } else {
                    throw new Exception('Supervizor nenalezen');
                }
            } else {
                $supervisorId = intval($supervisorIdParam);
            }

            if ($supervisorId <= 0) {
                throw new Exception('Neplatné ID supervizora');
            }

            // Načíst přiřazené prodejce
            // JOIN používá numerické id, protože supervisor_assignments ukládá INT
            $stmt = $pdo->prepare("
                SELECT
                    sa.id as assignment_id,
                    sa.salesperson_user_id,
                    sa.created_at,
                    u.{$nameCol} as jmeno,
                    u.email,
                    u.role
                FROM wgs_supervisor_assignments sa
                JOIN wgs_users u ON u.id = sa.salesperson_user_id
                WHERE sa.supervisor_user_id = :supervisor_id
                ORDER BY u.{$nameCol} ASC
            ");
            $stmt->execute([':supervisor_id' => $supervisorId]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'supervisor_id' => $supervisorId,
                    'assignments' => $assignments
                ]
            ]);
            break;

        // === NAČTENÍ VŠECH PRODEJCŮ (PRO VÝBĚR) ===
        case 'getSalespersons':
            $excludeUserId = intval($_GET['exclude_user_id'] ?? 0);

            // Zjistit strukturu tabulky wgs_users
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $hasUserIdCol = in_array('user_id', $columns);
            $nameCol = in_array('name', $columns) ? 'name' : (in_array('jmeno', $columns) ? 'jmeno' : 'email');
            $activeCol = in_array('is_active', $columns) ? 'is_active = 1' : '1=1';

            // Načíst všechny aktivní uživatele kromě sebe sama
            // Vrací OBĚ id (numerické pro ukládání) i user_id (VARCHAR pro zobrazení)
            if ($hasUserIdCol) {
                $sql = "
                    SELECT
                        id as numeric_id,
                        user_id,
                        {$nameCol} as jmeno,
                        email,
                        role
                    FROM wgs_users
                    WHERE {$activeCol}
                ";
            } else {
                $sql = "
                    SELECT
                        id as numeric_id,
                        id as user_id,
                        {$nameCol} as jmeno,
                        email,
                        role
                    FROM wgs_users
                    WHERE {$activeCol}
                ";
            }

            $params = [];
            if ($excludeUserId > 0) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = $excludeUserId;
            }

            $sql .= " ORDER BY {$nameCol} ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $salespersons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'salespersons' => $salespersons
                ]
            ]);
            break;

        // === NAČTENÍ UŽIVATELŮ POD MNOU (PRO SEZNAM.PHP) ===
        case 'getMySupervisedUsers':
            $userId = intval($_GET['user_id'] ?? $currentUserId ?? 0);

            if ($userId <= 0) {
                throw new Exception('Neplatné ID uživatele');
            }

            // Načíst ID prodejců které supervizuji
            $stmt = $pdo->prepare("
                SELECT salesperson_user_id
                FROM wgs_supervisor_assignments
                WHERE supervisor_user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $supervised = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'user_id' => $userId,
                    'supervised_user_ids' => $supervised
                ]
            ]);
            break;

        // === ULOŽENÍ PŘIŘAZENÍ (POUZE ADMIN) ===
        case 'saveAssignments':
            $supervisorIdParam = $_POST['supervisor_id'] ?? 0;
            $salespersonIds = $_POST['salesperson_ids'] ?? [];

            // Konvertovat supervisor_id na numerické ID (pokud je VARCHAR)
            $supervisorId = $supervisorIdParam;
            if (!is_numeric($supervisorIdParam)) {
                $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE user_id = :user_id LIMIT 1");
                $stmt->execute([':user_id' => $supervisorIdParam]);
                $numericId = $stmt->fetchColumn();
                if ($numericId) {
                    $supervisorId = intval($numericId);
                } else {
                    throw new Exception('Supervizor nenalezen');
                }
            } else {
                $supervisorId = intval($supervisorIdParam);
            }

            if ($supervisorId <= 0) {
                throw new Exception('Neplatné ID supervizora');
            }

            // Převést na pole integerů
            if (is_string($salespersonIds)) {
                $salespersonIds = json_decode($salespersonIds, true) ?? [];
            }
            $salespersonIds = array_map('intval', (array)$salespersonIds);
            $salespersonIds = array_filter($salespersonIds, fn($id) => $id > 0 && $id !== $supervisorId);

            $pdo->beginTransaction();

            try {
                // Smazat stávající přiřazení
                $stmt = $pdo->prepare("DELETE FROM wgs_supervisor_assignments WHERE supervisor_user_id = :supervisor_id");
                $stmt->execute([':supervisor_id' => $supervisorId]);
                $deletedCount = $stmt->rowCount();

                // Vložit nová přiřazení
                $insertedCount = 0;
                if (!empty($salespersonIds)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO wgs_supervisor_assignments
                            (supervisor_user_id, salesperson_user_id, created_by)
                        VALUES
                            (:supervisor_id, :salesperson_id, :admin_id)
                    ");

                    foreach ($salespersonIds as $salespersonId) {
                        $stmt->execute([
                            ':supervisor_id' => $supervisorId,
                            ':salesperson_id' => $salespersonId,
                            ':admin_id' => $adminId
                        ]);
                        $insertedCount++;
                    }
                }

                $pdo->commit();

                echo json_encode([
                    'status' => 'success',
                    'message' => "Přiřazení uloženo. Přidáno: {$insertedCount} prodejců.",
                    'data' => [
                        'supervisor_id' => $supervisorId,
                        'assigned_count' => $insertedCount,
                        'salesperson_ids' => $salespersonIds
                    ]
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // === PŘIDÁNÍ JEDNOHO PŘIŘAZENÍ ===
        case 'addAssignment':
            $supervisorId = intval($_POST['supervisor_id'] ?? 0);
            $salespersonId = intval($_POST['salesperson_id'] ?? 0);

            if ($supervisorId <= 0 || $salespersonId <= 0) {
                throw new Exception('Neplatné ID');
            }

            if ($supervisorId === $salespersonId) {
                throw new Exception('Uživatel nemůže být supervizorem sám sobě');
            }

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO wgs_supervisor_assignments
                    (supervisor_user_id, salesperson_user_id, created_by)
                VALUES
                    (:supervisor_id, :salesperson_id, :admin_id)
            ");
            $stmt->execute([
                ':supervisor_id' => $supervisorId,
                ':salesperson_id' => $salespersonId,
                ':admin_id' => $adminId
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Prodejce přiřazen pod supervizora'
            ]);
            break;

        // === ODEBRÁNÍ PŘIŘAZENÍ ===
        case 'removeAssignment':
            $supervisorId = intval($_POST['supervisor_id'] ?? 0);
            $salespersonId = intval($_POST['salesperson_id'] ?? 0);

            if ($supervisorId <= 0 || $salespersonId <= 0) {
                throw new Exception('Neplatné ID');
            }

            $stmt = $pdo->prepare("
                DELETE FROM wgs_supervisor_assignments
                WHERE supervisor_user_id = :supervisor_id
                  AND salesperson_user_id = :salesperson_id
            ");
            $stmt->execute([
                ':supervisor_id' => $supervisorId,
                ':salesperson_id' => $salespersonId
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Přiřazení odebráno'
            ]);
            break;

        default:
            throw new Exception("Neznámá akce: {$action}");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
