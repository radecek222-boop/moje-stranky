<?php
/**
 * Vytvoření testovacího zákazníka s kompletní reklamací
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vytvoření testovacího zákazníka</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333; color: #fff; border: none; cursor: pointer; font-weight: 600; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #000; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: #fff; font-weight: 600; }
        label { display: block; margin: 10px 0 5px; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        textarea { min-height: 60px; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🧪 Vytvoření testovacího zákazníka</h1>";

    // Zpracování formuláře
    if (isset($_POST['vytvorit'])) {
        echo "<div class='section'>";
        echo "<h2>📝 Vytváření testovací reklamace...</h2>";

        $pdo->beginTransaction();
        try {
            // Data zákazníka
            $jmeno = $_POST['jmeno'] ?? 'Jan Testovací';
            $telefon = $_POST['telefon'] ?? '+420 777 888 999';
            $email = $_POST['email'] ?? 'test@wgs-service.cz';
            $adresa = $_POST['adresa'] ?? 'Testovací 123, Praha 1, 110 00';

            // Data reklamace
            $typ = $_POST['typ'] ?? 'claim';
            $stav = $_POST['stav'] ?? 'wait';
            $opisProblemu = $_POST['popis'] ?? 'Testovací popis problému - sedačka prosedlá, opěradlo uvolněné, poškrábaná noha.';
            $poznamka = $_POST['poznamka'] ?? 'Testovací poznámka pro technika.';

            // Vygenerovat unikátní číslo reklamace
            $prefix = 'TEST';
            $datumRok = date('y');
            $datumMesic = date('m');
            $datumDen = date('d');

            // Najít poslední testovací číslo
            $stmt = $pdo->query("
                SELECT cislo FROM wgs_reklamace
                WHERE cislo LIKE 'TEST$datumRok-$datumMesic-$datumDen-%'
                ORDER BY cislo DESC LIMIT 1
            ");
            $posledni = $stmt->fetchColumn();

            if ($posledni) {
                $posledniCislo = (int)substr($posledni, -5);
                $noveCislo = $posledniCislo + 1;
            } else {
                $noveCislo = 1;
            }

            $cislo = sprintf("TEST%s-%s-%s-%05d", $datumRok, $datumMesic, $datumDen, $noveCislo);

            // Vložit reklamaci
            $stmt = $pdo->prepare("
                INSERT INTO wgs_reklamace (
                    cislo, jmeno, telefon, email, adresa,
                    typ, stav, popis_problemu, poznamka,
                    datum_vytvoreni, created_at, updated_at
                ) VALUES (
                    :cislo, :jmeno, :telefon, :email, :adresa,
                    :typ, :stav, :popis, :poznamka,
                    NOW(), NOW(), NOW()
                )
            ");

            $stmt->execute([
                'cislo' => $cislo,
                'jmeno' => $jmeno,
                'telefon' => $telefon,
                'email' => $email,
                'adresa' => $adresa,
                'typ' => $typ,
                'stav' => $stav,
                'popis' => $opisProblemu,
                'poznamka' => $poznamka
            ]);

            $reklamaceId = $pdo->lastInsertId();

            // Pokud chce fotky, vytvořit testovací fotky
            if (isset($_POST['vytvorit_fotky']) && $_POST['vytvorit_fotky'] == '1') {
                $photoSections = [
                    'before' => ['label' => 'BEFORE', 'color' => [100, 149, 237]],      // Cornflower blue
                    'problem' => ['label' => 'DETAIL BUG', 'color' => [220, 20, 60]],   // Crimson red
                    'photocustomer' => ['label' => 'CUSTOMER PHOTO', 'color' => [50, 205, 50]], // Lime green
                    'pricelist' => ['label' => 'PRICELIST', 'color' => [255, 165, 0]]   // Orange
                ];

                $fotkyVytvořeno = 0;

                foreach ($photoSections as $section => $info) {
                    // Vytvořit složku pro fotky
                    $photoDir = __DIR__ . "/uploads/reklamace_{$reklamaceId}/{$section}";
                    if (!file_exists($photoDir)) {
                        mkdir($photoDir, 0755, true);
                    }

                    // Vytvořit barevnou testovací fotku s textem pomocí GD
                    $width = 800;
                    $height = 600;
                    $image = imagecreatetruecolor($width, $height);

                    // Barva pozadí
                    $bgColor = imagecolorallocate($image, $info['color'][0], $info['color'][1], $info['color'][2]);
                    imagefill($image, 0, 0, $bgColor);

                    // Bílý text
                    $textColor = imagecolorallocate($image, 255, 255, 255);

                    // Nadpis (velké písmo)
                    $fontSize = 5; // 1-5 pro imagestring
                    $text = $info['label'];
                    $textWidth = imagefontwidth($fontSize) * strlen($text);
                    $textX = ($width - $textWidth) / 2;
                    $textY = $height / 2 - 50;
                    imagestring($image, $fontSize, $textX, $textY, $text, $textColor);

                    // Číslo reklamace (menší písmo)
                    $smallText = "Reklamace: {$cislo}";
                    $smallTextWidth = imagefontwidth(3) * strlen($smallText);
                    $smallX = ($width - $smallTextWidth) / 2;
                    $smallY = $height / 2 + 20;
                    imagestring($image, 3, $smallX, $smallY, $smallText, $textColor);

                    // Timestamp
                    $timestamp = date('Y-m-d H:i:s');
                    $timeWidth = imagefontwidth(2) * strlen($timestamp);
                    $timeX = ($width - $timeWidth) / 2;
                    $timeY = $height / 2 + 50;
                    imagestring($image, 2, $timeX, $timeY, $timestamp, $textColor);

                    // Uložit jako JPEG
                    $fileName = "test_photo_1.jpg";
                    $filePath = "{$photoDir}/{$fileName}";
                    imagejpeg($image, $filePath, 85);
                    imagedestroy($image);

                    $fotkyVytvořeno++;
                }

                echo "<tr><td>Vytvořené fotky</td><td>{$fotkyVytvořeno} testovacích fotek</td></tr>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ TESTOVACÍ ZÁKAZNÍK VYTVOŘEN!</strong><br><br>";
            echo "<table>";
            echo "<tr><th>Pole</th><th>Hodnota</th></tr>";
            echo "<tr><td><strong>Reklamace ID</strong></td><td><strong>{$reklamaceId}</strong></td></tr>";
            echo "<tr><td><strong>Číslo reklamace</strong></td><td><strong>{$cislo}</strong></td></tr>";
            echo "<tr><td>Jméno</td><td>{$jmeno}</td></tr>";
            echo "<tr><td>Telefon</td><td>{$telefon}</td></tr>";
            echo "<tr><td>Email</td><td>{$email}</td></tr>";
            echo "<tr><td>Adresa</td><td>{$adresa}</td></tr>";
            echo "<tr><td>Typ</td><td>" . ($typ == 'claim' ? 'Reklamace' : 'Pozáruční servis') . "</td></tr>";
            echo "<tr><td>Stav</td><td>" . ($stav == 'wait' ? 'Čeká' : ($stav == 'open' ? 'Domluvená' : 'Hotovo')) . "</td></tr>";
            echo "</table>";
            echo "<br>";
            echo "<a href='seznam.php' class='btn'>Otevřít Seznam</a> ";
            echo "<a href='protokol.php?id={$reklamaceId}' class='btn'>Otevřít Protokol</a>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI VYTVÁŘENÍ:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
        echo "</div>";
    }

    // Formulář
    echo "<div class='section'>";
    echo "<h2>📋 Vyplň údaje testovacího zákazníka</h2>";

    echo "<form method='post'>";

    echo "<label>Jméno zákazníka:</label>";
    echo "<input type='text' name='jmeno' value='Jan Testovací' required>";

    echo "<label>Telefon:</label>";
    echo "<input type='tel' name='telefon' value='+420 777 888 999' required>";

    echo "<label>Email:</label>";
    echo "<input type='email' name='email' value='test@wgs-service.cz' required>";

    echo "<label>Adresa:</label>";
    echo "<textarea name='adresa'>Testovací 123, Praha 1, 110 00</textarea>";

    echo "<label>Typ:</label>";
    echo "<select name='typ'>";
    echo "<option value='claim' selected>Reklamace (claim)</option>";
    echo "<option value='warranty_service'>Pozáruční servis (warranty_service)</option>";
    echo "</select>";

    echo "<label>Stav:</label>";
    echo "<select name='stav'>";
    echo "<option value='wait' selected>Čeká (wait)</option>";
    echo "<option value='open'>Domluvená (open)</option>";
    echo "<option value='done'>Hotovo (done)</option>";
    echo "</select>";

    echo "<label>Popis problému:</label>";
    echo "<textarea name='popis'>Testovací popis problému - sedačka prosedlá, opěradlo uvolněné, poškrábaná noha.</textarea>";

    echo "<label>Poznámka pro technika:</label>";
    echo "<textarea name='poznamka'>Testovací poznámka pro technika.</textarea>";

    echo "<label>";
    echo "<input type='checkbox' name='vytvorit_fotky' value='1' checked style='width: auto;'> ";
    echo "Vytvořit testovací fotky (before, problem, photocustomer, pricelist)";
    echo "</label>";

    echo "<br><br>";
    echo "<button type='submit' name='vytvorit' class='btn'>VYTVOŘIT TESTOVACÍHO ZÁKAZNÍKA</button>";
    echo "</form>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
