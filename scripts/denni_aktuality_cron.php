<?php
/**
 * CRON JOB: Denní přehazování pořadí článků v aktualitách
 *
 * Tento skript se spouští každý den v 6:00 ráno
 * a náhodně přeházne pořadí článků v existující aktualitě.
 * NEGENERUJE nový obsah - pouze mění pořadí.
 *
 * Nastavení crontabu:
 * 0 6 * * * /usr/bin/php /home/user/moje-stranky/scripts/denni_aktuality_cron.php >> /home/user/moje-stranky/logs/cron_aktuality.log 2>&1
 */

// Nastavit časovou zónu
date_default_timezone_set('Europe/Prague');

// Logování
$logFile = __DIR__ . '/../logs/cron_aktuality.log';
$startTime = microtime(true);

function logMessage(string $message, string $level = 'INFO'): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

logMessage("========================================");
logMessage("CRON JOB START: Prehazovani poradi clanku");
logMessage("========================================");

// Include init.php
require_once __DIR__ . '/../init.php';

try {
    $pdo = getDbConnection();
    logMessage("Pripojeni k databazi uspesne");

    // Nacist hlavni zaznam aktuality (ID 9 nebo nejnovejsi)
    $stmt = $pdo->query("SELECT id, obsah_cz, obsah_en, obsah_it FROM wgs_natuzzi_aktuality ORDER BY id ASC LIMIT 1");
    $aktualita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aktualita) {
        logMessage("Zadna aktualita nenalezena v databazi", 'ERROR');
        exit(1);
    }

    $aktualitaId = $aktualita['id'];
    logMessage("Nacten zaznam ID: {$aktualitaId}");

    // Zpracovat CZ verzi
    $obsahCZ = $aktualita['obsah_cz'] ?? '';
    $novyObsahCZ = prehazetClanky($obsahCZ);
    logMessage("CZ: Clanky prehazeny");

    // Zpracovat EN verzi (pokud existuje)
    $obsahEN = $aktualita['obsah_en'] ?? '';
    $novyObsahEN = !empty($obsahEN) ? prehazetClanky($obsahEN) : '';
    if (!empty($obsahEN)) {
        logMessage("EN: Clanky prehazeny");
    }

    // Zpracovat IT verzi (pokud existuje)
    $obsahIT = $aktualita['obsah_it'] ?? '';
    $novyObsahIT = !empty($obsahIT) ? prehazetClanky($obsahIT) : '';
    if (!empty($obsahIT)) {
        logMessage("IT: Clanky prehazeny");
    }

    // Aktualizovat zaznam v databazi
    $stmt = $pdo->prepare("
        UPDATE wgs_natuzzi_aktuality
        SET obsah_cz = :obsah_cz,
            obsah_en = :obsah_en,
            obsah_it = :obsah_it,
            datum = CURDATE()
        WHERE id = :id
    ");

    $stmt->execute([
        'obsah_cz' => $novyObsahCZ,
        'obsah_en' => $novyObsahEN,
        'obsah_it' => $novyObsahIT,
        'id' => $aktualitaId
    ]);

    logMessage("Zaznam ID {$aktualitaId} aktualizovan s novym poradim clanku");

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    logMessage("========================================");
    logMessage("CRON JOB END: Uspesne dokonceno za {$duration}s");
    logMessage("========================================");

    exit(0);

} catch (Exception $e) {
    logMessage("CHYBA: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    logMessage("========================================");
    logMessage("CRON JOB END: Selhalo po {$duration}s", 'ERROR');
    logMessage("========================================");

    exit(1);
}

/**
 * Prehodí pořadí článků v markdown obsahu
 *
 * Rozdělí obsah podle ## nadpisů a náhodně je přeuspořádá
 *
 * @param string $obsah Markdown obsah s články
 * @return string Obsah s přeházenými články
 */
function prehazetClanky(string $obsah): string
{
    if (empty($obsah)) {
        return '';
    }

    // Rozdělit podle ## nadpisů (zachovat delimiter)
    $casti = preg_split('/(?=^## )/m', $obsah);

    // Oddělit hlavičku (vše před prvním ##) a články
    $hlavicka = '';
    $clanky = [];

    foreach ($casti as $cast) {
        $cast = trim($cast);
        if (empty($cast)) {
            continue;
        }

        // Pokud začíná ## je to článek
        if (preg_match('/^## /', $cast)) {
            $clanky[] = $cast;
        } else {
            // Jinak je to hlavička (# nadpis, datum, úvod...)
            $hlavicka = $cast;
        }
    }

    // Přeházet pořadí článků
    if (count($clanky) > 1) {
        shuffle($clanky);
        logMessage("  Prehazeno " . count($clanky) . " clanku");
    } else {
        logMessage("  Pouze " . count($clanky) . " clanek - nic k prehazeni");
    }

    // Spojit zpět
    $vysledek = '';
    if (!empty($hlavicka)) {
        $vysledek = $hlavicka . "\n\n";
    }
    $vysledek .= implode("\n\n", $clanky);

    return $vysledek;
}
