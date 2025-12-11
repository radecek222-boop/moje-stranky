<?php
/**
 * API endpoint pro překlad aktuality na vyžádání
 * Admin klikne na vlajku -> systém přeloží CZ obsah do EN/IT a uloží do DB
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/translator.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola admin oprávnění
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen - pouze pro administrátory', 403);
}

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

try {
    $pdo = getDbConnection();

    // Rate limiting
    require_once __DIR__ . '/../includes/rate_limiter.php';
    $rateLimiter = new RateLimiter($pdo);
    if (!$rateLimiter->checkLimit('translate_aktualita', $_SERVER['REMOTE_ADDR'], 30, 3600)) {
        sendJsonError('Příliš mnoho požadavků na překlad', 429);
    }

    // Validace vstupních dat (GET nebo POST)
    $cilovyJazyk = $_REQUEST['jazyk'] ?? '';
    $aktualitaId = filter_var($_REQUEST['aktualita_id'] ?? '', FILTER_VALIDATE_INT);

    // Jazyk musí být 'en' nebo 'it'
    if (!in_array($cilovyJazyk, ['en', 'it'])) {
        sendJsonError('Neplatný cílový jazyk (povoleno: en, it)');
    }

    // Pokud není zadáno ID, přeložit VŠECHNY aktuality
    $prekladVsech = !$aktualitaId;

    if ($prekladVsech) {
        // Načíst všechny aktuality
        $stmt = $pdo->query("SELECT id, obsah_cz, obsah_en, obsah_it FROM wgs_natuzzi_aktuality ORDER BY datum DESC");
        $aktuality = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Načíst konkrétní aktualitu
        $stmt = $pdo->prepare("SELECT id, obsah_cz, obsah_en, obsah_it FROM wgs_natuzzi_aktuality WHERE id = :id");
        $stmt->execute(['id' => $aktualitaId]);
        $aktuality = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($aktuality)) {
            sendJsonError('Aktualita nebyla nalezena', 404);
        }
    }

    // Inicializovat překladač
    $translator = new WGSTranslator($pdo);

    $prelozenoCount = 0;
    $preskoceno = 0;
    $chyby = [];
    $maxPrekladu = 5; // Omezit počet překladů na jedno volání (Google rate limit)

    foreach ($aktuality as $aktualita) {
        // Limit počtu překladů
        if ($prelozenoCount >= $maxPrekladu) {
            break;
        }

        $id = $aktualita['id'];
        $obsahCz = $aktualita['obsah_cz'] ?? '';
        $obsahSloupec = 'obsah_' . $cilovyJazyk;
        $stavajiciPreklad = $aktualita[$obsahSloupec] ?? '';

        // Přeskočit pokud není český obsah
        if (empty(trim($obsahCz))) {
            $preskoceno++;
            continue;
        }

        // Přeskočit pokud překlad už existuje a není prázdný
        if (!empty(trim($stavajiciPreklad))) {
            $preskoceno++;
            continue;
        }

        try {
            // Přeložit obsah
            $prelozenoObsah = $translator->preloz($obsahCz, $cilovyJazyk, 'aktualita', $id);

            // Uložit do databáze
            $stmtUpdate = $pdo->prepare("
                UPDATE wgs_natuzzi_aktuality
                SET {$obsahSloupec} = :obsah,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                'obsah' => $prelozenoObsah,
                'id' => $id
            ]);

            $prelozenoCount++;

        } catch (Exception $e) {
            $chyby[] = "Aktualita #{$id}: " . $e->getMessage();
            error_log("Translation error for aktualita #{$id}: " . $e->getMessage());
        }
    }

    // Audit log
    error_log(sprintf(
        "ADMIN TRANSLATE AKTUALITY: User %s translated %d aktuality to %s | Skipped: %d | Errors: %d",
        $_SESSION['user_id'] ?? 'unknown',
        $prelozenoCount,
        strtoupper($cilovyJazyk),
        $preskoceno,
        count($chyby)
    ));

    $zprava = $prekladVsech
        ? sprintf('Přeloženo %d aktualit do %s', $prelozenoCount, strtoupper($cilovyJazyk))
        : sprintf('Aktualita přeložena do %s', strtoupper($cilovyJazyk));

    if ($prelozenoCount === 0 && $preskoceno > 0) {
        $zprava = sprintf('Všechny aktuality již byly přeloženy do %s', strtoupper($cilovyJazyk));
    }

    sendJsonSuccess($zprava, [
        'prelozeno' => $prelozenoCount,
        'preskoceno' => $preskoceno,
        'chyby' => count($chyby),
        'detail_chyb' => $chyby,
        'cilovy_jazyk' => $cilovyJazyk
    ]);

} catch (PDOException $e) {
    error_log("Database error in preloz_aktualitu.php: " . $e->getMessage());
    sendJsonError('Chyba databáze při překladu');
} catch (Exception $e) {
    error_log("Error in preloz_aktualitu.php: " . $e->getMessage());
    sendJsonError('Neočekávaná chyba při překladu');
}
?>
