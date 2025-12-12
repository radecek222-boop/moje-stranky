<?php
/**
 * Migrace: Přidání sloupce reklamace_id do wgs_nabidky
 *
 * Tento skript BEZPEČNĚ přidá sloupec pro propojení nabídky s reklamací.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
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
    <title>Migrace: reklamace_id pro nabidky</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
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
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: reklamace_id pro nabidky</h1>";

    // Kontrola, zda sloupec existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'reklamace_id'");
    $existuje = $stmt->fetch();

    if ($existuje) {
        echo "<div class='success'><strong>Sloupec reklamace_id jiz existuje.</strong> Migrace neni potreba.</div>";
    } else {
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            // Přidat sloupec
            $pdo->exec("ALTER TABLE wgs_nabidky ADD COLUMN reklamace_id INT NULL AFTER id");
            echo "<div class='success'>Sloupec <code>reklamace_id</code> pridan.</div>";

            // Přidat index
            $pdo->exec("ALTER TABLE wgs_nabidky ADD INDEX idx_reklamace_id (reklamace_id)");
            echo "<div class='success'>Index <code>idx_reklamace_id</code> pridan.</div>";

            // Přidat foreign key (volitelně - zakomentováno pro flexibilitu)
            // $pdo->exec("ALTER TABLE wgs_nabidky ADD CONSTRAINT fk_nabidky_reklamace FOREIGN KEY (reklamace_id) REFERENCES wgs_reklamace(id) ON DELETE SET NULL");

            echo "<div class='success'><strong>MIGRACE USPESNE DOKONCENA</strong></div>";

            echo "<div class='info'>
                <strong>Dalsi kroky:</strong><br>
                1. API nabidka_api.php nyni uklada reklamace_id pri vytvareni nabidky<br>
                2. Pri odeslani nabidky se vygeneruje PDF a ulozi do wgs_documents
            </div>";

        } else {
            echo "<div class='warning'>
                <strong>Sloupec reklamace_id neexistuje.</strong><br><br>
                Tato migrace prida:
                <ul>
                    <li>Sloupec <code>reklamace_id INT NULL</code> pro propojeni nabidky s reklamaci</li>
                    <li>Index pro rychle vyhledavani</li>
                </ul>
            </div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='/admin.php' class='btn' style='background:#666'>Zpet do admin</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
