<?php
/**
 * URGENTNÍ: Najít fotky pro Michala Kutzendorfera
 */
require_once __DIR__ . '/init.php';

// Pouze pro přihlášené
if (!isset($_SESSION['user_id'])) {
    die('Musíte být přihlášeni');
}

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Hledám fotky pro Michala Kutzendorfera</h1>";
    echo "<style>body { font-family: monospace; padding: 20px; background: #000; color: #0f0; }</style>";

    // Hledat reklamace
    $stmt = $pdo->prepare("
        SELECT reklamace_id, jmeno, telefon, email, datum_vytvoreni, stav, typ
        FROM wgs_reklamace
        WHERE jmeno LIKE :jmeno1 OR jmeno LIKE :jmeno2
        ORDER BY datum_vytvoreni DESC
        LIMIT 10
    ");
    $stmt->execute([
        'jmeno1' => '%Kutzendorfer%',
        'jmeno2' => '%Michal%'
    ]);
    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reklamace)) {
        echo "<p style='color: red;'>❌ Žádné reklamace nenalezeny</p>";
        exit;
    }

    echo "<h2>📋 Nalezené reklamace:</h2>";

    foreach ($reklamace as $r) {
        echo "<div style='border: 2px solid #0f0; padding: 15px; margin: 10px 0;'>";
        echo "<strong>ID: {$r['reklamace_id']}</strong><br>";
        echo "Jméno: {$r['jmeno']}<br>";
        echo "Email: {$r['email']}<br>";
        echo "Telefon: {$r['telefon']}<br>";
        echo "Vytvořeno: {$r['datum_vytvoreni']}<br>";
        echo "Stav: {$r['stav']}<br>";
        echo "Typ: {$r['typ']}<br>";

        // Hledat fotky v uploads/
        $reklamaceDir = __DIR__ . '/uploads/' . $r['reklamace_id'];
        if (is_dir($reklamaceDir)) {
            $files = array_diff(scandir($reklamaceDir), ['.', '..']);
            $photoCount = count($files);

            if ($photoCount > 0) {
                echo "<span style='color: yellow;'>📷 FOTKY NALEZENY: $photoCount souborů</span><br>";
                echo "<ul>";
                foreach ($files as $file) {
                    $filePath = $reklamaceDir . '/' . $file;
                    $fileSize = filesize($filePath);
                    $fileSizeKB = round($fileSize / 1024, 2);
                    echo "<li>$file ($fileSizeKB KB)</li>";
                }
                echo "</ul>";
            } else {
                echo "<span style='color: red;'>❌ Složka existuje ale je prázdná</span><br>";
            }
        } else {
            echo "<span style='color: red;'>❌ Složka s fotkami neexistuje</span><br>";
        }

        echo "<br><a href='protokol.php?reklamace_id={$r['reklamace_id']}' style='color: #0ff;'>➜ Otevřít protokol</a><br>";
        echo "<a href='seznam.php?reklamace_id={$r['reklamace_id']}' style='color: #0ff;'>➜ Otevřít detail</a>";
        echo "</div>";
    }

    echo "<h2>💡 CO DĚLAT:</h2>";
    echo "<ol>";
    echo "<li>Pokud FOTKY NALEZENY ✅ - otevřít protokol</li>";
    echo "<li>Pokud FOTKY CHYBÍ ❌ - technik musí otevřít <a href='diagnostika_indexeddb.php' style='color: #ff0;'>diagnostika_indexeddb.php</a> NA SVÉM TELEFONU</li>";
    echo "<li>IndexedDB uloží fotky i po vypršení session - stačí je obnovit</li>";
    echo "</ol>";

} catch (Exception $e) {
    echo "<p style='color: red;'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
