<?php
/**
 * Admin: Pomocné funkce pro správu email šablon - WGS Service
 *
 * Obsahuje handlery pro:
 * - Aktualizaci email šablon (grafické i textové)
 * - Načítání email šablon
 * - Náhled email šablon
 * - Aktualizaci příjemců notifikací
 * - Odesílání pozvánek
 */

if (!defined('BASE_PATH')) {
    die('Přímý přístup zakázán.');
}

/**
 * Aktualizovat email šablonu - podpora grafických šablon
 */
function handleUpdateEmailTemplate(PDO $pdo, array $payload): void
{
    $templateId = $payload['template_id'] ?? null;
    $subject = trim($payload['subject'] ?? '');
    $template = trim($payload['template'] ?? '');
    $templateData = $payload['template_data'] ?? null;
    $active = isset($payload['active']) ? (bool)$payload['active'] : false;

    if (!$templateId) {
        throw new InvalidArgumentException('Chybí ID šablony');
    }

    if (empty($subject)) {
        throw new InvalidArgumentException('Předmět emailu nesmí být prázdný');
    }

    // Pokud máme grafická data, aktualizovat je
    if ($templateData !== null && is_array($templateData)) {
        // Validace struktury grafické šablony
        if (empty($templateData['obsah'])) {
            throw new InvalidArgumentException('Obsah emailu nesmí být prázdný');
        }

        $templateDataJson = json_encode($templateData, JSON_UNESCAPED_UNICODE);

        // Aktualizovat šablonu včetně template_data
        $stmt = $pdo->prepare("
            UPDATE wgs_notifications
            SET subject = :subject,
                template = :template,
                template_data = :template_data,
                active = :active,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'subject' => $subject,
            'template' => $template,
            'template_data' => $templateDataJson,
            'active' => $active ? 1 : 0,
            'id' => $templateId
        ]);
    } else {
        // Starý formát - jen text
        if (empty($template)) {
            throw new InvalidArgumentException('Obsah šablony nesmí být prázdný');
        }

        $stmt = $pdo->prepare("
            UPDATE wgs_notifications
            SET subject = :subject,
                template = :template,
                active = :active,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'subject' => $subject,
            'template' => $template,
            'active' => $active ? 1 : 0,
            'id' => $templateId
        ]);
    }

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Šablona nebyla nalezena nebo nebyla změněna');
    }

    respondSuccess([
        'message' => 'Šablona byla úspěšně aktualizována',
        'template_id' => $templateId
    ]);
}

/**
 * Získat detail emailové šablony
 */
function handleGetEmailTemplate(PDO $pdo): void
{
    $templateId = $_GET['template_id'] ?? null;

    if (!$templateId) {
        throw new InvalidArgumentException('Chybí ID šablony');
    }

    $stmt = $pdo->prepare("
        SELECT id, name, description, subject, template, template_data, active, variables
        FROM wgs_notifications
        WHERE id = :id
    ");
    $stmt->execute(['id' => $templateId]);
    $sablona = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sablona) {
        throw new InvalidArgumentException('Šablona nebyla nalezena');
    }

    // Dekódovat JSON pole
    $sablona['template_data'] = $sablona['template_data'] ? json_decode($sablona['template_data'], true) : null;
    $sablona['variables'] = $sablona['variables'] ? json_decode($sablona['variables'], true) : [];
    $sablona['active'] = (bool)$sablona['active'];

    respondSuccess(['template' => $sablona]);
}

/**
 * Náhled grafické emailové šablony
 */
function handlePreviewEmailTemplate(PDO $pdo, array $payload): void
{
    require_once __DIR__ . '/../includes/email_template_base.php';

    $templateData = $payload['template_data'] ?? null;

    if (!$templateData || !is_array($templateData)) {
        throw new InvalidArgumentException('Chybí data šablony');
    }

    // Vygenerovat náhled s ukázkovými daty
    $html = nahledSablony($templateData);

    respondSuccess([
        'html' => $html
    ]);
}

/**
 * Aktualizovat příjemce email šablony
 */
function handleUpdateEmailRecipients(PDO $pdo, array $payload): void
{
    $templateId = $payload['template_id'] ?? null;
    $recipients = $payload['recipients'] ?? null;

    if (!$templateId) {
        throw new InvalidArgumentException('Chybí ID šablony');
    }

    if (!is_array($recipients)) {
        throw new InvalidArgumentException('Příjemci musí být pole');
    }

    // Validace typu (to/cc/bcc)
    $validTypes = ['to', 'cc', 'bcc'];
    $validateType = function($type) use ($validTypes) {
        return in_array($type, $validTypes) ? $type : 'to';
    };

    // Validace struktury recipients
    $validatedRecipients = [
        'customer' => [
            'enabled' => isset($recipients['customer']['enabled']) ? (bool)$recipients['customer']['enabled'] : false,
            'type' => $validateType($recipients['customer']['type'] ?? 'to')
        ],
        'seller' => [
            'enabled' => isset($recipients['seller']['enabled']) ? (bool)$recipients['seller']['enabled'] : false,
            'type' => $validateType($recipients['seller']['type'] ?? 'cc')
        ],
        'technician' => [
            'enabled' => isset($recipients['technician']['enabled']) ? (bool)$recipients['technician']['enabled'] : false,
            'type' => $validateType($recipients['technician']['type'] ?? 'cc')
        ],
        'importer' => [
            'enabled' => isset($recipients['importer']['enabled']) ? (bool)$recipients['importer']['enabled'] : false,
            'email' => isset($recipients['importer']['email']) ? trim($recipients['importer']['email']) : '',
            'type' => $validateType($recipients['importer']['type'] ?? 'cc')
        ],
        'other' => [
            'enabled' => isset($recipients['other']['enabled']) ? (bool)$recipients['other']['enabled'] : false,
            'email' => isset($recipients['other']['email']) ? trim($recipients['other']['email']) : '',
            'type' => $validateType($recipients['other']['type'] ?? 'cc')
        ]
    ];

    // Validace emailů pokud jsou enabled
    if ($validatedRecipients['importer']['enabled'] && !empty($validatedRecipients['importer']['email'])) {
        if (!filter_var($validatedRecipients['importer']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Neplatná emailová adresa výrobce');
        }
    }

    if ($validatedRecipients['other']['enabled'] && !empty($validatedRecipients['other']['email'])) {
        if (!filter_var($validatedRecipients['other']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Neplatná emailová adresa v poli "Jiné"');
        }
    }

    // Uložit do databáze jako JSON (pro nový formát)
    $recipientsJson = json_encode($validatedRecipients, JSON_UNESCAPED_UNICODE);

    // FIX: Rozdělit příjemce na TO/CC/BCC podle typu (pro kompatibilitu s notification_sender.php)
    $toRecipients = [];
    $ccRecipients = [];
    $bccRecipients = [];
    $ccEmails = [];
    $bccEmails = [];

    // Role-based příjemci (customer, seller, technician)
    foreach (['customer', 'seller', 'technician'] as $role) {
        if ($validatedRecipients[$role]['enabled']) {
            $typ = $validatedRecipients[$role]['type'];
            if ($typ === 'to') {
                $toRecipients[] = $role;
            } elseif ($typ === 'cc') {
                $ccRecipients[] = $role;
            } elseif ($typ === 'bcc') {
                $bccRecipients[] = $role;
            }
        }
    }

    // Explicitní emaily (importer, other)
    if ($validatedRecipients['importer']['enabled'] && !empty($validatedRecipients['importer']['email'])) {
        $typ = $validatedRecipients['importer']['type'];
        $email = $validatedRecipients['importer']['email'];
        if ($typ === 'cc') {
            $ccEmails[] = $email;
        } elseif ($typ === 'bcc') {
            $bccEmails[] = $email;
        } elseif ($typ === 'to') {
            $toRecipients[] = 'importer'; // Přidat jako roli pro zpracování
            $ccEmails[] = $email; // A zároveň jako explicitní email
        }
    }

    if ($validatedRecipients['other']['enabled'] && !empty($validatedRecipients['other']['email'])) {
        $typ = $validatedRecipients['other']['type'];
        $email = $validatedRecipients['other']['email'];
        if ($typ === 'cc') {
            $ccEmails[] = $email;
        } elseif ($typ === 'bcc') {
            $bccEmails[] = $email;
        } elseif ($typ === 'to') {
            $toRecipients[] = 'other'; // Přidat jako roli
            $ccEmails[] = $email; // A zároveň jako explicitní email
        }
    }

    // Určit recipient_type pro zpětnou kompatibilitu (první TO role)
    $recipientType = !empty($toRecipients) ? $toRecipients[0] : 'customer';

    // Uložit do databáze
    $stmt = $pdo->prepare("
        UPDATE wgs_notifications
        SET recipients = :recipients,
            recipient_type = :recipient_type,
            to_recipients = :to_recipients,
            cc_recipients = :cc_recipients,
            bcc_recipients = :bcc_recipients,
            cc_emails = :cc_emails,
            bcc_emails = :bcc_emails,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'recipients' => $recipientsJson,
        'recipient_type' => $recipientType,
        'to_recipients' => json_encode($toRecipients),
        'cc_recipients' => json_encode($ccRecipients),
        'bcc_recipients' => json_encode($bccRecipients),
        'cc_emails' => json_encode($ccEmails),
        'bcc_emails' => json_encode($bccEmails),
        'id' => $templateId
    ]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Šablona nebyla nalezena nebo nebyla změněna');
    }

    respondSuccess([
        'message' => 'Příjemci byli úspěšně aktualizováni',
        'template_id' => $templateId,
        'recipients' => $validatedRecipients,
        'to_recipients' => $toRecipients,
        'cc_recipients' => $ccRecipients,
        'bcc_recipients' => $bccRecipients,
        'cc_emails' => $ccEmails,
        'bcc_emails' => $bccEmails
    ]);
}

/**
 * Odeslat pozvánky na registraci
 */
function handleSendInvitations(PDO $pdo, array $payload): void
{
    $typ = strtolower(trim($payload['typ'] ?? ''));
    $klic = trim($payload['klic'] ?? '');
    $emaily = $payload['emaily'] ?? [];

    // Validace typu
    if (!in_array($typ, ['technik', 'prodejce'], true)) {
        throw new InvalidArgumentException('Neplatny typ pozvanky');
    }

    // Validace emailu
    if (!is_array($emaily) || count($emaily) === 0) {
        throw new InvalidArgumentException('Zadejte alespon jeden email');
    }

    if (count($emaily) > 30) {
        throw new InvalidArgumentException('Maximalne 30 emailu najednou');
    }

    // Filtrovat a validovat emaily
    $platneEmaily = [];
    foreach ($emaily as $email) {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $platneEmaily[] = $email;
        }
    }

    if (count($platneEmaily) === 0) {
        throw new InvalidArgumentException('Zadny z emailu neni platny');
    }

    // Ziskat nebo vytvorit klic
    $pouzityKlic = '';
    if ($klic === 'auto' || $klic === '') {
        // Vytvorit novy klic
        $prefix = strtoupper(substr($typ, 0, 3));
        $pouzityKlic = generateRegistrationKey($prefix);

        $stmt = $pdo->prepare(
            'INSERT INTO wgs_registration_keys (key_code, key_type, max_usage, usage_count, is_active, created_at)
             VALUES (:key_code, :key_type, NULL, 0, 1, NOW())'
        );
        $stmt->execute([
            ':key_code' => $pouzityKlic,
            ':key_type' => $typ
        ]);
    } else {
        // Overit ze klic existuje a je aktivni
        $stmt = $pdo->prepare('SELECT key_code, key_type, is_active FROM wgs_registration_keys WHERE key_code = :klic');
        $stmt->execute([':klic' => $klic]);
        $klicData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$klicData) {
            throw new InvalidArgumentException('Registracni klic nebyl nalezen');
        }
        if (!$klicData['is_active']) {
            throw new InvalidArgumentException('Registracni klic neni aktivni');
        }

        $pouzityKlic = $klicData['key_code'];
    }

    // ============================================
    // NACIST SABLONU Z WGS_NOTIFICATIONS
    // ============================================
    $notificationId = 'invitation_' . $typ; // invitation_prodejce nebo invitation_technik

    $stmt = $pdo->prepare("
        SELECT subject, template FROM wgs_notifications
        WHERE id = :id AND active = 1
        LIMIT 1
    ");
    $stmt->execute(['id' => $notificationId]);
    $sablona = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sablona) {
        throw new InvalidArgumentException('Sablona pozvanky nenalezena: ' . $notificationId . '. Spustte migraci add_invitation_templates.sql');
    }

    // Pripravit promenne pro nahrazeni
    $appUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'www.wgs-service.cz');

    // Nahradit promenne v sablone
    $predmet = $sablona['subject'];
    $telo = $sablona['template'];

    $nahrazeni = [
        '{{registration_key}}' => $pouzityKlic,
        '{{app_url}}' => $appUrl
    ];

    foreach ($nahrazeni as $promenna => $hodnota) {
        $predmet = str_replace($promenna, $hodnota, $predmet);
        $telo = str_replace($promenna, $hodnota, $telo);
    }

    // ============================================
    // ODESLAT EMAILY
    // ============================================
    require_once __DIR__ . '/../includes/EmailQueue.php';
    $emailQueue = new EmailQueue($pdo);

    $odeslanoPocet = 0;
    $chyby = [];

    foreach ($platneEmaily as $email) {
        try {
            $queueItem = [
                'recipient_email' => $email,
                'recipient_name' => null,
                'subject' => $predmet,
                'body' => $telo
            ];

            $result = $emailQueue->sendEmail($queueItem);

            if ($result['success']) {
                $odeslanoPocet++;

                // HISTORIE: Ulozit zaznam o odeslane pozvance do email_queue
                $stmtLog = $pdo->prepare("
                    INSERT INTO wgs_email_queue
                    (notification_id, recipient_email, subject, body, status, sent_at, created_at, scheduled_at)
                    VALUES (:notif_id, :email, :subject, :body, 'sent', NOW(), NOW(), NOW())
                ");
                $stmtLog->execute([
                    ':notif_id' => 'invitation_' . $typ,
                    ':email' => $email,
                    ':subject' => $predmet,
                    ':body' => $telo
                ]);
            } else {
                $chyby[] = $email . ': ' . ($result['error'] ?? 'Neznama chyba');
                error_log("Chyba odeslani pozvanky na {$email}: " . ($result['error'] ?? 'Neznama chyba'));
            }
        } catch (Exception $e) {
            $chyby[] = $email . ': ' . $e->getMessage();
            error_log("Chyba odeslani pozvanky na {$email}: " . $e->getMessage());
        }
    }

    // ============================================
    // ULOZIT EMAIL PRIJEMCE DO KLICE
    // ============================================
    if ($odeslanoPocet > 0) {
        // Spojit vsechny uspesne odeslane emaily
        $emailyString = implode(', ', $platneEmaily);

        $stmt = $pdo->prepare('
            UPDATE wgs_registration_keys
            SET sent_to_email = :email, sent_at = NOW()
            WHERE key_code = :key_code
        ');
        $stmt->execute([
            ':email' => $emailyString,
            ':key_code' => $pouzityKlic
        ]);
    }

    respondSuccess([
        'sent_count' => $odeslanoPocet,
        'key_code' => $pouzityKlic,
        'errors' => $chyby
    ]);
}

// ============================================
// POZVANKY NYNI POUZIVAJI SABLONY Z WGS_NOTIFICATIONS
// (invitation_prodejce, invitation_technik)
// Editace sablon je v karce "Email sablony" v admin panelu
// ============================================

