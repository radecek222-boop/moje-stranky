<?php
/**
 * API Endpoint: Odeslání emailu o pokusu o kontakt
 *
 * Tento endpoint se volá když technik klikne na tlačítko "Odeslat SMS".
 * Automaticky pošle email zákazníkovi s informací o pokusu o telefonický kontakt.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/EmailQueue.php';

header('Content-Type: application/json; charset=utf-8');

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

    // Extrakce CSRF tokenu z JSON
    if (isset($data['csrf_token'])) {
        $_POST['csrf_token'] = $data['csrf_token'];
    }

    // BEZPEČNOST: CSRF ochrana
    requireCSRF();

    $reklamaceId = $data['reklamace_id'] ?? null;

    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    // Načtení dat reklamace z databáze
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("
        SELECT
            id,
            reklamace_id,
            jmeno,
            email,
            telefon,
            model,
            cislo,
            adresa,
            ulice,
            mesto,
            psc
        FROM wgs_reklamace
        WHERE id = :id OR reklamace_id = :reklamace_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $reklamaceId,
        'reklamace_id' => $reklamaceId
    ]);

    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nenalezena: ' . $reklamaceId);
    }

    // Validace emailu zákazníka
    $customerEmail = trim($reklamace['email'] ?? '');
    if (!$customerEmail || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'error' => 'Zákazník nemá uvedený platný email',
            'warning' => true
        ]);
        exit;
    }

    // Načtení email šablony z databáze
    $stmt = $pdo->prepare("
        SELECT * FROM wgs_notifications
        WHERE name = 'Pokus o kontakt' AND active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('Email šablona "Pokus o kontakt" nenalezena nebo není aktivní. Spusťte migraci: pridej_email_sablonu_pokus_o_kontakt.php');
    }

    // Příprava dat pro šablonu
    $customerName = $reklamace['jmeno'] ?: 'Zákazník';
    $orderId = $reklamace['cislo'] ?: $reklamace['reklamace_id'] ?: ('WGS-' . $reklamace['id']);
    $product = $reklamace['model'] ?: 'Nábytek Natuzzi';

    // Sestavení adresy
    $adresa = $reklamace['adresa'];
    if (!$adresa) {
        $parts = array_filter([
            $reklamace['ulice'],
            $reklamace['mesto'],
            $reklamace['psc']
        ]);
        $adresa = implode(', ', $parts);
    }

    // Získání údajů o technikovi, který provádí akci
    $technicianName = $_SESSION['user_name'] ?? 'White Glove Service';
    $technicianEmail = $_SESSION['user_email'] ?? 'reklamace@wgs-service.cz';

    // Pokud je přihlášený uživatel (ne admin), zkusit načíst telefon z databáze
    $technicianPhone = '+420 725 965 826'; // Výchozí firemní telefon
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        try {
            $stmtUser = $pdo->prepare("
                SELECT phone
                FROM wgs_users
                WHERE id = :user_id OR user_id = :user_id
                LIMIT 1
            ");
            $stmtUser->execute(['user_id' => $_SESSION['user_id']]);
            $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($userInfo && !empty($userInfo['phone'])) {
                $technicianPhone = $userInfo['phone'];

                // Přidat předvolbu +420 pokud tam není
                $technicianPhone = trim($technicianPhone);
                if (!preg_match('/^\+/', $technicianPhone)) {
                    // Telefon nezačína na +, přidat +420
                    $technicianPhone = '+420 ' . ltrim($technicianPhone, '0');
                }
            }
        } catch (PDOException $e) {
            // Pokud telefon nelze načíst, použije se výchozí firemní telefon
            error_log('Nepodařilo se načíst telefon technika: ' . $e->getMessage());
        }
    }

    // Náhrada proměnných v šabloně
    $subject = str_replace([
        '{{customer_name}}',
        '{{order_id}}',
        '{{product}}',
        '{{date}}'
    ], [
        $customerName,
        $orderId,
        $product,
        date('d.m.Y')
    ], $notification['subject']);

    $body = str_replace([
        '{{customer_name}}',
        '{{order_id}}',
        '{{product}}',
        '{{date}}',
        '{{address}}',
        '{{technician_name}}',
        '{{technician_email}}',
        '{{technician_phone}}',
        '{{company_email}}',
        '{{company_phone}}'
    ], [
        $customerName,
        $orderId,
        $product,
        date('d.m.Y H:i'),
        $adresa ?: 'Neuvedena',
        $technicianName,
        $technicianEmail,
        $technicianPhone,
        'reklamace@wgs-service.cz',  // Obecný firemní email
        '+420 725 965 826'            // Obecný firemní telefon
    ], $notification['template']);

    // Nacist SMS sablonu z databaze (pokud existuje)
    $smsText = null;
    try {
        $stmtSms = $pdo->prepare("
            SELECT template FROM wgs_notifications
            WHERE trigger_event = 'contact_attempt' AND type = 'sms' AND active = 1
            LIMIT 1
        ");
        $stmtSms->execute();
        $smsSablona = $stmtSms->fetch(PDO::FETCH_ASSOC);

        if ($smsSablona && !empty($smsSablona['template'])) {
            // Nahradit promenne v SMS sablone
            $smsText = str_replace([
                '{{customer_name}}',
                '{{order_id}}',
                '{{product}}',
                '{{date}}',
                '{{address}}',
                '{{technician_name}}',
                '{{technician_email}}',
                '{{technician_phone}}',
                '{{company_email}}',
                '{{company_phone}}'
            ], [
                $customerName,
                $orderId,
                $product,
                date('d.m.Y'),
                $adresa ?: 'Neuvedena',
                $technicianName,
                $technicianEmail,
                $technicianPhone,
                'reklamace@wgs-service.cz',
                '+420 725 965 826'
            ], $smsSablona['template']);
        }
    } catch (PDOException $e) {
        error_log('Chyba pri nacitani SMS sablony: ' . $e->getMessage());
    }

    // Fallback pokud SMS sablona neexistuje
    if (!$smsText) {
        $smsText = "Dobry den {$customerName}, kontaktujeme Vas v zastoupeni Natuzzi ohledne servisni zakazky c. {$orderId}. Nepodarilo se nam Vas zastihnout. Zavolejte prosim zpet {$technicianName} na tel. {$technicianPhone}. Dekujeme, WGS Service";
    }

    // Přidání emailu do fronty
    $emailQueue = new EmailQueue($pdo);

    $queueId = $emailQueue->add(
        $customerEmail,
        $subject,
        $body,
        [
            'notification_id' => $notification['id'],
            'reklamace_id' => $reklamace['id'],
            'trigger' => 'contact_attempt'
        ]
    );

    if (!$queueId) {
        throw new Exception('Nepodařilo se přidat email do fronty');
    }

    // Logování akce
    error_log("[EMAIL] Pokus o kontakt: Zakaznik: {$customerName}, Email: {$customerEmail}, Zakazka: {$orderId}");

    // Úspěšná odpověď
    echo json_encode([
        'success' => true,
        'message' => 'Email o pokusu o kontakt byl úspěšně odeslán',
        'queue_id' => $queueId,
        'recipient' => $customerEmail,
        'customer_name' => $customerName,
        'order_id' => $orderId,
        'sms_text' => $smsText,  // SMS text se generuje ze stejnych dat jako email
        'product' => $product
    ]);

} catch (Exception $e) {
    error_log('ERROR send_contact_attempt_email.php: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
