<?php
/**
 * Migrace: Pridani nastaveni pro archivaci emailu
 *
 * Tento skript prida nastaveni 'email_archive_address' do wgs_system_config.
 * Vsechny odeslane emaily budou automaticky kopirovany na tento email.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Nastaveni archivace emailu</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 800px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #555; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        input[type='email'] { width: 100%; padding: 10px; font-size: 16px;
                              border: 1px solid #ccc; border-radius: 5px;
                              margin: 10px 0; }
        label { font-weight: bold; display: block; margin-top: 15px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Archivace odeslanych emailu</h1>";

    // Kontrola, zda nastaveni existuje
    $stmt = $pdo->prepare("SELECT config_value FROM wgs_system_config WHERE config_key = 'email_archive_address'");
    $stmt->execute();
    $existujici = $stmt->fetchColumn();

    // Zpracovani formulare
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
        $email = trim($_POST['email']);

        if (empty($email)) {
            // Vypnout archivaci
            $stmt = $pdo->prepare("DELETE FROM wgs_system_config WHERE config_key = 'email_archive_address'");
            $stmt->execute();
            echo "<div class='success'><strong>Archivace emailu byla VYPNUTA.</strong></div>";
            $existujici = null;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "<div class='error'><strong>Neplatna emailova adresa.</strong></div>";
        } else {
            // Ulozit nebo aktualizovat
            if ($existujici !== false) {
                $stmt = $pdo->prepare("UPDATE wgs_system_config SET config_value = :email WHERE config_key = 'email_archive_address'");
            } else {
                $stmt = $pdo->prepare("INSERT INTO wgs_system_config (config_key, config_value, config_type, description) VALUES ('email_archive_address', :email, 'string', 'Email pro archivaci vsech odeslanych emailu (BCC)')");
            }
            $stmt->execute(['email' => $email]);
            echo "<div class='success'><strong>Archivacni email nastaven na:</strong> " . htmlspecialchars($email) . "</div>";
            $existujici = $email;
        }
    }

    // Aktualni stav
    if ($existujici) {
        echo "<div class='info'><strong>Aktualne nastaveny archivacni email:</strong> " . htmlspecialchars($existujici) . "</div>";
    } else {
        echo "<div class='warning'><strong>Archivace emailu neni nastavena.</strong> Odeslane emaily se neukladaji.</div>";
    }

    echo "<div class='info'>
        <strong>Jak to funguje:</strong><br>
        Kazdy email odeslany ze systemu bude automaticky odeslan take na tento archivacni email jako BCC (slepa kopie).<br>
        Emaily se tak budou ukladat ve vasi emailove schrance.
    </div>";

    // Formular
    echo "<form method='post'>
        <label for='email'>Archivacni email (prazdne = vypnout):</label>
        <input type='email' id='email' name='email' value='" . htmlspecialchars($existujici ?: '') . "' placeholder='vas@email.cz'>
        <button type='submit' class='btn'>Ulozit nastaveni</button>
        <a href='/admin.php' class='btn' style='background:#666'>Zpet do admin</a>
    </form>";

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
