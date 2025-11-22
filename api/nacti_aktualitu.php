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
    $jazyk = $_GET['jazyk'] ?? 'cz';  // Vždy CZ
    $index = (int)($_GET['index'] ?? 0);

    if (empty($id) || !is_numeric($id)) {
        sendJsonError('Chybí nebo neplatné ID aktuality');
    }

    // Načíst aktualitu z databáze - pouze CZ verze
    $stmt = $pdo->prepare("
        SELECT obsah_cz
        FROM wgs_natuzzi_aktuality
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);
    $aktualita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aktualita) {
        sendJsonError('Aktualita nebyla nalezena');
    }

    $celyObsah = $aktualita['obsah_cz'] ?? '';

    // Rozdělit na jednotlivé články podle ## nadpisů
    $parts = preg_split('/(?=^## )/m', $celyObsah);

    // Najít článek podle indexu
    $obsahJednohoArticle = '';
    $currentIndex = 0;

    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;

        // První část je hlavní nadpis - přeskočit
        if ($currentIndex === 0 && !preg_match('/^## /', $part)) {
            continue;
        }

        // Pokud je to článek s ## nadpisem
        if (preg_match('/^## /', $part)) {
            if ($currentIndex === $index) {
                $obsahJednohoArticle = $part;
                break;
            }
            $currentIndex++;
        }
    }

    if (empty($obsahJednohoArticle)) {
        sendJsonError('Článek s indexem ' . $index . ' nebyl nalezen');
    }

    sendJsonSuccess('Obsah načten', [
        'obsah' => $obsahJednohoArticle
    ]);

} catch (PDOException $e) {
    error_log("Database error in nacti_aktualitu.php: " . $e->getMessage());
    sendJsonError('Chyba při načítání aktuality z databáze');
} catch (Exception $e) {
    error_log("Error in nacti_aktualitu.php: " . $e->getMessage());
    sendJsonError('Neočekávaná chyba při načítání aktuality');
}
?>
