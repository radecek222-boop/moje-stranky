<?php
/**
 * API endpoint pro zpracování PDF pověření k reklamaci
 *
 * Tento endpoint přijímá text extrahovaný z PDF (z frontendu pomocí PDF.js)
 * a parsuje z něj relevantní data pro formulář reklamace.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola přihlášení - pouze pro přihlášené uživatele
if (!isset($_SESSION['user_id'])) {
    sendJsonError('Uživatel není přihlášen', 401);
}

try {
    // Získat text z PDF (již extrahovaný na frontendu pomocí PDF.js)
    $pdfText = $_POST['pdf_text'] ?? '';

    if (empty($pdfText)) {
        sendJsonError('Chybí text z PDF');
    }

    // Parsování dat z textu
    $extrahovanaData = parsujDataZPDF($pdfText);

    sendJsonSuccess('PDF úspěšně zpracováno', $extrahovanaData);

} catch (Exception $e) {
    error_log("Chyba při parsování PDF: " . $e->getMessage());
    sendJsonError('Chyba při zpracování PDF');
}

/**
 * Parsuje data z textu PDF pověření
 *
 * @param string $text Text extrahovaný z PDF
 * @return array Asociativní pole s extrahovanými daty
 */
function parsujDataZPDF($text) {
    $data = [
        'cislo' => '',
        'datum_prodeje' => '',
        'datum_reklamace' => '',
        'jmeno' => '',
        'email' => '',
        'telefon' => '',
        'ulice' => '',
        'mesto' => '',
        'psc' => '',
        'model' => '',
        'provedeni' => '',
        'barva' => '',
        'popis_problemu' => '',
        'doplnujici_info' => ''
    ];

    // Normalizace textu - odstranit přebytečné whitespace
    $text = preg_replace('/\s+/', ' ', $text);

    // === ČÍSLO OBJEDNÁVKY/REKLAMACE ===
    // Hledáme vzory jako: "Číslo: 12345", "Objednávka: 12345", "Reklamace: 12345"
    if (preg_match('/(?:číslo|objednávka|reklamace)[:\s]+([A-Z0-9\-\/]+)/ui', $text, $matches)) {
        $data['cislo'] = trim($matches[1]);
    }

    // === DATUM PRODEJE ===
    // Hledáme vzory: "Datum prodeje: 01.12.2024", "Prodáno: 01.12.2024"
    if (preg_match('/(?:datum prodeje|prodáno)[:\s]+(\d{1,2}\.\d{1,2}\.\d{4})/ui', $text, $matches)) {
        $data['datum_prodeje'] = trim($matches[1]);
    }

    // === DATUM REKLAMACE ===
    // Hledáme vzory: "Datum reklamace: 01.12.2024", "Reklamováno: 01.12.2024"
    if (preg_match('/(?:datum reklamace|reklamováno)[:\s]+(\d{1,2}\.\d{1,2}\.\d{4})/ui', $text, $matches)) {
        $data['datum_reklamace'] = trim($matches[1]);
    }

    // === EMAIL ===
    // Standardní regex pro email
    if (preg_match('/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $matches)) {
        $data['email'] = trim($matches[1]);
    }

    // === TELEFON ===
    // Hledáme české/slovenské telefonní čísla
    if (preg_match('/(?:\+420|\+421|00420|00421)?\s*(\d{3}\s*\d{3}\s*\d{3})/', $text, $matches)) {
        $data['telefon'] = trim($matches[1]);
    } elseif (preg_match('/(?:telefon|tel|mobil)[:\s]+([\d\s+]+)/ui', $text, $matches)) {
        $data['telefon'] = preg_replace('/\s+/', '', trim($matches[1]));
    }

    // === JMÉNO ZÁKAZNÍKA ===
    // Hledáme vzory: "Zákazník: Jan Novák", "Jméno: Jan Novák"
    if (preg_match('/(?:zákazník|jméno|jméno zákazníka)[:\s]+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/u', $text, $matches)) {
        $data['jmeno'] = trim($matches[1]);
    }

    // === PSČ ===
    // Hledáme 5-místné PSČ (s mezerou nebo bez)
    if (preg_match('/(?:PSČ|psc)[:\s]*(\d{3}\s?\d{2})/', $text, $matches)) {
        $data['psc'] = trim($matches[1]);
    } elseif (preg_match('/\b(\d{3}\s?\d{2})\b/', $text, $matches)) {
        $data['psc'] = trim($matches[1]);
    }

    // === MĚSTO ===
    // Hledáme vzory: "Město: Praha", pokud je PSČ, hledáme text před PSČ
    if (preg_match('/(?:město|obec)[:\s]+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž\s]+)/u', $text, $matches)) {
        $data['mesto'] = trim($matches[1]);
    }

    // === ULICE ===
    // Hledáme vzory: "Ulice: Hlavní 123", "Adresa: Hlavní 123"
    if (preg_match('/(?:ulice|adresa)[:\s]+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž\s]+\d+[a-z]?)/u', $text, $matches)) {
        $data['ulice'] = trim($matches[1]);
    }

    // === MODEL ===
    // Hledáme vzory: "Model: Brio", "Výrobek: Brio"
    if (preg_match('/(?:model|výrobek|produkt)[:\s]+([A-Z0-9][A-Za-z0-9\s\-]+)/u', $text, $matches)) {
        $data['model'] = trim($matches[1]);
    }

    // === PROVEDENÍ ===
    // Hledáme klíčová slova: Látka, Kůže, Kombinace
    if (preg_match('/(?:provedení|materiál)[:\s]+(látka|kůže|kombinace)/ui', $text, $matches)) {
        $data['provedeni'] = ucfirst(strtolower(trim($matches[1])));
    } elseif (preg_match('/\b(látka|kůže|kombinace)\b/ui', $text, $matches)) {
        $data['provedeni'] = ucfirst(strtolower(trim($matches[1])));
    }

    // === BARVA ===
    // Hledáme vzory: "Barva: BF12", "Odstín: BF12"
    if (preg_match('/(?:barva|odstín|označení barvy)[:\s]+([A-Z0-9]+)/ui', $text, $matches)) {
        $data['barva'] = trim($matches[1]);
    }

    // === POPIS PROBLÉMU ===
    // Hledáme vzory: "Problém:", "Popis:", "Závada:"
    if (preg_match('/(?:problém|popis|závada|reklamace)[:\s]+(.{10,200})/ui', $text, $matches)) {
        $data['popis_problemu'] = trim($matches[1]);
    }

    // === DOPLŇUJÍCÍ INFO ===
    // Hledáme vzory: "Poznámka:", "Info:", "Doplňující informace:"
    if (preg_match('/(?:poznámka|info|doplňující)[:\s]+(.{10,200})/ui', $text, $matches)) {
        $data['doplnujici_info'] = trim($matches[1]);
    }

    // Sanitizace všech dat
    foreach ($data as $key => $value) {
        $data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    return $data;
}
?>
