<?php
/**
 * Migrace: Pridani sloupce gdpr_note do wgs_reklamace
 *
 * Problem: GDPR poznamka se ukladala do pole doplnujici_info,
 * coz zneprijemnuje editaci tohoto pole pro prodejce.
 *
 * Reseni: Novy sloupec gdpr_note pro ulozeni GDPR informaci samostatne.
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
    <title>Migrace: Pridani sloupce gdpr_note</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 900px; margin: 50px auto; padding: 20px;
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

    echo "<h1>Migrace: Pridani sloupce gdpr_note</h1>";

    // 1. Zjistit, jestli sloupec uz existuje
    echo "<h2>1. Kontrola aktualniho stavu</h2>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'gdpr_note'");
    $existuje = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existuje) {
        echo "<div class='success'>Sloupec <code>gdpr_note</code> uz existuje - migrace neni potreba.</div>";
        echo "<p><a href='admin.php' class='btn'>Zpet do Admin Panelu</a></p>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='warning'>Sloupec <code>gdpr_note</code> neexistuje - bude pridan.</div>";

    // 2. Spocitat zaznamy s GDPR v doplnujici_info
    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_reklamace WHERE doplnujici_info LIKE '%GDPR%'");
    $pocetGdpr = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

    echo "<div class='info'>Nalezeno <strong>{$pocetGdpr}</strong> zaznamu s GDPR textem v poli doplnujici_info.</div>";

    // 3. Pokud execute=1, provest migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>2. Provadim migraci...</h2>";

        $pdo->beginTransaction();

        try {
            // Pridat sloupec
            echo "<div class='info'>Pridavam sloupec <code>gdpr_note</code>...</div>";
            $pdo->exec("ALTER TABLE wgs_reklamace ADD COLUMN gdpr_note TEXT NULL AFTER doplnujici_info");
            echo "<div class='success'>Sloupec <code>gdpr_note</code> pridan.</div>";

            // Migrace existujicich GDPR poznamek
            if ($pocetGdpr > 0) {
                echo "<div class='info'>Migruji existujici GDPR poznamky...</div>";

                // Najit zaznamy s GDPR a presunout do noveho sloupce
                $stmt = $pdo->query("
                    SELECT id, doplnujici_info
                    FROM wgs_reklamace
                    WHERE doplnujici_info LIKE '%GDPR%'
                ");
                $zaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $migrovanoPocet = 0;
                foreach ($zaznamy as $zaznam) {
                    $originalText = $zaznam['doplnujici_info'];

                    // Rozdelit na uzivatelsky text a GDPR poznamku
                    // GDPR poznamka zacina "GDPR souhlas..."
                    if (preg_match('/^(.*?)\n\n(GDPR souhlas.*)$/s', $originalText, $matches)) {
                        $uzivatelskyCast = trim($matches[1]);
                        $gdprCast = trim($matches[2]);
                    } elseif (preg_match('/^(GDPR souhlas.*)$/s', $originalText, $matches)) {
                        // Pouze GDPR bez uzivatelskeho textu
                        $uzivatelskyCast = '';
                        $gdprCast = trim($matches[1]);
                    } else {
                        // Neco jineho obsahujici GDPR - nechat jak je
                        continue;
                    }

                    // Aktualizovat zaznam
                    $updateStmt = $pdo->prepare("
                        UPDATE wgs_reklamace
                        SET doplnujici_info = :uzivatelsky, gdpr_note = :gdpr
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        'uzivatelsky' => $uzivatelskyCast,
                        'gdpr' => $gdprCast,
                        'id' => $zaznam['id']
                    ]);
                    $migrovanoPocet++;
                }

                echo "<div class='success'>Migrovano <strong>{$migrovanoPocet}</strong> zaznamu.</div>";
            }

            $pdo->commit();

            echo "<div class='success' style='font-size: 1.2em; padding: 20px;'>
                <strong>MIGRACE USPESNE DOKONCENA!</strong><br><br>
                Nyni se GDPR poznamky ukladaji do samostatneho sloupce a nebudou mit uzivatele pri editaci.
            </div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>
                <strong>CHYBA:</strong><br>
                " . htmlspecialchars($e->getMessage()) . "
            </div>";
        }

    } else {
        // Nahled
        echo "<h2>2. Co bude provedeno</h2>";
        echo "<ul>";
        echo "<li>Pridani sloupce <code>gdpr_note TEXT NULL</code> do tabulky wgs_reklamace</li>";
        if ($pocetGdpr > 0) {
            echo "<li>Migrace {$pocetGdpr} existujicich GDPR poznamek do noveho sloupce</li>";
            echo "<li>Vycisteni pole <code>doplnujici_info</code> od GDPR textu</li>";
        }
        echo "</ul>";

        echo "<p><a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a></p>";
    }

    echo "<p><a href='admin.php' class='btn' style='background: #666;'>Zpet do Admin Panelu</a></p>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
