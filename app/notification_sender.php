<?php
/**
 * Notification Sender - Queue-Based Version
 * Posílá email a SMS notifikace zákazníkům a adminům
 *
 * ZMĚNA (2025-11-11): Přepsáno z hardcoded switch na databázové šablony
 * ZMĚNA (2025-11-14): Přepsáno na email queue pro asynchronní odeslání
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/EmailQueue.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Kontrola přihlášení
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Neautorizovaný přístup'
        ]);
        exit;
    }

    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // Načtení JSON dat PŘED CSRF kontrolou
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    // BEZPEČNOST: CSRF ochrana pro POST operace
    $csrfToken = $data['csrf_token'] ?? '';
    if (is_array($csrfToken)) {
        $csrfToken = '';
    }
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Neplatný CSRF token. Obnovte stránku a zkuste znovu.'
        ]);
        exit;
    }

    $notificationId = $data['notification_id'] ?? null;
    $notificationData = $data['data'] ?? [];

    if (!$notificationId) {
        throw new Exception('Chybí notification_id');
    }

    // BEZPEČNOST: notification_id musí být string, ne pole
    if (is_array($notificationId)) {
        $notificationId = $notificationId[0] ?? 'unknown';
    }
    $notificationId = (string)$notificationId;

    // ============================================
    // DATABÁZOVÉ PŘIPOJENÍ
    // ============================================
    $pdo = getDbConnection();

    // FIX 9: Databázový rate limiting - ochrana proti spamování
    $rateLimiter = new RateLimiter($pdo);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rateCheck = $rateLimiter->checkLimit(
        $ip,
        'notification',
        ['max_attempts' => 30, 'window_minutes' => 60, 'block_minutes' => 120]
    );

    if (!$rateCheck['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => $rateCheck['message']
        ]);
        exit;
    }

    // FIX 9: RateLimiter již zaznamenal pokus automaticky v checkLimit()

    // ============================================
    // NAČTENÍ ŠABLONY Z DATABÁZE
    // ============================================

    // Podpora pro oba formaty: ciselne ID nebo trigger_event string
    if (is_numeric($notificationId)) {
        // Hledani podle ID
        $stmt = $pdo->prepare("
            SELECT * FROM wgs_notifications
            WHERE id = :notification_id AND active = 1
            LIMIT 1
        ");
        $stmt->execute(['notification_id' => $notificationId]);
    } else {
        // Hledani podle trigger_event (napr. "appointment_confirmed")
        $recipientType = $notificationData['recipient_type'] ?? 'customer';
        $stmt = $pdo->prepare("
            SELECT * FROM wgs_notifications
            WHERE trigger_event = :trigger_event AND recipient_type = :recipient_type AND type = 'email' AND active = 1
            LIMIT 1
        ");
        $stmt->execute([
            'trigger_event' => $notificationId,
            'recipient_type' => $recipientType
        ]);
    }
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('Notifikace nenalezena nebo je neaktivní: ' . $notificationId);
    }

    // Dekódování JSON polí
    $ccEmails = !empty($notification['cc_emails']) ? json_decode($notification['cc_emails'], true) : [];
    $bccEmails = !empty($notification['bcc_emails']) ? json_decode($notification['bcc_emails'], true) : [];

    // ============================================
    // URČENÍ PŘÍJEMCŮ (role-based)
    // ============================================

    /**
     * Převede roli na email adresu
     * @param string $role Role (customer, admin, technician, seller)
     * @param array $data Data s emaily
     * @return string|null Email nebo null
     *
     * DULEZITE: Zadny fallback! Pokud email neni nastaven, vrati null.
     * Emaily se posilaji POUZE na adresy explicitne nastavene v sablone.
     */
    $resolveRoleToEmail = function($role, $data) use ($pdo) {
        switch ($role) {
            case 'customer':
                return $data['customer_email'] ?? null;

            case 'admin':
                // Načíst admin email z konfigurace - BEZ FALLBACKU
                $stmt = $pdo->prepare("SELECT config_value FROM wgs_system_config WHERE config_key = 'admin_email' LIMIT 1");
                $stmt->execute();
                $adminEmail = $stmt->fetchColumn();
                // Pokud neni nastaven, vratime null (zadny fallback!)
                return $adminEmail ?: null;

            case 'technician':
                return $data['technician_email'] ?? null;

            case 'seller':
                return $data['seller_email'] ?? null;

            default:
                return null;
        }
    };

    // TO příjemci - z to_recipients nebo fallback na recipient_type
    $toRecipients = [];
    $toRoles = !empty($notification['to_recipients'])
        ? json_decode($notification['to_recipients'], true)
        : [$notification['recipient_type']]; // Fallback pro zpětnou kompatibilitu

    if (is_array($toRoles)) {
        foreach ($toRoles as $role) {
            $email = $resolveRoleToEmail($role, $notificationData);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $toRecipients[] = $email;
            }
        }
    }

    // CC příjemci - z cc_recipients (role) + cc_emails (konkrétní adresy)
    $ccRecipients = [];
    $ccRoles = !empty($notification['cc_recipients'])
        ? json_decode($notification['cc_recipients'], true)
        : [];

    if (is_array($ccRoles)) {
        foreach ($ccRoles as $role) {
            $email = $resolveRoleToEmail($role, $notificationData);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $ccRecipients[] = $email;
            }
        }
    }

    // BCC příjemci - z bcc_recipients (role) + bcc_emails (konkrétní adresy)
    $bccRecipients = [];
    $bccRoles = !empty($notification['bcc_recipients'])
        ? json_decode($notification['bcc_recipients'], true)
        : [];

    if (is_array($bccRoles)) {
        foreach ($bccRoles as $role) {
            $email = $resolveRoleToEmail($role, $notificationData);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $bccRecipients[] = $email;
            }
        }
    }

    // Validace - musí být alespoň jeden TO příjemce
    if (empty($toRecipients)) {
        // Pokud není validní email, nepošleme
        echo json_encode([
            'success' => true,
            'message' => 'Email nebyl odeslán (chybí validní adresa příjemce)',
            'sent' => false,
            'notification_id' => $notificationId,
            'to_roles' => $toRoles
        ]);
        exit;
    }

    // Hlavní příjemce (první v seznamu)
    $to = $toRecipients[0];
    // Ostatní TO příjemci jdou do CC
    if (count($toRecipients) > 1) {
        $ccRecipients = array_merge(array_slice($toRecipients, 1), $ccRecipients);
    }

    // ============================================
    // NÁHRADA PROMĚNNÝCH V ŠABLONĚ
    // ============================================
    $subject = $notification['subject'] ?? 'Notifikace z WGS';
    $message = $notification['template'];

    // Mapování klíčů dat na template proměnné
    $variableMap = [
        '{{customer_name}}' => $notificationData['customer_name'] ?? 'Zákazník',
        '{{customer_email}}' => $notificationData['customer_email'] ?? '',
        '{{customer_phone}}' => $notificationData['customer_phone'] ?? '',
        '{{date}}' => $notificationData['appointment_date'] ?? $notificationData['date'] ?? '',
        '{{time}}' => $notificationData['appointment_time'] ?? $notificationData['time'] ?? '',
        '{{order_id}}' => $notificationData['order_id'] ?? '',
        '{{address}}' => $notificationData['address'] ?? '',
        '{{product}}' => $notificationData['product'] ?? '',
        '{{description}}' => $notificationData['description'] ?? '',
        '{{reopened_by}}' => $notificationData['reopened_by'] ?? '',
        '{{reopened_at}}' => $notificationData['reopened_at'] ?? '',
        '{{technician_name}}' => $notificationData['technician_name'] ?? '',
        '{{technician_email}}' => $notificationData['technician_email'] ?? '',
        '{{technician_phone}}' => $notificationData['technician_phone'] ?? '',
        '{{seller_name}}' => $notificationData['seller_name'] ?? '',
        '{{seller_email}}' => $notificationData['seller_email'] ?? '',
        '{{created_at}}' => $notificationData['created_at'] ?? date('d.m.Y H:i'),
        '{{completed_at}}' => $notificationData['completed_at'] ?? '',
        '{{company_email}}' => 'reklamace@wgs-service.cz',
        '{{company_phone}}' => '+420 777 888 999',
    ];

    // Replace variables in subject and message
    foreach ($variableMap as $variable => $value) {
        $subject = str_replace($variable, $value, $subject);
        $message = str_replace($variable, $value, $message);
    }

    // ============================================
    // SPOJENÍ ROLE-BASED A EXPLICITNÍCH CC/BCC
    // ============================================

    // Explicitní CC emaily (z cc_emails) - s podporou proměnných
    if (!empty($ccEmails) && is_array($ccEmails)) {
        $ccEmails = array_map(function($email) use ($variableMap) {
            foreach ($variableMap as $variable => $value) {
                $email = str_replace($variable, $value, $email);
            }
            return trim($email);
        }, $ccEmails);
        // Spojit s role-based CC
        $ccRecipients = array_merge($ccRecipients, $ccEmails);
    }

    // Explicitní BCC emaily (z bcc_emails) - s podporou proměnných
    if (!empty($bccEmails) && is_array($bccEmails)) {
        $bccEmails = array_map(function($email) use ($variableMap) {
            foreach ($variableMap as $variable => $value) {
                $email = str_replace($variable, $value, $email);
            }
            return trim($email);
        }, $bccEmails);
        // Spojit s role-based BCC
        $bccRecipients = array_merge($bccRecipients, $bccEmails);
    }

    // Filtrovat validní emaily a odstranit duplicity
    $finalCcEmails = array_unique(array_filter($ccRecipients, function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }));
    $finalBccEmails = array_unique(array_filter($bccRecipients, function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }));

    // ============================================
    // EMAIL QUEUE - ASYNCHRONNÍ ODESLÁNÍ
    // ============================================
    // Email se přidá do fronty a odešle se přes cron worker
    // Response je okamžitý (~100ms místo 15s)

    $emailQueue = new EmailQueue();

    // Přidat email do fronty
    $enqueued = $emailQueue->enqueue([
        'notification_id' => $notificationId,
        'to' => $to,
        'to_name' => $notificationData['customer_name'] ?? null,
        'subject' => $subject,
        'body' => $message,
        'cc' => array_values($finalCcEmails),
        'bcc' => array_values($finalBccEmails),
        'priority' => 'normal'
    ]);

    if (!$enqueued) {
        throw new Exception('Nepodařilo se přidat email do fronty');
    }

    // Logování
    error_log(sprintf(
        "✓ Email enqueued: %s -> %s (subject: %s)",
        $notificationId,
        $to,
        $subject
    ));

    // Úspěšná odpověď
    echo json_encode([
        'success' => true,
        'message' => 'Notifikace byla přidána do fronty',
        'sent' => true,
        'notification_id' => $notificationId,
        'to' => $to,
        'to_roles' => $toRoles,
        'cc' => array_values($finalCcEmails),
        'bcc_count' => count($finalBccEmails),
        'queued' => true
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
