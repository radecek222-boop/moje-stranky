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

    // Načíst spojenou cenovou nabídku (pokud existuje)
    $nabidka = null;
    $nabidkaPolozky = [];
    $nabidkaZalohaEur = 0.0;
    $nabidkaZfOdeslana = false;
    $nabidkaZfUhrazena = false;
    try {
        $nStmt = $pdo->prepare('
            SELECT cislo_nabidky, celkova_cena, mena, polozky_json,
                   zf_odeslana_at, zf_uhrazena_at, potvrzeno_at, stav
            FROM wgs_nabidky
            WHERE reklamace_id = :rid
              AND stav NOT IN (\'zamitnuta\', \'expirovana\', \'zrusena\')
            ORDER BY vytvoreno_at DESC
            LIMIT 1
        ');
        $nStmt->execute([':rid' => $idParam]);
        $nabidka = $nStmt->fetch(PDO::FETCH_ASSOC);
        if ($nabidka) {
            $nabidkaZfOdeslana = !empty($nabidka['zf_odeslana_at']);
            $nabidkaZfUhrazena = !empty($nabidka['zf_uhrazena_at']);
            $nabidkaPolozky = json_decode($nabidka['polozky_json'] ?? '[]', true) ?? [];
            foreach ($nabidkaPolozky as $pol) {
                $jeNahradniDil = ($pol['skupina'] ?? '') === 'dily'
                    || str_starts_with($pol['nazev'] ?? '', 'Náhradní díl:');
                if ($jeNahradniDil) {
                    $nabidkaZalohaEur += floatval($pol['cena']) * intval($pol['pocet'] ?? 1);
                }
            }
        }
    } catch (PDOException $e) {
        // Nabídka nemusí existovat — ignorovat
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
    <link rel="stylesheet" href="assets/css/wgs-loading.min.css">
    <style>
        :root {
            --wgs-darkest: #1a1a1a;
            --wgs-neon-green: #39ff14;
            --wgs-light-grey: #999;
        }
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

        .cn-tabulka { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .cn-tabulka th { background: #f0f0f0; padding: 0.35rem 0.6rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #555; }
        .cn-tabulka td { padding: 0.3rem 0.6rem; border-bottom: 1px solid #f0f0f0; }
        .cn-tabulka tr:last-child td { border-bottom: none; }
        .cn-skupina-radek td { background: #f5f5f5; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.8px; color: #888; padding: 0.2rem 0.6rem; }
        .cn-celkem-blok { margin-top: 0.75rem; border-top: 2px solid #111; padding-top: 0.5rem; }
        .cn-celkem-radek { display: flex; justify-content: space-between; padding: 0.2rem 0; font-size: 0.85rem; }
        .cn-celkem-radek.hlavni { font-size: 1rem; font-weight: 700; }
        .cn-zaloh-info { font-size: 0.75rem; color: #555; margin-top: 0.3rem; }
        .cn-zf-odznak { display: inline-block; background: #111; color: #fff; font-size: 0.65rem; padding: 0.15rem 0.5rem; border-radius: 2px; margin-left: 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        .btn-tisk {
            position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
            background: #000; color: #fff; border: none;
            padding: 0.75rem 1.5rem; border-radius: 4px;
            font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            white-space: nowrap;
        }
        .btn-tisk:hover { background: #333; }
        .btn-zavrit {
            position: fixed; bottom: 1.5rem; left: 1.5rem;
            background: #fff; color: #000; border: 1px solid #ccc;
            padding: 0.75rem 1.5rem; border-radius: 4px;
            font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-zavrit:hover { background: #f0f0f0; }
        .btn-odeslat {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            background: #999; color: #fff; border: none;
            padding: 0.75rem 1.5rem; border-radius: 4px;
            font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            white-space: nowrap;
        }
        .btn-odeslat:hover { background: #777; }
        .btn-odeslat:disabled { background: #ccc; cursor: not-allowed; }

        .tisk-pravni-poznamka {
            margin-top: 0.75rem; padding-top: 0.6rem;
            font-size: 0.65rem; color: #bbb;
            text-align: center; white-space: nowrap;
        }

        @media print {
            .btn-tisk { display: none !important; }
            .btn-zavrit { display: none !important; }
            .btn-odeslat { display: none !important; }
            body { background: #fff; }
            .tisk-obal { padding: 1rem; max-width: 100%; }
            @page { margin: 1.5cm; }
        }


        /* ── WGS Modal ── */
        #wgs-modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            display: none; align-items: center; justify-content: center;
            z-index: 10001;
        }
        .wgs-modal-karta {
            background: #fff; border-radius: 8px; overflow: hidden;
            max-width: 480px; width: 90%;
            box-shadow: 0 24px 64px rgba(0,0,0,0.45);
        }
        .wgs-modal-hlavicka {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 22px 28px;
        }
        .wgs-modal-titulek {
            margin: 0; font-size: 13px; font-weight: 700; color: #fff;
            text-transform: uppercase; letter-spacing: 2px;
        }
        .wgs-modal-podtitulek {
            margin: 5px 0 0; font-size: 11px; color: #888;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .wgs-modal-telo {
            padding: 24px 28px 16px;
        }
        .wgs-modal-zprava {
            margin: 0; font-size: 14px; color: #333; line-height: 1.65;
        }
        .wgs-modal-paticka {
            padding: 8px 28px 22px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .wgs-modal-countdown {
            font-size: 12px; color: #999;
        }
        .wgs-modal-btn {
            background: #111; color: #fff; border: none;
            padding: 10px 24px; border-radius: 4px; font-size: 13px;
            font-weight: 700; cursor: pointer; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .wgs-modal-btn:hover { background: #333; }
        .wgs-modal-btn-sekundarni {
            background: transparent; color: #666; border: 1px solid #ddd;
            padding: 10px 20px; border-radius: 4px; font-size: 13px;
            font-weight: 600; cursor: pointer; margin-right: 8px;
        }
        .wgs-modal-btn-sekundarni:hover { background: #f5f5f5; }
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

    <!-- Cenová nabídka (pokud existuje) -->
    <?php if ($nabidka && !empty($nabidkaPolozky)): ?>
    <?php
        // Seskupit položky podle skupiny
        $skupinyNazvy = [
            'doprava'   => 'Dopravné',
            'calouneni' => 'Čalounické práce',
            'mechanika' => 'Mechanické opravy',
            'priplatky' => 'Příplatky',
            'dily'      => 'Náhradní díly',
            'prace'     => 'Práce',
            'ostatni'   => 'Ostatní',
        ];
        $skupiny = [];
        foreach ($nabidkaPolozky as $pol) {
            $sk = $pol['skupina'] ?? 'ostatni';
            $skupiny[$sk][] = $pol;
        }
        $celkemEur = floatval($nabidka['celkova_cena']);
        $zalohaEur = $nabidkaZalohaEur;
        $doplatekEur = $nabidkaZfUhrazena ? max(0, $celkemEur - $zalohaEur) : null;
    ?>
    <div class="tisk-sekce">
        <div class="tisk-sekce-nadpis">
            Cenová nabídka <?= wygHtml($nabidka['cislo_nabidky'] ?? '') ?>
            <?php if ($nabidkaZfUhrazena): ?>
                <span class="cn-zf-odznak">Záloha uhrazena</span>
            <?php elseif ($nabidkaZfOdeslana): ?>
                <span class="cn-zf-odznak">Záloha odeslána</span>
            <?php endif; ?>
        </div>

        <table class="cn-tabulka">
            <thead>
                <tr>
                    <th>Název</th>
                    <th style="text-align:center;width:50px;">Ks</th>
                    <th style="text-align:right;width:80px;">Cena/ks</th>
                    <th style="text-align:right;width:80px;">Celkem</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $poradi = ['doprava', 'calouneni', 'mechanika', 'priplatky', 'dily', 'prace', 'ostatni'];
            foreach ($poradi as $sk):
                if (empty($skupiny[$sk])) continue;
                $nazevSkupiny = $skupinyNazvy[$sk] ?? ucfirst($sk);
            ?>
                <tr class="cn-skupina-radek">
                    <td colspan="4"><?= htmlspecialchars($nazevSkupiny, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php foreach ($skupiny[$sk] as $pol):
                    $pocet = intval($pol['pocet'] ?? 1);
                    $cena = floatval($pol['cena']);
                    $radekCelkem = $cena * $pocet;
                ?>
                <tr>
                    <td><?= wygHtml($pol['nazev'] ?? '') ?></td>
                    <td style="text-align:center;"><?= $pocet ?></td>
                    <td style="text-align:right;"><?= number_format($cena, 2, ',', ' ') ?> €</td>
                    <td style="text-align:right;font-weight:500;"><?= number_format($radekCelkem, 2, ',', ' ') ?> €</td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($zalohaEur > 0): ?>
        <!-- Záloha + doplatek - výrazný blok -->
        <div style="margin-top:1rem; border:2px solid #111; border-radius:4px; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:0.5rem 0.75rem; color:#333;">Celková cena nabídky:</td>
                    <td style="padding:0.5rem 0.75rem; text-align:right; font-weight:600;"><?= number_format($celkemEur, 2, ',', ' ') ?> €</td>
                </tr>
                <tr style="border-bottom:2px solid #111; background:#f5f5f5;">
                    <td style="padding:0.5rem 0.75rem; color:#333;">
                        Záloha – náhradní díly
                        <?php if ($nabidkaZfUhrazena): ?>
                            <span class="cn-zf-odznak">Uhrazena</span>
                        <?php elseif ($nabidkaZfOdeslana): ?>
                            <span class="cn-zf-odznak">Odeslána</span>
                        <?php else: ?>
                            <br><span style="font-size:0.72rem; color:#999; font-style:italic;">nebylo požadováno / uhrazeno</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:0.5rem 0.75rem; text-align:right; font-weight:600;">- <?= number_format($zalohaEur, 2, ',', ' ') ?> €</td>
                </tr>
                <?php if ($nabidkaZfUhrazena): ?>
                <tr style="background:#111;">
                    <td style="padding:0.65rem 0.75rem; color:#fff; font-weight:700; font-size:1rem;">Zbývá k doplacení:</td>
                    <td style="padding:0.65rem 0.75rem; text-align:right; color:#fff; font-weight:700; font-size:1rem;"><?= number_format($doplatekEur, 2, ',', ' ') ?> €<br><span style="font-size:0.75rem; font-weight:400; opacity:0.7;">cca <?= number_format($doplatekEur * 25, 0, ',', ' ') ?> Kč</span></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td style="padding:0.65rem 0.75rem; color:#333; font-weight:700; font-size:1rem;">Celková cena nabídky:</td>
                    <td style="padding:0.65rem 0.75rem; text-align:right; font-weight:700; font-size:1rem;"><?= number_format($celkemEur, 2, ',', ' ') ?> €</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php else: ?>
        <div class="cn-celkem-blok">
            <div class="cn-celkem-radek hlavni">
                <span>Celková cena nabídky:</span>
                <span><?= number_format($celkemEur, 2, ',', ' ') ?> €</span>
            </div>
        </div>
        <?php endif; ?>
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

    <?php if ($nabidka): ?>
    <p class="tisk-pravni-poznamka">Informační dokument. Fakturu nebo potvrzení o platbě zašleme obratem na vyžádání.</p>
    <?php endif; ?>

</div>

<button class="btn-tisk" onclick="window.print()">Tisknout</button>
<button class="btn-odeslat" id="btn-dale-odeslat" onclick="odeslatEmailem()">Odeslat</button>
<button class="btn-zavrit" onclick="window.close()">Zavřít</button>

<input type="hidden" id="csrf-token-tisk" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" id="reklamace-id-tisk" value="<?= (int)$idParam ?>">

<!-- WGS Loading Dialog - stejný styl jako protokol.php -->
<div class="wgs-loading-overlay" id="loadingOverlay">
  <div class="wgs-loading-box">
    <div class="wgs-loading-hourglass">
      <svg class="wgs-hourglass-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <path d="M20,10 L80,10 L80,20 L60,45 L60,55 L80,80 L80,90 L20,90 L20,80 L40,55 L40,45 L20,20 Z"
              fill="none" stroke="#39ff14" stroke-width="3" stroke-linejoin="round"/>
        <path d="M25,15 L75,15 L75,20 L57,42 L43,42 L25,20 Z" fill="#39ff14" opacity="0.6"/>
        <path d="M25,85 L75,85 L75,80 L57,58 L43,58 L25,80 Z" fill="#39ff14" opacity="0.3"/>
        <circle class="wgs-sand-particle" cx="50" cy="45" r="1.5" fill="#39ff14"/>
        <circle class="wgs-sand-particle" cx="48" cy="43" r="1.2" fill="#39ff14"/>
        <circle class="wgs-sand-particle" cx="52" cy="44" r="1.3" fill="#39ff14"/>
        <circle class="wgs-sand-particle" cx="49" cy="46" r="1.1" fill="#39ff14"/>
        <circle class="wgs-sand-particle" cx="51" cy="45" r="1.4" fill="#39ff14"/>
      </svg>
    </div>
    <div class="wgs-loading-message" id="loadingText">Odesílám email</div>
    <div class="wgs-loading-submessage" id="loadingSubtext">Prosím čekejte...</div>
  </div>
</div>

<!-- WGS modal (úspěch / chyba) -->
<div id="wgs-modal-overlay">
    <div class="wgs-modal-karta">
        <div class="wgs-modal-hlavicka">
            <p class="wgs-modal-titulek">WHITE GLOVE SERVICE</p>
            <p class="wgs-modal-podtitulek" id="wgs-modal-podtitulek">Odeslání přehledu zakázky</p>
        </div>
        <div class="wgs-modal-telo">
            <p class="wgs-modal-zprava" id="wgs-modal-zprava"></p>
        </div>
        <div class="wgs-modal-paticka">
            <span class="wgs-modal-countdown" id="wgs-modal-countdown"></span>
            <div>
                <button class="wgs-modal-btn-sekundarni" id="wgs-modal-btn-zavrit" onclick="wgsModalZavrit()" style="display:none;">Zavřít</button>
                <button class="wgs-modal-btn" id="wgs-modal-btn-akce" onclick="wgsModalAkce()">Přejít na seznam</button>
            </div>
        </div>
    </div>
</div>

<script>
let _wgsModalInterval = null;
let _wgsModalUspech = false;

function wgsZobrazLoading(zobrazit) {
    const overlay = document.getElementById('loadingOverlay');
    if (zobrazit) {
        overlay.style.display = '';
        overlay.classList.add('show');
    } else {
        overlay.classList.remove('show');
    }
}

function wgsZobrazModal(podtitulek, zprava, jeUspech) {
    _wgsModalUspech = jeUspech;
    document.getElementById('wgs-modal-podtitulek').textContent = podtitulek;
    document.getElementById('wgs-modal-zprava').textContent = zprava;
    document.getElementById('wgs-modal-btn-zavrit').style.display = jeUspech ? 'none' : 'inline-block';
    document.getElementById('wgs-modal-btn-akce').textContent = jeUspech ? 'Přejít na seznam' : 'Zkusit znovu';
    document.getElementById('wgs-modal-overlay').style.display = 'flex';

    if (jeUspech) {
        let zbyvaSekund = 4;
        const countdown = document.getElementById('wgs-modal-countdown');
        countdown.textContent = 'Přesměrování za ' + zbyvaSekund + ' s';
        _wgsModalInterval = setInterval(function () {
            zbyvaSekund--;
            if (zbyvaSekund <= 0) {
                clearInterval(_wgsModalInterval);
                window.location.href = '/seznam.php';
            } else {
                countdown.textContent = 'Přesměrování za ' + zbyvaSekund + ' s';
            }
        }, 1000);
    } else {
        document.getElementById('wgs-modal-countdown').textContent = '';
    }
}

function wgsModalAkce() {
    if (_wgsModalInterval) clearInterval(_wgsModalInterval);
    if (_wgsModalUspech) {
        window.location.href = '/seznam.php';
    } else {
        document.getElementById('wgs-modal-overlay').style.display = 'none';
    }
}

function wgsModalZavrit() {
    if (_wgsModalInterval) clearInterval(_wgsModalInterval);
    document.getElementById('wgs-modal-overlay').style.display = 'none';
}

async function odeslatEmailem() {
    const tlacitko = document.getElementById('btn-dale-odeslat');
    tlacitko.disabled = true;
    tlacitko.textContent = 'Odesílám...';
    wgsZobrazLoading(true);

    try {
        const formData = new FormData();
        formData.append('csrf_token', document.getElementById('csrf-token-tisk').value);
        formData.append('reklamace_id', document.getElementById('reklamace-id-tisk').value);

        const odpoved = await fetch('/api/odeslat_tisk_email.php', {
            method: 'POST',
            body: formData
        });

        const vysledek = await odpoved.json();
        wgsZobrazLoading(false);

        if (vysledek.status === 'success') {
            tlacitko.textContent = 'Odesláno';
            wgsZobrazModal('Email odeslán', vysledek.message, true);
        } else {
            tlacitko.disabled = false;
            tlacitko.textContent = 'Odeslat';
            wgsZobrazModal('Chyba při odesílání', vysledek.message || 'Nepodařilo se odeslat email.', false);
        }
    } catch (chyba) {
        wgsZobrazLoading(false);
        tlacitko.disabled = false;
        tlacitko.textContent = 'Odeslat';
        wgsZobrazModal('Síťová chyba', chyba.message, false);
    }
}
</script>

</body>
</html>
