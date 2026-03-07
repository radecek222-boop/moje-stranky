<?php
/**
 * API: Odeslání White Label poptávky
 * Uloží do DB (wgs_wl_poptavky) a odešle email adminovi
 */

define('BASE_PATH', dirname(__DIR__));

// Minimální bootstrap bez plného init.php
require_once BASE_PATH . '/includes/env_loader.php';
require_once BASE_PATH . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Neplatná metoda']);
    exit;
}

// Rate limiting jednoduchý — max 3 poptávky za hodinu z jedné IP
$souborLimitu = sys_get_temp_dir() . '/wl_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
$casNyni      = time();
$pokusy       = [];

if (file_exists($souborLimitu)) {
    $pokusy = array_filter(
        json_decode(file_get_contents($souborLimitu), true) ?? [],
        fn($cas) => ($casNyni - $cas) < 3600
    );
}

if (count($pokusy) >= 3) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Příliš mnoho poptávek. Zkuste to za hodinu.']);
    exit;
}

// Načíst a ošetřit vstup
$jmeno         = trim($_POST['jmeno']         ?? '');
$firma         = trim($_POST['firma']         ?? '');
$emailKontakt  = trim($_POST['email']         ?? '');
$telefon       = trim($_POST['telefon']       ?? '');
$pocetTechniku = trim($_POST['pocet_techniku'] ?? '');
$segment       = trim($_POST['segment']       ?? '');
$zprava        = trim($_POST['zprava']        ?? '');

// Validace povinných polí
if (!$jmeno || !$firma || !$emailKontakt) {
    echo json_encode(['status' => 'error', 'message' => 'Vyplňte povinná pole: jméno, firma a e-mail.']);
    exit;
}

if (!filter_var($emailKontakt, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Neplatná e-mailová adresa.']);
    exit;
}

// Sanitizace
$jmeno         = htmlspecialchars($jmeno,         ENT_QUOTES, 'UTF-8');
$firma         = htmlspecialchars($firma,         ENT_QUOTES, 'UTF-8');
$emailKontakt  = htmlspecialchars($emailKontakt,  ENT_QUOTES, 'UTF-8');
$telefon       = htmlspecialchars($telefon,       ENT_QUOTES, 'UTF-8');
$pocetTechniku = htmlspecialchars($pocetTechniku, ENT_QUOTES, 'UTF-8');
$segment       = htmlspecialchars($segment,       ENT_QUOTES, 'UTF-8');
$zprava        = htmlspecialchars($zprava,        ENT_QUOTES, 'UTF-8');

try {
    $pdo = Database::getInstance()->getConnection();

    // Uložit do DB (pokud tabulka existuje)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO wgs_wl_poptavky
                (jmeno, firma, email, telefon, pocet_techniku, segment, zprava, ip_adresa, datum_vytvoreni)
            VALUES
                (:jmeno, :firma, :email, :telefon, :pocet_techniku, :segment, :zprava, :ip, NOW())
        ");
        $stmt->execute([
            'jmeno'          => $jmeno,
            'firma'          => $firma,
            'email'          => $emailKontakt,
            'telefon'        => $telefon,
            'pocet_techniku' => $pocetTechniku,
            'segment'        => $segment,
            'zprava'         => $zprava,
            'ip'             => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (PDOException $e) {
        // Tabulka nemusí existovat — pouze logovat, nepřerušovat
        error_log('WL poptávka - DB uložení selhalo: ' . $e->getMessage());
    }

    // Odeslat email adminovi
    $adminEmail  = getenv('ADMIN_EMAIL') ?: 'radek@wgs-service.cz';
    $predmet     = "WL Poptávka: {$firma} — {$jmeno}";
    $telo        = "Nová White Label poptávka\n\n"
                 . "Jméno:           {$jmeno}\n"
                 . "Firma:           {$firma}\n"
                 . "E-mail:          {$emailKontakt}\n"
                 . "Telefon:         {$telefon}\n"
                 . "Počet techniků:  {$pocetTechniku}\n"
                 . "Segment:         {$segment}\n"
                 . "Zpráva:\n{$zprava}\n\n"
                 . "---\n"
                 . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n"
                 . "Čas: " . date('d.m.Y H:i') . "\n";

    $hlavicky  = "From: noreply@wgs-service.cz\r\n";
    $hlavicky .= "Reply-To: {$emailKontakt}\r\n";
    $hlavicky .= "Content-Type: text/plain; charset=utf-8\r\n";
    $hlavicky .= "X-Mailer: WGS White Label\r\n";

    mail($adminEmail, $predmet, $telo, $hlavicky);

    // Uložit rate limit
    $pokusy[] = $casNyni;
    file_put_contents($souborLimitu, json_encode(array_values($pokusy)));

    echo json_encode(['status' => 'success', 'message' => 'Poptávka odeslána.']);

} catch (Exception $e) {
    error_log('WL poptávka - kritická chyba: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Chyba serveru. Kontaktujte nás přímo na radek@wgs-service.cz']);
}
