<?php
/**
 * Pricing API
 *
 * API pro správu ceníku služeb.
 *
 * Actions:
 * - list: Seznam všech položek ceníku
 * - detail: Detail jedné položky
 * - update: Aktualizace položky (admin only)
 * - create: Vytvoření nové položky (admin only)
 * - delete: Smazání položky (admin only)
 * - reorder: Změna pořadí položek (admin only)
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // ✅ PERFORMANCE FIX: Načíst admin status a uvolnit zámek
    // Audit 2025-11-24: Ceník API - vysoký objem GET requestů
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    // KRITICKÉ: Uvolnit session lock pro paralelní zpracování
    session_write_close();

    $pdo = getDbConnection();

    // Parametry
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    $itemId = $_GET['id'] ?? $_POST['id'] ?? null;

    // ========================================
    // ACTION ROUTING
    // ========================================
    switch ($action) {
        // ========================================
        // LIST - Veřejný seznam položek ceníku
        // ========================================
        case 'list':
            $stmt = $pdo->query("
                SELECT *
                FROM wgs_pricing
                WHERE is_active = 1
                ORDER BY display_order ASC, category ASC
            ");

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Seskupit podle kategorií
            $byCategory = [];
            foreach ($items as $item) {
                $category = $item['category'] ?? 'Ostatní';
                if (!isset($byCategory[$category])) {
                    $byCategory[$category] = [];
                }
                $byCategory[$category][] = $item;
            }

            sendJsonSuccess('Ceník načten', [
                'items' => $items,
                'by_category' => $byCategory,
                'total' => count($items)
            ]);
            break;

        // ========================================
        // DETAIL - Detail jedné položky
        // ========================================
        case 'detail':
            if (!$itemId) {
                sendJsonError('Chybí ID položky');
            }

            $stmt = $pdo->prepare("SELECT * FROM wgs_pricing WHERE id = :id");
            $stmt->execute(['id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                sendJsonError('Položka nenalezena', 404);
            }

            sendJsonSuccess('Detail načten', ['item' => $item]);
            break;

        // ========================================
        // UPDATE - Aktualizace položky (admin only)
        // ========================================
        case 'update':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            // Admin check
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            if (!$itemId) {
                sendJsonError('Chybí ID položky');
            }

            // Zjistit v jakém jazyce se edituje
            $editLang = $_POST['edit_lang'] ?? 'cs';

            // Validace vstupů
            $serviceName = $_POST['service_name'] ?? null;
            $description = $_POST['description'] ?? null;
            $priceFrom = isset($_POST['price_from']) && $_POST['price_from'] !== '' ? floatval($_POST['price_from']) : null;
            $priceTo = isset($_POST['price_to']) && $_POST['price_to'] !== '' ? floatval($_POST['price_to']) : null;
            $priceUnit = $_POST['price_unit'] ?? '€';
            $category = $_POST['category'] ?? null;
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            if (!$serviceName) {
                sendJsonError('Chybí název služby');
            }

            // Určit správné sloupce podle jazyka
            $nameCol = $editLang === 'cs' ? 'service_name' : "service_name_{$editLang}";
            $descCol = $editLang === 'cs' ? 'description' : "description_{$editLang}";
            $catCol = $editLang === 'cs' ? 'category' : "category_{$editLang}";

            $sql = "
            UPDATE wgs_pricing
            SET {$nameCol} = :name,
                {$descCol} = :desc,
                price_from = :from,
                price_to = :to,
                price_unit = :unit,
                {$catCol} = :cat,
                is_active = :active
            WHERE id = :id
            ";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                'name' => $serviceName,
                'desc' => $description,
                'from' => $priceFrom,
                'to' => $priceTo,
                'unit' => $priceUnit,
                'cat' => $category,
                'active' => $isActive,
                'id' => $itemId
            ]);

            if ($success) {
                sendJsonSuccess('Položka aktualizována', ['id' => $itemId, 'lang' => $editLang]);
            } else {
                sendJsonError('Chyba při aktualizaci');
            }
            break;

        // ========================================
        // CREATE - Vytvoření nové položky (admin only)
        // ========================================
        case 'create':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            // Admin check
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            // Zjistit v jakém jazyce se vytváří
            $editLang = $_POST['edit_lang'] ?? 'cs';

            // Validace vstupů
            $serviceName = $_POST['service_name'] ?? null;
            $description = $_POST['description'] ?? null;
            $priceFrom = isset($_POST['price_from']) && $_POST['price_from'] !== '' ? floatval($_POST['price_from']) : null;
            $priceTo = isset($_POST['price_to']) && $_POST['price_to'] !== '' ? floatval($_POST['price_to']) : null;
            $priceUnit = $_POST['price_unit'] ?? '€';
            $category = $_POST['category'] ?? 'Ostatní';
            $displayOrder = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 999;

            if (!$serviceName) {
                sendJsonError('Chybí název služby');
            }

            // Určit správné sloupce podle jazyka
            $nameCol = $editLang === 'cs' ? 'service_name' : "service_name_{$editLang}";
            $descCol = $editLang === 'cs' ? 'description' : "description_{$editLang}";
            $catCol = $editLang === 'cs' ? 'category' : "category_{$editLang}";

            $sql = "
            INSERT INTO wgs_pricing ({$nameCol}, {$descCol}, price_from, price_to, price_unit, {$catCol}, display_order)
            VALUES (:name, :desc, :from, :to, :unit, :cat, :order)
            ";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                'name' => $serviceName,
                'desc' => $description,
                'from' => $priceFrom,
                'to' => $priceTo,
                'unit' => $priceUnit,
                'cat' => $category,
                'order' => $displayOrder
            ]);

            if ($success) {
                $newId = $pdo->lastInsertId();
                sendJsonSuccess('Položka vytvořena', ['id' => $newId, 'lang' => $editLang]);
            } else {
                sendJsonError('Chyba při vytváření');
            }
            break;

        // ========================================
        // DELETE - Smazání položky (admin only)
        // ========================================
        case 'delete':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            // Admin check
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            if (!$itemId) {
                sendJsonError('Chybí ID položky');
            }

            $stmt = $pdo->prepare("DELETE FROM wgs_pricing WHERE id = :id");
            $success = $stmt->execute(['id' => $itemId]);

            if ($success) {
                sendJsonSuccess('Položka smazána');
            } else {
                sendJsonError('Chyba při mazání');
            }
            break;

        // ========================================
        // REORDER - Změna pořadí (admin only)
        // ========================================
        case 'reorder':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            // Admin check
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            // Očekáváme JSON pole s ID a novým pořadím
            $orderData = json_decode($_POST['order_data'] ?? '[]', true);

            if (empty($orderData)) {
                sendJsonError('Chybí data pro změnu pořadí');
            }

            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("UPDATE wgs_pricing SET display_order = :order WHERE id = :id");

                foreach ($orderData as $item) {
                    $stmt->execute([
                        'id' => $item['id'],
                        'order' => $item['order']
                    ]);
                }

                $pdo->commit();
                sendJsonSuccess('Pořadí aktualizováno');

            } catch (Exception $e) {
                $pdo->rollBack();
                sendJsonError('Chyba při změně pořadí');
            }
            break;

        // ========================================
        // DEFAULT - Neplatná akce
        // ========================================
        default:
            sendJsonError('Neplatná akce: ' . $action);
    }

} catch (PDOException $e) {
    error_log("Pricing API - Database error: " . $e->getMessage());
    sendJsonError('Chyba databáze');
} catch (Exception $e) {
    error_log("Pricing API - Error: " . $e->getMessage());
    sendJsonError('Chyba při zpracování: ' . $e->getMessage());
}
?>
