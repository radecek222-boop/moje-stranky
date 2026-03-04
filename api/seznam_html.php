<?php
/**
 * seznam_html.php - HTMX HTML endpoint pro seznam reklamací (Step 143)
 *
 * Vrací HTML fragment s kartami reklamací pro HTMX swap.
 * Volání: hx-get="/api/seznam_html.php?status=wait" hx-target="#orderGrid"
 *
 * Parametry (GET):
 *   status        - wait | open | done | poz | odlozene | cekame-na-dily | all (výchozí: all)
 *   search        - textový vyhledávací dotaz
 *   prodejce_id   - filtr podle prodejce (pouze admin)
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// BEZPEČNOST: Kontrola přihlášení
$jeAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$jePrihlasen = isset($_SESSION['user_id']) || $jeAdmin;
if (!$jePrihlasen) {
    http_response_code(401);
    echo '<div class="empty-state"><div class="empty-state-text">Přihlášení vyžadováno.</div></div>';
    exit;
}

// Uvolnit session lock pro paralelní zpracování
session_write_close();

require_once __DIR__ . '/../includes/db_metadata.php';

// Vstupní parametry
$stavFiltr   = trim($_GET['status']      ?? 'all');
$hledej      = trim($_GET['search']      ?? '');
$prodejceId  = trim($_GET['prodejce_id'] ?? '');

// Povolené hodnoty status (ochrana před injekci)
$povoleneStavy = ['all', 'wait', 'open', 'done', 'poz', 'odlozene', 'cekame-na-dily'];
if (!in_array($stavFiltr, $povoleneStavy, true)) {
    $stavFiltr = 'all';
}

// -----------------------------------------------------------------------
// Pomocné funkce
// -----------------------------------------------------------------------

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function formatujDatum(?string $dateStr): string
{
    if (!$dateStr) return '—';
    $datum = new DateTime($dateStr);
    $dny = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'];
    return $dny[(int)$datum->format('w')] . ' ' . $datum->format('j.n.Y');
}

function formatujTermin(?string $dateStr, ?string $timeStr): string
{
    if (!$dateStr || !$timeStr) return '';
    $casti = explode('.', $dateStr);
    if (count($casti) !== 3) return $dateStr . ' ' . $timeStr;
    $den  = (int)$casti[0];
    $mes  = (int)$casti[1];
    $rok  = (int)$casti[2];
    $datum = new DateTime("{$rok}-{$mes}-{$den}");
    $dny = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'];
    return $dny[(int)$datum->format('w')] . " {$den}.{$mes}.-{$timeStr}";
}

function mapujStav(?string $stav): array
{
    $mapa = [
        'wait'         => ['trida' => 'wait',          'text' => 'NOVÁ'],
        'ČEKÁ'         => ['trida' => 'wait',          'text' => 'NOVÁ'],
        'open'         => ['trida' => 'open',          'text' => 'DOMLUVENÁ'],
        'DOMLUVENÁ'    => ['trida' => 'open',          'text' => 'DOMLUVENÁ'],
        'done'         => ['trida' => 'done',          'text' => 'HOTOVO'],
        'HOTOVO'       => ['trida' => 'done',          'text' => 'HOTOVO'],
        'cekame_na_dily'   => ['trida' => 'cekame-na-dily', 'text' => 'Čekáme na díly'],
        'ČEKÁME NA DÍLY'   => ['trida' => 'cekame-na-dily', 'text' => 'Čekáme na díly'],
    ];
    return $mapa[$stav ?? ''] ?? ['trida' => 'wait', 'text' => 'NOVÁ'];
}

// -----------------------------------------------------------------------
// Databázový dotaz
// -----------------------------------------------------------------------

try {
    $pdo = getDbConnection();
    $columns = db_get_table_columns($pdo, 'wgs_reklamace');

    $userId    = $_SESSION['user_id']    ?? null;
    $userEmail = $_SESSION['user_email'] ?? null;
    $userRole  = strtolower(trim($_SESSION['role'] ?? 'guest'));

    $whereParts = [];
    $params     = [];

    // --- Filtr stav ---
    // Stav 'poz', 'odlozene', 'cekame-na-dily' jsou aplikovány post-fetch v PHP
    // Pro jednoduché stavy filtrujeme přímo v SQL
    if ($stavFiltr === 'wait' || $stavFiltr === 'open' || $stavFiltr === 'done') {
        $sloupec = in_array('stav', $columns, true) ? 'r.stav' : 'r.status';
        $whereParts[] = "{$sloupec} = :stav";
        $params[':stav'] = $stavFiltr;
    }
    if ($stavFiltr === 'cekame-na-dily') {
        $sloupec = in_array('stav', $columns, true) ? 'r.stav' : 'r.status';
        $whereParts[] = "{$sloupec} = :stav";
        $params[':stav'] = 'cekame_na_dily';
    }
    if ($stavFiltr === 'odlozene') {
        $whereParts[] = 'r.je_odlozena = 1';
    }

    // --- Role-based access ---
    if (!$jeAdmin) {
        $isProdejce = in_array($userRole, ['prodejce', 'user'], true);
        $isTechnik  = in_array($userRole, ['technik', 'technician'], true);

        if ($isProdejce) {
            if ($userId !== null && in_array('created_by', $columns, true)) {
                $whereParts[] = 'r.created_by = :created_by';
                $params[':created_by'] = $userId;
            } else {
                $whereParts[] = '1 = 0';
            }
        } elseif (!$isTechnik) {
            $podmCasti = [];
            if ($userId !== null && in_array('created_by', $columns, true)) {
                $podmCasti[] = 'r.created_by = :created_by';
                $params[':created_by'] = $userId;
            }
            if ($userEmail && in_array('email', $columns, true)) {
                $podmCasti[] = 'LOWER(TRIM(r.email)) = LOWER(TRIM(:user_email))';
                $params[':user_email'] = $userEmail;
            }
            if (!empty($podmCasti)) {
                $whereParts[] = '(' . implode(' OR ', $podmCasti) . ')';
            } else {
                $whereParts[] = '1 = 0';
            }
        }
    }

    // Admin filtr podle prodejce
    if ($jeAdmin && $prodejceId !== '' && in_array('created_by', $columns, true)) {
        $whereParts[] = 'r.created_by = :prodejce_id';
        $params[':prodejce_id'] = $prodejceId;
    }

    $kde = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    $sql = "
        SELECT
            r.id,
            r.reklamace_id,
            r.stav,
            r.jmeno,
            r.email,
            r.adresa,
            r.ulice,
            r.mesto,
            r.psc,
            r.model,
            r.vyrobek,
            r.termin,
            r.cas_navstevy,
            r.created_at,
            r.je_odlozena,
            r.created_by
        FROM wgs_reklamace r
        $kde
        ORDER BY
            CASE
                WHEN COALESCE(r.je_odlozena, 0) = 0 AND r.stav = 'wait' THEN 1
                WHEN COALESCE(r.je_odlozena, 0) = 0 AND r.stav = 'open' THEN 2
                WHEN COALESCE(r.je_odlozena, 0) = 1 AND r.stav != 'done' THEN 3
                WHEN r.stav = 'done' THEN 5
                ELSE 4
            END ASC,
            r.created_at DESC
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Načíst unread counts najednou ---
    $unreadMapa = [];
    if (!empty($reklamace)) {
        $idsHolders = implode(',', array_fill(0, count($reklamace), '?'));
        $ids = array_column($reklamace, 'id');

        // Záznamy s autor != aktuální uživatel a is_read = 0
        $aktualniEmail = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? '';
        $stmtUnread = $pdo->prepare("
            SELECT claim_id, COUNT(*) as pocet
            FROM wgs_notes
            WHERE claim_id IN ($idsHolders)
              AND author_email != ?
              AND is_read = 0
            GROUP BY claim_id
        ");
        $stmtUnread->execute(array_merge($ids, [$aktualniEmail]));
        foreach ($stmtUnread->fetchAll(PDO::FETCH_ASSOC) as $radek) {
            $unreadMapa[(int)$radek['claim_id']] = (int)$radek['pocet'];
        }
    }

    // --- Načíst CN emaily najednou ---
    $emailyCN   = [];
    $stavyCN    = [];
    try {
        $stmtCn = $pdo->query("
            SELECT
                LOWER(zakaznik_email) as email,
                MAX(CASE
                    WHEN cekame_nd_at IS NOT NULL THEN 4
                    WHEN stav = 'potvrzena'  THEN 3
                    WHEN stav = 'zamitnuta'  THEN 2
                    WHEN stav = 'odeslana'   THEN 1
                    ELSE 0
                END) as priorita
            FROM wgs_nabidky
            WHERE stav IN ('potvrzena', 'odeslana', 'zamitnuta')
              AND zakaznik_email IS NOT NULL
              AND zakaznik_email != ''
            GROUP BY LOWER(zakaznik_email)
        ");
        foreach ($stmtCn->fetchAll(PDO::FETCH_ASSOC) as $radek) {
            $em = $radek['email'];
            $emailyCN[] = $em;
            if ($radek['priorita'] == 4)     $stavyCN[$em] = 'cekame_nd';
            elseif ($radek['priorita'] == 3) $stavyCN[$em] = 'potvrzena';
            elseif ($radek['priorita'] == 2) $stavyCN[$em] = 'zamitnuta';
            else                              $stavyCN[$em] = 'odeslana';
        }
    } catch (Exception $e) {
        // Tabulka wgs_nabidky nemusí existovat
    }

} catch (Exception $e) {
    error_log('seznam_html.php error: ' . $e->getMessage());
    http_response_code(500);
    echo '<div class="empty-state"><div class="empty-state-text">Chyba při načítání reklamací.</div></div>';
    exit;
}

// -----------------------------------------------------------------------
// Filtrování POZ post-fetch (nevratný SQL filtr)
// -----------------------------------------------------------------------
if ($stavFiltr === 'poz') {
    $reklamace = array_filter($reklamace, function ($r) use ($emailyCN) {
        $stav = $r['stav'] ?? 'wait';
        if ($stav === 'done' || $stav === 'HOTOVO') return false;
        if (empty(trim($r['created_by'] ?? ''))) return true;
        $email = strtolower(trim($r['email'] ?? ''));
        return $email && in_array($email, $emailyCN, true);
    });
    $reklamace = array_values($reklamace);
}

// -----------------------------------------------------------------------
// Fulltext search (volitelný)
// -----------------------------------------------------------------------
if ($hledej !== '') {
    $hledejMale = mb_strtolower($hledej, 'UTF-8');
    $reklamace = array_filter($reklamace, function ($r) use ($hledejMale) {
        $pole = [
            $r['reklamace_id'] ?? '',
            $r['jmeno']        ?? '',
            $r['email']        ?? '',
            $r['adresa']       ?? '',
            $r['ulice']        ?? '',
            $r['mesto']        ?? '',
            $r['model']        ?? '',
            $r['vyrobek']      ?? '',
        ];
        foreach ($pole as $hodnota) {
            if (strpos(mb_strtolower($hodnota, 'UTF-8'), $hledejMale) !== false) {
                return true;
            }
        }
        return false;
    });
    $reklamace = array_values($reklamace);
}

// -----------------------------------------------------------------------
// Vykreslení HTML
// -----------------------------------------------------------------------
if (empty($reklamace)) {
    echo '<div class="empty-state"><div class="empty-state-text">Žádné reklamace k zobrazení</div></div>';
    exit;
}

foreach ($reklamace as $r):
    $idZaznamu    = (int)($r['id'] ?? 0);
    $reklamaceId  = $r['reklamace_id'] ?? ('WGS-' . $idZaznamu);
    $jmeno        = $r['jmeno'] ?? 'Neznámý zákazník';
    $model        = $r['model'] ?: ($r['vyrobek'] ?: '—');
    $datum        = formatujDatum($r['created_at'] ?? null);
    $stavInfo     = mapujStav($r['stav'] ?? null);
    $stavTrida    = $stavInfo['trida'];
    $stavText     = $stavInfo['text'];

    // Adresa
    if (!empty($r['adresa'])) {
        $adresa = $r['adresa'];
    } else {
        $castiAdr = array_filter([$r['ulice'] ?? '', $r['mesto'] ?? '', $r['psc'] ?? '']);
        $adresa = !empty($castiAdr) ? implode(', ', $castiAdr) : '—';
    }
    // Zkrátit na 2 části
    $adrCasti = array_map('trim', explode(',', $adresa));
    $adresa   = implode(', ', array_slice($adrCasti, 0, 2));

    // Termín
    $terminText = '';
    if ($stavTrida === 'open' && !empty($r['termin']) && !empty($r['cas_navstevy'])) {
        $terminText = formatujTermin($r['termin'], $r['cas_navstevy']);
    }

    // CN stav
    $zakaznikEmail = strtolower(trim($r['email'] ?? ''));
    $maCN          = $zakaznikEmail && in_array($zakaznikEmail, $emailyCN, true);
    $stavCN        = $stavyCN[$zakaznikEmail] ?? null;
    $jeOdsouhlasena = $stavCN === 'potvrzena';
    $jeCekameNd     = $stavCN === 'cekame_nd';
    $jeZamitnuta    = $stavCN === 'zamitnuta';

    // CSS třídy karty
    $jeOdlozena   = (int)($r['je_odlozena'] ?? 0) === 1;
    $jeHotovo     = $stavTrida === 'done';

    if ($jeOdlozena) {
        $statusBgTrida = 'status-bg-odlozena';
    } elseif ($stavTrida === 'cekame-na-dily') {
        $statusBgTrida = 'cn-cekame-nd';
    } else {
        $statusBgTrida = 'status-bg-' . $stavTrida;
    }

    $cnTrida = '';
    if ($maCN && !$terminText && !$jeHotovo) {
        if ($jeCekameNd)       $cnTrida = 'cn-cekame-nd';
        elseif ($jeOdsouhlasena) $cnTrida = 'cn-odsouhlasena';
        elseif ($jeZamitnuta)  $cnTrida = 'cn-zamitnuta';
        else                   $cnTrida = 'ma-cenovou-nabidku';
    }

    // Badge stavu
    if ($terminText) {
        $stavBadgeHtml = '<span class="order-appointment">' . e($terminText) . '</span>';
    } elseif ($jeOdlozena) {
        $stavBadgeHtml = '<span class="order-status-text status-odlozena">ODLOŽENO</span>';
    } elseif ($stavTrida === 'cekame-na-dily') {
        $stavBadgeHtml = '<span class="order-cn-text cekame-nd">Čekáme na díly</span>';
    } elseif ($maCN && !$jeHotovo) {
        if ($jeCekameNd) {
            $stavBadgeHtml = '<span class="order-cn-text cekame-nd">Čekáme na díly</span>';
        } elseif ($jeOdsouhlasena) {
            $stavBadgeHtml = '<span class="order-cn-text odsouhlasena">Odsouhlasena</span>';
        } elseif ($jeZamitnuta) {
            $stavBadgeHtml = '<span class="order-cn-text zamitnuta">Zamítnuta</span>';
        } else {
            $stavBadgeHtml = '<span class="order-cn-text">Poslána CN</span>';
        }
    } else {
        $stavBadgeHtml = '<span class="order-status-text status-' . e($stavTrida) . '">' . e($stavText) . '</span>';
    }

    // Status dot
    $dotStav = ($jeCekameNd || $stavTrida === 'cekame-na-dily') ? 'cekame-na-dily' : $stavTrida;
    $stavDotHtml = '<div class="order-status status-' . e($dotStav) . '"></div>';

    // Chat badge
    $neprectenych = $unreadMapa[$idZaznamu] ?? 0;
    $chatTridy  = 'order-notes-badge' . ($neprectenych > 0 ? ' has-unread pulse' : '');
    $chatLabel  = 'CHAT' . ($neprectenych > 0 ? ' ' . $neprectenych : '');
    $chatTitle  = $neprectenych > 0 ? $neprectenych . ' nepřečtené' : 'Chat';
    $chatHtml   = '<div class="' . $chatTridy . '" data-action="showNotes" data-id="' . $idZaznamu . '" title="' . e($chatTitle) . '">' . e($chatLabel) . '</div>';

    ?>
<div class="order-box <?= e($statusBgTrida) ?> <?= e($cnTrida) ?>" data-action="showDetailById" data-id="<?= $idZaznamu ?>">
    <div class="order-header">
        <div class="order-number"><?= e($reklamaceId) ?></div>
        <div style="display: flex; gap: 0.4rem; align-items: center;">
            <?= $chatHtml ?>
            <?= $stavDotHtml ?>
        </div>
    </div>
    <div class="order-body">
        <div class="order-customer"><?= e($jmeno) ?></div>
        <div class="order-detail-line"><?= e($adresa) ?></div>
        <div class="order-detail-row">
            <div class="order-detail-left">
                <div class="order-detail-line"><?= e($model) ?></div>
                <div class="order-detail-line" style="opacity: 0.6;"><?= e($datum) ?></div>
            </div>
            <div class="order-detail-right"><?= $stavBadgeHtml ?></div>
        </div>
    </div>
</div>
<?php endforeach;
?>
<!-- HTMX endpoint: seznam_html.php | status=<?= e($stavFiltr) ?> | count=<?= count($reklamace) ?> -->
