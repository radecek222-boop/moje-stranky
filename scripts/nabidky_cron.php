<?php
/**
 * Cron: CN nabídky - připomínky a automatická expirace
 *
 * Spouštět denně (doporučeno: 09:00)
 *
 * Co dělá:
 * 1. Připomínka 7 dní: Nabídky se stavem 'odeslana' s platností do 7 dní
 *    a bez odeslané připomínky → odešle email zákazníkovi + uloží pripominka_7d_at
 * 2. Automatická expirace: Nabídky se stavem 'odeslana' s vypršenou platností
 *    → změní stav na 'zamitnuta' + odešle informační email zákazníkovi
 */

// Cron může běžet z CLI nebo přes HTTP (ochrana heslem)
if (PHP_SAPI !== 'cli') {
    // Přístup přes HTTP - vyžaduje tajný klíč nebo admin session
    $cronKlic = getenv('CRON_SECRET') ?: 'wgs-cron-nabidky-2025';
    if (($_GET['klic'] ?? '') !== $cronKlic && !isset($_SESSION['is_admin'])) {
        http_response_code(403);
        die('Přístup odepřen');
    }
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/init.php';
require_once $rootDir . '/includes/EmailQueue.php';

// Zahrnout email funkce z nabidka_api.php
require_once $rootDir . '/api/nabidka_api.php';

$zpracovano = 0;
$chyby = 0;
$odeslanoUpominky = 0;
$expiraci = 0;

try {
    $pdo = getDbConnection();

    // ===================================================
    // ČÁST 1: Připomínky 7 dní před expirací
    // ===================================================
    // Podmínky:
    // - stav = 'odeslana'
    // - platnost_do BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    // - pripominka_7d_at IS NULL (ještě nebyla odeslána)
    $stmt = $pdo->query("
        SELECT *
        FROM wgs_nabidky
        WHERE stav = 'odeslana'
          AND platnost_do >= NOW()
          AND platnost_do <= DATE_ADD(NOW(), INTERVAL 7 DAY)
          AND (pripominka_7d_at IS NULL OR pripominka_7d_at = '')
          AND zakaznik_email IS NOT NULL
          AND zakaznik_email != ''
        ORDER BY platnost_do ASC
    ");
    $upominkovyNabidky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("nabidky_cron: Nalezeno " . count($upominkovyNabidky) . " nabídek pro připomínku 7d");

    $emailQueue = new EmailQueue($pdo);

    foreach ($upominkovyNabidky as $nabidka) {
        try {
            // Vygenerovat email
            $emailBody = vygenerujEmailPripominka7dni($nabidka);
            $cisloNabidky = $nabidka['cislo_nabidky'] ?? ('CN-' . $nabidka['id']);
            $platnostDo = date('d.m.Y', strtotime($nabidka['platnost_do']));

            // Přidat do fronty
            $emailQueue->add(
                $nabidka['zakaznik_email'],
                'Připomínka: Cenová nabídka č. ' . $cisloNabidky . ' vyprší ' . $platnostDo,
                $emailBody,
                'cn_pripominka_7d_' . $nabidka['id']
            );

            // Zaznamenat odeslání připomínky
            $stmtUpd = $pdo->prepare("
                UPDATE wgs_nabidky SET pripominka_7d_at = NOW() WHERE id = ?
            ");
            $stmtUpd->execute([$nabidka['id']]);

            $odeslanoUpominky++;
            error_log("nabidky_cron: Připomínka odeslána pro CN {$cisloNabidky} ({$nabidka['zakaznik_email']})");

        } catch (Exception $e) {
            $chyby++;
            error_log("nabidky_cron: Chyba při připomínce CN ID {$nabidka['id']}: " . $e->getMessage());
        }
    }

    // ===================================================
    // ČÁST 2: Automatická expirace (platnost vypršela)
    // ===================================================
    // Podmínky:
    // - stav = 'odeslana'
    // - platnost_do < NOW() (platnost vypršela)
    $stmtExp = $pdo->query("
        SELECT *
        FROM wgs_nabidky
        WHERE stav = 'odeslana'
          AND platnost_do < NOW()
          AND zakaznik_email IS NOT NULL
          AND zakaznik_email != ''
        ORDER BY platnost_do ASC
    ");
    $expirovaneNabidky = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    error_log("nabidky_cron: Nalezeno " . count($expirovaneNabidky) . " nabídek k automatické expiraci");

    foreach ($expirovaneNabidky as $nabidka) {
        try {
            $cisloNabidky = $nabidka['cislo_nabidky'] ?? ('CN-' . $nabidka['id']);

            // Změní stav na 'zamitnuta' s příznakem automatické expirace (zamitnuto_kym = 'admin')
            $stmtUpd = $pdo->prepare("
                UPDATE wgs_nabidky
                SET stav = 'zamitnuta',
                    zamitnuta_at = NOW(),
                    zamitnuto_ip = 'cron',
                    zamitnuto_kym = 'admin'
                WHERE id = ?
            ");
            $stmtUpd->execute([$nabidka['id']]);

            // Informační email zákazníkovi
            $emailBody = vygenerujEmailAutoExpirace($nabidka);
            $emailQueue->add(
                $nabidka['zakaznik_email'],
                'Cenová nabídka č. ' . $cisloNabidky . ' expirovala',
                $emailBody,
                'cn_expirace_' . $nabidka['id']
            );

            $expiraci++;
            error_log("nabidky_cron: Nabídka {$cisloNabidky} expirovala, stav → zamitnuta ({$nabidka['zakaznik_email']})");

        } catch (Exception $e) {
            $chyby++;
            error_log("nabidky_cron: Chyba při expiraci CN ID {$nabidka['id']}: " . $e->getMessage());
        }
    }

    $zpracovano = $odeslanoUpominky + $expiraci;

    error_log("nabidky_cron: Hotovo - připomínky: {$odeslanoUpominky}, expirace: {$expiraci}, chyby: {$chyby}");

    if (PHP_SAPI !== 'cli') {
        echo json_encode([
            'status' => 'success',
            'pripominky' => $odeslanoUpominky,
            'expirace' => $expiraci,
            'chyby' => $chyby
        ]);
    }

} catch (Exception $e) {
    error_log("nabidky_cron: Kritická chyba: " . $e->getMessage());
    $chyby++;

    if (PHP_SAPI !== 'cli') {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
