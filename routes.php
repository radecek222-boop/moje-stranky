<?php
/**
 * routes.php - Definice tras centrálního routeru
 *
 * Formát:
 *   Router::get('/vzor/{param}', [Ovladac::class, 'metoda']);
 *   Router::post('/vzor', callable);
 *
 * Parametry v závorkách:
 *   {nazev}         → libovolný segment bez lomítka
 *   {nazev:int}     → celé číslo
 *   {nazev:slug}    → a-z0-9-
 *   {nazev:any}     → cokoliv (vč. lomítek)
 *
 * Middleware:
 *   Router::middleware(fn(array $p): bool => overeniPrihlaseni());
 */

// ============================================================
// GLOBÁLNÍ MIDDLEWARE
// ============================================================

// Logování každého requestu přes router (pouze v development)
Router::middleware(function (array $parametry): bool {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_log(sprintf(
            '[Router] %s %s | tenant=%d',
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            tenantId()
        ));
    }
    return true;
});

// ============================================================
// ERROR HANDLERY
// ============================================================

Router::chyba404(function (string $cesta): void {
    if (str_starts_with($cesta, '/api/') || str_starts_with($cesta, '/r/')) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => "Endpoint '{$cesta}' nenalezen"], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(404);
    require_once BASE_PATH . '/includes/page_header_minimal.php';
    ?>
    <main style="max-width:600px;margin:80px auto;padding:20px;font-family:sans-serif;">
        <h1>404 – Stránka nenalezena</h1>
        <p>Požadovaná stránka <code><?php echo htmlspecialchars($cesta, ENT_QUOTES, 'UTF-8'); ?></code> neexistuje.</p>
        <p><a href="/">Zpět na hlavní stránku</a> &nbsp; <a href="/seznam">Seznam reklamací</a></p>
    </main>
    <?php
});

Router::chyba500(function (Throwable $e): void {
    error_log('Router 500: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Interní chyba serveru'], JSON_UNESCAPED_UNICODE);
    }
});

// ============================================================
// ZKRÁCENÉ PŘÍMÉ TRASY (shortlinks)
// ============================================================

/**
 * GET /r/{cislo} → přesměrování na detail reklamace v seznam.php
 * Příklad: /r/WGS-2025-001 → /seznam?detail=WGS-2025-001
 */
Router::get('/r/{cislo:any}', function (array $p): void {
    $cislo = rawurldecode($p['cislo']);
    Router::presmerovat('/seznam?detail=' . rawurlencode($cislo), 302);
});

/**
 * GET /qr/{cislo} → QR redirect s logováním (pro naskenované QR kódy)
 * Příklad: /qr/WGS-2025-001 → nasměruje na reklamaci
 */
Router::get('/qr/{cislo:any}', function (array $p): void {
    $cislo = rawurldecode($p['cislo']);
    // Zalogovat naskenování QR kódu
    error_log(sprintf('[QR] Skenování kódu: %s | IP: %s', $cislo, $_SERVER['REMOTE_ADDR'] ?? '?'));
    Router::presmerovat('/seznam?detail=' . rawurlencode($cislo), 302);
});

// ============================================================
// ZDRAVOTNÍ KONTROLA (health check)
// ============================================================

/**
 * GET /zdravi → JSON stav systému (pro monitoring)
 * Přístup: pouze z localhost nebo se správnou hlavičkou
 */
Router::get('/zdravi', function (array $p): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $jeLokal = in_array($ip, ['127.0.0.1', '::1'], true);
    $maKlic  = ($_SERVER['HTTP_X_HEALTH_KEY'] ?? '') === (getenv('HEALTH_CHECK_KEY') ?: '');

    if (!$jeLokal && !$maKlic) {
        http_response_code(403);
        Router::json(['status' => 'error', 'message' => 'Přístup odepřen'], 403);
        return;
    }

    try {
        $pdo = getDbConnection();
        $pdo->query('SELECT 1');
        $dbStav = 'ok';
    } catch (Exception $e) {
        $dbStav = 'chyba: ' . $e->getMessage();
    }

    Router::json([
        'status'    => 'ok',
        'cas'       => date('Y-m-d H:i:s'),
        'php'       => PHP_VERSION,
        'databaze'  => $dbStav,
        'tenant_id' => tenantId(),
        'prostredi' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
    ]);
});

// ============================================================
// API v2 — UKÁZKOVÁ STRUKTURA (rozšiřovat postupně)
// ============================================================

/**
 * GET /api/v2/trasy → seznam všech registrovaných tras (pouze admin/dev)
 */
Router::get('/api/v2/trasy', function (array $p): void {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        Router::json(['status' => 'error', 'message' => 'Přístup odepřen'], 403);
        return;
    }
    Router::json([
        'status' => 'ok',
        'trasy'  => Router::listTrasy(),
    ]);
});

/**
 * GET /api/v2/tenant → informace o aktuálním tenantovi
 */
Router::get('/api/v2/tenant', function (array $p): void {
    if (!isset($_SESSION['user_id'])) {
        Router::json(['status' => 'error', 'message' => 'Přihlášení vyžadováno'], 401);
        return;
    }
    $tm = TenantManager::getInstance();
    Router::json([
        'status'    => 'ok',
        'tenant_id' => $tm->getTenantId(),
        'slug'      => $tm->getSlug(),
        'nazev'     => $tm->getNazev(),
    ]);
});
