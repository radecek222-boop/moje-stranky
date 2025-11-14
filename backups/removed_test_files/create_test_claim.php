<?php
/**
 * Create Test Claim API
 * Vytvoří testovací reklamaci pomocí skutečného save.php - simuluje reálné chování
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

// Only admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Simulace FormData z novareklamace.php
    $_POST['action'] = 'create';
    $_POST['typ'] = 'reklamace';
    $_POST['cislo'] = 'TEST-' . date('Ymd') . '-' . rand(1000, 9999);
    $_POST['datum_prodeje'] = date('d.m.Y', strtotime('-30 days'));
    $_POST['datum_reklamace'] = date('d.m.Y');
    $_POST['jmeno'] = $_POST['jmeno'] ?? 'Test Zákazník';
    $_POST['email'] = $_POST['email'] ?? 'test@wgs-service.cz';
    $_POST['telefon'] = $_POST['telefon'] ?? '+420777888999';
    $_POST['adresa'] = 'Testovací 123, Praha, 11000';
    $_POST['model'] = 'NATUZZI TEST MODEL';
    $_POST['provedeni'] = 'Testovací provedení';
    $_POST['barva'] = 'BF12';
    $_POST['seriove_cislo'] = '';
    $_POST['popis_problemu'] = $_POST['popis_problemu'] ?? 'Testovací popis problému - E2E test workflow';
    $_POST['doplnujici_info'] = 'Automaticky vytvořeno E2E testem';
    $_POST['fakturace_firma'] = 'CZ';
    $_POST['gdpr_consent'] = '1';
    $_POST['csrf_token'] = generateCSRFToken();

    // Zavolat skutečný save.php controller
    ob_start();
    require __DIR__ . '/../app/controllers/save.php';
    $output = ob_get_clean();

    // Pokud save.php již vytvořil response, předat ho
    if (!empty($output)) {
        echo $output;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
