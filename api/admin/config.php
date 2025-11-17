<?php
/**
 * Admin API - Config Module
 * Zpracování systémové konfigurace a SMTP nastavení
 * Extrahováno z control_center_api.php
 */

// Tento soubor je načítán přes api/admin.php router
// Proměnné $pdo, $data, $action jsou již k dispozici

switch ($action) {
    case 'get_system_config':
        $group = $_GET['group'] ?? null;

        $query = "SELECT * FROM wgs_system_config";
        if ($group) {
            $query .= " WHERE config_group = :group";
        }
        $query .= " ORDER BY config_group, config_key";

        $stmt = $pdo->prepare($query);
        if ($group) {
            $stmt->execute(['group' => $group]);
        } else {
            $stmt->execute();
        }

        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mask sensitive values
        foreach ($configs as &$config) {
            if ($config['is_sensitive']) {
                $value = $config['config_value'];
                if (strlen($value) > 8) {
                    $config['config_value_masked'] = substr($value, 0, 4) . '••••••••' . substr($value, -4);
                } else {
                    $config['config_value_masked'] = '••••••••';
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'data' => $configs
        ]);
        break;

    case 'save_system_config':
        $key = $data['key'] ?? null;
        $value = $data['value'] ?? '';

        if (!$key) {
            throw new Exception('Config key required');
        }

        $stmt = $pdo->prepare("
            UPDATE wgs_system_config
            SET config_value = :value,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :user_id
            WHERE config_key = :key
        ");

        $stmt->execute([
            'key' => $key,
            'value' => $value,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Config saved'
        ]);
        break;

    case 'get_smtp_config':
        $stmt = $pdo->prepare("
            SELECT config_key, config_value, is_sensitive
            FROM wgs_system_config
            WHERE config_group = 'email'
            ORDER BY config_key
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $smtpConfig = [];
        foreach ($rows as $row) {
            if ($row['is_sensitive'] && !empty($row['config_value'])) {
                $smtpConfig[$row['config_key']] = '••••••••';
            } else {
                $smtpConfig[$row['config_key']] = $row['config_value'];
            }
        }

        echo json_encode([
            'status' => 'success',
            'data' => $smtpConfig
        ]);
        break;

    case 'save_smtp_config':
        $smtpHost = $data['smtp_host'] ?? '';
        $smtpPort = $data['smtp_port'] ?? '587';
        $smtpUsername = $data['smtp_username'] ?? '';
        $smtpPassword = $data['smtp_password'] ?? '';
        $smtpEncryption = $data['smtp_encryption'] ?? 'tls';
        $smtpFrom = $data['smtp_from'] ?? 'reklamace@wgs-service.cz';
        $smtpFromName = $data['smtp_from_name'] ?? 'White Glove Service';

        // Pokud je password placeholder, necháme původní hodnotu
        if ($smtpPassword === '••••••••') {
            $smtpPassword = null;
        }

        $userId = $_SESSION['user_id'] ?? null;

        $configs = [
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_username' => $smtpUsername,
            'smtp_encryption' => $smtpEncryption,
            'smtp_from' => $smtpFrom,
            'smtp_from_name' => $smtpFromName
        ];

        if ($smtpPassword !== null) {
            $configs['smtp_password'] = $smtpPassword;
        }

        $stmt = $pdo->prepare("
            UPDATE wgs_system_config
            SET config_value = :value,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :user_id
            WHERE config_key = :key
        ");

        foreach ($configs as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => $value,
                'user_id' => $userId
            ]);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'SMTP konfigurace uložena'
        ]);
        break;

    case 'test_smtp_connection':
        $stmt = $pdo->prepare("
            SELECT config_value
            FROM wgs_system_config
            WHERE config_group = 'email' AND config_key = 'smtp_from'
            LIMIT 1
        ");
        $stmt->execute();
        $fromEmail = $stmt->fetchColumn();

        if (!$fromEmail) {
            $fromEmail = 'reklamace@wgs-service.cz';
        }

        $adminEmail = $_SESSION['user_email'] ?? 'reklamace@wgs-service.cz';

        $subject = 'WGS Admin Control Center - Test Email';
        $message = "Tento email byl odeslán jako test emailového systému.\n\n";
        $message .= "Čas odeslání: " . date('d.m.Y H:i:s') . "\n";
        $message .= "Odesláno z: Admin Control Center\n";
        $message .= "Server: " . ($_SERVER['SERVER_NAME'] ?? 'neznámý') . "\n\n";
        $message .= "Pokud vidíte tento email, emailový systém funguje správně.\n\n";
        $message .= "---\n";
        $message .= "White Glove Service\n";
        $message .= "https://wgs-service.cz";

        $headers = "From: White Glove Service <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: WGS Admin Control Center";

        $oldErrorHandler = set_error_handler(function() { return true; });
        $emailSent = mail($adminEmail, $subject, $message, $headers);
        restore_error_handler();

        if (!$emailSent) {
            throw new Exception('Nepodařilo se odeslat testovací email');
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Testovací email byl úspěšně odeslán na $adminEmail"
        ]);
        break;

    case 'send_test_email':
        $email = $data['email'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid email required');
        }

        // Rate limiting
        require_once __DIR__ . '/../../includes/rate_limiter.php';
        $rateLimiter = new RateLimiter($pdo);
        $identifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        $limitCheck = $rateLimiter->checkLimit($identifier, 'test_email', [
            'max_attempts' => 5,
            'window_minutes' => 10,
            'block_minutes' => 30
        ]);

        if (!$limitCheck['allowed']) {
            http_response_code(429);
            throw new Exception($limitCheck['message']);
        }

        $subject = 'WGS Control Center - Test Email';
        $message = "Hello!\n\nThis is a test email from WGS Control Center.\n\nTimestamp: " . date('Y-m-d H:i:s');
        $headers = "From: White Glove Service <reklamace@wgs-service.cz>\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $sent = mail($email, $subject, $message, $headers);

        if (!$sent) {
            throw new Exception('Failed to send email');
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Test email sent to ' . $email
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Unknown config action: {$action}"
        ]);
}
