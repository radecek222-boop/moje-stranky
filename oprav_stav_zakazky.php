<?php
/**
 * Oprava stavu zakázky - změna na "čeká" (poslána CN)
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava stavu zakázky</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #333; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    $cisloZakazky = $_GET['cislo'] ?? 'POZ/2025/08-12/01';
    $novyStav = $_GET['stav'] ?? 'wait';

    echo "<h1>Oprava stavu zakázky</h1>";

    // Najít zakázku
    $stmt = $pdo->prepare("SELECT id, cislo, jmeno, stav, termin, datum_dokonceni, dokonceno_kym FROM wgs_reklamace WHERE cislo = :cislo");
    $stmt->execute([':cislo' => $cisloZakazky]);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakazka) {
        echo "<div class='error'>Zakázka {$cisloZakazky} nebyla nalezena.</div>";
    } else {
        echo "<h2>Nalezena zakázka:</h2>";
        echo "<table>
            <tr><th>ID</th><td>{$zakazka['id']}</td></tr>
            <tr><th>Číslo</th><td>{$zakazka['cislo']}</td></tr>
            <tr><th>Jméno</th><td>{$zakazka['jmeno']}</td></tr>
            <tr><th>Aktuální stav</th><td>{$zakazka['stav']}</td></tr>
            <tr><th>Termín</th><td>{$zakazka['termin']}</td></tr>
            <tr><th>datum_dokonceni</th><td>{$zakazka['datum_dokonceni']}</td></tr>
        </table>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            // Aktualizovat stav a vymazat datum_dokonceni/dokonceno_kym
            $stmtUpdate = $pdo->prepare("
                UPDATE wgs_reklamace
                SET stav = :stav,
                    datum_dokonceni = NULL,
                    dokonceno_kym = NULL,
                    updated_at = NOW()
                WHERE cislo = :cislo
            ");
            $stmtUpdate->execute([':stav' => $novyStav, ':cislo' => $cisloZakazky]);

            echo "<div class='success'>";
            echo "<strong>ZAKÁZKA AKTUALIZOVÁNA</strong><br>";
            echo "Nový stav: <strong>{$novyStav}</strong> (ČEKÁ - poslána CN)<br>";
            echo "datum_dokonceni: vymazáno<br>";
            echo "dokonceno_kym: vymazáno";
            echo "</div>";
        } else {
            echo "<div class='info'>";
            echo "Zakázka bude změněna na stav <strong>wait</strong> (ČEKÁ - poslána CN)<br>";
            echo "datum_dokonceni a dokonceno_kym budou vymazány.";
            echo "</div>";

            echo "<a href='?cislo=" . urlencode($cisloZakazky) . "&stav=wait&execute=1' class='btn'>ZMĚNIT STAV NA ČEKÁ</a>";
        }
    }

    echo "<br><a href='/seznam.php' class='btn'>Zpět na seznam</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
