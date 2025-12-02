<?php
/**
 * Notification List HTML API (HTMX)
 * Vrací HTML fragment pro seznam notifikací
 *
 * Step 53: První HTMX endpoint pro server-driven UI
 */

require_once __DIR__ . '/../init.php';

// HTMX vrací HTML, ne JSON
header('Content-Type: text/html; charset=utf-8');

// Pomocná funkce pro escape HTML
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

try {
    // BEZPEČNOST: Pouze admin
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        http_response_code(401);
        echo '<div class="error-message">Neautorizovaný přístup. <a href="login.php">Přihlásit se</a></div>';
        exit;
    }

    // PERFORMANCE: Uvolnění session zámku
    session_write_close();

    $pdo = getDbConnection();

    // Kontrola existence tabulky
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM wgs_notifications LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        error_log("wgs_notifications table check failed: " . $e->getMessage());
    }

    if (!$tableExists) {
        echo '<div class="loading">Notifikační systém není inicializován</div>';
        exit;
    }

    // Načtení notifikací
    $stmt = $pdo->query("
        SELECT *
        FROM wgs_notifications
        ORDER BY id ASC
    ");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($notifications)) {
        echo '<div class="loading">Žádné notifikace k zobrazení</div>';
        exit;
    }

    // Renderování HTML
    foreach ($notifications as $notif):
        // Dekódovat JSON pole
        $variables = isset($notif['variables']) && $notif['variables'] ? json_decode($notif['variables'], true) : [];
        $ccEmails = isset($notif['cc_emails']) && $notif['cc_emails'] ? json_decode($notif['cc_emails'], true) : [];
        $bccEmails = isset($notif['bcc_emails']) && $notif['bcc_emails'] ? json_decode($notif['bcc_emails'], true) : [];
        $isActive = isset($notif['active']) ? (bool)$notif['active'] : false;

        // Názvy
        $name = $notif['name'] ?? $notif['type'] ?? $notif['subject'] ?? 'Notifikace #' . $notif['id'];
        $recipientTypes = [
            'customer' => 'Zákazník',
            'admin' => 'Admin',
            'technician' => 'Technik',
            'seller' => 'Prodejce'
        ];
        $recipientName = $recipientTypes[$notif['recipient_type'] ?? ''] ?? ($notif['recipient_type'] ?? 'Neznámý');

        $typeName = match($notif['type'] ?? '') {
            'both' => 'Email + SMS',
            'email' => 'Email',
            'sms' => 'SMS',
            default => $notif['type'] ?? 'Neznámý'
        };

        $description = $notif['description'] ?? 'Bez popisu';
        $triggerEvent = $notif['trigger_event'] ?? '';
        $subject = $notif['subject'] ?? '';
        $template = $notif['template'] ?? '';
?>
    <div class="notification-card">
        <div class="notification-header" onclick="toggleNotificationBody('<?= e($notif['id']) ?>')">
            <div class="notification-title">
                <span class="badge badge-<?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Aktivní' : 'Neaktivní' ?></span>
                <span><?= e($name) ?></span>
            </div>
            <div class="notification-toggle">
                <div class="toggle-switch <?= $isActive ? 'active' : '' ?>"
                     onclick="event.stopPropagation(); toggleNotification('<?= e($notif['id']) ?>')"></div>
            </div>
        </div>
        <div class="notification-body" id="notification-body-<?= e($notif['id']) ?>">
            <div class="notification-info">
                <div class="notification-info-label">Popis</div>
                <div class="notification-info-value"><?= e($description) ?></div>

                <div class="notification-info-label">Spouštěč</div>
                <div class="notification-info-value"><?= e($triggerEvent) ?></div>

                <div class="notification-info-label">Příjemce</div>
                <div class="notification-info-value"><?= e($recipientName) ?></div>

                <div class="notification-info-label">Typ</div>
                <div class="notification-info-value"><?= e($typeName) ?></div>
            </div>

            <?php if ($subject): ?>
            <div style="margin: 1rem 0;">
                <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; text-transform: uppercase;">Předmět emailu:</div>
                <div style="background: #f5f5f5; padding: 0.8rem; border: 1px solid #ddd;"><?= e($subject) ?></div>
            </div>
            <?php endif; ?>

            <div style="margin: 1rem 0;">
                <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; text-transform: uppercase;">Šablona zprávy:</div>
                <div class="template-preview"><?= nl2br(e($template)) ?></div>
            </div>

            <button class="btn btn-sm" onclick="openEditNotificationModal('<?= e($notif['id']) ?>')">Editovat šablonu</button>
        </div>
    </div>
<?php
    endforeach;

} catch (Exception $e) {
    http_response_code(500);
    error_log("notification_list_html.php error: " . $e->getMessage());
    echo '<div class="error-message">Chyba při načítání notifikací</div>';
}
?>
