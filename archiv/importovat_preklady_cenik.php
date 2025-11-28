<?php
/**
 * Migrace: Import překladů z wgs-translations-cenik.js do databáze
 *
 * Tento skript načte všechny překlady z JS souboru a automaticky je
 * naplní do databázových sloupců service_name_en/it, description_en/it, category_en/it.
 *
 * Můžete jej spustit vícekrát - nepřepíše existující překlady.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit import.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Import překladů ceníku do databáze</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1400px; margin: 50px auto; padding: 20px;
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
               border-radius: 5px; margin: 10px 5px 10px 0;
               cursor: pointer; border: none; font-size: 14px; }
        .btn:hover { background: #000; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; position: sticky; top: 0; }
        .matched { color: #28a745; font-weight: bold; }
        .missing { color: #dc3545; }
        .updated { background: #d4edda; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px;
              overflow-x: auto; border-left: 4px solid #333; font-size: 12px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;
                    border: 2px solid #ddd; }
        .stat-number { font-size: 32px; font-weight: bold; color: #333; }
        .stat-label { color: #666; margin-top: 5px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Import překladů z wgs-translations-cenik.js do databáze</h1>";

    // Načíst JS soubor s překlady
    $jsFile = __DIR__ . '/assets/js/wgs-translations-cenik.js';

    if (!file_exists($jsFile)) {
        throw new Exception("Soubor wgs-translations-cenik.js nenalezen!");
    }

    echo "<div class='info'><strong>NAČÍTÁM PŘEKLADY Z JS SOUBORU...</strong></div>";

    $jsContent = file_get_contents($jsFile);

    // Extrahovat překlady pomocí regex
    $preklady = [];

    // Pattern pro kategorie: 'pricing.category.XXX': { cs: '...', en: '...', it: '...' }
    preg_match_all("/'pricing\.category\.([^']+)':\s*\{\s*cs:\s*'([^']*)',\s*en:\s*'([^']*)',\s*it:\s*'([^']*)'/", $jsContent, $categoryMatches, PREG_SET_ORDER);

    foreach ($categoryMatches as $match) {
        $preklady['category'][$match[1]] = [
            'cs' => $match[2],
            'en' => $match[3],
            'it' => $match[4]
        ];
    }

    // Pattern pro služby
    preg_match_all("/'pricing\.service\.([^']+)':\s*\{\s*cs:\s*'([^']*)',\s*en:\s*'([^']*)',\s*it:\s*'([^']*)'/", $jsContent, $serviceMatches, PREG_SET_ORDER);

    foreach ($serviceMatches as $match) {
        $preklady['service'][$match[1]] = [
            'cs' => $match[2],
            'en' => $match[3],
            'it' => $match[4]
        ];
    }

    // Pattern pro popisy (mohou být i víceřádkové, escapované uvozovky)
    preg_match_all("/'pricing\.desc\.([^']+?)':\s*\{\s*cs:\s*'((?:[^'\\\\]|\\\\.)*)'.+?en:\s*'((?:[^'\\\\]|\\\\.)*)'.+?it:\s*'((?:[^'\\\\]|\\\\.)*)'/s", $jsContent, $descMatches, PREG_SET_ORDER);

    foreach ($descMatches as $match) {
        $preklady['desc'][$match[1]] = [
            'cs' => $match[2],
            'en' => $match[3],
            'it' => $match[4]
        ];
    }

    echo "<div class='stats'>";
    echo "<div class='stat-box'><div class='stat-number'>" . count($preklady['category'] ?? []) . "</div><div class='stat-label'>Kategorií</div></div>";
    echo "<div class='stat-box'><div class='stat-number'>" . count($preklady['service'] ?? []) . "</div><div class='stat-label'>Služeb</div></div>";
    echo "<div class='stat-box'><div class='stat-number'>" . count($preklady['desc'] ?? []) . "</div><div class='stat-label'>Popisů</div></div>";
    echo "<div class='stat-box'><div class='stat-number'>" . (count($preklady['category'] ?? []) + count($preklady['service'] ?? []) + count($preklady['desc'] ?? [])) . "</div><div class='stat-label'>Celkem</div></div>";
    echo "</div>";

    // Načíst všechny položky z databáze
    echo "<div class='info'><strong>NAČÍTÁM POLOŽKY Z DATABÁZE...</strong></div>";

    $stmt = $pdo->query("SELECT * FROM wgs_pricing ORDER BY category, service_name");
    $polozky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='success'>Nalezeno <strong>" . count($polozky) . "</strong> položek v databázi</div>";

    // Pokud je nastaveno ?execute=1, provést import
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM IMPORT...</strong></div>";

        $pdo->beginTransaction();

        $stats = [
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Název (CS)</th>
                <th>EN překlad</th>
                <th>IT překlad</th>
                <th>Status</th>
              </tr>";

        try {
            foreach ($polozky as $polozka) {
                $updates = [];
                $params = ['id' => $polozka['id']];
                $messages = [];

                // Najít překlad názvu služby
                if (!empty($polozka['service_name'])) {
                    if (isset($preklady['service'][$polozka['service_name']])) {
                        $preklad = $preklady['service'][$polozka['service_name']];

                        // Přidat EN pokud chybí
                        if (empty($polozka['service_name_en']) && !empty($preklad['en'])) {
                            $updates[] = "service_name_en = :name_en";
                            $params['name_en'] = $preklad['en'];
                            $messages[] = "✓ EN název";
                        }

                        // Přidat IT pokud chybí
                        if (empty($polozka['service_name_it']) && !empty($preklad['it'])) {
                            $updates[] = "service_name_it = :name_it";
                            $params['name_it'] = $preklad['it'];
                            $messages[] = "✓ IT název";
                        }
                    }
                }

                // Najít překlad popisu
                if (!empty($polozka['description'])) {
                    if (isset($preklady['desc'][$polozka['description']])) {
                        $preklad = $preklady['desc'][$polozka['description']];

                        if (empty($polozka['description_en']) && !empty($preklad['en'])) {
                            $updates[] = "description_en = :desc_en";
                            $params['desc_en'] = $preklad['en'];
                            $messages[] = "✓ EN popis";
                        }

                        if (empty($polozka['description_it']) && !empty($preklad['it'])) {
                            $updates[] = "description_it = :desc_it";
                            $params['desc_it'] = $preklad['it'];
                            $messages[] = "✓ IT popis";
                        }
                    }
                }

                // Najít překlad kategorie
                if (!empty($polozka['category'])) {
                    if (isset($preklady['category'][$polozka['category']])) {
                        $preklad = $preklady['category'][$polozka['category']];

                        if (empty($polozka['category_en']) && !empty($preklad['en'])) {
                            $updates[] = "category_en = :cat_en";
                            $params['cat_en'] = $preklad['en'];
                            $messages[] = "✓ EN kategorie";
                        }

                        if (empty($polozka['category_it']) && !empty($preklad['it'])) {
                            $updates[] = "category_it = :cat_it";
                            $params['cat_it'] = $preklad['it'];
                            $messages[] = "✓ IT kategorie";
                        }
                    }
                }

                // Provést update pokud jsou nějaké změny
                if (!empty($updates)) {
                    $sql = "UPDATE wgs_pricing SET " . implode(', ', $updates) . " WHERE id = :id";
                    $updateStmt = $pdo->prepare($sql);
                    $updateStmt->execute($params);

                    $stats['updated']++;
                    $status = "<span class='matched'>" . implode(', ', $messages) . "</span>";
                    $rowClass = "class='updated'";
                } else {
                    $stats['skipped']++;
                    $status = "<span class='missing'>Žádné nové překlady</span>";
                    $rowClass = "";
                }

                // Zobrazit řádek
                $nameEn = $params['name_en'] ?? $polozka['service_name_en'] ?? '-';
                $nameIt = $params['name_it'] ?? $polozka['service_name_it'] ?? '-';

                echo "<tr {$rowClass}>
                        <td>{$polozka['id']}</td>
                        <td><strong>{$polozka['service_name']}</strong></td>
                        <td>{$nameEn}</td>
                        <td>{$nameIt}</td>
                        <td>{$status}</td>
                      </tr>";
            }

            $pdo->commit();

            echo "</table>";

            echo "<div class='success'>";
            echo "<strong>✓ IMPORT ÚSPĚŠNĚ DOKONČEN</strong><br><br>";
            echo "📊 <strong>Statistiky:</strong><br>";
            echo "• Aktualizováno: <strong>{$stats['updated']}</strong> položek<br>";
            echo "• Přeskočeno: <strong>{$stats['skipped']}</strong> položek (již měly překlady)<br>";
            echo "• Celkem zpracováno: <strong>" . count($polozky) . "</strong> položek";
            echo "</div>";

            echo "<a href='cenik.php' class='btn'>Zobrazit ceník</a>";
            echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit DB strukturu</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $stats['errors']++;
            echo "</table>";
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI IMPORTU:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Náhled co bude provedeno
        echo "<h2>Náhled importu:</h2>";

        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Název (CS)</th>
                <th>Popis (CS)</th>
                <th>Kategorie (CS)</th>
                <th>Nalezený překlad</th>
              </tr>";

        $canImport = 0;

        foreach ($polozky as $polozka) {
            $found = [];

            // Zkontrolovat zda existují překlady
            if (!empty($polozka['service_name']) && isset($preklady['service'][$polozka['service_name']])) {
                $found[] = "Název";
            }
            if (!empty($polozka['description']) && isset($preklady['desc'][$polozka['description']])) {
                $found[] = "Popis";
            }
            if (!empty($polozka['category']) && isset($preklady['category'][$polozka['category']])) {
                $found[] = "Kategorie";
            }

            if (!empty($found)) {
                $canImport++;
                $status = "<span class='matched'>✓ " . implode(', ', $found) . "</span>";
            } else {
                $status = "<span class='missing'>✗ Žádný překlad</span>";
            }

            echo "<tr>
                    <td>{$polozka['id']}</td>
                    <td>" . htmlspecialchars($polozka['service_name']) . "</td>
                    <td>" . htmlspecialchars(mb_substr($polozka['description'] ?? '', 0, 50)) . "...</td>
                    <td>" . htmlspecialchars($polozka['category'] ?? '') . "</td>
                    <td>{$status}</td>
                  </tr>";
        }

        echo "</table>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
        echo "• Import naplní překlady do <strong>" . $canImport . "</strong> položek<br>";
        echo "• Nepřepíše existující překlady (pouze doplní prázdné sloupce)<br>";
        echo "• Operace je BEZPEČNÁ a REVERZIBILNÍ<br>";
        echo "• Po importu budou překlady načítány přímo z databáze";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>✓ SPUSTIT IMPORT</a>";
        echo "<a href='cenik.php' class='btn'>Zrušit a vrátit se</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
