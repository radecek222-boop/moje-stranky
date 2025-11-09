<?php
/**
 * Notification Sender
 * Posílá email a SMS notifikace zákazníkům a adminům
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

    // Příprava emailu podle typu notifikace
    $subject = '';
    $message = '';
    $to = '';

    switch ($notificationId) {
        case 'appointment_confirmed':
            $customerName = $notificationData['customer_name'] ?? 'Zákazník';
            $appointmentDate = $notificationData['appointment_date'] ?? 'neuvedeno';
            $appointmentTime = $notificationData['appointment_time'] ?? 'neuvedeno';
            $orderId = $notificationData['order_id'] ?? 'neuvedeno';

            $subject = "Potvrzení termínu návštěvy - WGS Servis";
            $message = "Dobrý den {$customerName},\n\n";
            $message .= "potvrzujeme termín návštěvy technika:\n\n";
            $message .= "Datum: {$appointmentDate}\n";
            $message .= "Čas: {$appointmentTime}\n";
            $message .= "Číslo zakázky: {$orderId}\n\n";
            $message .= "V případě jakýchkoli dotazů nás prosím kontaktujte.\n\n";
            $message .= "S pozdravem,\nWhite Glove Service\n";
            $message .= "Tel: +420 725 965 826\n";
            $message .= "Email: reklamace@wgs-service.cz";

            // Email zákazníka
            $to = $notificationData['customer_email'] ?? null;
            break;

        case 'order_reopened':
            $customerName = $notificationData['customer_name'] ?? 'Zákazník';
            $orderId = $notificationData['order_id'] ?? 'neuvedeno';
            $reopenedBy = $notificationData['reopened_by'] ?? 'admin';
            $reopenedAt = $notificationData['reopened_at'] ?? date('d.m.Y H:i');

            $subject = "Zakázka #{$orderId} byla znovu otevřena";
            $message = "Zákazník: {$customerName}\n";
            $message .= "Zakázka č.: {$orderId}\n\n";
            $message .= "Zakázka byla znovu otevřena uživatelem {$reopenedBy} dne {$reopenedAt}.\n\n";
            $message .= "Stav byl změněn na NOVÁ. Termín byl vymazán.\n";

            // Email pro adminy/techniky
            $to = 'reklamace@wgs-service.cz';
            break;

        default:
            throw new Exception('Neznámý typ notifikace: ' . $notificationId);
    }

    // Validace emailu
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        // Pokud není validní email, nepošleme (možná někdy email chybí)
        echo json_encode([
            'success' => true,
            'message' => 'Email nebyl odeslán (chybí validní adresa)',
            'sent' => false
        ]);
        exit;
    }

    // Příprava hlaviček
    $headers = "From: White Glove Service <reklamace@wgs-service.cz>\r\n";
    $headers .= "Reply-To: reklamace@wgs-service.cz\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Odeslání emailu
    $emailSent = mail($to, $subject, $message, $headers);

    if (!$emailSent) {
        throw new Exception('Nepodařilo se odeslat email');
    }

    // Úspěšná odpověď
    echo json_encode([
        'success' => true,
        'message' => 'Notifikace odeslána',
        'sent' => true,
        'to' => $to
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
