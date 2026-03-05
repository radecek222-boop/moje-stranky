<?php
/**
 * B2B poptávka - zpracování formuláře pro firemní spolupráci
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Neplatná metoda']));
}

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Neplatný bezpečnostní token']));
}

// Honeypot - robot vyplnil skryté pole
if (!empty($_POST['web'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Neplatný požadavek']));
}

// Časová past - formulář odeslán příliš rychle (robot)
$casZobrazeni = intval($_POST['cas_zobrazeni'] ?? 0);
if ($casZobrazeni === 0 || (time() - $casZobrazeni) < 3) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Formulář odeslán příliš rychle']));
}

// Rate limiting - max 3 poptávky za hodinu ze stejné IP
try {
    $pdo = getDbConnection();
    $rateLimiter = new RateLimiter($pdo);
    if (!$rateLimiter->checkLimit('b2b_poptavka', $_SERVER['REMOTE_ADDR'], 3, 3600)) {
        http_response_code(429);
        die(json_encode(['status' => 'error', 'message' => 'Příliš mnoho požadavků. Zkuste to za hodinu.']));
    }
} catch (Exception $e) {
    // Rate limiter selhal - pokračujeme bez něj
    error_log('B2B rate limiter chyba: ' . $e->getMessage());
}

// Sanitace a validace vstupů
function sanituj($hodnota) {
    return htmlspecialchars(trim($hodnota), ENT_QUOTES, 'UTF-8');
}

$firma   = sanituj($_POST['firma'] ?? '');
$kontakt = sanituj($_POST['kontakt'] ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$telefon = sanituj($_POST['telefon'] ?? '');
$zprava  = sanituj($_POST['zprava'] ?? '');

if (empty($firma)) {
    die(json_encode(['status' => 'error', 'message' => 'Chybí název firmy']));
}
if (empty($kontakt)) {
    die(json_encode(['status' => 'error', 'message' => 'Chybí kontaktní osoba']));
}
if (!$email) {
    die(json_encode(['status' => 'error', 'message' => 'Neplatná e-mailová adresa']));
}
if (empty($zprava)) {
    die(json_encode(['status' => 'error', 'message' => 'Chybí popis spolupráce']));
}

// Sestavení emailu
$predmet = 'B2B poptávka: ' . $firma;

$telo = '
<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"><title>B2B poptávka</title></head>
<body style="font-family: Arial, sans-serif; color: #222; background: #f5f5f5; margin: 0; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background: #fff; border: 1px solid #ddd; padding: 30px;">
    <h2 style="font-size: 1.2rem; font-weight: 700; letter-spacing: 0.05em; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px;">NOVÁ B2B POPTÁVKA</h2>
    <table style="width: 100%; border-collapse: collapse;">
      <tr>
        <td style="padding: 8px 0; font-weight: 700; width: 150px; color: #555; font-size: 0.85rem;">FIRMA</td>
        <td style="padding: 8px 0; font-size: 0.95rem;">' . $firma . '</td>
      </tr>
      <tr style="background: #f9f9f9;">
        <td style="padding: 8px 0; font-weight: 700; color: #555; font-size: 0.85rem;">KONTAKT</td>
        <td style="padding: 8px 0; font-size: 0.95rem;">' . $kontakt . '</td>
      </tr>
      <tr>
        <td style="padding: 8px 0; font-weight: 700; color: #555; font-size: 0.85rem;">E-MAIL</td>
        <td style="padding: 8px 0; font-size: 0.95rem;"><a href="mailto:' . $email . '" style="color: #000;">' . $email . '</a></td>
      </tr>
      <tr style="background: #f9f9f9;">
        <td style="padding: 8px 0; font-weight: 700; color: #555; font-size: 0.85rem;">TELEFON</td>
        <td style="padding: 8px 0; font-size: 0.95rem;">' . ($telefon ?: '–') . '</td>
      </tr>
    </table>
    <div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-left: 3px solid #000;">
      <div style="font-weight: 700; font-size: 0.85rem; color: #555; margin-bottom: 8px;">POPIS SPOLUPRÁCE</div>
      <div style="font-size: 0.95rem; line-height: 1.6;">' . nl2br($zprava) . '</div>
    </div>
    <div style="margin-top: 20px; font-size: 0.75rem; color: #999;">
      Odesláno: ' . date('d.m.Y H:i') . ' | IP: ' . htmlspecialchars($_SERVER['REMOTE_ADDR']) . '
    </div>
  </div>
</body>
</html>';

// Odeslání emailu přes EmailQueue nebo PHPMailer
$cilEmail = 'info@wgs-service.cz';
$odeslanoBool = false;

try {
    // Pokus přes EmailQueue
    require_once __DIR__ . '/../includes/EmailQueue.php';
    $frontaEmailu = new EmailQueue($pdo);
    $vysledek = $frontaEmailu->enqueue([
        'notification_id' => 'b2b_poptavka',
        'to'              => $cilEmail,
        'to_name'         => 'WGS Info',
        'subject'         => $predmet,
        'body'            => $telo,
        'priority'        => 'high',
        'reply_to'        => $email,
    ]);
    $odeslanoBool = !empty($vysledek);
} catch (Exception $e) {
    error_log('B2B EmailQueue chyba: ' . $e->getMessage());
}

// Záložní odeslání přes mail()
if (!$odeslanoBool) {
    $hlavicky  = "MIME-Version: 1.0\r\n";
    $hlavicky .= "Content-Type: text/html; charset=UTF-8\r\n";
    $hlavicky .= "From: noreply@wgs-service.cz\r\n";
    $hlavicky .= "Reply-To: {$email}\r\n";
    $odeslanoBool = mail($cilEmail, '=?UTF-8?B?' . base64_encode($predmet) . '?=', $telo, $hlavicky);
}

if ($odeslanoBool) {
    error_log("B2B poptávka odeslána: firma={$firma}, email={$email}");
    echo json_encode(['status' => 'success', 'message' => 'Poptávka odeslána']);
} else {
    error_log("B2B poptávka SELHALA: firma={$firma}, email={$email}");
    echo json_encode(['status' => 'error', 'message' => 'Nepodařilo se odeslat poptávku. Kontaktujte nás přímo na info@wgs-service.cz']);
}
