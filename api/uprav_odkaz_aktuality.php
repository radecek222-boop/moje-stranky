<?php
/**
 * API pro úpravu odkazů v aktualitách (pouze admin)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Admin kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen. Pouze administrátor může upravovat odkazy.', 403);
}

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

try {
    $pdo = getDbConnection();

    // Validace vstupů
    $aktualitaId = filter_var($_POST['aktualita_id'] ?? 0, FILTER_VALIDATE_INT);
    $jazyk = $_POST['jazyk'] ?? 'cz';
    $staraUrl = $_POST['stara_url'] ?? '';
    $novaUrl = $_POST['nova_url'] ?? '';

    // Kontrola povinných polí
    if (!$aktualitaId) {
        sendJsonError('Neplatné ID aktuality');
    }

    if (!in_array($jazyk, ['cz', 'en', 'it'])) {
        sendJsonError('Neplatný jazyk');
    }

    if (empty($staraUrl) || empty($novaUrl)) {
        sendJsonError('Stará i nová URL musí být vyplněny');
    }

    // Validace nové URL
    if (!filter_var($novaUrl, FILTER_VALIDATE_URL)) {
        sendJsonError('Neplatný formát nové URL');
    }

    // Pouze HTTP/HTTPS
    $scheme = parse_url($novaUrl, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        sendJsonError('URL musí začínat http:// nebo https://');
    }

    // Načíst aktualitu
    $obsahSloupec = 'obsah_' . $jazyk;
    $stmt = $pdo->prepare("SELECT id, {$obsahSloupec} FROM wgs_natuzzi_aktuality WHERE id = :id");
    $stmt->execute(['id' => $aktualitaId]);
    $aktualita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aktualita) {
        sendJsonError('Aktualita nebyla nalezena');
    }

    $obsah = $aktualita[$obsahSloupec];

    // Najít a nahradit odkaz v Markdown formátu
    // Formát: [text](URL)
    $staraUrlEscaped = preg_quote($staraUrl, '/');

    // Hledat markdown odkazy s touto URL
    $pattern = '/\[([^\]]+)\]\(' . $staraUrlEscaped . '\)/';
    $replacement = '[$1](' . $novaUrl . ')';

    $novyObsah = preg_replace($pattern, $replacement, $obsah, -1, $count);

    if ($count === 0) {
        // Zkusit najít jako samostatnou URL (bez markdown syntaxe)
        $novyObsah = str_replace($staraUrl, $novaUrl, $obsah, $count);

        if ($count === 0) {
            sendJsonError('Odkaz nebyl nalezen v obsahu. Možná byl již změněn.');
        }
    }

    // Uložit změnu
    $stmtUpdate = $pdo->prepare("
        UPDATE wgs_natuzzi_aktuality
        SET {$obsahSloupec} = :obsah,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        'obsah' => $novyObsah,
        'id' => $aktualitaId
    ]);

    // Audit log
    error_log(sprintf(
        "ADMIN EDIT LINK: User %d changed URL in aktualita #%d (%s): %s -> %s",
        $_SESSION['user_id'] ?? 0,
        $aktualitaId,
        $jazyk,
        $staraUrl,
        $novaUrl
    ));

    sendJsonSuccess('Odkaz byl úspěšně změněn', [
        'aktualita_id' => $aktualitaId,
        'jazyk' => $jazyk,
        'stara_url' => $staraUrl,
        'nova_url' => $novaUrl,
        'pocet_zmen' => $count
    ]);

} catch (PDOException $e) {
    error_log("Database error in uprav_odkaz_aktuality.php: " . $e->getMessage());
    sendJsonError('Chyba databáze při ukládání změn');
} catch (Exception $e) {
    error_log("Error in uprav_odkaz_aktuality.php: " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku: ' . $e->getMessage());
}
?>
