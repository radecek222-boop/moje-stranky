<?php
/**
 * KOMPLEXNÍ DIAGNOSTIKA DATABÁZOVÉ STRUKTURY
 *
 * Zkontroluje všechny potenciální problémy s neexistujícími tabulkami
 * nebo sloupci, které by mohly způsobit chyby v aplikaci.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika databázové struktury</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1400px; margin: 30px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; margin-top: 0; }
        h2 { color: #2D5016; border-bottom: 2px solid #ddd;
             padding-bottom: 8px; margin-top: 30px; }
        .success { background: #d4edda; border-left: 4px solid #28a745;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0;
                font-size: 13px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #2D5016; color: white; font-weight: 600; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; color: #c7254e;
               font-size: 12px; }
        .file-path { background: #e8f5e9; padding: 4px 8px; border-radius: 3px;
                     font-family: 'Courier New', monospace; font-size: 12px;
                     color: #2D5016; display: inline-block; margin: 2px 0; }
        .stat-box { display: inline-block; padding: 15px 25px; margin: 10px;
                    border-radius: 8px; text-align: center; min-width: 150px; }
        .stat-box .number { font-size: 32px; font-weight: bold; }
        .stat-box .label { font-size: 12px; text-transform: uppercase;
                          letter-spacing: 1px; margin-top: 5px; }
        .stat-ok { background: #d4edda; color: #155724; }
        .stat-warning { background: #fff3cd; color: #856404; }
        .stat-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Komplexní diagnostika databázové struktury</h1>";

try {
    $pdo = getDbConnection();

    $problemy = [];
    $varovani = [];
    $ok = [];

    // ====================
    // 1. KONTROLA TABULEK
    // ====================

    echo "<h2>📋 1. Kontrola existence tabulek</h2>";

    $ocekavane_tabulky = [
        'wgs_reklamace' => 'Hlavní tabulka reklamací',
        'wgs_users' => 'Uživatelé systému',
        'wgs_photos' => 'Fotografie k reklamacím',
        'wgs_documents' => 'PDF protokoly a dokumenty',
        'wgs_email_queue' => 'Fronta emailů k odeslání',
        'wgs_notifications' => 'Šablony notifikací (templates)',
        'wgs_tokens' => 'Autentizační tokeny',
        'wgs_smtp_settings' => 'SMTP konfigurace',
        'wgs_rate_limits' => 'Rate limiting',
        'wgs_registration_keys' => 'Registrační klíče',
        'wgs_theme_settings' => 'Nastavení vzhledu'
    ];

    $neocekavane_ale_pouzivane = [
        'wgs_notes' => [
            'pouzito_v' => ['api/notes_api.php'],
            'popis' => 'Poznámky k reklamacím (používá se v kódu, ale tabulka NEEXISTUJE!)'
        ]
    ];

    echo "<table>";
    echo "<tr><th>Tabulka</th><th>Stav</th><th>Popis</th><th>Akce</th></tr>";

    foreach ($ocekavane_tabulky as $tabulka => $popis) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabulka'");
        $existuje = $stmt->rowCount() > 0;

        echo "<tr>";
        echo "<td><code>$tabulka</code></td>";

        if ($existuje) {
            echo "<td style='color: #28a745; font-weight: bold;'>✅ Existuje</td>";
            echo "<td>$popis</td>";
            echo "<td>—</td>";
            $ok[] = "Tabulka $tabulka existuje";
        } else {
            echo "<td style='color: #dc3545; font-weight: bold;'>❌ Chybí</td>";
            echo "<td>$popis</td>";
            echo "<td><strong>KRITICKÉ</strong></td>";
            $problemy[] = "Tabulka $tabulka neexistuje";
        }

        echo "</tr>";
    }

    foreach ($neocekavane_ale_pouzivane as $tabulka => $info) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabulka'");
        $existuje = $stmt->rowCount() > 0;

        echo "<tr style='background: #fff3cd;'>";
        echo "<td><code>$tabulka</code></td>";

        if ($existuje) {
            echo "<td style='color: #28a745; font-weight: bold;'>✅ Existuje</td>";
            echo "<td>{$info['popis']}</td>";
            echo "<td>OK</td>";
            $ok[] = "Tabulka $tabulka existuje";
        } else {
            echo "<td style='color: #dc3545; font-weight: bold;'>❌ NEEXISTUJE!</td>";
            echo "<td>{$info['popis']}</td>";
            echo "<td><strong>PROBLÉM</strong></td>";
            $problemy[] = "Tabulka $tabulka neexistuje, ale používá se v: " . implode(', ', $info['pouzito_v']);
        }

        echo "</tr>";
    }

    echo "</table>";

    // ==========================
    // 2. KONTROLA SLOUPCŮ
    // ==========================

    echo "<h2>📊 2. Kontrola kritických sloupců</h2>";

    $sloupce_ke_kontrole = [
        'wgs_documents' => [
            'claim_id' => 'INT(11) - vazba na reklamaci (číselné ID)',
            'document_path' => 'VARCHAR(500) - cesta k dokumentu',
            'document_type' => 'VARCHAR(50) - typ dokumentu'
        ],
        'wgs_reklamace' => [
            'id' => 'INT(11) PRIMARY KEY - číselné ID',
            'reklamace_id' => 'VARCHAR(50) - textové ID (např. WGS251116-996873)',
            'termin' => 'VARCHAR(50) - datum návštěvy',
            'cas_navstevy' => 'VARCHAR(50) - čas návštěvy'
        ],
        'wgs_photos' => [
            'reklamace_id' => 'VARCHAR(50) - vazba na reklamaci',
            'file_path' => 'VARCHAR(500) - cesta k fotce'
        ],
        'wgs_email_queue' => [
            'recipient_email' => 'VARCHAR(255) - email příjemce',
            'status' => 'ENUM - stav emailu',
            'scheduled_at' => 'TIMESTAMP - čas odeslání'
        ]
    ];

    echo "<table>";
    echo "<tr><th>Tabulka</th><th>Sloupec</th><th>Očekávaný typ</th><th>Stav</th></tr>";

    foreach ($sloupce_ke_kontrole as $tabulka => $sloupce) {
        // Zkontrolovat jestli tabulka existuje
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabulka'");
        if ($stmt->rowCount() === 0) {
            foreach ($sloupce as $sloupec => $typ) {
                echo "<tr>";
                echo "<td><code>$tabulka</code></td>";
                echo "<td><code>$sloupec</code></td>";
                echo "<td>$typ</td>";
                echo "<td style='color: #dc3545; font-weight: bold;'>❌ Tabulka neexistuje</td>";
                echo "</tr>";
            }
            continue;
        }

        // Načíst sloupce tabulky
        $stmt = $pdo->query("SHOW COLUMNS FROM $tabulka");
        $existujici_sloupce = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existujici_sloupce[] = $row['Field'];
        }

        foreach ($sloupce as $sloupec => $typ) {
            echo "<tr>";
            echo "<td><code>$tabulka</code></td>";
            echo "<td><code>$sloupec</code></td>";
            echo "<td style='font-size: 11px;'>$typ</td>";

            if (in_array($sloupec, $existujici_sloupce)) {
                echo "<td style='color: #28a745; font-weight: bold;'>✅ OK</td>";
                $ok[] = "$tabulka.$sloupec existuje";
            } else {
                echo "<td style='color: #dc3545; font-weight: bold;'>❌ CHYBÍ</td>";
                $problemy[] = "Sloupec $tabulka.$sloupec neexistuje";
            }

            echo "</tr>";
        }
    }

    echo "</table>";

    // ===================================
    // 3. ANALÝZA KÓDU - RIZIKOVÉ SOUBORY
    // ===================================

    echo "<h2>⚠️ 3. Analýza rizikových souborů</h2>";

    $rizikove_soubory = [
        'api/notes_api.php' => [
            'problem' => 'Používá tabulku wgs_notes, která NEEXISTUJE',
            'dotazy' => [
                'SELECT FROM wgs_notes',
                'INSERT INTO wgs_notes',
                'DELETE FROM wgs_notes'
            ],
            'dopad' => 'KRITICKÝ - Aplikace spadne při pokusu o práci s poznámkami',
            'volano_z' => ['assets/js/seznam.js']
        ],
        'api/delete_reklamace.php' => [
            'problem' => 'Opraveno - již nepoužívá wgs_notes a wgs_notifications',
            'dotazy' => [],
            'dopad' => 'OPRAVENO ✅',
            'volano_z' => ['includes/admin_reklamace_management.php']
        ]
    ];

    echo "<table>";
    echo "<tr><th>Soubor</th><th>Problém</th><th>Dopad</th><th>Volá se z</th></tr>";

    foreach ($rizikove_soubory as $soubor => $info) {
        $je_kriticky = $info['dopad'] !== 'OPRAVENO ✅';

        echo "<tr" . ($je_kriticky ? " style='background: #fff3cd;'" : "") . ">";
        echo "<td><span class='file-path'>$soubor</span>";

        if (!empty($info['dotazy'])) {
            echo "<br><small style='color: #666;'>SQL dotazy:</small><ul style='margin: 5px 0; padding-left: 20px;'>";
            foreach ($info['dotazy'] as $dotaz) {
                echo "<li style='font-size: 11px;'><code>$dotaz</code></li>";
            }
            echo "</ul>";
        }

        echo "</td>";
        echo "<td>" . $info['problem'] . "</td>";
        echo "<td><strong>" . $info['dopad'] . "</strong></td>";
        echo "<td><small>" . implode(', ', $info['volano_z']) . "</small></td>";
        echo "</tr>";

        if ($je_kriticky) {
            $problemy[] = "Soubor $soubor: " . $info['problem'];
        }
    }

    echo "</table>";

    // ====================
    // 4. SOUHRN DIAGNOSTIKY
    // ====================

    echo "<h2>📊 Souhrn diagnostiky</h2>";

    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<div class='stat-box stat-ok'>";
    echo "<div class='number'>" . count($ok) . "</div>";
    echo "<div class='label'>✅ OK</div>";
    echo "</div>";

    echo "<div class='stat-box stat-warning'>";
    echo "<div class='number'>" . count($varovani) . "</div>";
    echo "<div class='label'>⚠️ Varování</div>";
    echo "</div>";

    echo "<div class='stat-box stat-error'>";
    echo "<div class='number'>" . count($problemy) . "</div>";
    echo "<div class='label'>❌ Problémy</div>";
    echo "</div>";
    echo "</div>";

    if (count($problemy) > 0) {
        echo "<div class='error'>";
        echo "<strong>❌ NALEZENY PROBLÉMY:</strong><br><br>";
        echo "<ol style='margin: 10px 0; padding-left: 25px;'>";
        foreach ($problemy as $problem) {
            echo "<li style='margin: 8px 0;'>$problem</li>";
        }
        echo "</ol>";
        echo "</div>";

        echo "<h2>🔧 Doporučená řešení</h2>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ KRITICKÉ AKCE POTŘEBNÉ:</strong><br><br>";

        // Pokud chybí wgs_notes
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_notes'");
        if ($stmt->rowCount() === 0) {
            echo "<h3 style='color: #856404; margin-top: 0;'>1. Vytvořit tabulku wgs_notes</h3>";
            echo "<p>Tabulka <code>wgs_notes</code> je používána v <code>api/notes_api.php</code>, ale neexistuje v databázi.</p>";
            echo "<p><strong>Možnosti řešení:</strong></p>";
            echo "<ul>";
            echo "<li><strong>Varianta A:</strong> Vytvořit tabulku podle dokumentace</li>";
            echo "<li><strong>Varianta B:</strong> Odstranit/zakomentovat <code>api/notes_api.php</code> a volání z UI</li>";
            echo "</ul>";

            echo "<details style='margin: 15px 0; padding: 15px; background: white; border-radius: 5px;'>";
            echo "<summary style='cursor: pointer; font-weight: bold; color: #2D5016;'>SQL pro vytvoření tabulky wgs_notes</summary>";
            echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; margin-top: 10px;'>";
            echo "CREATE TABLE wgs_notes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    claim_id INT(11) NOT NULL,
    note_text TEXT NOT NULL,
    author_id INT(11),
    created_by VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_claim_id (claim_id),
    INDEX idx_author_id (author_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            echo "</pre>";
            echo "</details>";
        }

        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "<strong>✅ VŠECHNY KONTROLY PROŠLY!</strong><br><br>";
        echo "Databázová struktura je v pořádku. Všechny očekávané tabulky a sloupce existují.";
        echo "</div>";
    }

    echo "<hr style='margin: 40px 0;'>";
    echo "<div style='text-align: center;'>";
    echo "<a href='/admin.php' class='btn'>← Zpět do Admin panelu</a>";
    echo "<a href='?refresh=1' class='btn' style='background: #17a2b8;'>🔄 Aktualizovat diagnostiku</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
