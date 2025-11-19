<?php
/**
 * CRON Job: Automatické odesílání připomínek termínů
 *
 * Tento skript se spouští každý den v 10:00 a odesílá připomenutí
 * zákazníkům, kteří mají domluvenou návštěvu ZÍTRA.
 *
 * Nastavení CRON (na hostingu):
 * 0 10 * * * /usr/bin/php /cesta/k/moje-stranky/cron_send_reminders.php
 *
 * Nebo pro testování:
 * php /home/user/moje-stranky/cron_send_reminders.php
 */

// Nastavení pro CLI běh
if (php_sapi_name() !== 'cli') {
    // Pokud se spouští přes web, vyžadovat admin přístup
    require_once __DIR__ . '/init.php';

    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        die("PŘÍSTUP ODEPŘEN: Tento skript může spustit pouze administrátor nebo CRON.");
    }
} else {
    // CLI režim - načíst pouze potřebné soubory
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/includes/EmailQueue.php';
}

// Logování
$logFile = __DIR__ . '/logs/cron_reminders.log';

/**
 * Zalogovat zprávu
 */
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage; // Také vypsat na konzolu
}

try {
    logMessage("=== START: Kontrola návštěv pro připomenutí ===");

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
        exit(0);
    }

    // Načíst šablonu pro připomenutí
    $stmtTemplate = $pdo->prepare("
        SELECT subject, template
        FROM wgs_notifications
        WHERE id = 'appointment_reminder_customer'
        LIMIT 1
    ");
    $stmtTemplate->execute();
    $template = $stmtTemplate->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception("Šablona 'appointment_reminder_customer' nebyla nalezena v databázi!");
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
        $datumZobrazeni = date('d.m.Y', strtotime($navsteva['termin']));
        $denVTydnu = [
            'Monday' => 'pondělí',
            'Tuesday' => 'úterý',
            'Wednesday' => 'středa',
            'Thursday' => 'čtvrtek',
            'Friday' => 'pátek',
            'Saturday' => 'sobota',
            'Sunday' => 'neděle'
        ];
        $denCesky = $denVTydnu[date('l', strtotime($navsteva['termin']))] ?? date('l', strtotime($navsteva['termin']));

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
            logMessage("✓ Email přidán do fronty pro: {$navsteva['email']}");

        } catch (Exception $e) {
            $chyby++;
            logMessage("✗ CHYBA při přidávání emailu pro {$navsteva['email']}: " . $e->getMessage());
        }
    }

    logMessage("---");
    logMessage("SOUHRN:");
    logMessage("  Nalezeno návštěv: {$pocetNalezenych}");
    logMessage("  Úspěšně přidáno do fronty: {$uspesneOdeslano}");
    logMessage("  Chyby: {$chyby}");
    logMessage("=== KONEC ===\n");

    // Vrátit exit code podle výsledku
    exit($chyby > 0 ? 1 : 0);

} catch (Exception $e) {
    logMessage("KRITICKÁ CHYBA: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    logMessage("=== KONEC S CHYBOU ===\n");
    exit(1);
}
