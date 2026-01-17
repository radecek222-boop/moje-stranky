<?php
/**
 * Migrace: Přidat možnost upravit chat zprávy
 *
 * Tento skript BEZPEČNĚ provede:
 * 1. Přidá sloupec edited_at do wgs_hry_chat (kdy byla zpráva naposledy upravena)
 *
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
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
    <title>Migrace: Chat Edit</title>
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
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 16px; }
        .btn:hover { background: #1a300d; }
        pre { background: #f8f8f8; padding: 15px; border-radius: 5px;
              border-left: 4px solid #2D5016; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Chat Edit (Úprava zpráv)</h1>";

    // 1. Kontrolní fáze
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $zmeny = [];
    $potrebaMigrace = false;

    // Zkontrolovat zda existuje sloupec edited_at
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_hry_chat LIKE 'edited_at'");
    $editedAtExistuje = $stmt->fetch() !== false;

    if (!$editedAtExistuje) {
        $zmeny[] = "➕ Přidat sloupec <code>edited_at</code> do tabulky <code>wgs_hry_chat</code>";
        $potrebaMigrace = true;
    } else {
        echo "<div class='success'>✅ Sloupec <code>edited_at</code> již existuje</div>";
    }

    // Pokud není potřeba migrace
    if (!$potrebaMigrace) {
        echo "<div class='success'><strong>✅ MIGRACE JIŽ BYLA PROVEDENA</strong><br>Všechny změny jsou již v databázi.</div>";
        echo "<a href='hry.php' class='btn'>← Zpět do herní zóny</a>";
        echo "</div></body></html>";
        exit;
    }

    // Zobrazit náhled změn
    echo "<div class='warning'><strong>📋 PLÁNOVANÉ ZMĚNY:</strong><ul>";
    foreach ($zmeny as $zmena) {
        echo "<li>" . $zmena . "</li>";
    }
    echo "</ul></div>";

    // 2. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Přidat sloupec edited_at
            if (!$editedAtExistuje) {
                $pdo->exec("
                    ALTER TABLE wgs_hry_chat
                    ADD COLUMN edited_at TIMESTAMP NULL DEFAULT NULL
                    COMMENT 'Kdy byla zpráva naposledy upravena'
                ");
                echo "<div class='success'>✅ Přidán sloupec <code>edited_at</code></div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Všechny změny byly úspěšně aplikovány.<br><br>";
            echo "<strong>Co bylo provedeno:</strong><ul>";
            foreach ($zmeny as $zmena) {
                echo "<li>" . $zmena . "</li>";
            }
            echo "</ul></div>";

            echo "<a href='hry.php' class='btn'>← Zpět do herní zóny</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
            echo "<a href='pridej_chat_edit.php' class='btn'>← Zkusit znovu</a>";
        }
    } else {
        // Náhled co bude provedeno
        echo "<h2>SQL příkazy které budou provedeny:</h2>";

        if (!$editedAtExistuje) {
            echo "<pre>ALTER TABLE wgs_hry_chat
ADD COLUMN edited_at TIMESTAMP NULL DEFAULT NULL
COMMENT 'Kdy byla zpráva naposledy upravena';</pre>";
        }

        echo "<a href='?execute=1' class='btn'>▶ SPUSTIT MIGRACI</a>";
        echo "<a href='hry.php' class='btn' style='background:#666;'>← Zpět bez změn</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
