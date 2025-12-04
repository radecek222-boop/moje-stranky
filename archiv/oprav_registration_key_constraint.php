<?php
/**
 * Migrace: Oprava UNIQUE constraint na registration_key_code
 *
 * Problém: Sloupec registration_key_code má UNIQUE constraint,
 * ale systém umožňuje více uživatelům použít stejný registrační klíč.
 *
 * Řešení: Odstranit UNIQUE constraint, ponechat pouze INDEX.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Oprava registration_key_code constraint</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 800px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
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
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Oprava registration_key_code constraint</h1>";

    // 1. Zjistit aktuální indexy na sloupci
    echo "<h2>1. Aktualni stav</h2>";

    $stmt = $pdo->query("SHOW INDEX FROM wgs_users WHERE Column_name = 'registration_key_code'");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($indexes)) {
        echo "<div class='info'>Sloupec <code>registration_key_code</code> nema zadne indexy.</div>";
    } else {
        echo "<table>
            <tr><th>Index</th><th>Unique</th><th>Sloupec</th></tr>";
        foreach ($indexes as $idx) {
            $unique = $idx['Non_unique'] == 0 ? 'ANO' : 'NE';
            echo "<tr>
                <td>{$idx['Key_name']}</td>
                <td>{$unique}</td>
                <td>{$idx['Column_name']}</td>
            </tr>";
        }
        echo "</table>";
    }

    // Najít UNIQUE indexy
    $uniqueIndexes = array_filter($indexes, fn($i) => $i['Non_unique'] == 0);

    if (empty($uniqueIndexes)) {
        echo "<div class='success'>Zadny UNIQUE constraint na <code>registration_key_code</code> - vse OK!</div>";
        echo "<p><a href='admin.php' class='btn'>Zpet do Admin Panelu</a></p>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='warning'><strong>Nalezen UNIQUE constraint!</strong> Toto zpusobuje chybu pri registraci vice uzivatelu se stejnym klicem.</div>";

    // 2. Pokud execute=1, provést opravu
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>2. Provadim opravu...</h2>";

        $pdo->beginTransaction();

        try {
            foreach ($uniqueIndexes as $idx) {
                $indexName = $idx['Key_name'];

                echo "<div class='info'>Odstranuji UNIQUE index: <code>{$indexName}</code></div>";

                // Odstranit UNIQUE index
                $pdo->exec("ALTER TABLE wgs_users DROP INDEX `{$indexName}`");

                echo "<div class='success'>Index <code>{$indexName}</code> odstranen.</div>";
            }

            // Přidat normální INDEX (ne UNIQUE) pokud neexistuje
            $stmt = $pdo->query("SHOW INDEX FROM wgs_users WHERE Column_name = 'registration_key_code'");
            $remainingIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($remainingIndexes)) {
                echo "<div class='info'>Pridavam novy INDEX (ne UNIQUE)...</div>";
                $pdo->exec("ALTER TABLE wgs_users ADD INDEX idx_registration_key_code (registration_key_code)");
                echo "<div class='success'>INDEX <code>idx_registration_key_code</code> pridan.</div>";
            }

            $pdo->commit();

            echo "<div class='success' style='font-size: 1.2em; padding: 20px;'>
                <strong>MIGRACE USPESNE DOKONCENA!</strong><br><br>
                Nyni muze vice uzivatelu pouzit stejny registracni klic.
            </div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>
                <strong>CHYBA:</strong><br>
                " . htmlspecialchars($e->getMessage()) . "
            </div>";
        }

    } else {
        // Náhled
        echo "<h2>2. Co bude provedeno</h2>";
        echo "<ul>";
        foreach ($uniqueIndexes as $idx) {
            echo "<li>Odstraneni UNIQUE indexu: <code>{$idx['Key_name']}</code></li>";
        }
        echo "<li>Pridani normalniho INDEX (pro vyhledavani)</li>";
        echo "</ul>";

        echo "<p><a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a></p>";
    }

    echo "<p><a href='admin.php' class='btn' style='background: #666;'>Zpet do Admin Panelu</a></p>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
