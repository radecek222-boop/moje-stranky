<?php
/**
 * Kontrola a oprava sloupce claim_id v tabulkách
 *
 * Tento skript zkontroluje, zda tabulky wgs_documents, wgs_notes, wgs_notifications
 * mají správný sloupec claim_id pro vazbu na reklamace.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tuto kontrolu.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola sloupce claim_id</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
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
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #1a300d; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; color: #c7254e; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Kontrola sloupce claim_id v databázových tabulkách</h1>";

try {
    $pdo = getDbConnection();

    $tabulkyKKontrole = ['wgs_documents', 'wgs_notes', 'wgs_notifications'];
    $vysledky = [];

    echo "<div class='info'><strong>Kontroluji tabulky:</strong> " . implode(', ', $tabulkyKKontrole) . "</div>";

    foreach ($tabulkyKKontrole as $tabulka) {
        echo "<h2>📋 Tabulka: $tabulka</h2>";

        // Zkontrolovat jestli tabulka existuje
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabulka'");
        if ($stmt->rowCount() === 0) {
            echo "<div class='warning'>⚠️ Tabulka <code>$tabulka</code> neexistuje.</div>";
            $vysledky[$tabulka] = ['exists' => false];
            continue;
        }

        // Načíst všechny sloupce tabulky
        $stmt = $pdo->query("SHOW COLUMNS FROM $tabulka");
        $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $maClaim = false;
        $maReklamace = false;
        $maId = false;

        echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th></tr>";

        foreach ($sloupce as $sloupec) {
            echo "<tr>";
            echo "<td><code>{$sloupec['Field']}</code></td>";
            echo "<td>{$sloupec['Type']}</td>";
            echo "<td>{$sloupec['Null']}</td>";
            echo "<td>{$sloupec['Key']}</td>";
            echo "</tr>";

            if ($sloupec['Field'] === 'claim_id') $maClaim = true;
            if ($sloupec['Field'] === 'reklamace_id') $maReklamace = true;
            if ($sloupec['Field'] === 'id') $maId = true;
        }

        echo "</table>";

        $vysledky[$tabulka] = [
            'exists' => true,
            'ma_claim_id' => $maClaim,
            'ma_reklamace_id' => $maReklamace,
            'ma_id' => $maId,
            'sloupce' => $sloupce
        ];

        // Vyhodnocení
        if ($maClaim) {
            echo "<div class='success'>✅ Tabulka <code>$tabulka</code> má sloupec <code>claim_id</code> - SPRÁVNĚ</div>";
        } elseif ($maReklamace) {
            echo "<div class='warning'>⚠️ Tabulka <code>$tabulka</code> má sloupec <code>reklamace_id</code> místo <code>claim_id</code></div>";
            echo "<div class='info'>💡 Potřeba přejmenovat <code>reklamace_id</code> → <code>claim_id</code></div>";
        } else {
            echo "<div class='error'>❌ Tabulka <code>$tabulka</code> nemá sloupec <code>claim_id</code> ani <code>reklamace_id</code></div>";
            echo "<div class='info'>💡 Potřeba přidat sloupec <code>claim_id</code></div>";
        }
    }

    // Souhrn a nabídka opravy
    echo "<hr><h2>📊 Souhrn kontroly</h2>";

    $potrebujeOpravu = false;
    foreach ($vysledky as $tabulka => $data) {
        if (!$data['exists']) {
            echo "<div class='warning'>⚠️ <strong>$tabulka:</strong> Tabulka neexistuje</div>";
        } elseif (!$data['ma_claim_id']) {
            echo "<div class='error'>❌ <strong>$tabulka:</strong> Chybí sloupec claim_id</div>";
            $potrebujeOpravu = true;
        } else {
            echo "<div class='success'>✅ <strong>$tabulka:</strong> Struktura OK</div>";
        }
    }

    if ($potrebujeOpravu) {
        echo "<hr><h2>🔧 Automatická oprava</h2>";

        if (isset($_GET['opravit']) && $_GET['opravit'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

            $pdo->beginTransaction();

            try {
                foreach ($vysledky as $tabulka => $data) {
                    if (!$data['exists'] || $data['ma_claim_id']) {
                        continue;
                    }

                    if ($data['ma_reklamace_id']) {
                        // Přejmenovat reklamace_id → claim_id
                        echo "<div class='info'>🔄 Přejmenovávám sloupec v tabulce <code>$tabulka</code>: reklamace_id → claim_id</div>";
                        $sql = "ALTER TABLE $tabulka CHANGE COLUMN reklamace_id claim_id INT(11) NOT NULL";
                        $pdo->exec($sql);
                        echo "<div class='success'>✅ Přejmenování úspěšné</div>";
                    } else {
                        // Přidat nový sloupec claim_id
                        echo "<div class='info'>➕ Přidávám sloupec <code>claim_id</code> do tabulky <code>$tabulka</code></div>";
                        $sql = "ALTER TABLE $tabulka ADD COLUMN claim_id INT(11) NOT NULL AFTER id";
                        $pdo->exec($sql);
                        echo "<div class='success'>✅ Sloupec přidán</div>";

                        // Přidat index
                        echo "<div class='info'>🔑 Přidávám index na <code>claim_id</code></div>";
                        $sql = "ALTER TABLE $tabulka ADD INDEX idx_claim_id (claim_id)";
                        try {
                            $pdo->exec($sql);
                            echo "<div class='success'>✅ Index přidán</div>";
                        } catch (PDOException $e) {
                            echo "<div class='warning'>⚠️ Index již existuje nebo nelze přidat: " . htmlspecialchars($e->getMessage()) . "</div>";
                        }
                    }
                }

                $pdo->commit();

                echo "<div class='success'><strong>✅ OPRAVA DOKONČENA!</strong><br><br>";
                echo "Všechny tabulky mají nyní správný sloupec <code>claim_id</code>.<br>";
                echo "Mazání reklamací by mělo nyní fungovat správně.</div>";

                echo "<a href='?refresh=1' class='btn'>🔄 Zkontrolovat znovu</a>";
                echo "<a href='/admin.php' class='btn' style='background: #28a745;'>← Zpět do Admin panelu</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'><strong>❌ CHYBA PŘI OPRAVĚ:</strong><br>";
                echo htmlspecialchars($e->getMessage()) . "</div>";
            }

        } else {
            echo "<div class='warning'>⚠️ <strong>POZOR:</strong> Nalezeny problémy se strukturou databáze.</div>";
            echo "<p>Kliknutím na tlačítko níže provedete automatickou opravu:</p>";
            echo "<a href='?opravit=1' class='btn' style='background: #dc3545; font-size: 16px;'>🔧 OPRAVIT STRUKTURU DATABÁZE</a>";
        }
    } else {
        echo "<div class='success'><strong>✅ VŠECHNY TABULKY MAJÍ SPRÁVNOU STRUKTURU</strong><br><br>";
        echo "Sloupec <code>claim_id</code> existuje ve všech požadovaných tabulkách.<br>";
        echo "Mazání reklamací by mělo fungovat správně.</div>";

        echo "<a href='/admin.php' class='btn' style='background: #28a745;'>← Zpět do Admin panelu</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
