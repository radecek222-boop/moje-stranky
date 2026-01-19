<?php
/**
 * Oprava ceny pro zakázku POZ/2025/17-12/01 - Adriana Satinová
 * Nastavit cenu na 300 EUR (naše chyba)
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
    <title>Oprava ceny - POZ/2025/17-12/01</title>
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

    echo "<h1>Oprava ceny - POZ/2025/17-12/01</h1>";
    echo "<p><strong>Zákazník:</strong> Adriana Satinová</p>";
    echo "<p><strong>Důvod:</strong> Naše chyba - cena má být 300 EUR</p>";

    // Načíst aktuální stav
    $stmt = $pdo->prepare("
        SELECT cislo, jmeno, adresa, model, stav, cena_celkem, technik, dokonceno_kym, created_by
        FROM wgs_reklamace
        WHERE cislo = :cislo
    ");
    $stmt->execute(['cislo' => 'POZ/2025/17-12/01']);
    $zaznam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zaznam) {
        echo "<div class='error'><strong>CHYBA:</strong> Záznam POZ/2025/17-12/01 nebyl nalezen v databázi.</div>";
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
    echo "<tr><td>Cena celkem</td><td><strong style='color: red;'>" . ($zaznam['cena_celkem'] ?? '0') . " EUR</strong></td></tr>";
    echo "<tr><td>Technik</td><td>{$zaznam['technik']}</td></tr>";
    echo "<tr><td>dokonceno_kym</td><td>" . ($zaznam['dokonceno_kym'] ?? 'NULL') . "</td></tr>";
    echo "<tr><td>created_by</td><td>" . ($zaznam['created_by'] ?? 'NULL') . "</td></tr>";
    echo "</table>";

    // Načíst technika a jeho provizi
    $technikInfo = null;
    if ($zaznam['dokonceno_kym']) {
        $stmt = $pdo->prepare("SELECT id, name, COALESCE(provize_procent, 33) as provize FROM wgs_users WHERE id = :id");
        $stmt->execute(['id' => $zaznam['dokonceno_kym']]);
        $technikInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($technikInfo) {
        $novyCelkem = 300.00;
        $novaProvize = $novyCelkem * ($technikInfo['provize'] / 100);

        echo "<div class='info'>";
        echo "<strong>Technik:</strong> {$technikInfo['name']}<br>";
        echo "<strong>Provize:</strong> {$technikInfo['provize']}%<br>";
        echo "<strong>Nový výdělek:</strong> " . number_format($novaProvize, 2, '.', '') . " EUR";
        echo "</div>";
    }

    // Pokud je execute=1, provést opravu
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET cena_celkem = :cena
            WHERE cislo = :cislo
        ");

        $vysledek = $stmt->execute([
            'cena' => 300.00,
            'cislo' => 'POZ/2025/17-12/01'
        ]);

        if ($vysledek) {
            echo "<div class='success'>";
            echo "<strong>✓ CENA ÚSPĚŠNĚ AKTUALIZOVÁNA</strong><br>";
            echo "Cena pro zákazníka Adriana Satinová byla nastavena na <strong>300 EUR</strong>";
            echo "</div>";

            // Zobrazit nový stav
            $stmt = $pdo->prepare("SELECT cena_celkem FROM wgs_reklamace WHERE cislo = :cislo");
            $stmt->execute(['cislo' => 'POZ/2025/17-12/01']);
            $novy = $stmt->fetch(PDO::FETCH_ASSOC);

            echo "<div class='info'>";
            echo "<strong>NOVÝ STAV:</strong> Cena celkem = <strong>{$novy['cena_celkem']} EUR</strong>";
            echo "</div>";

            if ($technikInfo) {
                $novaProvize = 300.00 * ($technikInfo['provize'] / 100);
                echo "<div class='info'>";
                echo "<strong>Nový výdělek technika {$technikInfo['name']}:</strong> " . number_format($novaProvize, 2, '.', '') . " EUR";
                echo "</div>";
            }

            echo "<p><a href='/vymaz_cache.php' class='btn'>Vymazat cache</a>";
            echo "<a href='/statistiky.php' class='btn'>Otevřít statistiky</a></p>";
        } else {
            echo "<div class='error'><strong>CHYBA:</strong> Aktualizace selhala.</div>";
        }
    } else {
        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>PROVÉST OPRAVU (nastavit 300 EUR)</button>";
        echo "</form>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
