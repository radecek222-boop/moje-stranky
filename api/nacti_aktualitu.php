<?php
/**
 * API endpoint pro načtení markdown obsahu aktuality
 * Pouze pro administrátory
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola admin oprávnění
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen - pouze pro administrátory', 403);
}

try {
    $pdo = getDbConnection();

    // Validace vstupních dat
    $id = $_GET['id'] ?? '';
    $jazyk = $_GET['jazyk'] ?? '';

    if (empty($id) || !is_numeric($id)) {
        sendJsonError('Chybí nebo neplatné ID aktuality');
    }

    if (!in_array($jazyk, ['cz', 'en', 'it'])) {
        sendJsonError('Neplatný jazyk (povoleno: cz, en, it)');
    }

    // Načíst aktualitu z databáze
    $stmt = $pdo->prepare("
        SELECT obsah_cz, obsah_en, obsah_it
        FROM wgs_natuzzi_aktuality
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);
    $aktualita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aktualita) {
        sendJsonError('Aktualita nebyla nalezena');
    }

    // Vrátit obsah pro vybraný jazyk
    $sloupec = 'obsah_' . $jazyk;
    $obsah = $aktualita[$sloupec] ?? '';

    sendJsonSuccess('Obsah načten', [
        'obsah' => $obsah
    ]);

} catch (PDOException $e) {
    error_log("Database error in nacti_aktualitu.php: " . $e->getMessage());
    sendJsonError('Chyba při načítání aktuality z databáze');
} catch (Exception $e) {
    error_log("Error in nacti_aktualitu.php: " . $e->getMessage());
    sendJsonError('Neočekávaná chyba při načítání aktuality');
}
?>
