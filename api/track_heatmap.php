<?php
/**
 * Track Heatmap API - Agregace dat pro heatmap
 *
 * Tento endpoint přijímá click a scroll data z Modulu #5 a agreguje je
 * do heatmap tabulek pro rychlou vizualizaci.
 *
 * Používá UPSERT pattern (INSERT ON DUPLICATE KEY UPDATE) pro efektivní agregaci.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #6 - Heatmap Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/geoip_helper.php';

// Centrálně zachytit fatální chyby a vrátit JSON místo prázdného těla
// SECURITY: Detaily logovat, ale NEPOSÍLAT klientovi
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    // Logovat detaily pro debugging (pouze server-side)
    error_log(sprintf(
        '[Track Heatmap FATAL] %s in %s on line %d',
        $error['message'],
        $error['file'],
        $error['line']
    ));

    // Vyčistit buffery
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    // SECURITY: Pouze generická zpráva klientovi, žádné detaily!
    $payload = [
        'status' => 'error',
        'message' => 'Neočekávaná chyba serveru'
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
});

header('Content-Type: application/json; charset=utf-8');

// CORS headers
header('Access-Control-Allow-Origin: https://www.wgs-service.cz');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// OPTIONS request pro CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Pouze POST metoda
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Pouze POST metoda je povolena', 405);
}

try {
    $pdo = getDbConnection();

    // Rate limiting - 1000 požadavků za hodinu per IP
    // OPRAVA: Použít skutečnou IP klienta (s podporou Cloudflare/proxy)
    $clientIp = GeoIPHelper::ziskejKlientIP();
    $rateLimiter = new RateLimiter($pdo);

    $rateLimitResult = $rateLimiter->checkLimit($clientIp, 'track_heatmap', [
        'max_attempts' => 1000,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);

    if (!$rateLimitResult['allowed']) {
        sendJsonError($rateLimitResult['message'], 429);
    }

    // Získání JSON dat
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (!$inputData) {
        $inputData = $_POST;
    }

    // POZNÁMKA: CSRF validace NENÍ potřeba pro heatmap tracking
    // Důvody:
    // 1. Je to pasivní tracking (read-only data collection)
    // 2. Každý návštěvník trackuje své vlastní kliky
    // 3. Není zde žádná "nežádoucí akce" kterou by CSRF mohlo zneužít
    // 4. Rate limiting (1000 req/hour) už chrání před abuse
    // 5. Session cookies nefungují správně při AJAX calls ze stránek
    //
    // CSRF je relevantní pro: DELETE, UPDATE, CREATE akcí admin operací
    // CSRF NENÍ relevantní pro: Pasivní analytics tracking

    // ✅ PERFORMANCE FIX: Uvolnit session lock pro paralelní zpracování
    // Audit 2025-11-24: Heatmap tracking - vysoká frekvence requestů
    session_write_close();

    // ========================================
    // BLACKLIST IP ADRES (admin/vlastník)
    // ========================================
    // Získat IP klienta
    $clientIpForBlacklist = GeoIPHelper::ziskejKlientIP();

    // 1. Kontrola databázové tabulky wgs_analytics_ignored_ips
    $stmtIgnored = $pdo->prepare("
        SELECT id FROM wgs_analytics_ignored_ips
        WHERE ip_address = :ip
        LIMIT 1
    ");
    $stmtIgnored->execute(['ip' => $clientIpForBlacklist]);
    if ($stmtIgnored->fetch()) {
        sendJsonSuccess('OK', ['ignored' => true, 'reason' => 'db_blacklist']);
    }

    // 2. Hardcoded blacklist (včetně IPv6 prefix matchingu)
    $blacklistedIPs = [
        // IPv6 - Radek domácí
        '2a00:11b1:10a2:5773:a4d3:7603:899e:d2f3',
        '2a00:11b1:10a2:5773:',  // IPv6 prefix pro celou síť
        // IPv4 - Radek domácí
        '46.135.89.44',
        '46.135.14.161',
        // IPv6 - VPN/proxy
        '2a09:bac2:2756:137::1f:ac',
        '2a09:bac2:2756:',  // IPv6 prefix
        // IPv4 - VPN/proxy
        '104.28.114.10',
    ];

    // Kontrola blacklistu (přesná shoda nebo prefix match pro IPv6)
    foreach ($blacklistedIPs as $blacklistedIp) {
        if ($clientIpForBlacklist === $blacklistedIp || strpos($clientIpForBlacklist, $blacklistedIp) === 0) {
            sendJsonSuccess('OK', ['ignored' => true, 'reason' => 'hardcoded_blacklist']);
        }
    }

    // ========================================
    // BLACKLIST REFERRER DOMÉN
    // ========================================
    // Ignorovat návštěvy z těchto referrer domén (např. GitHub)
    $blacklistedReferrers = [
        'github.com',
        'www.github.com',
        'raw.githubusercontent.com',
        'gist.github.com',
        'github.dev',
        'githubusercontent.com',
    ];

    // Kontrola HTTP_REFERER hlavičky
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referrer)) {
        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        if ($referrerHost) {
            foreach ($blacklistedReferrers as $blacklistedRef) {
                if ($referrerHost === $blacklistedRef || strpos($referrerHost, $blacklistedRef) !== false) {
                    sendJsonSuccess('OK', ['ignored' => true, 'reason' => 'referrer_header']);
                }
            }
        }
    }

    // Kontrola referrer z POST dat
    $referrerPost = $inputData['referrer'] ?? '';
    if (!empty($referrerPost)) {
        $referrerHostPost = parse_url($referrerPost, PHP_URL_HOST);
        if ($referrerHostPost) {
            foreach ($blacklistedReferrers as $blacklistedRef) {
                if ($referrerHostPost === $blacklistedRef || strpos($referrerHostPost, $blacklistedRef) !== false) {
                    sendJsonSuccess('OK', ['ignored' => true, 'reason' => 'referrer_post']);
                }
            }
        }
    }

    // ========================================
    // BLACKLIST INTERNÍCH/ADMIN STRÁNEK
    // ========================================
    // POZN: Zahrnout i verze bez .php (URL rewrite)
    $blacklistedPages = [
        // S příponou .php
        'admin.php',
        'seznam.php',
        'statistiky.php',
        'protokol.php',
        'login.php',
        'registration.php',
        'analytics.php',
        'analytics-heatmap.php',
        'vsechny_tabulky.php',
        // Bez přípony (URL rewrite)
        'admin',
        'seznam',
        'statistiky',
        'protokol',
        'login',
        'registration',
        'analytics',
        'analytics-heatmap',
        // Prefixy
        'kontrola_',
        'pridej_',
        'vycisti_',
        'migrace_',
        'test_',
        'doplnit_',
    ];

    $pageUrlToCheck = $inputData['page_url'] ?? '';
    if (!empty($pageUrlToCheck)) {
        $parsedPageUrl = parse_url($pageUrlToCheck);
        $pagePath = $parsedPageUrl['path'] ?? '';
        $pageName = basename($pagePath);

        foreach ($blacklistedPages as $blacklistedPage) {
            if ($pageName === $blacklistedPage || strpos($pageName, $blacklistedPage) === 0) {
                sendJsonSuccess('OK', ['ignored' => true, 'reason' => 'admin_page']);
            }
        }
    }

    // ========================================
    // GEOLOKACE Z IP ADRESY
    // ========================================
    $geoData = null;
    $countryCode = null;
    $city = null;
    $latitude = null;
    $longitude = null;

    // Získat skutečnou IP klienta (s podporou proxy/Cloudflare)
    $realClientIp = GeoIPHelper::ziskejKlientIP();

    // Získat geolokaci (cached, max 3s timeout)
    $geoData = GeoIPHelper::ziskejLokaci($realClientIp);

    if ($geoData !== null) {
        $countryCode = $geoData['country_code'] ?? null;
        $city = $geoData['city'] ?? null;
        $latitude = $geoData['lat'] ?? null;
        $longitude = $geoData['lng'] ?? null;
    }

    // ========================================
    // VALIDACE POVINNÝCH POLÍ
    // ========================================
    if (empty($inputData['page_url'])) {
        sendJsonError('Chybí povinné pole: page_url', 400);
    }

    if (empty($inputData['device_type'])) {
        sendJsonError('Chybí povinné pole: device_type', 400);
    }

    // ========================================
    // SANITIZACE A VALIDACE DAT
    // ========================================
    // Bezpečná URL validace (FILTER_VALIDATE_URL selže na hash/query/diakritice)
    $pageUrlRaw = trim($inputData['page_url'] ?? '');

    // Kontrola prázdné URL
    if (empty($pageUrlRaw)) {
        sendJsonError('Prázdná URL adresa', 400);
    }

    // Odstranit hash a query parametry před parsováním
    $pageUrlClean = strtok($pageUrlRaw, '?#');

    // Kontrola před parse_url - musí být neprázdný string
    if (!is_string($pageUrlClean) || empty($pageUrlClean)) {
        sendJsonError('Neplatná URL adresa (prázdná)', 400);
    }

    // Bezpečné parse_url - může vrátit false
    $parsedUrl = @parse_url($pageUrlClean);

    // Striktní kontrola výsledku parse_url
    if ($parsedUrl === false || !is_array($parsedUrl)) {
        sendJsonError('Neplatná URL adresa (nelze parsovat)', 400);
    }

    if (!isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
        sendJsonError('Neplatná URL adresa (chybí scheme nebo host)', 400);
    }

    // Validace scheme
    $scheme = strtolower($parsedUrl['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        sendJsonError('Neplatná URL adresa (nepovolený protokol)', 400);
    }

    // Bezpečná normalizace URL
    $host = $parsedUrl['host'];
    $path = $parsedUrl['path'] ?? '/';
    $normalizedUrl = $scheme . '://' . $host . $path;

    // Validace device_type
    $deviceType = sanitizeInput($inputData['device_type']);
    $povoleneDeviceTypes = ['desktop', 'mobile', 'tablet'];
    if (!in_array($deviceType, $povoleneDeviceTypes)) {
        sendJsonError('Neplatný device_type (povolené: desktop, mobile, tablet)', 400);
    }

    $clicksAggregated = 0;
    $scrollBucketsUpdated = 0;

    // ========================================
    // AGREGACE CLICK DATA
    // ========================================
    if (!empty($inputData['clicks']) && is_array($inputData['clicks'])) {
        foreach ($inputData['clicks'] as $click) {
            // Akceptovat oba formáty: x/y (z JS) nebo x_percent/y_percent (starší formát)
            $xValue = $click['x'] ?? $click['x_percent'] ?? null;
            $yValue = $click['y'] ?? $click['y_percent'] ?? null;

            if ($xValue === null || $yValue === null) {
                continue; // Přeskočit neplatné kliky
            }

            $xPercent = round((float)$xValue, 2);
            $yPercent = round((float)$yValue, 2);

            // Validace rozsahu (0-100%)
            if ($xPercent < 0 || $xPercent > 100 || $yPercent < 0 || $yPercent > 100) {
                continue;
            }

            // Viewport data (optional)
            $viewportWidth = isset($click['viewport_width']) ? (int)$click['viewport_width'] : null;
            $viewportHeight = isset($click['viewport_height']) ? (int)$click['viewport_height'] : null;

            // UPSERT: INSERT nebo UPDATE click_count
            // POZN: VALUES() nefunguje v MariaDB 10.3+ - vrací NULL
            // Řešení: Rolling average pomocí více parametrů
            // Vzorec: new_avg = (old_avg * count + new_value) / (count + 1)
            $stmt = $pdo->prepare("
                INSERT INTO wgs_analytics_heatmap_clicks (
                    page_url,
                    device_type,
                    click_x_percent,
                    click_y_percent,
                    click_count,
                    viewport_width_avg,
                    viewport_height_avg,
                    country_code,
                    city,
                    latitude,
                    longitude,
                    first_click,
                    last_click
                ) VALUES (
                    :page_url,
                    :device_type,
                    :click_x_percent,
                    :click_y_percent,
                    1,
                    :viewport_width,
                    :viewport_height,
                    :country_code,
                    :city,
                    :latitude,
                    :longitude,
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    click_count = click_count + 1,
                    viewport_width_avg = IF(:vw_check IS NOT NULL,
                        (COALESCE(viewport_width_avg, :vw_default) * click_count + :vw_new) / (click_count + 1),
                        viewport_width_avg
                    ),
                    viewport_height_avg = IF(:vh_check IS NOT NULL,
                        (COALESCE(viewport_height_avg, :vh_default) * click_count + :vh_new) / (click_count + 1),
                        viewport_height_avg
                    ),
                    country_code = COALESCE(country_code, :country_code_upd),
                    city = COALESCE(city, :city_upd),
                    latitude = COALESCE(latitude, :latitude_upd),
                    longitude = COALESCE(longitude, :longitude_upd),
                    last_click = NOW()
            ");

            $stmt->execute([
                'page_url' => $normalizedUrl,
                'device_type' => $deviceType,
                'click_x_percent' => $xPercent,
                'click_y_percent' => $yPercent,
                'viewport_width' => $viewportWidth,
                'viewport_height' => $viewportHeight,
                'country_code' => $countryCode,
                'city' => $city,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'vw_check' => $viewportWidth,
                'vw_default' => $viewportWidth,
                'vw_new' => $viewportWidth,
                'vh_check' => $viewportHeight,
                'vh_default' => $viewportHeight,
                'vh_new' => $viewportHeight,
                'country_code_upd' => $countryCode,
                'city_upd' => $city,
                'latitude_upd' => $latitude,
                'longitude_upd' => $longitude
            ]);

            $clicksAggregated++;
        }
    }

    // ========================================
    // AGREGACE SCROLL DATA
    // ========================================
    // Akceptovat oba formáty: scrolls (z JS) nebo scroll_depths (starší formát)
    $scrollData = $inputData['scrolls'] ?? $inputData['scroll_depths'] ?? [];
    if (!empty($scrollData) && is_array($scrollData)) {
        foreach ($scrollData as $scrollDepth) {
            $depth = (int)$scrollDepth;

            // Validace rozsahu (0-100)
            if ($depth < 0 || $depth > 100) {
                continue;
            }

            // Bucket do 10% intervalů (0, 10, 20, ..., 100)
            $bucket = floor($depth / 10) * 10;

            // Viewport data (optional)
            $viewportWidth = isset($inputData['viewport_width']) ? (int)$inputData['viewport_width'] : null;
            $viewportHeight = isset($inputData['viewport_height']) ? (int)$inputData['viewport_height'] : null;

            // UPSERT: INSERT nebo UPDATE reach_count
            // POZN: VALUES() nefunguje v MariaDB 10.3+ - vrací NULL
            // Řešení: Rolling average pomocí více parametrů
            $stmt = $pdo->prepare("
                INSERT INTO wgs_analytics_heatmap_scroll (
                    page_url,
                    device_type,
                    scroll_depth_bucket,
                    reach_count,
                    viewport_width_avg,
                    viewport_height_avg,
                    country_code,
                    city,
                    latitude,
                    longitude,
                    first_reach,
                    last_reach
                ) VALUES (
                    :page_url,
                    :device_type,
                    :scroll_depth_bucket,
                    1,
                    :viewport_width,
                    :viewport_height,
                    :country_code,
                    :city,
                    :latitude,
                    :longitude,
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    reach_count = reach_count + 1,
                    viewport_width_avg = IF(:vw_check IS NOT NULL,
                        (COALESCE(viewport_width_avg, :vw_default) * reach_count + :vw_new) / (reach_count + 1),
                        viewport_width_avg
                    ),
                    viewport_height_avg = IF(:vh_check IS NOT NULL,
                        (COALESCE(viewport_height_avg, :vh_default) * reach_count + :vh_new) / (reach_count + 1),
                        viewport_height_avg
                    ),
                    country_code = COALESCE(country_code, :country_code_upd),
                    city = COALESCE(city, :city_upd),
                    latitude = COALESCE(latitude, :latitude_upd),
                    longitude = COALESCE(longitude, :longitude_upd),
                    last_reach = NOW()
            ");

            $stmt->execute([
                'page_url' => $normalizedUrl,
                'device_type' => $deviceType,
                'scroll_depth_bucket' => $bucket,
                'viewport_width' => $viewportWidth,
                'viewport_height' => $viewportHeight,
                'country_code' => $countryCode,
                'city' => $city,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'vw_check' => $viewportWidth,
                'vw_default' => $viewportWidth,
                'vw_new' => $viewportWidth,
                'vh_check' => $viewportHeight,
                'vh_default' => $viewportHeight,
                'vh_new' => $viewportHeight,
                'country_code_upd' => $countryCode,
                'city_upd' => $city,
                'latitude_upd' => $latitude,
                'longitude_upd' => $longitude
            ]);

            $scrollBucketsUpdated++;
        }
    }

    // ========================================
    // LOGOVÁNÍ (debug)
    // ========================================
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log(sprintf(
            '[Track Heatmap] Page: %s | Device: %s | Clicks: %d | Scroll Buckets: %d',
            $normalizedUrl,
            $deviceType,
            $clicksAggregated,
            $scrollBucketsUpdated
        ));
    }

    // ========================================
    // ODPOVĚĎ
    // ========================================
    sendJsonSuccess('Heatmap data agregována', [
        'clicks_aggregated' => $clicksAggregated,
        'scroll_buckets_updated' => $scrollBucketsUpdated,
        'page_url' => $normalizedUrl,
        'device_type' => $deviceType,
        'geo' => $geoData ? [
            'country' => $countryCode,
            'city' => $city
        ] : null
    ]);

} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    error_log('Track Heatmap API Error: ' . $errorMsg);
    error_log('Stack trace: ' . $e->getTraceAsString());

    // Detekce specifických chyb pro lepší debugging
    if (strpos($errorMsg, "doesn't exist") !== false || strpos($errorMsg, 'Base table or view not found') !== false) {
        // Tabulka neexistuje - spusťte migraci
        error_log('Track Heatmap: TABULKY NEEXISTUJÍ - spusťte /migrace_module6_heatmaps.php?execute=1');
        sendJsonError('Heatmap tabulky neexistují. Spusťte migraci.', 500);
    } else {
        sendJsonError('Chyba při zpracování požadavku', 500);
    }

} catch (Exception $e) {
    error_log('Track Heatmap API Unexpected Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonError('Neočekávaná chyba serveru', 500);
}

// POZNÁMKA: sanitizeInput() je definována v config/config.php (loadována přes init.php)
// NEPOUŽÍVAT lokální definici - způsobí "Cannot redeclare sanitizeInput()" fatal error!
?>
