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

    public     /**
     *   construct
     *
     * @param mixed $pdo Pdo
     */
function __construct($pdo = null) {
        $this->pdo = $pdo ?? getDbConnection();
    }

    /**
     * Přidá email do fronty
     *
     * @param array $data Email data
     * @param bool $useTransaction Pokud true, obalí operaci v transakci (default: false pro zpětnou kompatibilitu)
     * @return bool Success
     */
    public     /**
     * Enqueue
     *
     * @param mixed $data Data
     * @param mixed $useTransaction UseTransaction
     */
function enqueue($data, $useTransaction = false) {
        try {
            // CRITICAL FIX: Volitelná transakce pro atomicitu při vkládání do fronty
            if ($useTransaction && !$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

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

            $result = $stmt->execute([
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

            if ($useTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return $result;

        } catch (PDOException $e) {
            if ($useTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Získá SMTP nastavení z databáze
     */
    private     /**
     * GetSMTPSettings
     */
function getSMTPSettings() {
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
     *
     * Automaticky vybírá metodu odeslání:
     * - PHPMailer pokud je dostupný (preferováno)
     * - PHP mail() jako fallback
     *
     * @param array $queueItem Email položka z fronty (musí obsahovat: recipient_email, subject, body)
     * @return array ['success' => bool, 'error' => string|null]
     * @throws Exception Při kritické chybě odeslání
     */
    public     /**
     * SendEmail
     *
     * @param mixed $queueItem QueueItem
     */
function sendEmail($queueItem) {
        $settings = $this->getSMTPSettings();

        // Použít PHPMailer pokud je dostupný
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->sendWithPHPMailer($queueItem, $settings);
        }

        // Fallback na PHP mail()
        return $this->sendWithPHPMail($queueItem, $settings);
    }

    /**
     * Odeslání emailu pomocí PHPMailer (SMTP)
     *
     * Podporuje:
     * - SMTP autentizaci
     * - TLS/SSL šifrování
     * - CC/BCC příjemce
     * - UTF-8 encoding
     * - Timeout 10s
     *
     * @param array $queueItem Email položka z fronty
     * @param array $settings SMTP konfigurace z wgs_system_config
     * @return array ['success' => bool, 'error' => string|null]
     */
    private     /**
     * SendWithPHPMailer
     *
     * @param mixed $queueItem QueueItem
     * @param mixed $settings Settings
     */
function sendWithPHPMailer($queueItem, $settings) {
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
                // BUGFIX: JSON error check
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON in cc_emails: ' . json_last_error_msg());
                }
                if (is_array($ccEmails)) {
                    foreach ($ccEmails as $cc) {
                        if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                            $mail->addCC($cc);
                        }
                    }
                }
            }

            // BCC
            if (!empty($queueItem['bcc_emails'])) {
                $bccEmails = json_decode($queueItem['bcc_emails'], true);
                // BUGFIX: JSON error check
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON in bcc_emails: ' . json_last_error_msg());
                }
                if (is_array($bccEmails)) {
                    foreach ($bccEmails as $bcc) {
                        if (filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                            $mail->addBCC($bcc);
                        }
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
    private     /**
     * SendWithPHPMail
     *
     * @param mixed $queueItem QueueItem
     * @param mixed $settings Settings
     */
function sendWithPHPMail($queueItem, $settings) {
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
     * Zpracuje frontu emailů (volá se z cron jobu)
     *
     * Zpracovává pending emaily z fronty s následující logikou:
     * - Priorita: vysoká -> nízká
     * - Pouze emaily s scheduled_at <= NOW()
     * - Pouze emaily s attempts < max_attempts
     * - Používá transakce pro atomicitu
     * - Retry mechanika při selhání
     *
     * @param int $limit Maximální počet emailů ke zpracování (default: 10)
     * @return array ['processed' => int, 'sent' => int, 'failed' => int]
     */
    public     /**
     * ProcessQueue
     *
     * @param mixed $limit Limit
     */
function processQueue($limit = 10) {
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

            // CRITICAL FIX: Email queue atomicity - transaction support pro DB operace
            try {
                // CRITICAL FIX: Začít transakci pro všechny DB operace
                $this->pdo->beginTransaction();

                // Označit jako "sending"
                $this->updateStatus($email['id'], 'sending');

                // CRITICAL FIX: COMMIT transakce před odesláním emailu
                // (nemůžeme rollbackovat skutečné odeslání emailu, jen DB operace)
                $this->pdo->commit();

                // Pokusit se odeslat email (mimo transakci - nelze rollbackovat)
                $result = $this->sendEmail($email);

                // CRITICAL FIX: Nová transakce pro update po odeslání
                $this->pdo->beginTransaction();

                if ($result['success']) {
                    // Úspěch
                    $this->updateStatus($email['id'], 'sent', null, date('Y-m-d H:i:s'));
                    $this->pdo->commit();
                    $results['sent']++;

                    error_log("✓ Email sent: {$email['id']} -> {$email['recipient_email']}");
                } else {
                    // Selhání
                    $attempts = $email['attempts'] + 1;
                    $status = ($attempts >= $email['max_attempts']) ? 'failed' : 'pending';

                    $this->updateStatus($email['id'], $status, $result['message']);
                    $this->incrementAttempts($email['id']);
                    $this->pdo->commit();

                    $results['failed']++;

                    error_log("✗ Email failed: {$email['id']} -> {$email['recipient_email']} ({$result['message']})");
                }
            } catch (\Exception $e) {
                // CRITICAL FIX: ROLLBACK transakce při chybě
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                // KRITICKÁ CHYBA: Vrátit email zpět na pending pro retry (nová transakce)
                try {
                    $this->pdo->beginTransaction();
                    $this->updateStatus($email['id'], 'pending', 'Exception: ' . $e->getMessage());
                    $this->incrementAttempts($email['id']);
                    $this->pdo->commit();
                } catch (\Exception $innerE) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    error_log("✗ Failed to update email status after exception: " . $innerE->getMessage());
                }

                $results['failed']++;

                error_log("✗ Email exception: {$email['id']} -> {$email['recipient_email']} ({$e->getMessage()})");
            }
        }

        return $results;
    }

    /**
     * Aktualizuje stav emailu
     */
    private     /**
     * UpdateStatus
     *
     * @param mixed $id Id
     * @param mixed $status Status
     * @param mixed $errorMessage ErrorMessage
     * @param mixed $sentAt SentAt
     */
function updateStatus($id, $status, $errorMessage = null, $sentAt = null) {
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
    private     /**
     * IncrementAttempts
     *
     * @param mixed $id Id
     */
function incrementAttempts($id) {
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
    public     /**
     * GetStats
     */
function getStats() {
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
    public     /**
     * GetQueue
     *
     * @param mixed $status Status
     * @param mixed $limit Limit
     * @param mixed $offset Offset
     */
function getQueue($status = null, $limit = 50, $offset = 0) {
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
    public     /**
     * Retry
     *
     * @param mixed $id Id
     */
function retry($id) {
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
    public     /**
     * Delete
     *
     * @param mixed $id Id
     */
function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM wgs_email_queue WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
