<?php
/**
 * Změna FROM emailu pro řešení SPF problému
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Změna FROM Email</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; }
        .section { background: #f8f9fa; padding: 15px; border-radius: 5px;
                   margin: 15px 0; border-left: 4px solid #2D5016; }
        .success { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .btn { padding: 10px 20px; background: #2D5016; color: white;
               border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #1a300d; }
        input[type=text] { width: 100%; padding: 10px; border: 1px solid #ddd;
                           border-radius: 5px; margin: 5px 0; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Změna FROM Email pro řešení SPF problému</h1>";

echo "<div class='section error'>";
echo "<strong>Problém:</strong><br>";
echo "SPF Policy Error při použití <code>reklamace@wgs-service.cz</code><br>";
echo "SMTP server odmítá tento email jako odesílatele kvůli SPF politice.";
echo "</div>";

if (isset($_POST['change_email'])) {
    // CSRF ochrana
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("<div class='section error'>❌ Neplatný CSRF token. Obnovte stránku a zkuste znovu.</div>");
    }

    try {
        $pdo = getDbConnection();

        $newFromEmail = trim($_POST['from_email']);
        $newFromName = trim($_POST['from_name']);

        if (!filter_var($newFromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Neplatný formát emailu');
        }

        $stmt = $pdo->prepare("
            UPDATE wgs_smtp_settings
            SET smtp_from_email = :email,
                smtp_from_name = :name
            WHERE is_active = 1
        ");

        $stmt->execute([
            ':email' => $newFromEmail,
            ':name' => $newFromName
        ]);

        echo "<div class='section success'>";
        echo "✅ FROM email změněn na: <strong>{$newFromEmail}</strong><br>";
        echo "FROM name změněn na: <strong>{$newFromName}</strong><br><br>";
        echo "<a href='/smtp_test.php' class='btn'>Otestovat znovu</a>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='section error'>";
        echo "❌ Chyba: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}

// Načíst aktuální nastavení
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='section'>";
    echo "<strong>Aktuální nastavení:</strong><br>";
    echo "FROM Email: <code>{$smtp['smtp_from_email']}</code><br>";
    echo "FROM Name: <code>{$smtp['smtp_from_name']}</code>";
    echo "</div>";

    echo "<div class='section'>";
    echo "<h3>Změnit FROM email:</h3>";
    echo "<form method='POST'>";

    // CSRF token
    $csrfToken = generateCSRFToken();
    echo "<input type='hidden' name='csrf_token' value='{$csrfToken}'>";

    echo "<label>Nový FROM Email:</label>";
    echo "<input type='text' name='from_email' value='info@wgs-service.cz' required>";
    echo "<small>Doporučené: info@wgs-service.cz nebo admin@wgs-service.cz</small><br><br>";

    echo "<label>FROM Name:</label>";
    echo "<input type='text' name='from_name' value='White Glove Service' required>";
    echo "<br><br>";

    echo "<button type='submit' name='change_email' class='btn'>Změnit FROM email</button>";
    echo "</form>";
    echo "</div>";

    echo "<div class='section'>";
    echo "<strong>Důležité:</strong><br>";
    echo "• Zkontrolujte, že nový email účet existuje v cPanel<br>";
    echo "• Po změně otestujte přes <a href='/smtp_test.php'>smtp_test.php</a><br>";
    echo "• Pokud problém přetrvává, kontaktujte support Českého Hostingu";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "❌ Chyba: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
