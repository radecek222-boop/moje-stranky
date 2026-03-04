<?php
/**
 * Tisknutelný výtisk zakázky (U10)
 * Načte zakázku podle ?id=X a zobrazí ji v tisknutelné podobě.
 */

require_once __DIR__ . '/init.php';

$jeAdmin    = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$jeUzivatel = isset($_SESSION['user_id']);

if (!$jeAdmin && !$jeUzivatel) {
    header('Location: login.php?redirect=tisk.php');
    exit;
}

$idParam = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idParam) {
    http_response_code(400);
    die('Chybí platné ID zakázky.');
}

$zakázka     = null;
$fotky       = [];
$protokoly   = [];
$chyba       = null;

try {
    $pdo = getDbConnection();

    // Načíst zakázku
    $stmt = $pdo->prepare('
        SELECT r.*,
               u.name  AS zadavatel_jmeno,
               t.name  AS technik_jmeno,
               t.phone AS technik_telefon,
               t.email AS technik_email
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        LEFT JOIN wgs_users t ON r.assigned_to = t.id
        WHERE r.id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $idParam]);
    $zakázka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakázka) {
        http_response_code(404);
        die('Zakázka nenalezena.');
    }

    // Načíst fotky
    $fStmt = $pdo->prepare('
        SELECT photo_path, section_name, file_name
        FROM wgs_photos
        WHERE reklamace_id = :rid
        ORDER BY photo_order ASC, uploaded_at ASC
        LIMIT 30
    ');
    $fStmt->execute([':rid' => $zakázka['reklamace_id'] ?? $zakázka['cislo'] ?? $idParam]);
    $fotky = $fStmt->fetchAll(PDO::FETCH_ASSOC);

    // Načíst protokoly (pokud existují)
    try {
        $pStmt = $pdo->prepare('
            SELECT created_at, problem_description, repair_proposal, solved, technician, cena_celkem
            FROM wgs_protokoly
            WHERE reklamace_id = :rid
            ORDER BY created_at DESC
            LIMIT 5
        ');
        $pStmt->execute([':rid' => $zakázka['reklamace_id'] ?? $zakázka['cislo']]);
        $protokoly = $pStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Tabulka nemusí existovat — ignorovat
    }

} catch (Exception $e) {
    $chyba = $e->getMessage();
    error_log('tisk.php chyba: ' . $e->getMessage());
}

// Pomocné funkce pro výpis
function wygHtml(string $hodnota): string {
    return htmlspecialchars($hodnota ?? '–', ENT_QUOTES, 'UTF-8');
}

function wygStav(string $stav): string {
    $stavyMapa = [
        'wait'           => 'Čeká na zpracování',
        'open'           => 'Domluvená návštěva',
        'done'           => 'Hotovo',
        'cekame_na_dily' => 'Čekáme na díly',
    ];
    return $stavyMapa[$stav] ?? $stav;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Výtisk zakázky – <?= wygHtml($zakázka['reklamace_id'] ?? $zakázka['cislo'] ?? '#' . $idParam) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #111; background: #fff; font-size: 13px; }

        .tisk-obal { max-width: 800px; margin: 0 auto; padding: 2rem 2rem 1rem; }

        .tisk-hlavicka {
            border-bottom: 3px solid #000;
            padding-bottom: 1rem; margin-bottom: 1.5rem;
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .tisk-logo { font-size: 1.3rem; font-weight: 900; letter-spacing: 2px; }
        .tisk-nadpis { font-size: 0.75rem; color: #666; margin-top: 0.2rem; }
        .tisk-cislo { text-align: right; }
        .tisk-cislo .cislo { font-size: 1.1rem; font-weight: 700; }
        .tisk-cislo .datum-tisku { font-size: 0.75rem; color: #666; }

        .tisk-sekce { margin-bottom: 1.5rem; }
        .tisk-sekce-nadpis {
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1.5px; color: #fff; background: #111;
            padding: 0.3rem 0.6rem; border-radius: 2px; margin-bottom: 0.75rem;
        }
        .tisk-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 0.3rem 2rem;
        }
        .tisk-pole { display: flex; flex-direction: column; padding: 0.25rem 0; border-bottom: 1px solid #f0f0f0; }
        .tisk-pole-label { font-size: 0.68rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .tisk-pole-value { font-size: 0.9rem; font-weight: 500; margin-top: 0.1rem; }

        .tisk-stav {
            display: inline-block; padding: 0.25rem 0.7rem; border-radius: 3px;
            font-weight: 700; font-size: 0.85rem; background: #111; color: #fff;
        }
        .stav-done { background: #555; }

        .protokol-blok { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.75rem 1rem; margin-bottom: 0.75rem; }
        .protokol-blok-datum { font-size: 0.72rem; color: #888; margin-bottom: 0.4rem; }
        .protokol-label { font-size: 0.7rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.5rem; }
        .protokol-text { font-size: 0.85rem; margin-top: 0.15rem; }

        .fotky-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
        .fotky-grid img { width: 120px; height: 90px; object-fit: cover; border: 1px solid #ddd; border-radius: 2px; }

        .tisk-paticka {
            margin-top: 2rem; padding-top: 0.75rem; border-top: 1px solid #ccc;
            font-size: 0.72rem; color: #888;
            display: flex; justify-content: space-between;
        }

        .btn-tisk {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            background: #000; color: #fff; border: none;
            padding: 0.75rem 1.5rem; border-radius: 4px;
            font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .btn-tisk:hover { background: #333; }

        @media print {
            .btn-tisk { display: none !important; }
            body { background: #fff; }
            .tisk-obal { padding: 1rem; max-width: 100%; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body>

<div class="tisk-obal">

    <!-- Hlavička -->
    <div class="tisk-hlavicka">
        <div>
            <div class="tisk-logo">WGS</div>
            <div class="tisk-nadpis">White Glove Service — Servisní výtisk</div>
        </div>
        <div class="tisk-cislo">
            <div class="cislo"><?= wygHtml($zakázka['reklamace_id'] ?? $zakázka['cislo'] ?? '#' . $idParam) ?></div>
            <div class="datum-tisku">Vytištěno: <?= date('d.m.Y H:i') ?></div>
            <div style="margin-top:0.4rem;">
                <span class="tisk-stav <?= $zakázka['stav'] === 'done' ? 'stav-done' : '' ?>">
                    <?= wygHtml(wygStav($zakázka['stav'] ?? '')) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Zákazník -->
    <div class="tisk-sekce">
        <div class="tisk-sekce-nadpis">Zákazník</div>
        <div class="tisk-grid">
            <div class="tisk-pole">
                <span class="tisk-pole-label">Jméno</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['jmeno'] ?? '') ?></span>
            </div>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Telefon</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['telefon'] ?? '') ?></span>
            </div>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Email</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['email'] ?? '') ?></span>
            </div>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Adresa</span>
                <span class="tisk-pole-value">
                    <?= wygHtml(trim(($zakázka['adresa'] ?? '') ?: (($zakázka['ulice'] ?? '') . ' ' . ($zakázka['mesto'] ?? '') . ' ' . ($zakázka['psc'] ?? '')))) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Produkt -->
    <div class="tisk-sekce">
        <div class="tisk-sekce-nadpis">Produkt</div>
        <div class="tisk-grid">
            <div class="tisk-pole">
                <span class="tisk-pole-label">Model</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['model'] ?? '') ?></span>
            </div>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Provedení / Barva</span>
                <span class="tisk-pole-value">
                    <?= wygHtml(trim(($zakázka['provedeni'] ?? '') . ' ' . ($zakázka['barva'] ?? ''))) ?>
                </span>
            </div>
            <?php if (!empty($zakázka['seriove_cislo'])): ?>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Sériové číslo</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['seriove_cislo']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($zakázka['datum_prodeje'])): ?>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Datum prodeje</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['datum_prodeje']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Zakázka -->
    <div class="tisk-sekce">
        <div class="tisk-sekce-nadpis">Zakázka</div>
        <div class="tisk-grid">
            <div class="tisk-pole">
                <span class="tisk-pole-label">Číslo zakázky</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['reklamace_id'] ?? $zakázka['cislo'] ?? '') ?></span>
            </div>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Typ</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['typ'] ?? '') ?></span>
            </div>
            <?php if (!empty($zakázka['termin'])): ?>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Termín návštěvy</span>
                <span class="tisk-pole-value">
                    <?= wygHtml($zakázka['termin']) ?><?= !empty($zakázka['cas_navstevy']) ? ' · ' . wygHtml($zakázka['cas_navstevy']) : '' ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Zadal</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['zadavatel_jmeno'] ?? $zakázka['created_by'] ?? '') ?></span>
            </div>
            <?php if (!empty($zakázka['technik_jmeno'])): ?>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Přiřazený technik</span>
                <span class="tisk-pole-value"><?= wygHtml($zakázka['technik_jmeno']) ?></span>
            </div>
            <?php endif; ?>
            <div class="tisk-pole">
                <span class="tisk-pole-label">Vytvořeno</span>
                <span class="tisk-pole-value"><?= wygHtml(date('d.m.Y H:i', strtotime($zakázka['created_at'] ?? 'now'))) ?></span>
            </div>
        </div>
    </div>

    <!-- Popis problému -->
    <?php if (!empty($zakázka['popis_problemu'])): ?>
    <div class="tisk-sekce">
        <div class="tisk-sekce-nadpis">Popis problému</div>
        <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:3px;padding:0.75rem;font-size:0.9rem;line-height:1.5;">
            <?= nl2br(wygHtml($zakázka['popis_problemu'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Protokoly -->
    <?php if (!empty($protokoly)): ?>
    <div class="tisk-sekce">
        <div class="tisk-sekce-nadpis">Servisní protokoly</div>
        <?php foreach ($protokoly as $p): ?>
        <div class="protokol-blok">
            <div class="protokol-blok-datum"><?= wygHtml(date('d.m.Y H:i', strtotime($p['created_at'] ?? 'now'))) ?> — <?= wygHtml($p['technician'] ?? '') ?></div>
            <?php if (!empty($p['problem_description'])): ?>
            <div class="protokol-label">Popis problému</div>
            <div class="protokol-text"><?= nl2br(wygHtml($p['problem_description'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($p['repair_proposal'])): ?>
            <div class="protokol-label">Navržená oprava</div>
            <div class="protokol-text"><?= nl2br(wygHtml($p['repair_proposal'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($p['cena_celkem'])): ?>
            <div class="protokol-label">Celková cena</div>
            <div class="protokol-text" style="font-weight:600;"><?= number_format((float) $p['cena_celkem'], 2, ',', ' ') ?> €</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Fotky (omezeno pro tisk) -->
    <?php if (!empty($fotky)): ?>
    <div class="tisk-sekce">
        <div class="tisk-sekce-nadpis">Fotky (<?= count($fotky) ?>)</div>
        <div class="fotky-grid">
            <?php foreach (array_slice($fotky, 0, 12) as $f):
                $cesta = htmlspecialchars($f['photo_path'] ?? $f['file_name'] ?? '', ENT_QUOTES, 'UTF-8');
                if (!$cesta) continue;
                // Cesta může být absolutní nebo relativní URL
                if (!str_starts_with($cesta, 'http') && !str_starts_with($cesta, '/')) {
                    $cesta = '/' . $cesta;
                }
            ?>
            <img src="<?= $cesta ?>" alt="foto" loading="lazy">
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Patička -->
    <div class="tisk-paticka">
        <span>White Glove Service — Natuzzi Italy</span>
        <span><?= wygHtml($zakázka['reklamace_id'] ?? $zakázka['cislo'] ?? '#' . $idParam) ?> | <?= date('d.m.Y') ?></span>
    </div>

</div>

<button class="btn-tisk" onclick="window.print()">Tisknout</button>

</body>
</html>
