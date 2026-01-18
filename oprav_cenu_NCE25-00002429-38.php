<?php
/**
 * Oprava ceny pro zákazníka Andrea Beránková (NCE25-00002429-38)
 * Cena má být 110 EUR místo 0 EUR
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava ceny - NCE25-00002429-38</title>
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
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: 600; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; cursor: pointer;
               border: none; }
        .btn:hover { background: #1a1a1a; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Oprava ceny - NCE25-00002429-38</h1>";
    echo "<p><strong>Zákazník:</strong> Andrea Beránková</p>";
    echo "<p><strong>Nová cena:</strong> 110 EUR</p>";

    // Načíst aktuální stav
    $stmt = $pdo->prepare("
        SELECT cislo, jmeno, adresa, model, stav, cena_celkem, fakturace_firma, created_by, assigned_to
        FROM wgs_reklamace
        WHERE cislo = :cislo
    ");
    $stmt->execute(['cislo' => 'NCE25-00002429-38']);
    $zaznam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zaznam) {
        echo "<div class='error'><strong>CHYBA:</strong> Záznam NCE25-00002429-38 nebyl nalezen v databázi.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'><strong>AKTUÁLNÍ STAV:</strong></div>";
    echo "<table>";
    echo "<tr><th>Pole</th><th>Hodnota</th></tr>";
    echo "<tr><td>Číslo</td><td>{$zaznam['cislo']}</td></tr>";
    echo "<tr><td>Jméno</td><td>{$zaznam['jmeno']}</td></tr>";
    echo "<tr><td>Adresa</td><td>{$zaznam['adresa']}</td></tr>";
    echo "<tr><td>Model</td><td>{$zaznam['model']}</td></tr>";
    echo "<tr><td>Stav</td><td>{$zaznam['stav']}</td></tr>";
    echo "<tr><td>Cena celkem</td><td><strong>" . ($zaznam['cena_celkem'] ?? '0') . " EUR</strong></td></tr>";
    echo "<tr><td>Fakturace</td><td>{$zaznam['fakturace_firma']}</td></tr>";
    echo "<tr><td>Prodejce (created_by)</td><td>{$zaznam['created_by']}</td></tr>";
    echo "<tr><td>Technik (assigned_to)</td><td>{$zaznam['assigned_to']}</td></tr>";
    echo "</table>";

    // Pokud je execute=1, provést opravu
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET cena_celkem = :cena
            WHERE cislo = :cislo
        ");

        $vysledek = $stmt->execute([
            'cena' => 110.00,
            'cislo' => 'NCE25-00002429-38'
        ]);

        if ($vysledek) {
            echo "<div class='success'>";
            echo "<strong>✓ CENA ÚSPĚŠNĚ AKTUALIZOVÁNA</strong><br>";
            echo "Cena pro zákazníka Andrea Beránková byla nastavena na <strong>110 EUR</strong>";
            echo "</div>";

            // Zobrazit nový stav
            $stmt = $pdo->prepare("SELECT cena_celkem FROM wgs_reklamace WHERE cislo = :cislo");
            $stmt->execute(['cislo' => 'NCE25-00002429-38']);
            $novy = $stmt->fetch(PDO::FETCH_ASSOC);

            echo "<div class='info'>";
            echo "<strong>NOVÝ STAV:</strong> Cena celkem = <strong>{$novy['cena_celkem']} EUR</strong>";
            echo "</div>";

            echo "<p><a href='https://www.wgs-service.cz/statistiky.php' class='btn'>Otevřít statistiky</a></p>";
        } else {
            echo "<div class='error'><strong>CHYBA:</strong> Aktualizace selhala.</div>";
        }
    } else {
        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>PROVÉST OPRAVU (nastavit 110 EUR)</button>";
        echo "</form>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
