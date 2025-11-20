<?php
/**
 * Diagnostika: Současný stav PDF parserů
 *
 * Zobrazí všechny konfigurace v databázi včetně patterns a mappingů
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: PDF Parsery</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1600px;
               margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; }
        h2 { color: #2D5016; margin-top: 30px; border-bottom: 2px solid #2D5016;
             padding-bottom: 5px; }
        .parser { background: #f8f9fa; padding: 20px; margin: 20px 0;
                  border-radius: 5px; border-left: 4px solid #007acc; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px;
              border-radius: 5px; overflow-x: auto; font-size: 0.85em; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px;
                 font-size: 0.85em; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Diagnostika PDF Parserů</h1>";

    // Načíst všechny konfigurace
    $stmt = $pdo->query("
        SELECT *
        FROM wgs_pdf_parser_configs
        ORDER BY priorita DESC
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>📊 Celkem konfigurací:</strong> " . count($configs) . "<br>";
    echo "<strong>📅 Aktuální čas:</strong> " . date('Y-m-d H:i:s');
    echo "</div>";

    // Pro každou konfiguraci
    foreach ($configs as $config) {
        $prioritaClass = 'badge-success';
        if ($config['priorita'] < 50) {
            $prioritaClass = 'badge-danger';
        } elseif ($config['priorita'] < 90) {
            $prioritaClass = 'badge-warning';
        }

        echo "<div class='parser'>";
        echo "<h2>";
        echo htmlspecialchars($config['nazev']);
        echo " <span class='badge {$prioritaClass}'>Priorita: {$config['priorita']}</span>";
        echo " <span class='badge " . ($config['aktivni'] ? 'badge-success' : 'badge-danger') . "'>";
        echo $config['aktivni'] ? 'Aktivní ✅' : 'Neaktivní ❌';
        echo "</span>";
        echo "</h2>";

        echo "<table>";
        echo "<tr><th>Vlastnost</th><th>Hodnota</th></tr>";
        echo "<tr><td><strong>Zdroj (ID)</strong></td><td><code>{$config['zdroj']}</code></td></tr>";
        echo "<tr><td><strong>Detekční pattern</strong></td><td><code>" . htmlspecialchars($config['detekce_pattern'] ?: '(žádný)') . "</code></td></tr>";
        echo "</table>";

        // Regex patterns
        $patterns = json_decode($config['regex_patterns'], true);
        if ($patterns) {
            echo "<h3>🔍 Regex Patterns:</h3>";
            echo "<table>";
            echo "<tr><th>Klíč</th><th>Pattern</th></tr>";
            foreach ($patterns as $key => $pattern) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
                echo "<td><code style='word-break: break-all;'>" . htmlspecialchars($pattern) . "</code></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ Žádné regex patterns</div>";
        }

        // Pole mapping
        $mapping = json_decode($config['pole_mapping'], true);
        if ($mapping) {
            echo "<h3>🔗 Pole Mapping:</h3>";
            echo "<table>";
            echo "<tr><th>Pattern Klíč</th><th>→</th><th>Formulářové Pole</th></tr>";
            foreach ($mapping as $sourceKey => $targetField) {
                echo "<tr>";
                echo "<td><code>" . htmlspecialchars($sourceKey) . "</code></td>";
                echo "<td>→</td>";
                echo "<td><code>" . htmlspecialchars($targetField) . "</code></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ Žádný pole mapping</div>";
        }

        echo "</div>";
    }

    // KONTROLY
    echo "<h2>🧪 Automatické kontroly:</h2>";

    $kontroly = [];

    // Kontrola 1: NATUZZI má správné patterns?
    $natuzzi = array_filter($configs, fn($c) => $c['zdroj'] === 'natuzzi')[0] ?? null;
    if ($natuzzi) {
        $patterns = json_decode($natuzzi['regex_patterns'], true);

        // Kontrola ulice patternu
        if (isset($patterns['ulice'])) {
            if (strpos($patterns['ulice'], 'Místo reklamace') !== false &&
                strpos($patterns['ulice'], 'Město:') !== false &&
                strpos($patterns['ulice'], 'Adresa:') !== false) {
                $kontroly[] = ['status' => 'success', 'message' => 'NATUZZI ulice pattern vypadá správně (hledá v sekci Místo reklamace)'];
            } else {
                $kontroly[] = ['status' => 'error', 'message' => 'NATUZZI ulice pattern NENÍ správný - nehledá v sekci "Místo reklamace"'];
            }
        } else {
            $kontroly[] = ['status' => 'error', 'message' => 'NATUZZI nemá pattern pro ulici'];
        }

        // Kontrola PSČ patternu
        if (isset($patterns['psc'])) {
            if (strpos($patterns['psc'], 'Místo reklamace') !== false) {
                $kontroly[] = ['status' => 'success', 'message' => 'NATUZZI PSČ pattern vypadá správně (hledá v sekci Místo reklamace)'];
            } else {
                $kontroly[] = ['status' => 'error', 'message' => 'NATUZZI PSČ pattern NENÍ správný - nehledá v sekci "Místo reklamace"'];
            }
        } else {
            $kontroly[] = ['status' => 'error', 'message' => 'NATUZZI nemá pattern pro PSČ'];
        }

        // Kontrola čísla reklamace
        if (isset($patterns['cislo_reklamace'])) {
            if (strpos($patterns['cislo_reklamace'], '[A-Z0-9') !== false) {
                $kontroly[] = ['status' => 'success', 'message' => 'NATUZZI číslo reklamace pattern vypadá univerzální'];
            } else {
                $kontroly[] = ['status' => 'warning', 'message' => 'NATUZZI číslo reklamace pattern může být příliš specifický'];
            }
        }
    } else {
        $kontroly[] = ['status' => 'error', 'message' => 'NATUZZI konfigurace nebyla nalezena'];
    }

    // Kontrola 2: PHASE CZ existuje?
    $phaseCz = array_filter($configs, fn($c) => $c['zdroj'] === 'phase_cz')[0] ?? null;
    if ($phaseCz) {
        $kontroly[] = ['status' => 'success', 'message' => 'PHASE CZ konfigurace existuje (priorita: ' . $phaseCz['priorita'] . ')'];

        if ($phaseCz['priorita'] >= 90 && $phaseCz['priorita'] < 100) {
            $kontroly[] = ['status' => 'success', 'message' => 'PHASE CZ má správnou prioritu (95)'];
        } else {
            $kontroly[] = ['status' => 'warning', 'message' => 'PHASE CZ má podezřelou prioritu: ' . $phaseCz['priorita']];
        }
    } else {
        $kontroly[] = ['status' => 'error', 'message' => 'PHASE CZ konfigurace NEEXISTUJE'];
    }

    // Kontrola 3: PHASE SK má správnou prioritu?
    $phaseSk = array_filter($configs, fn($c) => $c['zdroj'] === 'phase')[0] ?? null;
    if ($phaseSk) {
        if ($phaseSk['priorita'] == 90) {
            $kontroly[] = ['status' => 'success', 'message' => 'PHASE SK má správnou prioritu (90)'];
        } else {
            $kontroly[] = ['status' => 'error', 'message' => 'PHASE SK má ŠPATNOU prioritu: ' . $phaseSk['priorita'] . ' (mělo být 90)'];
        }
    }

    // Kontrola 4: Pořadí priorit
    $natuzziPrio = $natuzzi['priorita'] ?? 0;
    $phaseCzPrio = $phaseCz['priorita'] ?? 0;
    $phaseSkPrio = $phaseSk['priorita'] ?? 0;

    if ($natuzziPrio > $phaseCzPrio && $phaseCzPrio > $phaseSkPrio) {
        $kontroly[] = ['status' => 'success', 'message' => 'Pořadí priorit je správné: NATUZZI ('.$natuzziPrio.') > PHASE CZ ('.$phaseCzPrio.') > PHASE SK ('.$phaseSkPrio.')'];
    } else {
        $kontroly[] = ['status' => 'error', 'message' => 'Pořadí priorit NENÍ správné! NATUZZI='.$natuzziPrio.', PHASE CZ='.$phaseCzPrio.', PHASE SK='.$phaseSkPrio];
    }

    // Zobrazit kontroly
    foreach ($kontroly as $kontrola) {
        $iconClass = [
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning'
        ][$kontrola['status']];

        $icon = [
            'success' => '✅',
            'error' => '❌',
            'warning' => '⚠️'
        ][$kontrola['status']];

        echo "<div style='padding: 10px; margin: 5px 0; background: ";
        echo $kontrola['status'] === 'success' ? '#d4edda' : ($kontrola['status'] === 'error' ? '#f8d7da' : '#fff3cd');
        echo "; border-radius: 5px;'>";
        echo "<span class='{$iconClass}'>{$icon} " . htmlspecialchars($kontrola['message']) . "</span>";
        echo "</div>";
    }

    echo "<h2>📋 Doporučené akce:</h2>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
    echo "<strong>Pokud vidíš chyby výše, spusť tyto migrační skripty:</strong><br><br>";
    echo "1. <a href='oprav_univerzalni_patterns.php?execute=1' target='_blank'>oprav_univerzalni_patterns.php?execute=1</a> - Opraví NATUZZI patterns<br>";
    echo "2. <a href='pridej_phase_cz.php?execute=1' target='_blank'>pridej_phase_cz.php?execute=1</a> - Přidá PHASE CZ a opraví PHASE SK<br>";
    echo "3. <a href='oprav_prioritu_phase_sk.php' target='_blank'>oprav_prioritu_phase_sk.php</a> - Opraví prioritu PHASE SK<br><br>";
    echo "<strong>Pak otestuj znovu na:</strong> <a href='live_test_pdf.html'>live_test_pdf.html</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
