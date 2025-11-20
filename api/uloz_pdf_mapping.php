<?php
/**
 * API endpoint pro uložení PDF mapping z vizuálního nástroje
 *
 * Přijímá mapping vytvořený uživatelem a aktualizuje konfiguraci v databázi
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola přihlášení - pouze pro adminy
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Pouze administrátor může měnit mapping', 403);
}

try {
    $pdo = getDbConnection();

    // Získat data
    $mapping = $_POST['mapping'] ?? '';
    $configId = $_POST['config_id'] ?? '';
    $configName = $_POST['config_name'] ?? '';

    if (empty($mapping)) {
        sendJsonError('Chybí mapping data');
    }

    // Dekódovat mapping (pokud je JSON string)
    if (is_string($mapping)) {
        $mappingArray = json_decode($mapping, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonError('Neplatný formát mappingu');
        }
    } else {
        $mappingArray = $mapping;
    }

    // Pokud máme config_id, aktualizovat
    if (!empty($configId)) {
        $stmt = $pdo->prepare("
            UPDATE wgs_pdf_parser_configs
            SET pole_mapping = :mapping
            WHERE config_id = :id
        ");

        $stmt->execute([
            'mapping' => json_encode($mappingArray, JSON_UNESCAPED_UNICODE),
            'id' => $configId
        ]);

        sendJsonSuccess('Mapping aktualizován', [
            'config_id' => $configId,
            'updated_rows' => $stmt->rowCount(),
            'mapping' => $mappingArray
        ]);
    } else {
        // Jinak vrátit SQL příkaz pro manuální spuštění
        $mappingJson = json_encode($mappingArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $sqlCommand = "UPDATE wgs_pdf_parser_configs\nSET pole_mapping = '" . addslashes($mappingJson) . "'\nWHERE nazev = '" . addslashes($configName) . "';";

        sendJsonSuccess('SQL příkaz vygenerován', [
            'sql' => $sqlCommand,
            'mapping' => $mappingArray,
            'info' => 'Zkopíruj SQL příkaz a spusť ho v phpMyAdmin nebo diagnostic nástroji'
        ]);
    }

} catch (PDOException $e) {
    error_log("Chyba při ukládání PDF mappingu: " . $e->getMessage());
    sendJsonError('Chyba při ukládání mappingu');
} catch (Exception $e) {
    error_log("Obecná chyba: " . $e->getMessage());
    sendJsonError('Chyba serveru');
}
?>
