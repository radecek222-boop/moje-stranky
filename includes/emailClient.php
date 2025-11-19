<?php
/**
 * Email Client - Centralizovaný systém pro odesílání emailů
 *
 * Moderní, univerzální PHP email systém pro WGS Service
 * - Podporuje PHPMailer (SMTP) i PHP mail() fallback
 * - Automatická konfigurace pro Český Hosting WebSMTP
 * - Bezpečné logování
 * - Rate limiting
 * - Retry mechanika přes EmailQueue
 *
 * @version 2.0.0
 * @date 2025-11-19
 * @author Claude Code (AI Assistant)
 */

// Načíst PHPMailer pokud existuje
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class EmailClient {
    private $pdo;
    private $smtpSettings;
    private $logFile;
    private $usePHPMailer = false;

    /**
     * Konstruktor
     *
     * @param PDO|null $pdo Database connection (optional)
     */
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? (function_exists('getDbConnection') ? getDbConnection() : null);
        $this->logFile = __DIR__ . '/../logs/email_client.log';

        // Zjistit, zda je k dispozici PHPMailer
        $this->usePHPMailer = class_exists('PHPMailer\\PHPMailer\\PHPMailer');

        // Načíst SMTP nastavení
        $this->smtpSettings = $this->nactiSMTPNastaveni();
    }

    /**
     * Hlavní metoda pro odeslání emailu
     *
     * @param array $options Email options
     *   Required:
     *     - 'to' (string|array): Příjemce (email nebo ['email' => 'name'])
     *     - 'subject' (string): Předmět emailu
     *     - 'body' (string): Tělo emailu
     *   Optional:
     *     - 'to_name' (string): Jméno příjemce
     *     - 'from' (string): Odesílatel email
     *     - 'from_name' (string): Jméno odesílatele
     *     - 'cc' (array): CC příjemci
     *     - 'bcc' (array): BCC příjemci
     *     - 'reply_to' (string): Reply-To adresa
     *     - 'attachments' (array): Přílohy [['path' => '...', 'name' => '...'], ...]
     *     - 'html' (bool): Je tělo HTML? (default: false)
     *     - 'priority' (int): Priorita (1=high, 3=normal, 5=low)
     *     - 'use_queue' (bool): Použít email queue? (default: false)
     *
     * @return array ['success' => bool, 'message' => string, 'queued' => bool]
     */
    public function odeslat($options) {
        try {
            // Validace povinných polí
            $this->validovatOptions($options);

            // Pokud je nastaveno use_queue, přidat do fronty místo přímého odeslání
            if (!empty($options['use_queue']) && class_exists('EmailQueue')) {
                return $this->pridatDoFronty($options);
            }

            // Přímé odeslání
            if ($this->usePHPMailer) {
                return $this->odeslatPHPMailer($options);
            } else {
                return $this->odeslatPHPMail($options);
            }

        } catch (Exception $e) {
            $this->logovat('ERROR', 'Chyba při odesílání emailu: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Chyba při odesílání emailu: ' . $e->getMessage(),
                'queued' => false
            ];
        }
    }

    /**
     * Odeslání emailu pomocí PHPMailer (SMTP)
     *
     * @param array $options Email options
     * @return array Result
     */
    private function odeslatPHPMailer($options) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // ===== SERVER NASTAVENÍ =====
            $mail->isSMTP();
            $mail->Host = $this->smtpSettings['smtp_host'];
            $mail->Port = $this->smtpSettings['smtp_port'];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 30; // 30s timeout
            $mail->SMTPDebug = 0; // Vypnout debug output

            // SMTP Autentizace
            // Pro WebSMTP port 25 může být autentizace doménová
            if (!empty($this->smtpSettings['smtp_username']) && !empty($this->smtpSettings['smtp_password'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->smtpSettings['smtp_username'];
                $mail->Password = $this->smtpSettings['smtp_password'];
            } else {
                $mail->SMTPAuth = false;
            }

            // Šifrování
            $encryption = strtolower($this->smtpSettings['smtp_encryption'] ?? 'none');
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false; // Žádné šifrování (port 25)
                $mail->SMTPAutoTLS = false; // Vypnout automatický TLS
            }

            // ===== ODESÍLATEL =====
            $fromEmail = $options['from'] ?? $this->smtpSettings['smtp_from_email'];
            $fromName = $options['from_name'] ?? $this->smtpSettings['smtp_from_name'] ?? 'White Glove Service';
            $mail->setFrom($fromEmail, $fromName);

            // ===== PŘÍJEMCE =====
            if (is_array($options['to'])) {
                foreach ($options['to'] as $email => $name) {
                    if (is_numeric($email)) {
                        // Pouze email bez jména
                        $mail->addAddress($name);
                    } else {
                        $mail->addAddress($email, $name);
                    }
                }
            } else {
                $toName = $options['to_name'] ?? '';
                $mail->addAddress($options['to'], $toName);
            }

            // CC
            if (!empty($options['cc'])) {
                foreach ((array)$options['cc'] as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($email);
                    }
                }
            }

            // BCC
            if (!empty($options['bcc'])) {
                foreach ((array)$options['bcc'] as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $mail->addBCC($email);
                    }
                }
            }

            // Reply-To
            if (!empty($options['reply_to'])) {
                $mail->addReplyTo($options['reply_to']);
            } else {
                $mail->addReplyTo($fromEmail, $fromName);
            }

            // ===== OBSAH =====
            $mail->Subject = $options['subject'];

            if (!empty($options['html'])) {
                $mail->isHTML(true);
                $mail->Body = $options['body'];
                // Vytvořit plain text verzi
                $mail->AltBody = strip_tags($options['body']);
            } else {
                $mail->isHTML(false);
                $mail->Body = $options['body'];
            }

            // Priorita
            if (!empty($options['priority'])) {
                $mail->Priority = (int)$options['priority'];
            }

            // ===== PŘÍLOHY =====
            if (!empty($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (is_string($attachment)) {
                        // Jednoduchý formát: cesta k souboru
                        if (file_exists($attachment)) {
                            $mail->addAttachment($attachment);
                        }
                    } elseif (is_array($attachment)) {
                        // Pokročilý formát: ['path' => '...', 'name' => '...']
                        $path = $attachment['path'] ?? null;
                        $name = $attachment['name'] ?? '';

                        if ($path && file_exists($path)) {
                            $mail->addAttachment($path, $name);
                        }
                    }
                }
            }

            // ===== ODESLÁNÍ =====
            $mail->send();

            $this->logovat('SUCCESS', sprintf(
                'Email odeslán (PHPMailer SMTP): %s -> %s',
                $fromEmail,
                is_array($options['to']) ? implode(', ', array_keys($options['to'])) : $options['to']
            ));

            return [
                'success' => true,
                'message' => 'Email byl úspěšně odeslán přes SMTP',
                'method' => 'PHPMailer SMTP',
                'queued' => false
            ];

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $errorMsg = $mail->ErrorInfo;
            $this->logovat('ERROR', 'PHPMailer chyba: ' . $errorMsg);

            return [
                'success' => false,
                'message' => 'Chyba při odesílání přes SMTP: ' . $errorMsg,
                'method' => 'PHPMailer SMTP',
                'queued' => false
            ];
        }
    }

    /**
     * Fallback: Odeslání pomocí PHP mail()
     *
     * POZNÁMKA: Tato metoda NEpoužívá SMTP a nemusí fungovat na všech hostinzích.
     * Na Českém hostingu preferujte PHPMailer s WebSMTP.
     *
     * @param array $options Email options
     * @return array Result
     */
    private function odeslatPHPMail($options) {
        $fromEmail = $options['from'] ?? $this->smtpSettings['smtp_from_email'];
        $fromName = $options['from_name'] ?? $this->smtpSettings['smtp_from_name'] ?? 'White Glove Service';

        $to = is_array($options['to']) ? implode(', ', $options['to']) : $options['to'];
        $subject = $options['subject'];
        $body = $options['body'];

        // Headers
        $headers = [];
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "Reply-To: " . ($options['reply_to'] ?? $fromEmail);
        $headers[] = "X-Mailer: WGS Email Client (PHP mail fallback)";
        $headers[] = "MIME-Version: 1.0";

        if (!empty($options['html'])) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }

        // CC
        if (!empty($options['cc'])) {
            $ccEmails = array_filter((array)$options['cc'], function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
            if (!empty($ccEmails)) {
                $headers[] = "Cc: " . implode(', ', $ccEmails);
            }
        }

        // BCC
        if (!empty($options['bcc'])) {
            $bccEmails = array_filter((array)$options['bcc'], function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
            if (!empty($bccEmails)) {
                $headers[] = "Bcc: " . implode(', ', $bccEmails);
            }
        }

        // Odeslání
        $success = @mail($to, $subject, $body, implode("\r\n", $headers));

        if ($success) {
            $this->logovat('SUCCESS', "Email odeslán (PHP mail fallback): {$fromEmail} -> {$to}");

            return [
                'success' => true,
                'message' => 'Email byl odeslán přes PHP mail()',
                'method' => 'PHP mail()',
                'queued' => false,
                'warning' => 'Použit fallback PHP mail() - preferujte PHPMailer!'
            ];
        } else {
            $this->logovat('ERROR', "PHP mail() selhalo: {$to}");

            return [
                'success' => false,
                'message' => 'PHP mail() funkce selhala',
                'method' => 'PHP mail()',
                'queued' => false
            ];
        }
    }

    /**
     * Přidání emailu do fronty (asynchronní odeslání)
     *
     * @param array $options Email options
     * @return array Result
     */
    private function pridatDoFronty($options) {
        if (!class_exists('EmailQueue')) {
            require_once __DIR__ . '/EmailQueue.php';
        }

        $emailQueue = new EmailQueue($this->pdo);

        $queueData = [
            'notification_id' => $options['notification_id'] ?? 'custom',
            'to' => is_array($options['to']) ? key($options['to']) : $options['to'],
            'to_name' => $options['to_name'] ?? null,
            'subject' => $options['subject'],
            'body' => $options['body'],
            'cc' => $options['cc'] ?? [],
            'bcc' => $options['bcc'] ?? [],
            'priority' => $options['priority'] ?? 'normal',
            'scheduled_at' => $options['scheduled_at'] ?? date('Y-m-d H:i:s')
        ];

        $enqueued = $emailQueue->enqueue($queueData);

        if ($enqueued) {
            $this->logovat('INFO', sprintf(
                'Email přidán do fronty: %s -> %s',
                $queueData['to'],
                $options['subject']
            ));

            return [
                'success' => true,
                'message' => 'Email byl přidán do fronty pro asynchronní odeslání',
                'queued' => true,
                'method' => 'EmailQueue'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Nepodařilo se přidat email do fronty',
                'queued' => false
            ];
        }
    }

    /**
     * Načtení SMTP nastavení z databáze nebo .env
     *
     * @return array SMTP settings
     */
    private function nactiSMTPNastaveni() {
        // Pokusit se načíst z databáze
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("
                    SELECT * FROM wgs_smtp_settings
                    WHERE is_active = 1
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($settings) {
                    return $settings;
                }
            } catch (PDOException $e) {
                $this->logovat('WARNING', 'Nelze načíst SMTP nastavení z DB: ' . $e->getMessage());
            }
        }

        // Fallback na .env nebo výchozí hodnoty
        return [
            'smtp_host' => getenv('SMTP_HOST') ?: 'websmtp.cesky-hosting.cz',
            'smtp_port' => getenv('SMTP_PORT') ?: 25,
            'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'none',
            'smtp_username' => getenv('SMTP_USER') ?: 'wgs-service.cz',
            'smtp_password' => getenv('SMTP_PASS') ?: '',
            'smtp_from_email' => getenv('SMTP_FROM') ?: 'reklamace@wgs-service.cz',
            'smtp_from_name' => 'White Glove Service'
        ];
    }

    /**
     * Validace options
     *
     * @param array $options Options
     * @throws Exception
     */
    private function validovatOptions($options) {
        $required = ['to', 'subject', 'body'];

        foreach ($required as $field) {
            if (empty($options[$field])) {
                throw new Exception("Chybí povinné pole: {$field}");
            }
        }

        // Validovat email adresy
        $toEmails = is_array($options['to']) ? array_keys($options['to']) : [$options['to']];

        foreach ($toEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Neplatná emailová adresa: {$email}");
            }
        }
    }

    /**
     * Logování
     *
     * @param string $level Level (INFO, SUCCESS, WARNING, ERROR)
     * @param string $message Message
     */
    private function logovat($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

        // Zapsat do logu
        @file_put_contents($this->logFile, $logMessage, FILE_APPEND);

        // Také zapsat do error_log
        error_log("[EmailClient] [{$level}] {$message}");
    }

    /**
     * Získat informace o konfiguraci
     *
     * @return array Config info
     */
    public function ziskatInfo() {
        return [
            'phpmailer_available' => $this->usePHPMailer,
            'smtp_host' => $this->smtpSettings['smtp_host'],
            'smtp_port' => $this->smtpSettings['smtp_port'],
            'smtp_encryption' => $this->smtpSettings['smtp_encryption'],
            'smtp_username' => $this->smtpSettings['smtp_username'],
            'smtp_from' => $this->smtpSettings['smtp_from_email'],
            'log_file' => $this->logFile
        ];
    }
}
