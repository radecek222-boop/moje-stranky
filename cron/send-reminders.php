<?php
/**
 * Webcron endpoint: Automatické odesílání připomínek
 *
 * Tento soubor je určen pro spuštění přes webcron na hostingu.
 * URL: https://www.wgs-service.cz/cron/send-reminders.php?key=TAJNY_KLIC
 *
 * Bezpečnost: Vyžaduje tajný klíč v URL parametru
 */

// Načíst konfiguraci
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/EmailQueue.php';

// === BEZPEČNOSTNÍ KONTROLA ===
$tajnyKlic = getenv('CRON_SECRET_KEY') ?: 'wgs2025reminder';  // Výchozí klíč - změňte v .env!

// Kontrola tajného klíče
if (!isset($_GET['key']) || $_GET['key'] !== $tajnyKlic) {
    http_response_code(403);
    error_log("CRON send-reminders: Neplatný klíč - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    die('Forbidden');
}

// === LOGOVÁNÍ ===
$logFile = __DIR__ . '/../logs/cron_reminders.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// === HLAVNÍ LOGIKA ===
try {
    logMessage("=== START: Kontrola návštěv pro připomenutí (webcron) ===");

    $pdo = getDbConnection();

    // Vypočítat datum zítřka v ČESKÉM FORMÁTU (DD.MM.YYYY) - tak jak je v databázi
    $zitra = date('d.m.Y', strtotime('+1 day'));
    logMessage("Hledám návštěvy na datum: {$zitra}");

    // Najít všechny reklamace se stavem 'open' (DOMLUVENÁ) a termínem na zítřek
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.reklamace_id,
            r.cislo,
            r.jmeno,
            r.email,
            r.telefon,
            r.adresa,
            r.termin,
            r.cas_navstevy,
            r.model,
            r.popis_problemu,
            r.technik,
            u.phone as technik_telefon
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON u.name = r.technik AND u.role = 'technik'
        WHERE r.stav = 'open'
          AND r.termin = :zitra
          AND r.email IS NOT NULL
          AND r.email != ''
        ORDER BY r.termin, r.cas_navstevy
    ");

    $stmt->execute([':zitra' => $zitra]);
    $navstevy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pocetNalezenych = count($navstevy);
    logMessage("Nalezeno návštěv: {$pocetNalezenych}");

    if ($pocetNalezenych === 0) {
        logMessage("Žádné návštěvy na zítřek - konec.");
        logMessage("=== KONEC ===\n");
        echo json_encode([
            'status' => 'success',
            'message' => 'Žádné návštěvy na zítřek',
            'found' => 0,
            'sent' => 0
        ]);
        exit(0);
    }

    // Nacist sablonu pro pripomenuti (hledat podle trigger_event)
    $stmtTemplate = $pdo->prepare("
        SELECT subject, template
        FROM wgs_notifications
        WHERE trigger_event = 'appointment_reminder' AND type = 'email' AND active = 1
        LIMIT 1
    ");
    $stmtTemplate->execute();
    $template = $stmtTemplate->fetch(PDO::FETCH_ASSOC);

    // Fallback sablona pokud neni v databazi
    if (!$template) {
        logMessage("Sablona 'appointment_reminder' neni v databazi - pouzivam fallback");
        $template = [
            'subject' => 'Pripominka: Zitrejsi navsteva technika - WGS Service',
            'template' => "Dobry den {{customer_name}},\n\npripominame Vam zitrejsi navstevu technika WGS Service.\n\nTermin: {{date}} v {{time}}\nAdresa: {{address}}\nTechnik: {{technician_name}}, tel. {{technician_phone}}\n\nDULEZITE: Prosime, pripravte nabytek pred prichodem technika. Odstrante z nej vsechny osobni veci a luzkoviny, aby technik mohl bez prekazek provest servisni zasah.\n\nV pripade potreby zmeny terminu nas kontaktujte.\n\nS pozdravem,\nWGS Service\nTel: +420 725 965 826\nEmail: reklamace@wgs-service.cz"
        ];
    }

    $uspesneOdeslano = 0;
    $chyby = 0;

    // EmailQueue instance
    $emailQueue = new EmailQueue($pdo);

    // Pro každou návštěvu odeslat připomenutí
    foreach ($navstevy as $navsteva) {
        $reference = $navsteva['cislo'] ?? $navsteva['reklamace_id'];
        logMessage("Zpracovávám: {$reference} - {$navsteva['jmeno']} ({$navsteva['email']})");

        // Připravit data pro nahrazení v šabloně
        // Převést český formát DD.MM.YYYY na YYYY-MM-DD pro strtotime
        $terminParts = explode('.', $navsteva['termin']);
        if (count($terminParts) === 3) {
            $terminISO = $terminParts[2] . '-' . $terminParts[1] . '-' . $terminParts[0]; // YYYY-MM-DD
            $datumZobrazeni = $navsteva['termin']; // Už je v českém formátu DD.MM.YYYY
        } else {
            $terminISO = $navsteva['termin'];
            $datumZobrazeni = $navsteva['termin'];
        }

        // Zjistit den v týdnu
        $denVTydnu = [
            'Monday' => 'pondělí',
            'Tuesday' => 'úterý',
            'Wednesday' => 'středa',
            'Thursday' => 'čtvrtek',
            'Friday' => 'pátek',
            'Saturday' => 'sobota',
            'Sunday' => 'neděle'
        ];
        $denCesky = $denVTydnu[date('l', strtotime($terminISO))] ?? 'den';

        $nahradit = [
            '{{customer_name}}' => $navsteva['jmeno'],
            '{{date}}' => $datumZobrazeni,
            '{{day}}' => $denCesky,
            '{{time}}' => $navsteva['cas_navstevy'] ?? '(čas upřesní technik)',
            '{{address}}' => $navsteva['adresa'],
            '{{order_id}}' => $reference,
            '{{product}}' => $navsteva['model'] ?? 'nábytek',
            '{{description}}' => $navsteva['popis_problemu'] ?? '',
            '{{technician_name}}' => $navsteva['technik'] ?? 'WGS technik',
            '{{technician_phone}}' => $navsteva['technik_telefon'] ?? '+420 725 965 826'
        ];

        // Nahradit proměnné v předmětu a těle emailu
        $predmet = str_replace(array_keys($nahradit), array_values($nahradit), $template['subject']);
        $telo = str_replace(array_keys($nahradit), array_values($nahradit), $template['template']);

        // Přidat email do fronty
        try {
            $emailQueue->add(
                $navsteva['email'],
                $predmet,
                $telo,
                'appointment_reminder',
                $navsteva['id']
            );

            $uspesneOdeslano++;
            logMessage("[OK] Email pridan do fronty pro: {$navsteva['email']}");

        } catch (Exception $e) {
            $chyby++;
            logMessage("[CHYBA] pri pridavani emailu pro {$navsteva['email']}: " . $e->getMessage());
        }
    }

    logMessage("---");
    logMessage("SOUHRN:");
    logMessage("  Nalezeno návštěv: {$pocetNalezenych}");
    logMessage("  Úspěšně přidáno do fronty: {$uspesneOdeslano}");
    logMessage("  Chyby: {$chyby}");
    logMessage("=== KONEC ===\n");

    // Vrátit JSON odpověď
    echo json_encode([
        'status' => 'success',
        'message' => 'Připomínky odeslány',
        'found' => $pocetNalezenych,
        'sent' => $uspesneOdeslano,
        'errors' => $chyby
    ]);

    exit($chyby > 0 ? 1 : 0);

} catch (Exception $e) {
    logMessage("KRITICKÁ CHYBA: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    logMessage("=== KONEC S CHYBOU ===\n");

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit(1);
}
