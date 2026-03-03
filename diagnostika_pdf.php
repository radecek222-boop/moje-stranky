<?php
/**
 * Diagnostika PDF dokumentů v knihovně
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$reklamaceId = $_GET['id'] ?? '';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika PDF</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: #fff; font-weight: 600; }
        tr:nth-child(even) { background: #f9f9f9; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        input[type=text] { padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 300px; }
        button { padding: 8px 20px; background: #333; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        button:hover { background: #000; }
    </style>
</head>
<body>";

if (empty($reklamaceId)) {
    echo "<h1>🔍 Diagnostika PDF knihovny</h1>";
    echo "<div class='section'>";
    echo "<form method='get'>";
    echo "<label for='id'>Zadejte číslo reklamace:</label><br>";
    echo "<input type='text' name='id' id='id' placeholder='Např. 01-26-20-00001' required>";
    echo " <button type='submit'>Zobrazit</button>";
    echo "</form>";
    echo "</div>";
    echo "</body></html>";
    exit;
}

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Diagnostika PDF pro reklamaci: " . htmlspecialchars($reklamaceId) . "</h1>";

    // Najít reklamaci
    $stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE cislo = :cislo OR reklamace_id = :reklamace_id LIMIT 1");
    $stmt->execute([':cislo' => $reklamaceId, ':reklamace_id' => $reklamaceId]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        echo "<div class='error'>❌ Reklamace nenalezena!</div>";
        exit;
    }

    $claimId = $reklamace['id'];

    // Načíst všechny PDF dokumenty
    $stmt = $pdo->prepare("
        SELECT * FROM wgs_documents
        WHERE claim_id = :claim_id
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([':claim_id' => $claimId]);
    $dokumenty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='section'>";
    echo "<h2>📄 PDF dokumenty v databázi</h2>";

    if (empty($dokumenty)) {
        echo "<div class='warning'>⚠️ Žádné PDF dokumenty v databázi!</div>";
    } else {
        echo "<table>";
        echo "<tr>";
        echo "<th>Název souboru</th>";
        echo "<th>Typ</th>";
        echo "<th>Velikost</th>";
        echo "<th>Nahrál</th>";
        echo "<th>Datum nahrání</th>";
        echo "<th>Existuje soubor?</th>";
        echo "<th>Akce</th>";
        echo "</tr>";

        foreach ($dokumenty as $dok) {
            $filePath = __DIR__ . '/' . $dok['document_path'];
            $fileExists = file_exists($filePath);
            $actualSize = $fileExists ? filesize($filePath) : 0;
            
            $sizeColor = '';
            $sizeWarning = '';
            
            // Detekce typu podle velikosti
            if ($dok['document_type'] === 'complete_report') {
                if ($actualSize < 50000) { // Méně než 50KB = pravděpodobně jen protokol
                    $sizeColor = 'background: #fff3cd;';
                    $sizeWarning = '<br><small style="color: #856404;">⚠️ Příliš malé - pravděpodobně BEZ fotek!</small>';
                } elseif ($actualSize > 500000) { // Více než 500KB = s fotkami
                    $sizeColor = 'background: #d4edda;';
                    $sizeWarning = '<br><small style="color: #155724;">✅ Velikost OK - obsahuje fotky</small>';
                }
            }

            echo "<tr style='$sizeColor'>";
            echo "<td><code>" . htmlspecialchars($dok['document_name']) . "</code></td>";
            echo "<td><strong>" . htmlspecialchars($dok['document_type']) . "</strong></td>";
            echo "<td>" . number_format($actualSize / 1024, 2) . " KB$sizeWarning</td>";
            echo "<td>" . htmlspecialchars($dok['uploaded_by'] ?: '-') . "</td>";
            echo "<td>" . date('d.m.Y H:i:s', strtotime($dok['uploaded_at'])) . "</td>";
            echo "<td>" . ($fileExists ? '✅ Ano' : '❌ Ne') . "</td>";
            echo "<td><a href='/" . htmlspecialchars($dok['document_path']) . "' target='_blank'>Zobrazit</a></td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    echo "</div>";

    // Načíst fotky
    $stmt = $pdo->prepare("SELECT COUNT(*) as pocet FROM wgs_photos WHERE claim_id = :claim_id");
    $stmt->execute([':claim_id' => $claimId]);
    $pocetFotek = $stmt->fetchColumn();

    // Načíst kalkulaci
    $stmt = $pdo->prepare("SELECT * FROM wgs_kalkulace WHERE reklamace_id = :id LIMIT 1");
    $stmt->execute([':id' => $claimId]);
    $kalkulace = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='section'>";
    echo "<h2>📊 Stav reklamace</h2>";
    echo "<table>";
    echo "<tr><th>Parametr</th><th>Hodnota</th><th>Status</th></tr>";
    echo "<tr><td>Fotky v databázi</td><td><strong>$pocetFotek</strong></td><td>" . ($pocetFotek > 0 ? '✅ Ano' : '❌ Žádné') . "</td></tr>";
    echo "<tr><td>Kalkulace</td><td>" . ($kalkulace ? 'Celkem: ' . number_format($kalkulace['celkova_cena'], 2) . ' €' : '-') . "</td><td>" . ($kalkulace ? '✅ Ano' : '❌ Žádná') . "</td></tr>";
    echo "<tr><td>Stav</td><td>" . htmlspecialchars($reklamace['stav']) . "</td><td>-</td></tr>";
    echo "</table>";
    echo "</div>";

    echo "<div class='section'>";
    echo "<h2>💡 Doporučení</h2>";
    
    if (empty($dokumenty)) {
        echo "<div class='error'><strong>❌ Žádné PDF!</strong><br>Technik ještě nevytvořil PDF. Požádejte ho, aby klikl na 'Export do PDF' nebo 'Odeslat zákazníkovi'.</div>";
    } else {
        $nejnovejsi = $dokumenty[0];
        $velikost = file_exists(__DIR__ . '/' . $nejnovejsi['document_path']) ? filesize(__DIR__ . '/' . $nejnovejsi['document_path']) : 0;
        
        if ($velikost < 50000 && $pocetFotek > 0) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ PDF je příliš malé, ale existují fotky v databázi!</strong><br><br>";
            echo "Pravděpodobná příčina:<br>";
            echo "1. Technik vytvořil PDF <strong>PŘED</strong> přidáním fotek do photocustomer<br>";
            echo "2. Pak přidal fotky, ale už neklikl znovu na 'Export do PDF'<br><br>";
            echo "<strong>Řešení:</strong><br>";
            echo "• Požádejte technika, aby otevřel protokol této zakázky<br>";
            echo "• Klikl na 'Přidat fotky' (fotky se načtou z databáze)<br>";
            echo "• A pak znovu klikl na 'Export do PDF' nebo 'Odeslat zákazníkovi'<br>";
            echo "• Tím se vytvoří nové kompletní PDF s fotkami";
            echo "</div>";
        } elseif ($velikost < 50000 && !$kalkulace) {
            echo "<div class='info'>";
            echo "<strong>ℹ️ PDF neobsahuje pricelist ani fotky</strong><br><br>";
            echo "To je normální, pokud:<br>";
            echo "• Nebyla vytvořena kalkulace (pricelist)<br>";
            echo "• Nebyly přidány fotky";
            echo "</div>";
        } elseif ($velikost > 500000) {
            echo "<div class='success'>";
            echo "<strong>✅ PDF vypadá kompletně!</strong><br><br>";
            echo "Velikost: " . number_format($velikost / 1024, 2) . " KB<br>";
            echo "PDF pravděpodobně obsahuje protokol + pricelist + fotodokumentaci.";
            echo "</div>";
        }
    }
    
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
