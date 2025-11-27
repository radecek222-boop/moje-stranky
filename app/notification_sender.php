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

    // ============================================
    // DATABÁZOVÉ PŘIPOJENÍ
    // ============================================
    $pdo = getDbConnection();

    // ✅ FIX 9: Databázový rate limiting - ochrana proti spamování
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

    // ✅ FIX 9: RateLimiter již zaznamenal pokus automaticky v checkLimit()

    // ============================================
    // NAČTENÍ ŠABLONY Z DATABÁZE
    // ============================================

    $stmt = $pdo->prepare("
        SELECT * FROM wgs_notifications
        WHERE id = :notification_id AND active = 1
        LIMIT 1
    ");
    $stmt->execute(['notification_id' => $notificationId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('Notifikace nenalezena nebo je neaktivní: ' . $notificationId);
    }

    // Dekódování JSON polí
    $ccEmails = !empty($notification['cc_emails']) ? json_decode($notification['cc_emails'], true) : [];
    $bccEmails = !empty($notification['bcc_emails']) ? json_decode($notification['bcc_emails'], true) : [];

    // ============================================
    // URČENÍ PŘÍJEMCE
    // ============================================
    $to = null;

    switch ($notification['recipient_type']) {
        case 'customer':
            $to = $notificationData['customer_email'] ?? null;
            break;

        case 'admin':
            $to = 'reklamace@wgs-service.cz';
            break;

        case 'technician':
            $to = $notificationData['technician_email'] ?? null;
            break;

        case 'seller':
            $to = $notificationData['seller_email'] ?? null;
            break;

        default:
            throw new Exception('Neznámý typ příjemce: ' . $notification['recipient_type']);
    }

    // Validace emailu
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        // Pokud není validní email, nepošleme (možná někdy email chybí)
        echo json_encode([
            'success' => true,
            'message' => 'Email nebyl odeslán (chybí validní adresa příjemce)',
            'sent' => false,
            'notification_id' => $notificationId,
            'recipient_type' => $notification['recipient_type']
        ]);
        exit;
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
    // PŘÍPRAVA A ODESLÁNÍ EMAILU
    // ============================================
    $headers = "From: White Glove Service <reklamace@wgs-service.cz>\r\n";
    $headers .= "Reply-To: reklamace@wgs-service.cz\r\n";

    // Přidání CC emailů
    if (!empty($ccEmails) && is_array($ccEmails)) {
        $validCcEmails = array_filter($ccEmails, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        if (!empty($validCcEmails)) {
            $headers .= "Cc: " . implode(', ', $validCcEmails) . "\r\n";
        }
    }

    // Přidání BCC emailů
    if (!empty($bccEmails) && is_array($bccEmails)) {
        $validBccEmails = array_filter($bccEmails, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        if (!empty($validBccEmails)) {
            $headers .= "Bcc: " . implode(', ', $validBccEmails) . "\r\n";
        }
    }

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
        'cc' => $ccEmails ?? [],
        'bcc' => $bccEmails ?? [],
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
        'cc' => $ccEmails ?? [],
        'bcc_count' => count($bccEmails ?? []),
        'queued' => true
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
