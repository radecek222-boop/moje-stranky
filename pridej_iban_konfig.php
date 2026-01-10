<?php
/**
 * Migrace: Přidání IBAN konfigurace pro QR platby
 *
 * Tento skript BEZPEČNĚ přidá konfiguraci firemního IBAN do wgs_system_config.
 * Můžete jej spustit vícekrát - neprovede duplicitní záznamy.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: IBAN konfigurace</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 800px; margin: 50px auto; padding: 20px;
               background: #1a1a1a; color: #fff; }
        .container { background: #222; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        h1 { color: #39ff14; border-bottom: 3px solid #39ff14;
             padding-bottom: 10px; }
        .success { background: #143d14; border: 1px solid #39ff14;
                   color: #39ff14; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #3d1414; border: 1px solid #ff4444;
                 color: #ff4444; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #3d3d14; border: 1px solid #ffaa00;
                   color: #ffaa00; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #143d3d; border: 1px solid #00aaff;
                color: #00aaff; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #39ff14; color: #000; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; font-weight: bold; }
        .btn:hover { background: #2dd10f; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; color: #aaa; }
        .form-group input { width: 100%; padding: 10px; background: #333;
                           border: 1px solid #555; color: #fff; border-radius: 5px;
                           font-size: 1rem; }
        .form-group input:focus { border-color: #39ff14; outline: none; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: IBAN konfigurace pro QR platby</h1>";

    // Kontrola existence tabulky wgs_system_config
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_system_config'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='error'>Tabulka wgs_system_config neexistuje! Spusťte nejprve základní migraci.</div>";
        echo "</div></body></html>";
        exit;
    }

    // Kontrola existence záznamu
    $stmt = $pdo->prepare("SELECT config_value FROM wgs_system_config WHERE config_key = 'company_iban'");
    $stmt->execute();
    $existujiciIban = $stmt->fetch(PDO::FETCH_ASSOC);

    // Zpracování formuláře
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iban'])) {
        $novyIban = strtoupper(str_replace(' ', '', trim($_POST['iban'])));

        // Validace IBAN formátu (CZ má 24 znaků)
        if (!preg_match('/^CZ\d{22}$/', $novyIban)) {
            echo "<div class='error'>Neplatný formát IBAN! Očekáván český IBAN (CZ + 22 číslic).</div>";
        } else {
            if ($existujiciIban) {
                // Aktualizace
                $stmt = $pdo->prepare("UPDATE wgs_system_config SET config_value = :iban, updated_at = NOW() WHERE config_key = 'company_iban'");
                $stmt->execute(['iban' => $novyIban]);
                echo "<div class='success'>IBAN úspěšně aktualizován na: <code>{$novyIban}</code></div>";
            } else {
                // Vložení nového záznamu
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_system_config (config_key, config_value, config_group, is_sensitive, requires_restart, description, created_at, updated_at)
                    VALUES ('company_iban', :iban, 'payment', 0, 0, 'Firemní IBAN pro QR platby zákazníků', NOW(), NOW())
                ");
                $stmt->execute(['iban' => $novyIban]);
                echo "<div class='success'>IBAN úspěšně přidán: <code>{$novyIban}</code></div>";
            }

            // Aktualizovat hodnotu
            $stmt = $pdo->prepare("SELECT config_value FROM wgs_system_config WHERE config_key = 'company_iban'");
            $stmt->execute();
            $existujiciIban = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Zobrazení formuláře
    $aktualniIban = $existujiciIban['config_value'] ?? '';

    if ($aktualniIban) {
        echo "<div class='info'>Aktuální IBAN: <code>{$aktualniIban}</code></div>";
    } else {
        echo "<div class='warning'>IBAN zatím není nakonfigurován.</div>";
    }

    echo "
    <form method='POST'>
        <div class='form-group'>
            <label for='iban'>Firemní IBAN (český formát CZ + 22 číslic):</label>
            <input type='text' id='iban' name='iban' value='{$aktualniIban}'
                   placeholder='CZ5855000000001265098001' maxlength='24'
                   pattern='CZ[0-9]{22}' required>
        </div>
        <button type='submit' class='btn'>Uložit IBAN</button>
    </form>

    <hr style='border-color: #444; margin: 30px 0;'>

    <h2>Informace</h2>
    <p>Tento IBAN se používá pro generování QR kódů plateb v detailu zákazníka.</p>
    <p>QR kód bude obsahovat:</p>
    <ul>
        <li><strong>Účet:</strong> Firemní IBAN</li>
        <li><strong>Částka:</strong> Celková cena zakázky (cena_celkem)</li>
        <li><strong>Variabilní symbol:</strong> Číslo reklamace</li>
        <li><strong>Měna:</strong> CZK</li>
    </ul>

    <p><a href='/seznam.php' class='btn' style='background: #666;'>Zpět na Seznam</a></p>
    ";

} catch (Exception $e) {
    echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
