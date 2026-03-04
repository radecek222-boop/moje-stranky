<?php
/**
 * WGS Sentry Handler - Lehká integrace error monitoringu
 *
 * Odesílá PHP chyby a výjimky do Sentry bez Composer závislosti.
 * Používá přímé cURL volání na Sentry HTTP API.
 *
 * Aktivace: nastavit SENTRY_DSN v .env souboru
 * DSN formát: https://PUBLIC_KEY@sentry.io/PROJECT_ID
 */

// Globální konfigurace Sentry (nastavena při inicializaci)
$GLOBALS['_wgs_sentry'] = null;

/**
 * Rozloží Sentry DSN na komponenty potřebné pro API volání
 */
function parsujSentryDsn(string $dsn): ?array
{
    $parsovany = parse_url($dsn);
    if (!$parsovany) {
        return null;
    }

    $verejnyKlic = $parsovany['user'] ?? null;
    $hostitel    = $parsovany['host'] ?? null;
    $projektId   = ltrim($parsovany['path'] ?? '', '/');

    if (!$verejnyKlic || !$hostitel || !$projektId) {
        return null;
    }

    return [
        'verejny_klic' => $verejnyKlic,
        'url_store'    => "https://{$hostitel}/api/{$projektId}/store/",
    ];
}

/**
 * Inicializuje Sentry monitoring.
 * Registruje shutdown funkci pro zachycení fatálních chyb.
 * Neovlivňuje existující error_handler.php.
 */
function inicializovatSentry(string $dsn): void
{
    $konfig = parsujSentryDsn($dsn);
    if (!$konfig) {
        error_log('WGS Sentry: Neplatný DSN formát');
        return;
    }

    $GLOBALS['_wgs_sentry'] = $konfig;

    // Zachytit fatální chyby při ukončení skriptu (E_ERROR, E_PARSE, atd.)
    // Tato metoda NEKONFLIKTUJE s existujícím set_error_handler v error_handler.php
    register_shutdown_function(function () {
        $chyba = error_get_last();
        if (!$chyba) {
            return;
        }

        // Zachytit jen fatální chyby, ne warnings/notices
        $fatalniTypy = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($chyba['type'], $fatalniTypy, true)) {
            return;
        }

        if (!isset($GLOBALS['_wgs_sentry'])) {
            return;
        }

        $e = new ErrorException(
            $chyba['message'],
            0,
            $chyba['type'],
            $chyba['file'],
            $chyba['line']
        );

        odesilatVyjimkuDoSentry($e, $GLOBALS['_wgs_sentry']);
    });
}

/**
 * Manuálně odešle výjimku do Sentry.
 * Použití v catch blocích: zachytitDoSentry($e);
 */
function zachytitDoSentry(Throwable $e): void
{
    if (!isset($GLOBALS['_wgs_sentry'])) {
        return;
    }

    odesilatVyjimkuDoSentry($e, $GLOBALS['_wgs_sentry']);
}

/**
 * Sestaví a odešle událost do Sentry API
 */
function odesilatVyjimkuDoSentry(Throwable $e, array $konfig): void
{
    if (!extension_loaded('curl')) {
        return;
    }

    // Sestavit stack trace
    $snimkyZasobniku = [];
    foreach (array_reverse($e->getTrace()) as $snimek) {
        $snimkyZasobniku[] = [
            'filename' => $snimek['file'] ?? '[internal]',
            'lineno'   => $snimek['line'] ?? 0,
            'function' => ($snimek['class'] ?? '') . ($snimek['type'] ?? '') . ($snimek['function'] ?? ''),
        ];
    }

    // Přidat aktuální pozici kde výjimka nastala
    $snimkyZasobniku[] = [
        'filename' => $e->getFile(),
        'lineno'   => $e->getLine(),
        'function' => get_class($e),
    ];

    // URL aktuálního požadavku
    $protokol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $aktualniUrl = $protokol . '://' . ($_SERVER['HTTP_HOST'] ?? 'wgs-service.cz') . ($_SERVER['REQUEST_URI'] ?? '/');

    // Sestavit Sentry událost
    $udalost = [
        'event_id'    => str_replace('-', '', sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        )),
        'timestamp'   => gmdate('Y-m-d\TH:i:s\Z'),
        'level'       => 'error',
        'platform'    => 'php',
        'sdk'         => ['name' => 'wgs-php', 'version' => '1.0'],
        'environment' => (function_exists('getEnvValue') ? (getEnvValue('ENVIRONMENT') ?? 'production') : 'production'),
        'server_name' => 'wgs-service.cz',
        'request'     => [
            'url'    => $aktualniUrl,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        ],
        'exception'   => [
            'values' => [[
                'type'       => get_class($e),
                'value'      => $e->getMessage(),
                'stacktrace' => ['frames' => $snimkyZasobniku],
            ]],
        ],
    ];

    $json        = json_encode($udalost, JSON_UNESCAPED_UNICODE);
    $hlavickaAuth = sprintf(
        'Sentry sentry_version=7, sentry_key=%s, sentry_client=wgs-php/1.0',
        $konfig['verejny_klic']
    );

    // Odeslat přes cURL (timeout 3s - nechceme blokovat requesty)
    $ch = curl_init($konfig['url_store']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "X-Sentry-Auth: {$hlavickaAuth}",
        ],
    ]);

    curl_exec($ch);

    // Zalogovat pokud Sentry API vrátilo chybu
    $httpKod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpKod !== 200) {
        error_log("WGS Sentry: Odesílání selhalo (HTTP {$httpKod}) pro chybu: " . $e->getMessage());
    }

    curl_close($ch);
}
