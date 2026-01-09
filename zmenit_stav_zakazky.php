<?php
/**
 * Admin nástroj pro změnu stavu zakázky a CN workflow
 *
 * Použití: ?cislo=POZ/2025/08-12/01&stav=wait&cn_stav=cekame_nd
 *
 * Parametry:
 * - cislo: Číslo reklamace/zakázky
 * - stav: Nový stav (wait, open, done)
 * - cn_stav: Stav CN workflow (cekame_nd, zf_odeslana, zf_uhrazena, dokonceno, fa_uhrazena) - volitelné
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může měnit stavy zakázek.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Změna stavu zakázky</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 800px; margin: 50px auto; padding: 20px;
               background: #1a1a1a; color: #fff; }
        .container { background: #222; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        h1 { color: #fff; border-bottom: 3px solid #39ff14;
             padding-bottom: 10px; }
        .success { background: rgba(57, 255, 20, 0.1); border: 1px solid #39ff14;
                   color: #39ff14; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: rgba(220, 53, 69, 0.1); border: 1px solid #dc3545;
                 color: #dc3545; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: rgba(255, 193, 7, 0.1); border: 1px solid #ffc107;
                   color: #ffc107; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: rgba(0, 153, 255, 0.1); border: 1px solid #0099ff;
                color: #0099ff; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        form { margin: 20px 0; }
        label { display: block; margin: 10px 0 5px; color: #ccc; }
        input, select { padding: 10px; width: 100%; box-sizing: border-box;
                        background: #333; border: 1px solid #555; color: #fff;
                        border-radius: 5px; margin-bottom: 10px; }
        input:focus, select:focus { border-color: #39ff14; outline: none; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #39ff14; color: #000; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-weight: 600; }
        .btn:hover { background: #2dd10f; }
        .btn-secondary { background: #555; color: #fff; }
        .btn-secondary:hover { background: #666; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #444; }
        th { color: #39ff14; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Změna stavu zakázky</h1>";

    // Získat parametry
    $cislo = $_GET['cislo'] ?? $_POST['cislo'] ?? '';
    $novyStav = $_GET['stav'] ?? $_POST['stav'] ?? '';
    $cnStav = $_GET['cn_stav'] ?? $_POST['cn_stav'] ?? '';
    $potvrdit = isset($_GET['potvrdit']) || isset($_POST['potvrdit']);

    // Mapování stavů
    $stavyMap = [
        'wait' => 'ČEKÁ (NOVÁ)',
        'open' => 'DOMLUVENÁ',
        'done' => 'HOTOVO'
    ];

    $cnStavyMap = [
        '' => '(bez změny)',
        'cekame_nd' => 'Čekáme ND',
        'zf_odeslana' => 'ZF odeslána',
        'zf_uhrazena' => 'ZF uhrazena',
        'dokonceno' => 'Dokončeno',
        'fa_uhrazena' => 'FA uhrazena'
    ];

    // Formulář
    echo "<form method='POST'>
        <label for='cislo'>Číslo zakázky:</label>
        <input type='text' id='cislo' name='cislo' value='" . htmlspecialchars($cislo) . "' placeholder='např. POZ/2025/08-12/01' required>

        <label for='stav'>Nový stav zakázky:</label>
        <select id='stav' name='stav' required>
            <option value=''>-- Vyberte stav --</option>";
    foreach ($stavyMap as $key => $label) {
        $selected = ($novyStav === $key) ? 'selected' : '';
        echo "<option value='$key' $selected>$label</option>";
    }
    echo "</select>

        <label for='cn_stav'>Stav CN (volitelné):</label>
        <select id='cn_stav' name='cn_stav'>";
    foreach ($cnStavyMap as $key => $label) {
        $selected = ($cnStav === $key) ? 'selected' : '';
        echo "<option value='$key' $selected>$label</option>";
    }
    echo "</select>

        <button type='submit' class='btn'>Vyhledat zakázku</button>
        <a href='/seznam.php' class='btn btn-secondary'>Zpět na seznam</a>
    </form>";

    // Pokud je zadáno číslo, vyhledat
    if ($cislo) {
        echo "<hr style='border-color: #444; margin: 20px 0;'>";

        // Najít zakázku
        $stmt = $pdo->prepare("
            SELECT id, reklamace_id, cislo_objednavky, jmeno, email, stav, termin, cas_navstevy
            FROM wgs_reklamace
            WHERE reklamace_id = :cislo OR cislo_objednavky = :cislo
            LIMIT 1
        ");
        $stmt->execute(['cislo' => $cislo]);
        $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$zakazka) {
            echo "<div class='error'>Zakázka <code>$cislo</code> nebyla nalezena.</div>";
        } else {
            $aktualniStav = $zakazka['stav'];
            $zakaznikEmail = strtolower(trim($zakazka['email'] ?? ''));

            echo "<div class='info'><strong>Nalezená zakázka:</strong></div>";
            echo "<table>
                <tr><th>ID</th><td>{$zakazka['id']}</td></tr>
                <tr><th>Číslo</th><td>" . ($zakazka['reklamace_id'] ?: $zakazka['cislo_objednavky']) . "</td></tr>
                <tr><th>Zákazník</th><td>{$zakazka['jmeno']}</td></tr>
                <tr><th>Email</th><td>{$zakazka['email']}</td></tr>
                <tr><th>Aktuální stav</th><td><code>$aktualniStav</code> ({$stavyMap[$aktualniStav]})</td></tr>
            </table>";

            // Najít CN pro zákazníka
            $cnNabidka = null;
            if ($zakaznikEmail) {
                $stmt = $pdo->prepare("
                    SELECT id, cislo_nabidky, stav, cekame_nd_at, zf_odeslana_at, zf_uhrazena_at, dokonceno_at, fa_uhrazena_at
                    FROM wgs_nabidky
                    WHERE LOWER(zakaznik_email) = :email
                    ORDER BY vytvoreno_at DESC
                    LIMIT 1
                ");
                $stmt->execute(['email' => $zakaznikEmail]);
                $cnNabidka = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($cnNabidka) {
                echo "<div class='info' style='margin-top: 15px;'><strong>Cenová nabídka:</strong></div>";
                echo "<table>
                    <tr><th>Číslo CN</th><td>{$cnNabidka['cislo_nabidky']}</td></tr>
                    <tr><th>Stav CN</th><td>{$cnNabidka['stav']}</td></tr>
                    <tr><th>Čekáme ND</th><td>" . ($cnNabidka['cekame_nd_at'] ? $cnNabidka['cekame_nd_at'] : '-') . "</td></tr>
                    <tr><th>ZF odeslána</th><td>" . ($cnNabidka['zf_odeslana_at'] ? $cnNabidka['zf_odeslana_at'] : '-') . "</td></tr>
                </table>";
            } else {
                echo "<div class='warning' style='margin-top: 15px;'>Zákazník nemá žádnou cenovou nabídku.</div>";
            }

            // Provést změnu
            if ($novyStav && $potvrdit) {
                $pdo->beginTransaction();

                try {
                    // 1. Změnit stav zakázky
                    $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = :stav WHERE id = :id");
                    $stmt->execute(['stav' => $novyStav, 'id' => $zakazka['id']]);

                    echo "<div class='success'>Stav zakázky změněn: <code>$aktualniStav</code> → <code>$novyStav</code></div>";

                    // 2. Změnit CN stav (pokud je zadán a existuje CN)
                    if ($cnStav && $cnNabidka) {
                        $sloupec = $cnStav . '_at';

                        // Nastavit timestamp
                        $stmt = $pdo->prepare("UPDATE wgs_nabidky SET {$sloupec} = NOW() WHERE id = ?");
                        $stmt->execute([$cnNabidka['id']]);

                        echo "<div class='success'>CN workflow aktualizován: <code>$sloupec</code> nastaven</div>";
                    }

                    $pdo->commit();

                    echo "<div class='success' style='font-size: 1.2em; margin-top: 20px;'>
                        <strong>ZMĚNY ULOŽENY</strong><br>
                        Zakázka bude nyní zobrazovat stav: <strong>{$cnStavyMap[$cnStav] ?: $stavyMap[$novyStav]}</strong>
                    </div>";

                    echo "<a href='/seznam.php' class='btn'>Zpět na seznam</a>";

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "<div class='error'>Chyba při ukládání: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            } elseif ($novyStav) {
                // Zobrazit potvrzení
                echo "<div class='warning' style='margin-top: 20px;'>
                    <strong>Plánované změny:</strong><br>
                    - Stav zakázky: <code>$aktualniStav</code> → <code>$novyStav</code> ({$stavyMap[$novyStav]})<br>";
                if ($cnStav && $cnNabidka) {
                    echo "- CN workflow: nastavit <code>{$cnStav}_at</code><br>";
                }
                echo "</div>";

                echo "<form method='POST' style='margin-top: 15px;'>
                    <input type='hidden' name='cislo' value='" . htmlspecialchars($cislo) . "'>
                    <input type='hidden' name='stav' value='" . htmlspecialchars($novyStav) . "'>
                    <input type='hidden' name='cn_stav' value='" . htmlspecialchars($cnStav) . "'>
                    <input type='hidden' name='potvrdit' value='1'>
                    <button type='submit' class='btn'>POTVRDIT ZMĚNY</button>
                    <a href='?cislo=" . urlencode($cislo) . "' class='btn btn-secondary'>Zrušit</a>
                </form>";
            }
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
