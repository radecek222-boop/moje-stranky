<?php
/**
 * Notification Sender - Database-Driven Version
 * Posílá email a SMS notifikace zákazníkům a adminům
 *
 * ZMĚNA (2025-11-11): Přepsáno z hardcoded switch na databázové šablony
 */

require_once __DIR__ . '/../init.php';

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

    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    $notificationId = $data['notification_id'] ?? null;
    $notificationData = $data['data'] ?? [];

    if (!$notificationId) {
        throw new Exception('Chybí notification_id');
    }

    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimit = checkRateLimit("notification_$ip", 30, 3600); // 30 notifikací za hodinu

    if (!$rateLimit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Příliš mnoho notifikací. Zkuste to později.'
        ]);
        exit;
    }

    recordLoginAttempt("notification_$ip");

    // ============================================
    // NAČTENÍ ŠABLONY Z DATABÁZE
    // ============================================
    $pdo = getDbConnection();

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
        '{{seller_name}}' => $notificationData['seller_name'] ?? '',
        '{{created_at}}' => $notificationData['created_at'] ?? date('d.m.Y H:i'),
        '{{completed_at}}' => $notificationData['completed_at'] ?? '',
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

    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Odeslání emailu
    $emailSent = mail($to, $subject, $message, $headers);

    if (!$emailSent) {
        throw new Exception('Nepodařilo se odeslat email');
    }

    // Logování úspěšného odeslání
    error_log(sprintf(
        "Notification sent: %s -> %s (subject: %s)",
        $notificationId,
        $to,
        $subject
    ));

    // Úspěšná odpověď
    echo json_encode([
        'success' => true,
        'message' => 'Notifikace odeslána',
        'sent' => true,
        'notification_id' => $notificationId,
        'to' => $to,
        'cc' => $ccEmails ?? [],
        'bcc_count' => count($bccEmails ?? [])
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
