<?php
/**
 * Oprava techniku pro zakázku GREY M - Pelikán Martin
 * Změna z Radek Zikmund na Milan Kolín
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
    <title>Oprava technika - GREY M</title>
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

    echo "<h1>Oprava technika - GREY M</h1>";
    echo "<p><strong>Zákazník:</strong> Pelikán Martin</p>";
    echo "<p><strong>Změna:</strong> Radek Zikmund → Milan Kolín</p>";

    // Načíst aktuální stav
    $stmt = $pdo->prepare("
        SELECT cislo, jmeno, adresa, model, assigned_to, technik, created_by
        FROM wgs_reklamace
        WHERE jmeno LIKE :jmeno AND cislo LIKE :cislo
        LIMIT 1
    ");
    $stmt->execute([
        'jmeno' => '%Pelikán%Martin%',
        'cislo' => '%GREY%M%'
    ]);
    $zaznam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zaznam) {
        echo "<div class='error'><strong>CHYBA:</strong> Záznam pro Pelikán Martin (GREY M) nebyl nalezen v databázi.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'><strong>AKTUÁLNÍ STAV:</strong></div>";
    echo "<table>";
    echo "<tr><th>Pole</th><th>Hodnota</th></tr>";
    echo "<tr><td>Číslo zakázky</td><td>{$zaznam['cislo']}</td></tr>";
    echo "<tr><td>Jméno zákazníka</td><td>{$zaznam['jmeno']}</td></tr>";
    echo "<tr><td>Adresa</td><td>{$zaznam['adresa']}</td></tr>";
    echo "<tr><td>Model</td><td>{$zaznam['model']}</td></tr>";
    echo "<tr><td>Technik (assigned_to)</td><td><strong>" . ($zaznam['assigned_to'] ?? 'NULL') . "</strong></td></tr>";
    echo "<tr><td>Technik (technik)</td><td><strong>" . ($zaznam['technik'] ?? 'NULL') . "</strong></td></tr>";
    echo "<tr><td>Prodejce (created_by)</td><td>" . ($zaznam['created_by'] ?? 'NULL') . "</td></tr>";
    echo "</table>";

    // Najít Milan Kolín - potřebujeme číselné ID pro assigned_to
    $stmt = $pdo->prepare("SELECT id, user_id, name FROM wgs_users WHERE name LIKE :name LIMIT 1");
    $stmt->execute(['name' => '%Milan%Kolín%']);
    $milanKolin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$milanKolin) {
        echo "<div class='error'><strong>CHYBA:</strong> Uživatel 'Milan Kolín' nebyl nalezen v databázi wgs_users.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>";
    echo "<strong>Milan Kolín v databázi:</strong><br>";
    echo "id (INTEGER): <code>{$milanKolin['id']}</code><br>";
    echo "user_id (VARCHAR): <code>{$milanKolin['user_id']}</code><br>";
    echo "name: <code>{$milanKolin['name']}</code>";
    echo "</div>";

    // Najít prodejce podle emailu soho@natuzzi.cz
    $stmt = $pdo->prepare("SELECT user_id, name, email FROM wgs_users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => 'soho@natuzzi.cz']);
    $prodejce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prodejce) {
        echo "<div class='error'><strong>CHYBA:</strong> Prodejce se s emailem 'soho@natuzzi.cz' nebyl nalezen v databázi wgs_users.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>";
    echo "<strong>Prodejce v databázi:</strong><br>";
    echo "user_id: <code>{$prodejce['user_id']}</code><br>";
    echo "name: <code>{$prodejce['name']}</code><br>";
    echo "email: <code>{$prodejce['email']}</code>";
    echo "</div>";

    // Pokud je execute=1, provést opravu
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET assigned_to = :technik_id,
                technik = :technik_name,
                created_by = :prodejce_id
            WHERE cislo = :cislo
        ");

        $vysledek = $stmt->execute([
            'technik_id' => $milanKolin['id'],  // INTEGER - číselné ID uživatele
            'technik_name' => $milanKolin['name'],
            'prodejce_id' => $prodejce['user_id'],  // VARCHAR - user_id prodejce
            'cislo' => $zaznam['cislo']
        ]);

        if ($vysledek) {
            echo "<div class='success'>";
            echo "<strong>✓ ZAKÁZKA ÚSPĚŠNĚ OPRAVENA</strong><br>";
            echo "Zakázka <strong>{$zaznam['cislo']}</strong> (Pelikán Martin):<br>";
            echo "- Technik změněn na: <strong>Milan Kolín</strong><br>";
            echo "- Zadavatel (prodejce) nastaven na: <strong>{$prodejce['user_id']}</strong> ({$prodejce['email']})";
            echo "</div>";

            // Zobrazit nový stav
            $stmt = $pdo->prepare("SELECT assigned_to, technik, created_by FROM wgs_reklamace WHERE cislo = :cislo");
            $stmt->execute(['cislo' => $zaznam['cislo']]);
            $novy = $stmt->fetch(PDO::FETCH_ASSOC);

            echo "<div class='info'>";
            echo "<strong>NOVÝ STAV:</strong><br>";
            echo "assigned_to: <strong>{$novy['assigned_to']}</strong><br>";
            echo "technik: <strong>{$novy['technik']}</strong><br>";
            echo "created_by: <strong>{$novy['created_by']}</strong>";
            echo "</div>";

            echo "<p><a href='https://www.wgs-service.cz/statistiky.php' class='btn'>Otevřít statistiky</a></p>";
        } else {
            echo "<div class='error'><strong>CHYBA:</strong> Aktualizace selhala.</div>";
        }
    } else {
        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>PROVÉST OPRAVU (technik + prodejce)</button>";
        echo "</form>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
