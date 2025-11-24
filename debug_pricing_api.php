<?php
/**
 * Debug script pro pricing_api.php
 * Testuje API a zobrazuje detailní výstup
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/init.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Debug Pricing API</title>
    <style>
        body { font-family: monospace; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Debug Pricing API</h1>";
echo "<p><strong>Datum:</strong> " . date('d.m.Y H:i:s') . "</p>";

// ===================================================
// 1. KONTROLA DATABÁZOVÉHO PŘIPOJENÍ
// ===================================================
echo "<h2>1. Kontrola databazoveho pripojeni</h2>";

try {
    $pdo = getDbConnection();
    echo "<div class='success'>Pripojeni k databazi: OK</div>";
} catch (Exception $e) {
    echo "<div class='error'>Chyba pripojeni: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</div></body></html>";
    exit;
}

// ===================================================
// 2. KONTROLA TABULKY wgs_pricing
// ===================================================
echo "<h2>2. Kontrola tabulky wgs_pricing</h2>";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pricing'");
    if ($stmt && $stmt->rowCount() > 0) {
        echo "<div class='success'>Tabulka wgs_pricing EXISTUJE</div>";

        // Počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_pricing");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<div class='info'>Celkem zaznamu: <strong>{$total}</strong></div>";

        // Počet aktivních záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_pricing WHERE is_active = 1");
        $active = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<div class='info'>Aktivnich zaznamu (is_active = 1): <strong>{$active}</strong></div>";

        if ($active == 0) {
            echo "<div class='error'>PROBLEM: Zadne aktivni zaznamy! Cenik bude prazdny.</div>";
        }

    } else {
        echo "<div class='error'>Tabulka wgs_pricing NEEXISTUJE!</div>";
    }
} catch (PDOException $e) {
    echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ===================================================
// 3. STRUKTURA TABULKY
// ===================================================
echo "<h2>3. Struktura tabulky</h2>";

try {
    $stmt = $pdo->query("DESCRIBE wgs_pricing");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ===================================================
// 4. SIMULACE API DOTAZU
// ===================================================
echo "<h2>4. Simulace API dotazu (action=list)</h2>";

try {
    $stmt = $pdo->query("
        SELECT *
        FROM wgs_pricing
        WHERE is_active = 1
        ORDER BY display_order ASC, category ASC
    ");

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>Nacteno polozek: <strong>" . count($items) . "</strong></div>";

    if (count($items) > 0) {
        // Seskupit podle kategorií
        $byCategory = [];
        foreach ($items as $item) {
            $category = $item['category'] ?? 'Ostatni';
            if (!isset($byCategory[$category])) {
                $byCategory[$category] = [];
            }
            $byCategory[$category][] = $item;
        }

        echo "<div class='info'>Pocet kategorii: <strong>" . count($byCategory) . "</strong></div>";
        echo "<div class='info'>Kategorie: <strong>" . implode(', ', array_keys($byCategory)) . "</strong></div>";

        // Zobrazit první 3 položky
        echo "<h3>Ukazka polozek (prvni 3):</h3>";
        echo "<pre>" . htmlspecialchars(json_encode(array_slice($items, 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

        // Simulovat API odpověď
        $apiResponse = [
            'status' => 'success',
            'message' => 'Cenik nacten',
            'data' => [
                'items' => $items,
                'by_category' => $byCategory,
                'total' => count($items)
            ]
        ];

        echo "<div class='success'>API by melo vratit validni data!</div>";
    } else {
        echo "<div class='error'>PROBLEM: Zadne polozky v ceniku!</div>";
        echo "<p>Zkontrolujte, zda existuji zaznamy v tabulce wgs_pricing s is_active = 1</p>";
    }

} catch (PDOException $e) {
    echo "<div class='error'>Chyba SQL: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ===================================================
// 5. TEST PŘÍMÉHO API VOLÁNÍ
// ===================================================
echo "<h2>5. Test primeho API volani</h2>";

$apiUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.wgs-service.cz') . '/api/pricing_api.php?action=list';
echo "<div class='info'>URL: <code>{$apiUrl}</code></div>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<div class='error'>cURL chyba: {$error}</div>";
} else {
    echo "<div class='info'>HTTP kod: <strong>{$httpCode}</strong></div>";

    if ($httpCode == 200) {
        $json = json_decode($response, true);
        if ($json) {
            echo "<div class='success'>API odpovida validnim JSON!</div>";
            echo "<div class='info'>Status: <strong>{$json['status']}</strong></div>";
            if (isset($json['data']['total'])) {
                echo "<div class='info'>Pocet polozek: <strong>{$json['data']['total']}</strong></div>";
            }
        } else {
            echo "<div class='error'>API nevraci validni JSON!</div>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
        }
    } else {
        echo "<div class='error'>API vraci HTTP {$httpCode}</div>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
}

// ===================================================
// SOUHRN
// ===================================================
echo "<h2>Souhrn a doporuceni</h2>";
echo "<div class='info'>";
echo "<strong>Pokud cenik nefunguje, zkontrolujte:</strong><br>";
echo "1. Existuji aktivni zaznamy v tabulce wgs_pricing (is_active = 1)?<br>";
echo "2. Neni v prohlizeci chyba v konzoli (F12)?<br>";
echo "3. Funguje API endpoint /api/pricing_api.php?action=list?<br>";
echo "4. Jsou nacteny vsechny JS soubory (language-switcher.js, cenik.js)?<br>";
echo "</div>";

echo "<a href='cenik.php' class='btn'>Otevrit Cenik</a>";
echo "<a href='admin.php' class='btn'>Otevrit Admin</a>";

echo "</div></body></html>";
?>
