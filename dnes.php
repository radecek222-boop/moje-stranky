<?php
/**
 * Denní přehled technika
 * Zobrazuje aktivní zakázky přiřazené přihlášenému technikovi.
 */

require_once __DIR__ . '/init.php';

$jeAdmin    = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$jeUzivatel = isset($_SESSION['user_id']);

if (!$jeAdmin && !$jeUzivatel) {
    header('Location: login.php?redirect=dnes.php');
    exit;
}

$aktualniUserId = $_SESSION['user_id'] ?? null;
$aktualniJmeno  = $_SESSION['user_name'] ?? 'Admin';

$zakázky  = [];
$skupiny  = [];
$chyba    = null;
$celkem   = 0;

try {
    $pdo = getDbConnection();

    // Převod VARCHAR user_id na INT id (assigned_to je INT)
    $aktualniIntId = null;
    if ($aktualniUserId) {
        if (is_numeric($aktualniUserId)) {
            $aktualniIntId = (int) $aktualniUserId;
        } else {
            $s = $pdo->prepare('SELECT id FROM wgs_users WHERE user_id = :uid LIMIT 1');
            $s->execute([':uid' => $aktualniUserId]);
            $row = $s->fetchColumn();
            if ($row) {
                $aktualniIntId = (int) $row;
            }
        }
    }

    if ($jeAdmin) {
        $where  = "WHERE r.stav != 'done'";
        $params = [];
    } else {
        $conditions = ["r.stav != 'done'"];
        $params     = [];
        if ($aktualniIntId) {
            $conditions[] = '(r.assigned_to = :int_id OR r.created_by = :user_id)';
            $params[':int_id']  = $aktualniIntId;
            $params[':user_id'] = $aktualniUserId;
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);
    }

    $sql = "
        SELECT
            r.id, r.reklamace_id, r.cislo, r.jmeno, r.email, r.telefon,
            r.adresa, r.mesto, r.model, r.stav, r.termin, r.cas_navstevy,
            r.popis_problemu, r.created_at, r.updated_at,
            COALESCE(r.je_odlozena, 0) AS je_odlozena,
            t.name  AS technik_jmeno,
            t.phone AS technik_telefon
        FROM wgs_reklamace r
        LEFT JOIN wgs_users t ON r.assigned_to = t.id
        $where
        ORDER BY
            CASE r.stav
                WHEN 'open'           THEN 1
                WHEN 'cekame_na_dily' THEN 2
                WHEN 'wait'           THEN 3
                ELSE 4
            END,
            CASE WHEN r.termin IS NOT NULL AND r.termin != '' THEN 0 ELSE 1 END,
            r.termin ASC,
            r.updated_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $zakázky = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $celkem  = count($zakázky);

    $skupiny = [
        'open'           => ['nazev' => 'Domluvená návštěva',  'zakazky' => [], 'trida' => 'sk-open'],
        'cekame_na_dily' => ['nazev' => 'Čekáme na díly',      'zakazky' => [], 'trida' => 'sk-dily'],
        'wait'           => ['nazev' => 'Čeká na zpracování',   'zakazky' => [], 'trida' => 'sk-wait'],
    ];

    foreach ($zakázky as $z) {
        $stav = $z['stav'] ?? 'wait';
        if (!isset($skupiny[$stav])) {
            $skupiny[$stav] = ['nazev' => $stav, 'zakazky' => [], 'trida' => 'sk-wait'];
        }
        $skupiny[$stav]['zakazky'][] = $z;
    }

} catch (Exception $e) {
    $chyba = $e->getMessage();
    error_log('dnes.php chyba: ' . $e->getMessage());
}

$dnes     = date('d.m.Y');
$denTydne = ['Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota'][date('w')];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Denní přehled – <?= htmlspecialchars($dnes, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f2f2f2; color: #111; min-height: 100vh; }

        .hlavicka {
            background: #000; color: #fff;
            padding: 1rem 1.5rem;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
        }
        .hlavicka-levo h1 { font-size: 1.05rem; font-weight: 700; letter-spacing: 0.5px; }
        .hlavicka-levo .datum { font-size: 0.8rem; opacity: 0.6; margin-top: 0.1rem; }
        .hlavicka-pravo { display: flex; align-items: center; gap: 1rem; font-size: 0.85rem; }
        .hlavicka-pravo a { color: #ccc; text-decoration: none; font-size: 0.8rem; }
        .hlavicka-pravo a:hover { color: #fff; }

        .obsah { max-width: 960px; margin: 1.5rem auto; padding: 0 1rem; }

        .souhrn {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.75rem; margin-bottom: 1.75rem;
        }
        .souhrn-karta {
            background: #fff; border-radius: 5px;
            padding: 1rem 1.25rem; text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
        }
        .souhrn-cislo { font-size: 2rem; font-weight: 700; line-height: 1; }
        .souhrn-popisek { font-size: 0.75rem; color: #777; margin-top: 0.3rem; }

        .skupina { margin-bottom: 1.75rem; }
        .skupina-nadpis {
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; color: #fff;
            padding: 0.55rem 1rem; border-radius: 4px 4px 0 0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .sk-open  { background: #111; }
        .sk-dily  { background: #444; }
        .sk-wait  { background: #888; }

        .zakazka {
            background: #fff; border: 1px solid #e0e0e0; border-top: none;
            padding: 1rem 1.25rem;
            display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 2rem;
        }
        .zakazka:last-child { border-radius: 0 0 4px 4px; }
        .zakazka:hover { background: #fafafa; }

        .zak-cislo { font-size: 0.78rem; color: #666; font-family: monospace; }
        .zak-jmeno { font-size: 1rem; font-weight: 600; margin: 0.15rem 0; }
        .zak-telefon a { color: #000; font-size: 0.95rem; text-decoration: none; font-weight: 500; }
        .zak-telefon a:hover { text-decoration: underline; }
        .zak-model { font-size: 0.85rem; color: #333; }
        .zak-adresa { font-size: 0.82rem; color: #777; margin-top: 0.2rem; }
        .zak-termin {
            display: inline-block; margin-top: 0.4rem;
            background: #f0f0f0; border-radius: 3px;
            padding: 0.2rem 0.55rem; font-size: 0.82rem; font-weight: 600;
        }
        .zak-akce { margin-top: 0.6rem; }
        .btn-detail {
            display: inline-block; padding: 0.35rem 0.85rem;
            background: #111; color: #fff; border: none; border-radius: 3px;
            font-size: 0.78rem; text-decoration: none; cursor: pointer;
            font-family: inherit;
        }
        .btn-detail:hover { background: #333; }

        .prazdno { text-align: center; padding: 3rem 1rem; background: #fff; border-radius: 5px; color: #999; font-size: 0.9rem; }

        @media (max-width: 600px) {
            .zakazka { grid-template-columns: 1fr; }
            .souhrn { grid-template-columns: repeat(2, 1fr); }
        }
        @media print {
            .hlavicka { background: #fff !important; color: #000 !important; border-bottom: 2px solid #000; }
            .btn-detail { display: none; }
        }
    </style>
</head>
<body>

<div class="hlavicka">
    <div class="hlavicka-levo">
        <h1>Denní přehled</h1>
        <div class="datum"><?= $denTydne ?>, <?= $dnes ?></div>
    </div>
    <div class="hlavicka-pravo">
        <span><?= htmlspecialchars($aktualniJmeno, ENT_QUOTES, 'UTF-8') ?></span>
        <a href="seznam.php">← Seznam</a>
    </div>
</div>

<div class="obsah">

<?php if ($chyba): ?>
    <div style="background:#fff;padding:1.25rem;border:1px solid #ccc;border-radius:4px;color:#721c24;margin-bottom:1rem;">
        Chyba načítání: <?= htmlspecialchars($chyba, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php elseif ($celkem === 0): ?>
    <div class="prazdno">Žádné aktivní zakázky — vše vyřízeno.</div>
<?php else: ?>

    <!-- Souhrn -->
    <div class="souhrn">
        <div class="souhrn-karta">
            <div class="souhrn-cislo"><?= $celkem ?></div>
            <div class="souhrn-popisek">Aktivních zakázek</div>
        </div>
        <?php foreach ($skupiny as $skupina):
            $pocet = count($skupina['zakazky']);
            if ($pocet === 0) continue; ?>
        <div class="souhrn-karta">
            <div class="souhrn-cislo"><?= $pocet ?></div>
            <div class="souhrn-popisek"><?= htmlspecialchars($skupina['nazev'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Skupiny zakázek -->
    <?php foreach ($skupiny as $skupina):
        if (empty($skupina['zakazky'])) continue; ?>
    <div class="skupina">
        <div class="skupina-nadpis <?= htmlspecialchars($skupina['trida'], ENT_QUOTES, 'UTF-8') ?>">
            <span><?= htmlspecialchars($skupina['nazev'], ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= count($skupina['zakazky']) ?></span>
        </div>
        <?php foreach ($skupina['zakazky'] as $z):
            $cislo   = htmlspecialchars($z['reklamace_id'] ?? $z['cislo'] ?? '#' . $z['id'], ENT_QUOTES, 'UTF-8');
            $jmeno   = htmlspecialchars($z['jmeno'] ?? '–', ENT_QUOTES, 'UTF-8');
            $telefon = htmlspecialchars($z['telefon'] ?? '', ENT_QUOTES, 'UTF-8');
            $model   = htmlspecialchars($z['model'] ?? '–', ENT_QUOTES, 'UTF-8');
            $adresa  = htmlspecialchars(
                trim(($z['adresa'] ?? '') ?: (trim(($z['mesto'] ?? '') . ' ' . ($z['psc'] ?? '')))),
                ENT_QUOTES, 'UTF-8'
            );
            $termin  = htmlspecialchars($z['termin'] ?? '', ENT_QUOTES, 'UTF-8');
            $cas     = htmlspecialchars($z['cas_navstevy'] ?? '', ENT_QUOTES, 'UTF-8');
            $id      = (int) $z['id'];
        ?>
        <div class="zakazka">
            <div>
                <div class="zak-cislo"><?= $cislo ?></div>
                <div class="zak-jmeno"><?= $jmeno ?></div>
                <?php if ($telefon): ?>
                <div class="zak-telefon"><a href="tel:<?= $telefon ?>"><?= $telefon ?></a></div>
                <?php endif; ?>
            </div>
            <div>
                <div class="zak-model"><?= $model ?></div>
                <?php if ($adresa): ?><div class="zak-adresa"><?= $adresa ?></div><?php endif; ?>
                <?php if ($termin): ?>
                <div>
                    <span class="zak-termin"><?= $termin ?><?= $cas ? ' · ' . $cas : '' ?></span>
                </div>
                <?php endif; ?>
                <div class="zak-akce">
                    <a href="seznam.php?openId=<?= $id ?>" class="btn-detail">Detail</a>
                    <?php if ($telefon): ?>
                    <a href="tel:<?= $telefon ?>" class="btn-detail" style="background:#333; margin-left:0.4rem;">Zavolat</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

<?php endif; ?>
</div>

</body>
</html>
