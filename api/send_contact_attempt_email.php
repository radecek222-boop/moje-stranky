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
        '{{address}}'
    ], [
        $customerName,
        $orderId,
        $product,
        date('d.m.Y H:i'),
        $adresa ?: 'Neuvedena'
    ], $notification['template']);

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
    error_log("✉️ EMAIL - Pokus o kontakt: Zákazník: {$customerName}, Email: {$customerEmail}, Zakázka: {$orderId}");

    // Úspěšná odpověď
    echo json_encode([
        'success' => true,
        'message' => 'Email o pokusu o kontakt byl úspěšně odeslán',
        'queue_id' => $queueId,
        'recipient' => $customerEmail,
        'customer_name' => $customerName,
        'order_id' => $orderId
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
