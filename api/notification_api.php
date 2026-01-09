<?php
/**
 * Notification API
 * API pro správu emailových a SMS notifikací
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

try {
    // Health check ping - bez autentizace
    $action = $_GET['action'] ?? '';
    if ($action === 'ping') {
        echo json_encode(['status' => 'ok', 'api' => 'notification', 'timestamp' => time()]);
        exit;
    }

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

    $pdo = getDbConnection();

    // GET akce - pouze pro cteni (bez CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        switch ($action) {
            case 'get':
                // Nacteni jedne notifikace podle ID (muze byt cislo i retezec)
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('Chybi ID notifikace');
                }
                // Sanitizace - povoleny jen alfanumericke znaky a podtrzitko
                $id = preg_replace('/[^a-zA-Z0-9_]/', '', $id);

                $stmt = $pdo->prepare("
                    SELECT id, name, description, trigger_event, recipient_type,
                           type, subject, template, active,
                           to_recipients, cc_recipients, bcc_recipients,
                           cc_emails, bcc_emails, recipients,
                           created_at, updated_at
                    FROM wgs_notifications
                    WHERE id = :id
                    LIMIT 1
                ");
                $stmt->execute([':id' => $id]);
                $notification = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$notification) {
                    throw new Exception('Notifikace nenalezena');
                }

                // Dekodovat JSON pole - role-based příjemci
                $notification['to_recipients'] = json_decode($notification['to_recipients'] ?? '[]', true) ?: [];
                $notification['cc_recipients'] = json_decode($notification['cc_recipients'] ?? '[]', true) ?: [];
                $notification['bcc_recipients'] = json_decode($notification['bcc_recipients'] ?? '[]', true) ?: [];
                // Dekodovat JSON pole - explicitní emaily
                $notification['cc_emails'] = json_decode($notification['cc_emails'] ?? '[]', true) ?: [];
                $notification['bcc_emails'] = json_decode($notification['bcc_emails'] ?? '[]', true) ?: [];
                // Dekodovat JSON pole - struktura příjemců (customer, seller, technician, importer, other)
                $notification['recipients'] = json_decode($notification['recipients'] ?? 'null', true);

                echo json_encode([
                    'status' => 'success',
                    'notification' => $notification
                ]);
                exit;

            case 'list':
                // Seznam vsech notifikaci
                $type = $_GET['type'] ?? null;
                $whereClause = '';
                $params = [];

                if ($type && in_array($type, ['email', 'sms'])) {
                    $whereClause = 'WHERE type = :type';
                    $params[':type'] = $type;
                }

                $stmt = $pdo->prepare("
                    SELECT id, name, description, trigger_event, recipient_type,
                           to_recipients, cc_recipients, bcc_recipients,
                           type, subject, template, active, created_at, updated_at
                    FROM wgs_notifications
                    $whereClause
                    ORDER BY name ASC
                ");
                $stmt->execute($params);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Dekodovat JSON pole pro všechny notifikace
                foreach ($notifications as &$n) {
                    $n['to_recipients'] = json_decode($n['to_recipients'] ?? '[]', true) ?: [];
                    $n['cc_recipients'] = json_decode($n['cc_recipients'] ?? '[]', true) ?: [];
                    $n['bcc_recipients'] = json_decode($n['bcc_recipients'] ?? '[]', true) ?: [];
                }

                echo json_encode([
                    'status' => 'success',
                    'notifications' => $notifications
                ]);
                exit;

            default:
                throw new Exception('Neplatna GET akce: ' . $action);
        }
    }

    // POST akce - vyzaduji CSRF
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze GET nebo POST metoda');
    }

    // Načtení JSON dat PŘED CSRF kontrolou
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    // BEZPEČNOST: CSRF ochrana pro POST operace
    $csrfToken = $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    // SECURITY: Ensure CSRF token is a string, not an array
    if (is_array($csrfToken)) {
        $csrfToken = '';
    }
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Neplatný CSRF token. Obnovte stránku a zkuste znovu.'
        ]);
        exit;
    }

    // PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
    session_write_close();

    switch ($action) {
        case 'toggle':
            // Přepnutí aktivního stavu notifikace
            $notificationId = $data['notification_id'] ?? null;
            $active = isset($data['active']) ? (bool)$data['active'] : null;

            if (!$notificationId || $active === null) {
                throw new Exception('Chybí notification_id nebo active');
            }

            // BEZPEČNOST: Sanitizace ID (alfanumericke + podtrzitko)
            $notificationId = preg_replace('/[^a-zA-Z0-9_]/', '', $notificationId);

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
            $subject = $data['subject'] ?? '';
            $template = $data['template'] ?? '';

            // Role-based příjemci (pole rolí: customer, admin, technician, seller)
            $toRecipients = $data['to_recipients'] ?? [];
            $ccRecipients = $data['cc_recipients'] ?? [];
            $bccRecipients = $data['bcc_recipients'] ?? [];

            // Explicitní emaily (volitelné)
            $ccEmails = $data['cc_emails'] ?? [];
            $bccEmails = $data['bcc_emails'] ?? [];

            if (!$notificationId) {
                throw new Exception('Chybí ID notifikace');
            }

            if (!$template) {
                throw new Exception('Šablona nesmí být prázdná');
            }

            // BEZPEČNOST: Sanitizace ID (alfanumericke + podtrzitko)
            $notificationId = preg_replace('/[^a-zA-Z0-9_]/', '', $notificationId);

            // BEZPEČNOST: Validace rolí
            $allowedRoles = ['customer', 'admin', 'technician', 'seller'];
            foreach ($toRecipients as $role) {
                if (!in_array($role, $allowedRoles)) {
                    throw new Exception('Neplatná role v TO: ' . $role);
                }
            }
            foreach ($ccRecipients as $role) {
                if (!in_array($role, $allowedRoles)) {
                    throw new Exception('Neplatná role v CC: ' . $role);
                }
            }
            foreach ($bccRecipients as $role) {
                if (!in_array($role, $allowedRoles)) {
                    throw new Exception('Neplatná role v BCC: ' . $role);
                }
            }

            // BEZPEČNOST: Validace explicitních emailů
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

            // Určit recipient_type pro zpětnou kompatibilitu (první TO role)
            $recipientType = !empty($toRecipients) ? $toRecipients[0] : 'customer';

            $stmt = $pdo->prepare("
                UPDATE wgs_notifications
                SET
                    recipient_type = :recipient_type,
                    to_recipients = :to_recipients,
                    cc_recipients = :cc_recipients,
                    bcc_recipients = :bcc_recipients,
                    subject = :subject,
                    template = :template,
                    cc_emails = :cc_emails,
                    bcc_emails = :bcc_emails,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':recipient_type' => $recipientType,
                ':to_recipients' => json_encode($toRecipients),
                ':cc_recipients' => json_encode($ccRecipients),
                ':bcc_recipients' => json_encode($bccRecipients),
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
