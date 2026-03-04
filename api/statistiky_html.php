<?php
/**
 * statistiky_html.php - HTMX HTML endpoint pro souhrn statistik (Step 145)
 *
 * Vrací HTML fragment se 4 souhrnnými kartami (KPI) pro statistiky.
 * Volání: hx-get="/api/statistiky_html.php?rok=2025&mesic=3" hx-target="#stats-summary-container"
 *
 * Parametry (GET) - stejné jako statistiky_api.php:
 *   rok                  - filtr roku (prázdné = vše)
 *   mesic                - filtr měsíce (prázdné = vše)
 *   prodejci[]           - filtr prodejců (multi-select)
 *   technici[]           - filtr techniků (multi-select)
 *   zeme[]               - filtr zemí (multi-select)
 *   zobrazit_mimozarucni - 1 | 0 (výchozí: 1)
 *   pouze_dokoncene      - 1 | 0 (výchozí: 0)
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// BEZPEČNOST: Pouze admin
$jeAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$jeAdmin) {
    http_response_code(401);
    echo '<div class="stats-summary-error">Přístup odepřen.</div>';
    exit;
}

// Uvolnit session lock pro paralelní zpracování
session_write_close();

// -----------------------------------------------------------------------
// Pomocná funkce: sestavit WHERE podmínku (zrcadlo buildFilterWhere z statistiky_api.php)
// -----------------------------------------------------------------------
function sestavWhereStatistiky(PDO $pdo): array
{
    $podmínky = [];
    $params   = [];

    // Zjistit existenci sloupců
    $stmtCol = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'datum_dokonceni'");
    $maDatumDokonceni = ($stmtCol->fetch() !== false);
    $datumSloupec = $maDatumDokonceni
        ? 'COALESCE(r.datum_dokonceni, r.created_at)'
        : 'r.created_at';

    $stmtCol2 = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'dokonceno_kym'");
    $maDokoncenokym = ($stmtCol2->fetch() !== false);

    // Rok
    if (!empty($_GET['rok'])) {
        $podmínky[] = "YEAR({$datumSloupec}) = :rok";
        $params[':rok'] = (int)$_GET['rok'];
    }

    // Měsíc
    if (!empty($_GET['mesic'])) {
        $podmínky[] = "MONTH({$datumSloupec}) = :mesic";
        $params[':mesic'] = (int)$_GET['mesic'];
    }

    // Mimozáruční servis — výchozí: zobrazit
    $zobrazitMimozarucni = !isset($_GET['zobrazit_mimozarucni']) || $_GET['zobrazit_mimozarucni'] === '1';
    if (!$zobrazitMimozarucni) {
        $podmínky[] = "(r.created_by IS NOT NULL AND r.created_by != '')";
    }

    // Pouze dokončené
    $pouzeDokoncene = isset($_GET['pouze_dokoncene']) && $_GET['pouze_dokoncene'] === '1';
    if ($pouzeDokoncene) {
        $podmínky[] = "r.stav = 'done'";
    }

    // Prodejci (multi-select)
    if (!empty($_GET['prodejci'])) {
        $prodejci = is_array($_GET['prodejci']) ? $_GET['prodejci'] : [$_GET['prodejci']];
        $prodPodminky = [];
        foreach ($prodejci as $idx => $prodejce) {
            $klic = ":prodejce_$idx";
            $prodPodminky[] = "r.created_by = $klic";
            $params[$klic]  = $prodejce;
        }
        if (!empty($prodPodminky)) {
            $podmínky[] = '(' . implode(' OR ', $prodPodminky) . ')';
        }
    }

    // Technici (multi-select)
    if (!empty($_GET['technici'])) {
        $technici = is_array($_GET['technici']) ? $_GET['technici'] : [$_GET['technici']];
        $techPodminky = [];
        foreach ($technici as $idx => $technik) {
            $technikId = (int)$technik;
            if ($maDokoncenokym) {
                $kDok = ":technik_dok_$idx";
                $kAss = ":technik_ass_$idx";
                $params[$kDok] = $technikId;
                $params[$kAss] = $technikId;
                $techPodminky[] = "(r.dokonceno_kym = $kDok OR (r.dokonceno_kym IS NULL AND r.assigned_to = $kAss))";
            } else {
                $kAss = ":technik_ass_$idx";
                $params[$kAss] = $technikId;
                $techPodminky[] = "r.assigned_to = $kAss";
            }
        }
        if (!empty($techPodminky)) {
            $podmínky[] = '(' . implode(' OR ', $techPodminky) . ')';
        }
    }

    // Země (multi-select)
    if (!empty($_GET['zeme'])) {
        $zeme = is_array($_GET['zeme']) ? $_GET['zeme'] : [$_GET['zeme']];
        $zemePodminky = [];
        foreach ($zeme as $idx => $z) {
            $klic = ":zeme_$idx";
            $zemePodminky[] = "LOWER(r.faktura) = $klic";
            $params[$klic]  = strtolower($z);
        }
        if (!empty($zemePodminky)) {
            $podmínky[] = '(' . implode(' OR ', $zemePodminky) . ')';
        }
    }

    $kde = !empty($podmínky) ? 'WHERE ' . implode(' AND ', $podmínky) : '';
    return [$kde, $params];
}

// -----------------------------------------------------------------------
// Výpočet statistik
// -----------------------------------------------------------------------
try {
    $pdo = getDbConnection();

    // Celkem reklamací (bez filtru)
    $stmtCelkem = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_reklamace");
    $celkemVse = (int)($stmtCelkem->fetch(PDO::FETCH_ASSOC)['pocet'] ?? 0);

    // Celková částka (bez filtru)
    $stmtCastkaCelkem = $pdo->query("
        SELECT SUM(CAST(COALESCE(cena_celkem, 0) AS DECIMAL(10,2))) as castka
        FROM wgs_reklamace
    ");
    $castkaCelkem = (float)($stmtCastkaCelkem->fetch(PDO::FETCH_ASSOC)['castka'] ?? 0);

    // Filtrované statistiky
    [$kde, $params] = sestavWhereStatistiky($pdo);

    $stmtMesic = $pdo->prepare("SELECT COUNT(*) as pocet FROM wgs_reklamace r $kde");
    $stmtMesic->execute($params);
    $celkemMesic = (int)($stmtMesic->fetch(PDO::FETCH_ASSOC)['pocet'] ?? 0);

    $stmtCastkaMesic = $pdo->prepare("
        SELECT SUM(CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2))) as castka
        FROM wgs_reklamace r $kde
    ");
    $stmtCastkaMesic->execute($params);
    $castkaMesic = (float)($stmtCastkaMesic->fetch(PDO::FETCH_ASSOC)['castka'] ?? 0);

} catch (Exception $e) {
    error_log('statistiky_html.php error: ' . $e->getMessage());
    http_response_code(500);
    echo '<div class="stats-summary-error">Chyba při načítání statistik.</div>';
    exit;
}

// Formátování čísel
$celkemVseStr    = number_format($celkemVse, 0, ',', ' ');
$celkemMesicStr  = number_format($celkemMesic, 0, ',', ' ');
$castkaCelkemStr = number_format($castkaCelkem, 0, ',', ' ') . ' €';
$castkaMesicStr  = number_format($castkaMesic, 0, ',', ' ') . ' €';
?>
<div class="summary-card">
    <div class="summary-card-label">Celkem reklamací</div>
    <div class="summary-card-value" id="total-all"><?= htmlspecialchars($celkemVseStr, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="summary-card-sub">Všechny v systému</div>
</div>

<div class="summary-card">
    <div class="summary-card-label">Reklamací v měsíci</div>
    <div class="summary-card-value" id="total-month"><?= htmlspecialchars($celkemMesicStr, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="summary-card-sub">Podle filtrů</div>
</div>

<div class="summary-card">
    <div class="summary-card-label">Částka celkem</div>
    <div class="summary-card-value" id="revenue-all"><?= htmlspecialchars($castkaCelkemStr, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="summary-card-sub">Všechny zakázky</div>
</div>

<div class="summary-card">
    <div class="summary-card-label">Částka v měsíci</div>
    <div class="summary-card-value" id="revenue-month"><?= htmlspecialchars($castkaMesicStr, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="summary-card-sub">Podle filtrů</div>
</div>
<!-- HTMX endpoint: statistiky_html.php -->
