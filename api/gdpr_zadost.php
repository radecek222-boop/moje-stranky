<?php
/**
 * GDPR API - Žádost o výmaz nebo export dat
 *
 * Endpoint pro zpracování GDPR žádostí:
 * - DELETE: Žádost o smazání osobních údajů
 * - EXPORT: Žádost o export osobních údajů
 *
 * POST parametry:
 * - csrf_token: CSRF token (povinné)
 * - typ: 'vymazat' nebo 'exportovat' (povinné)
 * - email: Email pro ověření identity (povinné)
 * - jmeno: Jméno pro ověření (povinné)
 * - telefon: Telefon pro ověření (volitelné)
 * - duvod: Důvod žádosti (volitelné)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Pouze POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metoda není povolena']);
    exit;
}

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Neplatný bezpečnostní token']);
    exit;
}

// Validace povinných polí
$typ = trim($_POST['typ'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$jmeno = trim($_POST['jmeno'] ?? '');
$telefon = trim($_POST['telefon'] ?? '');
$duvod = trim($_POST['duvod'] ?? '');

if (!in_array($typ, ['vymazat', 'exportovat'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Neplatný typ žádosti']);
    exit;
}

if (!$email) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Zadejte platný email']);
    exit;
}

if (strlen($jmeno) < 2) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Zadejte jméno']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Vyhledat záznamy spojené s tímto emailem
    $nalezeneZaznamy = [];

    // 1. Reklamace
    $stmt = $pdo->prepare("SELECT reklamace_id, jmeno, email, telefon, created_at FROM wgs_reklamace WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($reklamace) > 0) {
        $nalezeneZaznamy['reklamace'] = count($reklamace);
    }

    // 2. Uživatelský účet
    $stmt = $pdo->prepare("SELECT user_id, name, email FROM wgs_users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $uzivatel = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($uzivatel) {
        $nalezeneZaznamy['uzivatelsky_ucet'] = 1;
    }

    // 3. Push subscriptions
    $stmt = $pdo->prepare("SELECT id FROM wgs_push_subscriptions WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $pushSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($pushSubscriptions) > 0) {
        $nalezeneZaznamy['push_notifikace'] = count($pushSubscriptions);
    }

    // 4. GDPR consenty
    $stmt = $pdo->prepare("SELECT id FROM wgs_gdpr_consents WHERE fingerprint_id = :email");
    $stmt->execute(['email' => $email]);
    $consents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($consents) > 0) {
        $nalezeneZaznamy['gdpr_souhlasy'] = count($consents);
    }

    // Pokud nejsou žádné záznamy
    if (empty($nalezeneZaznamy)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Žádné osobní údaje spojené s tímto emailem nebyly nalezeny.',
            'data' => ['nalezeno' => false]
        ]);
        exit;
    }

    // Vytvořit žádost v databázi
    $zadostId = uniqid('GDPR-', true);
    $typZadosti = $typ === 'vymazat' ? 'DELETE' : 'EXPORT';

    // Kontrola existence tabulky wgs_gdpr_requests, pokud ne, vytvořit
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_gdpr_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            zadost_id VARCHAR(50) NOT NULL UNIQUE,
            typ ENUM('DELETE', 'EXPORT') NOT NULL,
            email VARCHAR(255) NOT NULL,
            jmeno VARCHAR(255) NOT NULL,
            telefon VARCHAR(50) DEFAULT NULL,
            duvod TEXT DEFAULT NULL,
            nalezene_zaznamy JSON DEFAULT NULL,
            stav ENUM('nova', 'zpracovava_se', 'dokoncena', 'zamitnuta') DEFAULT 'nova',
            ip_adresa VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL DEFAULT NULL,
            processed_by INT DEFAULT NULL,
            poznamka TEXT DEFAULT NULL,
            INDEX idx_email (email),
            INDEX idx_stav (stav),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Vložit žádost
    $stmt = $pdo->prepare("
        INSERT INTO wgs_gdpr_requests
        (zadost_id, typ, email, jmeno, telefon, duvod, nalezene_zaznamy, ip_adresa)
        VALUES (:zadost_id, :typ, :email, :jmeno, :telefon, :duvod, :zaznamy, :ip)
    ");

    $stmt->execute([
        'zadost_id' => $zadostId,
        'typ' => $typZadosti,
        'email' => $email,
        'jmeno' => htmlspecialchars($jmeno, ENT_QUOTES, 'UTF-8'),
        'telefon' => htmlspecialchars($telefon, ENT_QUOTES, 'UTF-8'),
        'duvod' => htmlspecialchars($duvod, ENT_QUOTES, 'UTF-8'),
        'zaznamy' => json_encode($nalezeneZaznamy),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // Odeslat email administrátorovi
    $adminEmail = getenv('ADMIN_EMAIL') ?: 'reklamace@wgs-service.cz';

    $predmet = "[WGS GDPR] Nová žádost: {$typZadosti} - {$email}";
    $telo = "
Byla přijata nová GDPR žádost.

ID žádosti: {$zadostId}
Typ: {$typZadosti}
Email: {$email}
Jméno: {$jmeno}
Telefon: {$telefon}
Důvod: {$duvod}

Nalezené záznamy:
" . print_r($nalezeneZaznamy, true) . "

IP adresa: " . ($_SERVER['REMOTE_ADDR'] ?? 'neznámá') . "
Datum: " . date('d.m.Y H:i:s') . "

Zpracujte žádost v administraci: https://www.wgs-service.cz/admin.php
    ";

    // Vložit do email queue
    $stmt = $pdo->prepare("
        INSERT INTO wgs_email_queue (to_email, subject, body, status)
        VALUES (:to_email, :subject, :body, 'pending')
    ");
    $stmt->execute([
        'to_email' => $adminEmail,
        'subject' => $predmet,
        'body' => $telo
    ]);

    // Audit log
    if (function_exists('auditLog')) {
        auditLog('GDPR_REQUEST', "Nová {$typZadosti} žádost: {$email}", ['zadost_id' => $zadostId]);
    }

    // Odpověď
    $zprava = $typ === 'vymazat'
        ? 'Vaše žádost o výmaz osobních údajů byla přijata. Zpracujeme ji do 30 dnů.'
        : 'Vaše žádost o export osobních údajů byla přijata. Zpracujeme ji do 30 dnů.';

    echo json_encode([
        'status' => 'success',
        'message' => $zprava,
        'data' => [
            'zadost_id' => $zadostId,
            'nalezeno' => true,
            'zaznamy' => $nalezeneZaznamy
        ]
    ]);

} catch (PDOException $e) {
    error_log("GDPR API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Chyba při zpracování žádosti']);
}
