<?php
/**
 * Admin API - Theme & Content Module
 * Zpracování theme nastavení a content textů
 * Extrahováno z control_center_api.php
 */

// Tento soubor je načítán přes api/admin.php router
// Proměnné $pdo, $data, $action jsou již k dispozici

switch ($action) {
    case 'save_theme':
        $settings = $data['settings'] ?? [];

        if (empty($settings)) {
            throw new Exception('No settings provided');
        }

        // Update theme settings v databázi
        $stmt = $pdo->prepare("
            INSERT INTO wgs_theme_settings (setting_key, setting_value, setting_type, setting_group, updated_by)
            VALUES (:key, :value, :type, :group, :user_id)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP,
                updated_by = VALUES(updated_by)
        ");

        $userId = $_SESSION['user_id'] ?? null;

        foreach ($settings as $key => $value) {
            $type = strpos($key, 'color') !== false ? 'color' :
                    ($key === 'font_family' ? 'font' : 'size');
            $group = strpos($key, 'color') !== false ? 'colors' :
                     ($key === 'font_family' || $key === 'font_size_base' ? 'typography' : 'layout');

            $stmt->execute([
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'user_id' => $userId
            ]);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Theme settings saved',
            'settings' => $settings
        ]);
        break;

    case 'get_content_texts':
        $page = $_GET['page'] ?? null;

        $query = "SELECT * FROM wgs_content_texts";
        if ($page) {
            $query .= " WHERE page = :page";
        }
        $query .= " ORDER BY page, section, text_key";

        $stmt = $pdo->prepare($query);
        if ($page) {
            $stmt->execute(['page' => $page]);
        } else {
            $stmt->execute();
        }

        $texts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $texts
        ]);
        break;

    case 'save_content_text':
        $id = $data['id'] ?? null;
        $valueCz = $data['value_cz'] ?? '';
        $valueEn = $data['value_en'] ?? '';
        $valueSk = $data['value_sk'] ?? '';

        if (!$id) {
            throw new Exception('Text ID required');
        }

        $stmt = $pdo->prepare("
            UPDATE wgs_content_texts
            SET value_cz = :value_cz,
                value_en = :value_en,
                value_sk = :value_sk,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :user_id
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'value_cz' => $valueCz,
            'value_en' => $valueEn,
            'value_sk' => $valueSk,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Content text saved'
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Unknown theme action: {$action}"
        ]);
}
