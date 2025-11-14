<?php
/**
 * Email Queue Manager
 * Spravuje frontu emailů pro asynchronní odeslání
 */

// Pokusit se načíst PHPMailer (pokud existuje)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class EmailQueue {
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? getDbConnection();
    }

    /**
     * Přidá email do fronty
     */
    public function enqueue($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_email_queue (
                notification_id,
                recipient_email,
                recipient_name,
                subject,
                body,
                cc_emails,
                bcc_emails,
                priority,
                scheduled_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $ccJson = !empty($data['cc']) ? json_encode($data['cc']) : null;
        $bccJson = !empty($data['bcc']) ? json_encode($data['bcc']) : null;
        $priority = $data['priority'] ?? 'normal';
        $scheduledAt = $data['scheduled_at'] ?? date('Y-m-d H:i:s');

        return $stmt->execute([
            $data['notification_id'] ?? 'custom',
            $data['to'],
            $data['to_name'] ?? null,
            $data['subject'],
            $data['body'],
            $ccJson,
            $bccJson,
            $priority,
            $scheduledAt
        ]);
    }

    /**
     * Získá SMTP nastavení z databáze
     */
    private function getSMTPSettings() {
        $stmt = $this->pdo->query("
            SELECT * FROM wgs_smtp_settings
            WHERE is_active = 1
            ORDER BY id DESC
            LIMIT 1
        ");

        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback na .env pokud není v DB
        if (!$settings) {
            return [
                'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
                'smtp_port' => getenv('SMTP_PORT') ?: 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => getenv('SMTP_USER') ?: '',
                'smtp_password' => getenv('SMTP_PASS') ?: '',
                'smtp_from_email' => getenv('SMTP_FROM') ?: 'noreply@wgs-service.cz',
                'smtp_from_name' => 'White Glove Service'
            ];
        }

        return $settings;
    }

    /**
     * Odešle jeden email z fronty pomocí PHPMailer nebo PHP mail()
     */
    public function sendEmail($queueItem) {
        $settings = $this->getSMTPSettings();

        // Použít PHPMailer pokud je dostupný
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->sendWithPHPMailer($queueItem, $settings);
        }

        // Fallback na PHP mail()
        return $this->sendWithPHPMail($queueItem, $settings);
    }

    /**
     * Odeslání pomocí PHPMailer
     */
    private function sendWithPHPMailer($queueItem, $settings) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_username'];
            $mail->Password = $settings['smtp_password'];

            // Encryption
            if ($settings['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($settings['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = $settings['smtp_port'];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 10;

            // Recipients
            $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
            $mail->addAddress($queueItem['recipient_email'], $queueItem['recipient_name'] ?? '');

            // CC
            if (!empty($queueItem['cc_emails'])) {
                $ccEmails = json_decode($queueItem['cc_emails'], true);
                foreach ($ccEmails as $cc) {
                    if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($cc);
                    }
                }
            }

            // BCC
            if (!empty($queueItem['bcc_emails'])) {
                $bccEmails = json_decode($queueItem['bcc_emails'], true);
                foreach ($bccEmails as $bcc) {
                    if (filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                        $mail->addBCC($bcc);
                    }
                }
            }

            // Content
            $mail->isHTML(false);
            $mail->Subject = $queueItem['subject'];
            $mail->Body = $queueItem['body'];

            $mail->send();

            return [
                'success' => true,
                'message' => 'Email sent via PHPMailer'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $mail->ErrorInfo
            ];
        }
    }

    /**
     * Fallback - odeslání pomocí PHP mail()
     */
    private function sendWithPHPMail($queueItem, $settings) {
        $to = $queueItem['recipient_email'];
        $subject = $queueItem['subject'];
        $message = $queueItem['body'];

        $headers = "From: {$settings['smtp_from_name']} <{$settings['smtp_from_email']}>\r\n";
        $headers .= "Reply-To: {$settings['smtp_from_email']}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: WGS Email Queue (PHP mail fallback)\r\n";

        // CC
        if (!empty($queueItem['cc_emails'])) {
            $ccEmails = json_decode($queueItem['cc_emails'], true);
            $validCC = array_filter($ccEmails, function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
            if (!empty($validCC)) {
                $headers .= "Cc: " . implode(', ', $validCC) . "\r\n";
            }
        }

        // BCC
        if (!empty($queueItem['bcc_emails'])) {
            $bccEmails = json_decode($queueItem['bcc_emails'], true);
            $validBCC = array_filter($bccEmails, function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
            if (!empty($validBCC)) {
                $headers .= "Bcc: " . implode(', ', $validBCC) . "\r\n";
            }
        }

        $success = @mail($to, $subject, $message, $headers);

        if ($success) {
            return [
                'success' => true,
                'message' => 'Email sent via PHP mail() fallback'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'PHP mail() failed'
            ];
        }
    }

    /**
     * Zpracuje frontu (volá se z cron jobu)
     */
    public function processQueue($limit = 10) {
        // Získat pending emaily
        $stmt = $this->pdo->prepare("
            SELECT * FROM wgs_email_queue
            WHERE status = 'pending'
              AND scheduled_at <= NOW()
              AND attempts < max_attempts
            ORDER BY priority DESC, created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0
        ];

        foreach ($emails as $email) {
            $results['processed']++;

            // Označit jako "sending"
            $this->updateStatus($email['id'], 'sending');

            // Pokusit se odeslat
            $result = $this->sendEmail($email);

            if ($result['success']) {
                // Úspěch
                $this->updateStatus($email['id'], 'sent', null, date('Y-m-d H:i:s'));
                $results['sent']++;

                error_log("✓ Email sent: {$email['id']} -> {$email['recipient_email']}");
            } else {
                // Selhání
                $attempts = $email['attempts'] + 1;
                $status = ($attempts >= $email['max_attempts']) ? 'failed' : 'pending';

                $this->updateStatus($email['id'], $status, $result['message']);
                $this->incrementAttempts($email['id']);

                $results['failed']++;

                error_log("✗ Email failed: {$email['id']} -> {$email['recipient_email']} ({$result['message']})");
            }
        }

        return $results;
    }

    /**
     * Aktualizuje stav emailu
     */
    private function updateStatus($id, $status, $errorMessage = null, $sentAt = null) {
        $stmt = $this->pdo->prepare("
            UPDATE wgs_email_queue
            SET status = ?, error_message = ?, sent_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $errorMessage, $sentAt, $id]);
    }

    /**
     * Zvýší počet pokusů
     */
    private function incrementAttempts($id) {
        $stmt = $this->pdo->prepare("
            UPDATE wgs_email_queue
            SET attempts = attempts + 1
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    /**
     * Získá statistiky fronty
     */
    public function getStats() {
        $stmt = $this->pdo->query("
            SELECT
                status,
                COUNT(*) as count
            FROM wgs_email_queue
            GROUP BY status
        ");

        $stats = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row['status']] = (int)$row['count'];
        }

        return $stats;
    }

    /**
     * Získá seznam emailů z fronty
     */
    public function getQueue($status = null, $limit = 50, $offset = 0) {
        if ($status) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM wgs_email_queue
                WHERE status = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$status, $limit, $offset]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM wgs_email_queue
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Přeodešle selhavší email
     */
    public function retry($id) {
        $stmt = $this->pdo->prepare("
            UPDATE wgs_email_queue
            SET status = 'pending', attempts = 0, error_message = NULL
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Smaže email z fronty
     */
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM wgs_email_queue WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
