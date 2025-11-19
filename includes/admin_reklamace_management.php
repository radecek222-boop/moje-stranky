<?php
/**
 * Control Center - Správa reklamací
 * Kompletní správa všech reklamací s timeline historií
 */

require_once __DIR__ . '/../init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Check if accessed directly (not through admin.php)
$directAccess = !defined('ADMIN_PHP_LOADED');

// If embed mode, output full HTML structure
if ($embedMode && $directAccess):
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa reklamací - WGS Admin</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="embed-mode">
<?php
endif;

// Načíst aktuální filtr - při prvním načtení vždy 'all'
$filterStav = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Načíst reklamace z databáze
$reklamace = [];
$stats = ['all' => 0, 'wait' => 0, 'open' => 0, 'done' => 0];

try {
    // Statistiky
    $stmt = $pdo->query("SELECT stav, COUNT(*) as count FROM wgs_reklamace GROUP BY stav");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['stav']] = (int)$row['count'];
        $stats['all'] += (int)$row['count'];
    }

    // Seznam reklamací
    $whereClause = '';
    $params = [];
    if ($filterStav !== 'all') {
        $whereClause = "WHERE stav = :stav";
        $params = ['stav' => $filterStav];
    }

    $sql = "
        SELECT
            reklamace_id, cislo, jmeno, telefon, email,
            ulice, mesto, psc, model, provedeni, barva,
            popis_problemu, termin, cas_navstevy, stav,
            created_at as datum_vytvoreni, datum_dokonceni, prodejce as jmeno_prodejce, typ
        FROM wgs_reklamace
        $whereClause
        ORDER BY created_at DESC
        LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("CHYBA PDO při načítání reklamací: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    $reklamace = [];
    $stats = ['all' => 0, 'wait' => 0, 'open' => 0, 'done' => 0];
} catch (Exception $e) {
    error_log("CHYBA Exception při načítání reklamací: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    $reklamace = [];
    $stats = ['all' => 0, 'wait' => 0, 'open' => 0, 'done' => 0];
}
?>

<?php if (!$directAccess): ?>
<link rel="stylesheet" href="/assets/css/admin.css">
<?php endif; ?>

<div class="control-detail active">
    <?php if (!$directAccess): ?>
    <!-- Header -->
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title" style="font-family: 'Poppins', sans-serif; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Správa reklamací</h2>
    </div>
    <?php endif; ?>

    <div class="control-detail-content">

        <!-- Alert -->
        <div id="reklamace-alert" style="display: none; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.85rem;"></div>

        <!-- Statistiky - kompaktní -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
            <div onclick="filterReklamace('all')" style="background: <?= $filterStav === 'all' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.4rem 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s; min-width: 90px;">
                <span style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStav === 'all' ? '#fff' : '#000' ?>;"><?= $stats['all'] ?></span>
                <span style="font-size: 0.7rem; color: <?= $filterStav === 'all' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 0.3rem;">Celkem</span>
            </div>
            <div onclick="filterReklamace('wait')" style="background: <?= $filterStav === 'wait' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.4rem 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s; min-width: 90px;">
                <span style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStav === 'wait' ? '#fff' : '#000' ?>;"><?= $stats['wait'] ?></span>
                <span style="font-size: 0.7rem; color: <?= $filterStav === 'wait' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 0.3rem;">Čekající</span>
            </div>
            <div onclick="filterReklamace('open')" style="background: <?= $filterStav === 'open' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.4rem 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s; min-width: 90px;">
                <span style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStav === 'open' ? '#fff' : '#000' ?>;"><?= $stats['open'] ?></span>
                <span style="font-size: 0.7rem; color: <?= $filterStav === 'open' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 0.3rem;">V řešení</span>
            </div>
            <div onclick="filterReklamace('done')" style="background: <?= $filterStav === 'done' ? '#000' : '#fff' ?>; border: 1px solid #000; padding: 0.4rem 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s; min-width: 90px;">
                <span style="font-size: 0.9rem; font-weight: 600; font-family: 'Poppins', sans-serif; color: <?= $filterStav === 'done' ? '#fff' : '#000' ?>;"><?= $stats['done'] ?></span>
                <span style="font-size: 0.7rem; color: <?= $filterStav === 'done' ? '#fff' : '#666' ?>; font-family: 'Poppins', sans-serif; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 0.3rem;">Vyřízené</span>
            </div>
        </div>

        <!-- Seznam reklamací -->
        <?php if (count($reklamace) > 0): ?>
        <div style="background: #fff; border: 1px solid #000;">
            <div style="padding: 0.75rem; background: #f5f5f5; border-bottom: 1px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.85rem; font-weight: 600;">
                Nalezeno reklamací: <?= count($reklamace) ?>
            </div>

            <?php foreach ($reklamace as $rek): ?>
            <div class="reklamace-row" style="border-bottom: 1px solid #ddd; padding: 1rem; transition: background 0.2s; cursor: pointer; position: relative;"
                 onmouseover="this.style.background='#f5f5f5'"
                 onmouseout="this.style.background='#fff'"
                 onclick="otevritDetailReklamace('<?= htmlspecialchars($rek['reklamace_id']) ?>')">

                <!-- Hlavní info -->
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                    <div style="flex: 1;">
                        <div style="font-family: 'Poppins', sans-serif; font-size: 1rem; font-weight: 600; color: #000; margin-bottom: 0.25rem;">
                            <?= htmlspecialchars($rek['cislo'] ?? $rek['reklamace_id']) ?>
                        </div>
                        <div style="font-size: 0.9rem; color: #333; margin-bottom: 0.25rem;">
                            <strong><?= htmlspecialchars($rek['jmeno']) ?></strong>
                        </div>
                        <div style="font-size: 0.8rem; color: #666;">
                            <?= htmlspecialchars($rek['ulice'] . ', ' . $rek['mesto']) ?>
                        </div>
                    </div>

                    <!-- Status badge -->
                    <div style="margin-left: 1rem;">
                        <span style="display: inline-block; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid #000; background: <?= $rek['stav'] === 'done' ? '#000' : '#fff' ?>; color: <?= $rek['stav'] === 'done' ? '#fff' : '#000' ?>; border-radius: 3px;">
                            <?php
                                if ($rek['stav'] === 'wait') echo 'ČEKAJÍCÍ';
                                elseif ($rek['stav'] === 'open') echo 'V ŘEŠENÍ';
                                else echo 'VYŘÍZENÉ';
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Detail info -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem; font-size: 0.75rem; font-family: 'Poppins', sans-serif; color: #666; margin-bottom: 0.75rem;">
                    <div><strong>Model:</strong> <?= htmlspecialchars($rek['model'] ?: '-') ?></div>
                    <div><strong>Provedení:</strong> <?= htmlspecialchars($rek['provedeni'] ?: '-') ?></div>
                    <div><strong>Termín:</strong> <?= $rek['termin'] ? date('d.m.Y', strtotime($rek['termin'])) . ' - ' . $rek['cas_navstevy'] : '-' ?></div>
                    <div><strong>Vytvořeno:</strong> <?= date('d.m.Y H:i', strtotime($rek['datum_vytvoreni'])) ?></div>
                </div>

                <!-- Akce -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;" onclick="event.stopPropagation();">
                    <!-- Změna stavu -->
                    <select class="reklamace-stav-select"
                            data-reklamace-id="<?= htmlspecialchars($rek['reklamace_id']) ?>"
                            onchange="zmenitStavReklamace('<?= htmlspecialchars($rek['reklamace_id']) ?>', this.value)"
                            style="padding: 0.35rem 0.75rem; background: #fff; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; cursor: pointer;">
                        <option value="wait" <?= $rek['stav'] === 'wait' ? 'selected' : '' ?>>ČEKAJÍCÍ</option>
                        <option value="open" <?= $rek['stav'] === 'open' ? 'selected' : '' ?>>V ŘEŠENÍ</option>
                        <option value="done" <?= $rek['stav'] === 'done' ? 'selected' : '' ?>>VYŘÍZENÉ</option>
                    </select>

                    <!-- Smazat -->
                    <button onclick="smazatReklamaci('<?= htmlspecialchars($rek['reklamace_id']) ?>', '<?= htmlspecialchars($rek['cislo'] ?? $rek['reklamace_id']) ?>')"
                            style="padding: 0.35rem 0.75rem; background: #dc3545; color: #fff; border: 1px solid #dc3545; font-family: 'Poppins', sans-serif; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; cursor: pointer; border-radius: 3px;">
                        Smazat
                    </button>

                    <!-- Detail -->
                    <button onclick="otevritDetailReklamace('<?= htmlspecialchars($rek['reklamace_id']) ?>')"
                            style="padding: 0.35rem 0.75rem; background: #000; color: #fff; border: 1px solid #000; font-family: 'Poppins', sans-serif; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; cursor: pointer; border-radius: 3px;">
                        Detail + Historie
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem 2rem; color: #888; border: 1px solid #ddd; background: #f5f5f5;">
            <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">-</div>
            <h3 style="font-family: 'Poppins', sans-serif; font-size: 1rem; color: #666; margin-bottom: 0.5rem;">Žádné reklamace nenalezeny</h3>
            <p style="font-size: 0.85rem; color: #999;">Pro vybraný filtr neexistují žádné reklamace.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal pro detail reklamace + timeline -->
<div id="detail-reklamace-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 900px; margin: 2rem auto; background: #fff; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <!-- Header -->
        <div style="padding: 1.5rem; background: #000; color: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0;">
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.2rem; font-weight: 600; margin: 0;">Detail reklamace + Historie</h2>
            <button onclick="zavritDetailModal()" style="background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Obsah -->
        <div id="detail-reklamace-content" style="padding: 1.5rem;">
            <div style="text-align: center; padding: 2rem; color: #999;">
                Načítám...
            </div>
        </div>
    </div>
</div>

<script>
// Filter reklamací
function filterReklamace(stav) {
    const url = new URL(window.location);
    url.searchParams.set('filter', stav);
    window.location.href = url.toString();
}

// Změnit stav reklamace
async function zmenitStavReklamace(reklamaceId, novyStav) {
    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() :
                         document.querySelector('meta[name="csrf-token"]')?.content;

        const odpoved = await fetch('/api/admin_api.php?action=change_reklamace_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                reklamace_id: reklamaceId,
                new_status: novyStav,
                csrf_token: csrfToken
            })
        });

        const data = await odpoved.json();

        if (data.status === 'success') {
            zobrazAlert('Stav byl úspěšně změněn', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.message || 'Nepodařilo se změnit stav');
        }
    } catch (error) {
        zobrazAlert('Chyba: ' + error.message, 'error');
    }
}

// Smazat reklamaci
async function smazatReklamaci(reklamaceId, cislo) {
    if (!confirm(`Opravdu chcete TRVALE SMAZAT reklamaci ${cislo}?\n\nTato akce je NEVRATNÁ!`)) {
        return;
    }

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() :
                         document.querySelector('meta[name="csrf-token"]')?.content;

        const odpoved = await fetch('/api/delete_reklamace.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                reklamace_id: reklamaceId,
                csrf_token: csrfToken
            })
        });

        const data = await odpoved.json();

        if (data.status === 'success') {
            zobrazAlert('Reklamace byla smazána', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.message || 'Nepodařilo se smazat reklamaci');
        }
    } catch (error) {
        zobrazAlert('Chyba: ' + error.message, 'error');
    }
}

// Otevřít detail reklamace s timeline
async function otevritDetailReklamace(reklamaceId) {
    const modal = document.getElementById('detail-reklamace-modal');
    const content = document.getElementById('detail-reklamace-content');

    modal.style.display = 'block';
    content.innerHTML = '<div style="text-align: center; padding: 2rem; color: #999;">Načítám...</div>';

    try {
        const odpoved = await fetch(`/api/admin_api.php?action=get_reklamace_detail&reklamace_id=${encodeURIComponent(reklamaceId)}`);
        const data = await odpoved.json();

        if (data.status === 'success') {
            zobrazitDetailReklamace(data.reklamace, data.timeline);
        } else {
            throw new Error(data.message || 'Nepodařilo se načíst detail');
        }
    } catch (error) {
        content.innerHTML = `<div style="text-align: center; padding: 2rem; color: #dc3545;">Chyba: ${error.message}</div>`;
    }
}

// Zobrazit detail reklamace
function zobrazitDetailReklamace(rek, timeline) {
    const content = document.getElementById('detail-reklamace-content');

    let html = `
        <!-- Základní info -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 600; color: #000; margin-bottom: 1rem; border-bottom: 2px solid #000; padding-bottom: 0.5rem;">
                ${rek.cislo || rek.reklamace_id}
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-family: 'Poppins', sans-serif; font-size: 0.85rem;">
                <div><strong>Zákazník:</strong> ${rek.jmeno}</div>
                <div><strong>Telefon:</strong> ${rek.telefon || '-'}</div>
                <div><strong>Email:</strong> ${rek.email || '-'}</div>
                <div><strong>Adresa:</strong> ${rek.ulice}, ${rek.mesto}, ${rek.psc}</div>
                <div><strong>Model:</strong> ${rek.model || '-'}</div>
                <div><strong>Provedení:</strong> ${rek.provedeni || '-'}</div>
                <div><strong>Barva:</strong> ${rek.barva || '-'}</div>
                <div><strong>Termín:</strong> ${rek.termin ? new Date(rek.termin).toLocaleDateString('cs-CZ') + ' ' + rek.cas_navstevy : '-'}</div>
                <div><strong>Stav:</strong> ${rek.stav === 'wait' ? 'ČEKAJÍCÍ' : rek.stav === 'open' ? 'V ŘEŠENÍ' : 'VYŘÍZENÉ'}</div>
                <div><strong>Vytvořeno:</strong> ${new Date(rek.datum_vytvoreni).toLocaleString('cs-CZ')}</div>
            </div>
        </div>

        <!-- Popis problému -->
        <div style="margin-bottom: 1.5rem;">
            <h4 style="font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 600; color: #000; margin-bottom: 0.5rem;">Popis problému:</h4>
            <div style="background: #f5f5f5; padding: 1rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.85rem; white-space: pre-wrap;">
                ${rek.popis_problemu || 'Žádný popis'}
            </div>
        </div>

        <!-- Timeline historie -->
        <div style="margin-bottom: 1.5rem;">
            <h4 style="font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 600; color: #000; margin-bottom: 1rem; border-bottom: 2px solid #000; padding-bottom: 0.5rem;">
                📅 Historie života zákazníka (Timeline)
            </h4>

            <div style="position: relative; padding-left: 2rem;">
                <!-- Vertikální linka -->
                <div style="position: absolute; left: 0.5rem; top: 0; bottom: 0; width: 2px; background: #ddd;"></div>

                ${timeline && timeline.length > 0 ? timeline.map(event => `
                    <div style="position: relative; margin-bottom: 1.5rem;">
                        <!-- Bod na timeline -->
                        <div style="position: absolute; left: -1.5rem; top: 0.25rem; width: 12px; height: 12px; background: #000; border: 2px solid #fff; border-radius: 50%;"></div>

                        <!-- Obsah události -->
                        <div style="background: ${event.typ === 'email' ? '#e3f2fd' : '#f5f5f5'}; padding: 0.75rem; border-left: 3px solid ${event.typ === 'email' ? '#2196F3' : '#666'}; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong style="font-family: 'Poppins', sans-serif; font-size: 0.85rem; color: #000;">${event.nazev}</strong>
                                <span style="font-size: 0.7rem; color: #666;">${new Date(event.datum).toLocaleString('cs-CZ')}</span>
                            </div>
                            ${event.popis ? `<div style="font-size: 0.75rem; color: #333;">${event.popis}</div>` : ''}
                            ${event.user ? `<div style="font-size: 0.7rem; color: #666; margin-top: 0.25rem;">👤 ${event.user}</div>` : ''}
                        </div>
                    </div>
                `).join('') : '<div style="text-align: center; color: #999; padding: 2rem;">Žádná historie k zobrazení</div>'}
            </div>
        </div>
    `;

    content.innerHTML = html;
}

// Zavřít detail modal
function zavritDetailModal() {
    document.getElementById('detail-reklamace-modal').style.display = 'none';
}

// Zobrazit alert
function zobrazAlert(message, type) {
    const alert = document.getElementById('reklamace-alert');
    if (!alert) return;

    alert.textContent = message;
    alert.style.display = 'block';
    alert.style.background = type === 'success' ? '#f0fdf4' : '#fef2f2';
    alert.style.borderColor = type === 'success' ? '#22c55e' : '#ef4444';
    alert.style.color = type === 'success' ? '#15803d' : '#991b1b';

    setTimeout(() => {
        alert.style.display = 'none';
    }, 5000);
}

// Zavřít modal při kliku mimo něj
document.getElementById('detail-reklamace-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        zavritDetailModal();
    }
});
</script>


<?php if ($embedMode && $directAccess): ?>
</body>
</html>
<?php endif; ?>
