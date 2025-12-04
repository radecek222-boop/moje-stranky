<?php
/**
 * Migrace: Přidání sloupce pro příjemce emailových šablon
 *
 * Přidá sloupec `recipients` do tabulky `wgs_notifications`
 * pro uložení nastavení příjemců (zákazník, prodejce, technik, výrobce, jiné)
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
    <title>Migrace: Příjemci emailových šablon</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; }
        .btn:hover { background: #1a300d; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání sloupce pro příjemce emailových šablon</h1>";

    // 1. Kontrola zda sloupec již existuje
    echo "<div class='info'><strong>KONTROLA SOUČASNÉHO STAVU...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_notifications LIKE 'recipients'");
    $columnExists = $stmt->rowCount() > 0;

    if ($columnExists) {
        echo "<div class='success'>Sloupec 'recipients' již existuje v tabulce wgs_notifications.</div>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>← Zpět do Admin panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>Sloupec 'recipients' nebyl nalezen. Bude vytvořen.</div>";

    // 2. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Přidat sloupec recipients (JSON)
            $pdo->exec("
                ALTER TABLE wgs_notifications
                ADD COLUMN recipients JSON DEFAULT NULL
                COMMENT 'Nastavení příjemců emailu (zákazník, prodejce, technik, výrobce, jiné)'
            ");

            // Nastavit výchozí hodnoty pro existující šablony
            $defaultRecipients = json_encode([
                'customer' => true,
                'seller' => false,
                'technician' => false,
                'importer' => [
                    'enabled' => false,
                    'email' => ''
                ],
                'other' => [
                    'enabled' => false,
                    'email' => ''
                ]
            ]);

            $pdo->exec("
                UPDATE wgs_notifications
                SET recipients = '{$defaultRecipients}'
                WHERE recipients IS NULL
            ");

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Sloupec 'recipients' byl úspěšně přidán do tabulky wgs_notifications.<br>";
            echo "Všechny existující šablony mají nastaveny výchozí příjemce (zákazník).<br>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>CO BYLO PROVEDENO:</strong><br>";
            echo "• Přidán sloupec 'recipients' (JSON) do tabulky wgs_notifications<br>";
            echo "• Výchozí hodnota: email se odesílá pouze zákazníkovi<br>";
            echo "• Možnost výběru příjemců:<br>";
            echo "&nbsp;&nbsp;- Zákazník<br>";
            echo "&nbsp;&nbsp;- Prodejce (který vytvořil reklamaci)<br>";
            echo "&nbsp;&nbsp;- Technik (který pracoval na reklamaci)<br>";
            echo "&nbsp;&nbsp;- Import zastupující / Výrobce (s možností vyplnit email)<br>";
            echo "&nbsp;&nbsp;- Jiné (s možností vyplnit vlastní email)<br>";
            echo "</div>";

            echo "<div class='success'>";
            echo "<strong>📝 STRUKTURA DAT:</strong><br>";
            echo "<pre>" . htmlspecialchars($defaultRecipients) . "</pre>";
            echo "</div>";

            echo "<a href='/admin.php' class='btn'>← Zpět do Admin panelu</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI PROVÁDĚNÍ MIGRACE:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Náhled co bude provedeno
        echo "<h3>📋 Co bude provedeno:</h3>";
        echo "<div class='info'>";
        echo "• Přidání sloupce <code>recipients</code> (JSON) do tabulky <code>wgs_notifications</code><br>";
        echo "• Nastavení výchozích hodnot pro existující šablony<br>";
        echo "• Možnost výběru příjemců u každé šablony<br>";
        echo "</div>";

        echo "<h3>📊 Struktura dat (JSON):</h3>";
        echo "<pre>{
  \"customer\": true,           // Zákazník
  \"seller\": false,            // Prodejce
  \"technician\": false,        // Technik
  \"importer\": {               // Výrobce/Import
    \"enabled\": false,
    \"email\": \"\"
  },
  \"other\": {                  // Jiné
    \"enabled\": false,
    \"email\": \"\"
  }
}</pre>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>← Zpět do Admin panelu</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
