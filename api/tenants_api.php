<?php
/**
 * Tenants API - Správa multi-tenant konfigurací
 *
 * Akce:
 *   seznam    GET  - seznam všech tenantů
 *   detail    GET  - detail jednoho tenanta
 *   vytvorit  POST - nový tenant
 *   upravit   POST - editace existujícího tenanta
 *   smazat    POST - deaktivace tenanta (soft delete)
 *
 * Přístup: pouze admin
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/TenantManager.php';

header('Content-Type: application/json; charset=utf-8');

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen', 403);
}

try {
    $pdo   = getDbConnection();
    $akce  = $_GET['akce'] ?? $_POST['akce'] ?? '';

    // GET akce nevyžadují CSRF
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            sendJsonError('Neplatný CSRF token', 403);
        }
    }

    switch ($akce) {

        // ============================================================
        case 'seznam':
        // ============================================================
            $stmt = $pdo->query(
                "SELECT tenant_id, slug, nazev, domena, je_aktivni, datum_vytvoreni,
                        (SELECT COUNT(*) FROM wgs_reklamace r WHERE r.tenant_id = t.tenant_id) AS pocet_reklamaci,
                        (SELECT COUNT(*) FROM wgs_users u WHERE u.tenant_id = t.tenant_id) AS pocet_uzivatelu
                 FROM wgs_tenants t
                 ORDER BY nazev"
            );
            sendJsonSuccess('OK', ['tenanti' => $stmt->fetchAll()]);
            break;

        // ============================================================
        case 'detail':
        // ============================================================
            $tenantId = (int) ($_GET['tenant_id'] ?? 0);
            if ($tenantId <= 0) {
                sendJsonError('Chybí tenant_id');
            }

            $stmt = $pdo->prepare(
                "SELECT tenant_id, slug, nazev, domena, nastaveni_json, je_aktivni, datum_vytvoreni
                 FROM wgs_tenants WHERE tenant_id = :id"
            );
            $stmt->execute([':id' => $tenantId]);
            $tenant = $stmt->fetch();

            if (!$tenant) {
                sendJsonError('Tenant nenalezen', 404);
            }

            // Dekódovat nastavení
            if ($tenant['nastaveni_json']) {
                $tenant['nastaveni'] = json_decode($tenant['nastaveni_json'], true) ?? [];
            } else {
                $tenant['nastaveni'] = [];
            }
            unset($tenant['nastaveni_json']);

            sendJsonSuccess('OK', ['tenant' => $tenant]);
            break;

        // ============================================================
        case 'vytvorit':
        // ============================================================
            $slug  = trim($_POST['slug'] ?? '');
            $nazev = trim($_POST['nazev'] ?? '');
            $domena = trim($_POST['domena'] ?? '');

            if (!$slug || !$nazev) {
                sendJsonError('Povinná pole: slug, nazev');
            }

            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                sendJsonError('Slug smí obsahovat pouze malá písmena, číslice a pomlčky.');
            }

            if (strlen($nazev) > 255 || strlen($slug) > 64) {
                sendJsonError('Název nebo slug je příliš dlouhý.');
            }

            $tenantId = TenantManager::vytvorit($pdo, $slug, $nazev, $domena);
            sendJsonSuccess("Tenant '{$nazev}' byl vytvořen.", ['tenant_id' => $tenantId]);
            break;

        // ============================================================
        case 'upravit':
        // ============================================================
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $nazev    = trim($_POST['nazev'] ?? '');
            $domena   = trim($_POST['domena'] ?? '');

            if ($tenantId <= 0 || !$nazev) {
                sendJsonError('Povinná pole: tenant_id, nazev');
            }

            // Ověřit existenci
            $stmt = $pdo->prepare("SELECT tenant_id FROM wgs_tenants WHERE tenant_id = :id");
            $stmt->execute([':id' => $tenantId]);
            if (!$stmt->fetch()) {
                sendJsonError('Tenant nenalezen', 404);
            }

            $stmt = $pdo->prepare(
                "UPDATE wgs_tenants SET nazev = :nazev, domena = :domena WHERE tenant_id = :id"
            );
            $stmt->execute([':nazev' => $nazev, ':domena' => $domena, ':id' => $tenantId]);

            sendJsonSuccess('Tenant byl aktualizován.');
            break;

        // ============================================================
        case 'smazat':
        // ============================================================
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            if ($tenantId <= 0) {
                sendJsonError('Chybí tenant_id');
            }

            TenantManager::deaktivovat($pdo, $tenantId);
            sendJsonSuccess('Tenant byl deaktivován.');
            break;

        // ============================================================
        default:
        // ============================================================
            sendJsonError("Neznámá akce: {$akce}", 400);
    }

} catch (InvalidArgumentException $e) {
    sendJsonError($e->getMessage());
} catch (Exception $e) {
    error_log('tenants_api chyba: ' . $e->getMessage());
    sendJsonError('Chyba serveru při zpracování požadavku');
}
