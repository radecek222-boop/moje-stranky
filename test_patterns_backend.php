<?php
/**
 * Backend pro test patterns na PDF textu
 */
require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die(json_encode(['status' => 'error', 'message' => 'Přístup odepřen']));
}

try {
    $pdo = getDbConnection();

    $pdfText = $_POST['pdf_text'] ?? '';
    $expectedConfig = $_POST['expected_config'] ?? '';

    if (empty($pdfText)) {
        die(json_encode(['status' => 'error', 'message' => 'Chybí PDF text']));
    }

    // Načíst všechny konfigurace
    $stmt = $pdo->query("
        SELECT config_id, nazev, priorita, aktivni, regex_patterns, pole_mapping, detekce_pattern
        FROM wgs_pdf_parser_configs
        WHERE aktivni = 1
        ORDER BY priorita DESC
    ");

    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';

    foreach ($configs as $config) {
        $patterns = json_decode($config['regex_patterns'], true);
        $mapping = json_decode($config['pole_mapping'], true);

        if (!$patterns || !$mapping) {
            continue;
        }

        $isExpected = (strpos($config['nazev'], $expectedConfig) !== false);
        $borderColor = $isExpected ? '#4ec9b0' : '#3e3e3e';

        $html .= "<div class='config-test' style='border-left: 4px solid {$borderColor};'>";
        $html .= "<h3>" . htmlspecialchars($config['nazev']) . " (ID: {$config['config_id']})";

        if ($isExpected) {
            $html .= " <span class='success'>← OČEKÁVANÁ KONFIGURACE</span>";
        }

        $html .= "</h3>";

        // Test detekčního patternu
        $detekceOk = preg_match($config['detekce_pattern'], $pdfText);
        $html .= "<p><strong>Detekční pattern:</strong> ";
        $html .= $detekceOk ? "<span class='success'>✅ MATCH</span>" : "<span class='error'>❌ NO MATCH</span>";
        $html .= " <code>" . htmlspecialchars(substr($config['detekce_pattern'], 0, 80)) . "...</code></p>";

        // Test každého patternu
        $successCount = 0;
        $totalCount = count($patterns);

        $html .= "<table>";
        $html .= "<tr><th>Pole</th><th>Pattern</th><th>Výsledek</th></tr>";

        foreach ($patterns as $key => $pattern) {
            $match = preg_match($pattern, $pdfText, $matches);
            $value = $match && isset($matches[1]) ? htmlspecialchars(trim($matches[1])) : '';

            if ($match) {
                $successCount++;
                $resultHtml = "<span class='success'>✅ \"" . substr($value, 0, 50) . "\"</span>";
            } else {
                $resultHtml = "<span class='error'>❌ NENALEZENO</span>";
            }

            $html .= "<tr>";
            $html .= "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
            $html .= "<td><code>" . htmlspecialchars(substr($pattern, 0, 60)) . "...</code></td>";
            $html .= "<td>{$resultHtml}</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";

        // Score
        $scorePercent = round(($successCount / $totalCount) * 100);
        $scoreColor = $scorePercent >= 80 ? '#4ec9b0' : ($scorePercent >= 50 ? '#dcdcaa' : '#f48771');

        $html .= "<p class='score' style='color: {$scoreColor};'>";
        $html .= "Score: {$successCount}/{$totalCount} ({$scorePercent}%)";
        $html .= "</p>";

        $html .= "</div>";
    }

    echo json_encode([
        'status' => 'success',
        'html' => $html
    ]);

} catch (Exception $e) {
    error_log("Test patterns error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba při testování: ' . $e->getMessage()
    ]);
}
?>
