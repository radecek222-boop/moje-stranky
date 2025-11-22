<?php
/**
 * Smazání dnešního záznamu aktualit
 * Použij pro regeneraci bez emoji
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může smazat záznam.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Smazat dnešní aktualitu</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a1a1a; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #dc3545; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 16px; }
        .btn:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        form { display: inline; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();
    $dnes = date('Y-m-d');

    echo "<h1>Smazání dnešní aktuality</h1>";

    // Zkontrolovat existenci záznamu
    $stmtCheck = $pdo->prepare("SELECT * FROM wgs_natuzzi_aktuality WHERE datum = :datum");
    $stmtCheck->execute(['datum' => $dnes]);
    $zaznam = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$zaznam) {
        echo "<div class='warning'><strong>Záznam nenalezen.</strong><br>";
        echo "Pro datum {$dnes} neexistuje žádná aktualita.</div>";
        echo "<a href='admin.php' class='btn btn-secondary'>Zpět do Admin panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    // Zobrazit náhled
    echo "<div class='warning'>";
    echo "<strong>Nalezen záznam:</strong><br>";
    echo "Datum: {$zaznam['datum']}<br>";
    echo "Svátek: {$zaznam['svatek_cz']}<br>";
    echo "ID: {$zaznam['id']}<br>";
    echo "Vytvořeno: {$zaznam['created_at']}<br>";
    echo "</div>";

    // Pokud je potvrzeno přes POST, smazat
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
        // CSRF validace
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong> Neplatný CSRF token. Obnovte stránku a zkuste to znovu.";
            echo "</div>";
        } else {
            $stmtDelete = $pdo->prepare("DELETE FROM wgs_natuzzi_aktuality WHERE datum = :datum");
            $stmtDelete->execute(['datum' => $dnes]);

            echo "<div class='success'>";
            echo "<strong>✅ ZÁZNAM SMAZÁN</strong><br>";
            echo "Dnešní aktualita byla úspěšně smazána z databáze.<br><br>";
            echo "Nyní můžete vygenerovat novou aktualitu BEZ EMOJI:<br>";
            echo "<a href='api/generuj_aktuality_debug.php' class='btn btn-secondary'>Vygenerovat novou aktualitu</a>";
            echo "</div>";
        }
    } else {
        // Formulář s CSRF tokenem
        echo "<p><strong>Opravdu chcete smazat tento záznam?</strong></p>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') . "'>";
        echo "<input type='hidden' name='confirm' value='1'>";
        echo "<button type='submit' class='btn'>ANO, SMAZAT</button>";
        echo "</form>";
        echo "<a href='admin.php' class='btn btn-secondary'>Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
