<?php
/**
 * Nastavení CRON_SECRET_KEY v .env souboru
 *
 * Tento skript vygeneruje bezpečný náhodný klíč a zapíše jej do .env
 * Používá se pro zabezpečení webcron endpointů:
 *   - cron/send-reminders.php?key=KLIC
 *   - cron/process-email-queue.php?key=KLIC
 *
 * Spusťte jednou po nasazení, pak skript smažte nebo ponechte (přístup je jen pro adminy).
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

$envSoubor = __DIR__ . '/.env';
$aktualniKlic = getenv('CRON_SECRET_KEY') ?: null;

// --- Zpracování formuláře ---
$zprava = null;
$typZpravy = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akce'])) {
    // CSRF kontrola
    require_once __DIR__ . '/includes/csrf_helper.php';
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $zprava = 'Neplatný CSRF token. Obnovte stránku a zkuste znovu.';
        $typZpravy = 'chyba';
    } else {
        if ($_POST['akce'] === 'uloz') {
            $novyKlic = trim($_POST['klic'] ?? '');

            if (strlen($novyKlic) < 32) {
                $zprava = 'Klíč musí mít alespoň 32 znaků. Použijte vygenerovaný klíč.';
                $typZpravy = 'chyba';
            } elseif (!file_exists($envSoubor)) {
                $zprava = 'Soubor .env nenalezen. Kontaktujte správce serveru.';
                $typZpravy = 'chyba';
            } else {
                $obsah = file_get_contents($envSoubor);

                if (preg_match('/^CRON_SECRET_KEY=.*/m', $obsah)) {
                    // Aktualizovat existující řádek
                    $obsah = preg_replace('/^CRON_SECRET_KEY=.*/m', 'CRON_SECRET_KEY=' . $novyKlic, $obsah);
                } else {
                    // Přidat nový řádek na konec
                    $obsah = rtrim($obsah) . "\n\n# Tajný klíč pro webcron endpointy\nCRON_SECRET_KEY=" . $novyKlic . "\n";
                }

                if (file_put_contents($envSoubor, $obsah) !== false) {
                    $zprava = 'CRON_SECRET_KEY byl úspěšně uložen do .env';
                    $typZpravy = 'ok';
                    $aktualniKlic = $novyKlic;
                    // Nastavit do aktuálního procesu
                    putenv("CRON_SECRET_KEY={$novyKlic}");
                } else {
                    $zprava = 'Nepodařilo se zapsat do .env. Zkontrolujte práva k souboru.';
                    $typZpravy = 'chyba';
                }
            }
        }
    }
}

// Vygenerovat návrh nového klíče pro zobrazení
$navrhKlice = bin2hex(random_bytes(32)); // 64 znaků hex

// Sestavit webcron URL
$protokol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'www.wgs-service.cz';
$webcronUrlReminders = $protokol . '://' . $host . '/cron/send-reminders.php?key=';
$webcronUrlEmail = $protokol . '://' . $host . '/cron/process-email-queue.php?key=';

?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nastavení CRON klíče</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
            color: #222;
        }
        .container {
            background: #fff;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.12);
        }
        h1 {
            font-size: 1.5rem;
            border-bottom: 2px solid #222;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        h2 {
            font-size: 1.1rem;
            margin: 25px 0 10px;
            color: #333;
        }
        .stav {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .stav-ok   { background: #eee; color: #222; border: 1px solid #999; }
        .stav-chybi { background: #222; color: #fff; }
        .zprava-ok   { background: #eee; border: 1px solid #999; color: #222; padding: 12px 16px; border-radius: 5px; margin: 15px 0; }
        .zprava-chyba { background: #222; border: 1px solid #666; color: #fff; padding: 12px 16px; border-radius: 5px; margin: 15px 0; }
        .klic-pole {
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            background: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px 14px;
            width: 100%;
            box-sizing: border-box;
            color: #111;
        }
        .klic-pole:focus { outline: 2px solid #333; border-color: #333; }
        .url-pole {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            width: 100%;
            box-sizing: border-box;
            color: #444;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 10px 22px;
            background: #222;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            margin: 5px 5px 5px 0;
        }
        .btn:hover { background: #444; }
        .btn-sekundarni { background: #888; }
        .btn-sekundarni:hover { background: #666; }
        .poznamka {
            font-size: 0.85rem;
            color: #666;
            margin: 6px 0;
            line-height: 1.5;
        }
        .sekce { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        td, th { padding: 9px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        th { background: #f5f5f5; font-weight: 600; }
        td:first-child { font-weight: 600; white-space: nowrap; }
        code { font-family: 'Courier New', monospace; background: #f0f0f0; padding: 1px 5px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="container">

    <h1>Nastavení CRON_SECRET_KEY</h1>

    <?php if ($zprava): ?>
        <div class="zprava-<?= $typZpravy ?>">
            <?= htmlspecialchars($zprava, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Aktuální stav -->
    <h2>Aktuální stav</h2>
    <table>
        <tr>
            <td>CRON_SECRET_KEY</td>
            <td>
                <?php if ($aktualniKlic): ?>
                    <span class="stav stav-ok">Nastaven</span>
                    &nbsp; (délka: <?= strlen($aktualniKlic) ?> znaků)
                <?php else: ?>
                    <span class="stav stav-chybi">CHYBI - cron nefunguje!</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>Soubor .env</td>
            <td>
                <?php if (file_exists($envSoubor)): ?>
                    <span class="stav stav-ok">Nalezen</span>
                    &nbsp; (<?= number_format(filesize($envSoubor)) ?> B)
                <?php else: ?>
                    <span class="stav stav-chybi">Soubor neexistuje</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>Zápis do .env</td>
            <td>
                <?php if (is_writable($envSoubor)): ?>
                    <span class="stav stav-ok">Povoleno</span>
                <?php else: ?>
                    <span class="stav stav-chybi">Zamknuto - nelze zapsat</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Formulář pro nastavení klíče -->
    <div class="sekce">
        <h2>Nastavit nový klíč</h2>
        <p class="poznamka">Vygenerovaný klíč níže je bezpečný (256 bitů náhodnosti). Pokud chcete vlastní, musí mít minimálně 32 znaků.</p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="akce" value="uloz">

            <label for="klic" style="display:block; font-weight:600; margin-bottom:6px;">
                Klíč (zkopírujte nebo upravte):
            </label>
            <input
                type="text"
                id="klic"
                name="klic"
                class="klic-pole"
                value="<?= htmlspecialchars($navrhKlice, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="off"
                spellcheck="false"
            >
            <p class="poznamka" style="margin-top:6px;">
                Délka: <span id="delkaKlice"><?= strlen($navrhKlice) ?></span> znaků
            </p>

            <button type="submit" class="btn">Uložit do .env</button>
            <button type="button" class="btn btn-sekundarni" onclick="vygenerujNovy()">Vygenerovat jiný</button>
        </form>
    </div>

    <!-- Webcron URL -->
    <?php if ($aktualniKlic): ?>
    <div class="sekce">
        <h2>Webcron URL (nastavte na hostingu)</h2>
        <p class="poznamka">Tyto URL zadejte do administrace webhostingu (sekce Cron / Webcron):</p>

        <p class="poznamka" style="margin-top:15px;"><strong>1. Připomínky termínů</strong> (spouštět denně v 10:00):</p>
        <input type="text" class="url-pole" readonly
            value="<?= htmlspecialchars($webcronUrlReminders . $aktualniKlic, ENT_QUOTES, 'UTF-8') ?>"
            onclick="this.select()">

        <p class="poznamka" style="margin-top:12px;"><strong>2. Fronta emailů</strong> (spouštět každých 15 minut):</p>
        <input type="text" class="url-pole" readonly
            value="<?= htmlspecialchars($webcronUrlEmail . $aktualniKlic, ENT_QUOTES, 'UTF-8') ?>"
            onclick="this.select()">

        <p class="poznamka" style="margin-top:10px;">
            Kliknutím na pole URL se vybere celý text pro snadné kopírování.
        </p>
    </div>
    <?php endif; ?>

    <div class="sekce">
        <p class="poznamka">
            Po nastaveni klice doporucujeme zachovat tento skript — slouzi jako sprava nastaveni. Pristup maji pouze administratori.
        </p>
        <a href="/admin.php" class="btn btn-sekundarni">Zpet do adminu</a>
    </div>

</div>

<script>
function vygenerujNovy() {
    var pole = document.getElementById('klic');
    // Generovat 64 náhodných hex znaků v prohlížeči
    var pole32 = new Uint8Array(32);
    window.crypto.getRandomValues(pole32);
    var hex = Array.from(pole32).map(function(b) {
        return b.toString(16).padStart(2, '0');
    }).join('');
    pole.value = hex;
    document.getElementById('delkaKlice').textContent = hex.length;
}

document.getElementById('klic').addEventListener('input', function() {
    document.getElementById('delkaKlice').textContent = this.value.length;
});
</script>
</body>
</html>
