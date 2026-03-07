<?php
/**
 * poznamky_html.php - HTMX HTML endpoint pro poznámky (Step 141)
 *
 * Vrací HTML fragment pro seznam poznámek.
 * Volání: hx-get="/api/poznamky_html.php?reklamace_id=WGS-..." hx-target="#poznamky-obsah"
 *
 * Výhody oproti JSON API:
 * - Server generuje HTML → méně JavaScriptu
 * - HTMX swap = automatická aktualizace DOM bez rerenderování celé stránky
 * - Snadná rozšiřitelnost (přidání pole = změna PHP šablony)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/reklamace_id_validator.php';

header('Content-Type: text/html; charset=utf-8');
// Zakázat cachování — poznámky se mění v reálném čase
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// BEZPEČNOST: Kontrola přihlášení
$jePrihlasen = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
if (!$jePrihlasen) {
    http_response_code(401);
    echo '<div class="notes-chyba">Přihlášení vyžadováno.</div>';
    exit;
}

$aktualniEmail = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? null;
$jeAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Uvolnit session lock pro paralelní zpracování
session_write_close();

// Vstupní validace — whitelist znaky, max délka 120
try {
    $reklamaceId = sanitizeReklamaceId($_GET['reklamace_id'] ?? null, 'reklamace_id');
} catch (Exception $e) {
    http_response_code(400);
    echo '<div class="notes-chyba">Neplatné nebo chybějící ID reklamace.</div>';
    exit;
}

try {
    $pdo = getDbConnection();

    // Načíst poznámky
    $stmt = $pdo->prepare("
        SELECT
            n.id,
            n.reklamace_id,
            n.text,
            n.created_at        AS timestamp,
            n.author_email      AS author,
            u.jmeno             AS author_name,
            n.is_read           AS is_read,
            n.has_audio,
            CASE
                WHEN n.has_audio = 1 AND n.audio_path IS NOT NULL
                THEN CONCAT('/uploads/audio/', n.audio_path)
                ELSE NULL
            END AS audio_url
        FROM wgs_notes n
        LEFT JOIN wgs_users u ON u.email = n.author_email
        WHERE n.reklamace_id = :reklamace_id
        ORDER BY n.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([':reklamace_id' => $reklamaceId]);
    $poznamky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Označit jako přečtené (pro aktuálního uživatele)
    if ($aktualniEmail) {
        $stmtRead = $pdo->prepare("
            UPDATE wgs_notes
            SET is_read = 1
            WHERE reklamace_id = :reklamace_id
              AND author_email != :email
              AND is_read = 0
        ");
        $stmtRead->execute([
            ':reklamace_id' => $reklamaceId,
            ':email' => $aktualniEmail,
        ]);
    }

} catch (Exception $e) {
    error_log('poznamky_html.php error: ' . $e->getMessage());
    http_response_code(500);
    echo '<div class="notes-chyba">Chyba při načítání poznámek.</div>';
    exit;
}

// Pomocné funkce pro formátování
function formatujCas(string $isoString): string
{
    $datum = new DateTime($isoString);
    $nyni  = new DateTime();
    $rozdil = $nyni->getTimestamp() - $datum->getTimestamp();

    if ($rozdil < 60) return 'Právě teď';
    if ($rozdil < 3600) return 'Před ' . floor($rozdil / 60) . ' min';
    if ($rozdil < 86400) return 'Před ' . floor($rozdil / 3600) . ' h';

    $dny = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'];
    return $dny[(int)$datum->format('w')] . ' ' . $datum->format('j.n.Y H:i');
}

function escHtml(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

?>
<div class="notes-container" id="notes-container-<?php echo escHtml($reklamaceId); ?>">

<?php if (empty($poznamky)): ?>
    <div class="empty-notes">Zatím žádné poznámky</div>

<?php else: ?>
    <?php foreach ($poznamky as $p): ?>
    <?php
        $muzemazat = $jeAdmin || ($aktualniEmail && $p['author'] === $aktualniEmail);
        $maAudio   = !empty($p['has_audio']) && !empty($p['audio_url']);
        $jeHlas    = ($p['text'] === '[Hlasová poznámka]' || $p['text'] === '[Hlasova poznamka]');
        $precteno  = !empty($p['is_read']);
    ?>
    <div class="note-item <?php echo $precteno ? '' : 'unread'; ?> <?php echo $maAudio ? 'has-audio' : ''; ?>"
         data-note-id="<?php echo (int)$p['id']; ?>">

        <div class="note-header">
            <span class="note-author"><?php echo escHtml($p['author_name'] ?: $p['author']); ?></span>
            <span class="note-time"><?php echo formatujCas($p['timestamp']); ?></span>

            <?php if ($muzemazat): ?>
            <button class="note-delete-btn"
                    data-note-id="<?php echo (int)$p['id']; ?>"
                    data-order-id="<?php echo escHtml($reklamaceId); ?>"
                    onclick="event.stopPropagation(); potvrditSmazaniPoznamky(this);"
                    title="Smazat poznámku">x</button>
            <?php endif; ?>
        </div>

        <?php if (!$jeHlas): ?>
        <div class="note-text"><?php echo escHtml($p['text']); ?></div>
        <?php endif; ?>

        <?php if ($maAudio): ?>
        <div class="note-audio">
            <audio controls preload="metadata" class="note-audio-player">
                <source src="<?php echo escHtml($p['audio_url']); ?>" type="audio/mp4">
                <source src="<?php echo escHtml($p['audio_url']); ?>" type="audio/webm">
                <source src="<?php echo escHtml($p['audio_url']); ?>" type="audio/mpeg">
                Váš prohlížeč nepodporuje přehrávání audia.
            </audio>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>
<!-- HTMX endpoint: poznamky_html.php | reklamace_id=<?php echo escHtml($reklamaceId); ?> -->
