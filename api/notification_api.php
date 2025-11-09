<?php
/**
 * Notification API
 * API pro správu emailových a SMS notifikací
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Pouze admin
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]);
        exit;
    }

    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // BEZPEČNOST: CSRF ochrana pro POST operace
    requireCSRF();

    // Načtení akce
    $action = $_GET['action'] ?? '';

    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    $pdo = getDbConnection();

    switch ($action) {
        case 'toggle':
            // Přepnutí aktivního stavu notifikace
            $notificationId = $data['notification_id'] ?? null;
            $active = isset($data['active']) ? (bool)$data['active'] : null;

            if (!$notificationId || $active === null) {
                throw new Exception('Chybí notification_id nebo active');
            }

            // BEZPEČNOST: Validace ID (pouze čísla)
            if (!is_numeric($notificationId)) {
                throw new Exception('Neplatné ID notifikace');
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_notifications
                SET active = :active, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':active' => $active ? 1 : 0,
                ':id' => $notificationId
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Stav notifikace změněn'
            ]);
            break;

        case 'update':
            // Aktualizace šablony notifikace
            $notificationId = $data['id'] ?? null;
            $recipient = $data['recipient'] ?? null;
            $subject = $data['subject'] ?? '';
            $template = $data['template'] ?? '';
            $ccEmails = $data['cc_emails'] ?? [];
            $bccEmails = $data['bcc_emails'] ?? [];

            if (!$notificationId) {
                throw new Exception('Chybí ID notifikace');
            }

            if (!$template) {
                throw new Exception('Šablona nesmí být prázdná');
            }

            // BEZPEČNOST: Validace ID
            if (!is_numeric($notificationId)) {
                throw new Exception('Neplatné ID notifikace');
            }

            // BEZPEČNOST: Validace recipient
            $allowedRecipients = ['customer', 'admin', 'technician', 'seller'];
            if ($recipient && !in_array($recipient, $allowedRecipients)) {
                throw new Exception('Neplatný typ příjemce');
            }

            // BEZPEČNOST: Validace emailů
            foreach ($ccEmails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Neplatný CC email: ' . $email);
                }
            }
            foreach ($bccEmails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Neplatný BCC email: ' . $email);
                }
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_notifications
                SET
                    recipient_type = :recipient_type,
                    subject = :subject,
                    template = :template,
                    cc_emails = :cc_emails,
                    bcc_emails = :bcc_emails,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':recipient_type' => $recipient,
                ':subject' => $subject,
                ':template' => $template,
                ':cc_emails' => json_encode($ccEmails),
                ':bcc_emails' => json_encode($bccEmails),
                ':id' => $notificationId
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Šablona notifikace aktualizována'
            ]);
            break;

        default:
            throw new Exception('Neplatná akce: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
