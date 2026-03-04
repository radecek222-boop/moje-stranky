<?php
/**
 * Migrace: Přidání sloupce sms_kontakt_datum do wgs_reklamace
 *
 * Tento skript BEZPEČNĚ přidá sloupec sms_kontakt_datum (DATETIME, NULL)
 * do tabulky wgs_reklamace. Slouží k persistentnímu uložení informace,
 * že byl zákazník kontaktován přes funkci "Odeslat SMS".
 * Informace bude viditelná pro všechny přihlášené uživatele (admin, technik, prodejce).
 *
 * Spuštění je bezpečné i opakované – pokud sloupec již existuje, skript jen hlásí OK.
 */

require_once __DIR__ . '/init.php';

$jeLoggedIn = isset($_SESSION['user_id']);
$jeAdmin    = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$jeLoggedIn && !$jeAdmin) {
    die("PŘÍSTUP ODEPŘEN: Pouze přihlášený uživatel může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: sms_kontakt_datum</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error   { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
                   padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info    { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;
                   padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;
                   padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white;
               text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #111; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání sloupce sms_kontakt_datum</h1>";
    echo "<div class='info'><strong>Účel:</strong> Přidá sloupec <code>sms_kontakt_datum</code> do tabulky <code>wgs_reklamace</code>.<br>
    Slouží k trvalému uložení informace, že zákazník byl kontaktován přes funkci <strong>Odeslat SMS</strong>.
    Informace bude viditelná pro všechny přihlášené uživatele (admin, technik, prodejce) v detailu zákazníka.</div>";

    // Kontrola zda sloupec již existuje
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pocet
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'wgs_reklamace'
          AND COLUMN_NAME = 'sms_kontakt_datum'
    ");
    $stmt->execute();
    $radek = $stmt->fetch(PDO::FETCH_ASSOC);
    $jizExistuje = (int)$radek['pocet'] > 0;

    if ($jizExistuje) {
        echo "<div class='success'><strong>Sloupec <code>sms_kontakt_datum</code> již existuje.</strong><br>
        Migrace není nutná – databáze je aktuální.</div>";
    } else {
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {

            $pdo->exec("
                ALTER TABLE wgs_reklamace
                ADD COLUMN sms_kontakt_datum DATETIME NULL DEFAULT NULL
                COMMENT 'Datum a čas odeslání SMS zákazníkovi (pokus o kontakt)'
            ");

            echo "<div class='success'><strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>
            Sloupec <code>sms_kontakt_datum</code> byl přidán do tabulky <code>wgs_reklamace</code>.<br>
            Od nynějška bude informace o pokusu o SMS kontakt uložena v databázi a viditelná pro všechny uživatele.</div>";

        } else {
            echo "<div class='warning'><strong>Sloupec dosud neexistuje.</strong><br>
            SQL příkaz, který bude spuštěn:<br>
            <code>ALTER TABLE wgs_reklamace ADD COLUMN sms_kontakt_datum DATETIME NULL DEFAULT NULL</code></div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

    // Zobrazit aktuální počet záznamů s hodnotou
    if ($jizExistuje || (isset($_GET['execute']) && $_GET['execute'] === '1')) {
        $stmt2 = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE sms_kontakt_datum IS NOT NULL");
        $pocetSms = $stmt2->fetchColumn();
        echo "<div class='info'>Záznamy se SMS kontaktem v databázi: <strong>{$pocetSms}</strong></div>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
