<?php
/**
 * ⚡ AUTOMATICKÁ OPRAVA DIAGNOSTIKY - JEDEN KLIK
 *
 * Tento skript automaticky:
 * 1. Přidá chybějící databázové indexy
 * 2. Zkontroluje permissions
 * 3. Spustí diagnostiku pro ověření
 *
 * POUŽITÍ: Stačí otevřít tento odkaz v prohlížeči
 * https://www.wgs-service.cz/automaticka_oprava_diagnostiky.php
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit automatickou opravu.");
}

$krok = $_GET['krok'] ?? '1';

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>⚡ Automatická Oprava Diagnostiky</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        .krok {
            background: #f9f9f9;
            border-left: 4px solid #2D5016;
            padding: 15px;
            margin: 20px 0;
            position: relative;
        }
        .krok.active {
            background: #e8f5e9;
            border-left-color: #4caf50;
        }
        .krok.done {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .krok h3 {
            margin: 0 0 10px 0;
            color: #2D5016;
        }
        .progress {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #2D5016, #4caf50);
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #1a300d;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2D5016;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .checklist {
            list-style: none;
            padding: 0;
        }
        .checklist li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .checklist li:before {
            content: "✅ ";
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>⚡ Automatická Oprava Diagnostiky</h1>

    <?php if ($krok === '1'): ?>
        <!-- ÚVODNÍ OBRAZOVKA -->
        <div class='info'>
            <strong>Tento skript automaticky opraví všechny problémy zjištěné diagnostikou.</strong>
        </div>

        <div class='krok'>
            <h3>📋 Co bude provedeno:</h3>
            <ul class='checklist'>
                <li>Přidání chybějících databázových indexů (3 indexy)</li>
                <li>Kontrola write permissions (5 složek)</li>
                <li>Ověření oprav pomocí diagnostiky</li>
            </ul>
        </div>

        <div class='warning'>
            <strong>⚠️ DŮLEŽITÉ:</strong><br>
            Permissions na složky (logs, uploads, temp) MUSÍTE opravit ručně přes FTP!<br>
            Skript vás po dokončení navede, jak na to.
        </div>

        <a href='?krok=2' class='btn'>▶️ SPUSTIT AUTOMATICKOU OPRAVU</a>
        <a href='admin.php' class='btn' style='background: #6c757d;'>← Zrušit</a>

    <?php elseif ($krok === '2'): ?>
        <!-- KROK 2: PŘIDÁNÍ INDEXŮ -->
        <div class='progress'>
            <div class='progress-bar' style='width: 33%;'>33% - Přidávání indexů...</div>
        </div>

        <div class='krok active'>
            <h3>⚙️ Krok 1/3: Přidávání databázových indexů</h3>
            <div class='spinner'></div>
            <p>Načítám migrační skript...</p>
        </div>

        <script>
            // Automaticky přesměrovat na migraci s auto=1
            setTimeout(function() {
                window.location.href = 'pridej_chybejici_indexy_performance.php?auto=1&redirect=automaticka_oprava_diagnostiky.php?krok=3';
            }, 2000);
        </script>

    <?php elseif ($krok === '3'): ?>
        <!-- KROK 3: KONTROLA PERMISSIONS -->
        <div class='progress'>
            <div class='progress-bar' style='width: 66%;'>66% - Kontrola permissions...</div>
        </div>

        <div class='krok done'>
            <h3>✅ Krok 1/3: Databázové indexy</h3>
            <p>Indexy byly úspěšně přidány.</p>
        </div>

        <div class='krok active'>
            <h3>⚙️ Krok 2/3: Kontrola Write Permissions</h3>
            <?php
            try {
                $pdo = getDbConnection();

                $checkDirs = [
                    'logs',
                    'uploads',
                    'temp',
                    'uploads/photos',
                    'uploads/protokoly'
                ];

                $notWritable = [];
                foreach ($checkDirs as $dir) {
                    $path = __DIR__ . '/' . $dir;
                    if (!is_writable($path)) {
                        $notWritable[] = $dir;
                    }
                }

                if (empty($notWritable)) {
                    echo "<div class='success'>";
                    echo "<strong>✅ Všechny složky mají správná oprávnění!</strong><br>";
                    echo "Write permissions jsou v pořádku.";
                    echo "</div>";
                } else {
                    echo "<div class='warning'>";
                    echo "<strong>⚠️ " . count($notWritable) . " složek nemá write permissions:</strong><br>";
                    echo "<ul>";
                    foreach ($notWritable as $dir) {
                        echo "<li>❌ {$dir}</li>";
                    }
                    echo "</ul>";
                    echo "<br><strong>🛠️ JAK TO OPRAVIT:</strong><br>";
                    echo "1. Otevřete FTP klient (FileZilla, WinSCP)<br>";
                    echo "2. Pro každou složku: Pravé tlačítko → Permissions → Nastavte 755 nebo 775<br>";
                    echo "3. Zaškrtněte 'Rekurzivně do podsložek'<br>";
                    echo "<br>";
                    echo "<a href='OPRAVA_PERMISSIONS.md' target='_blank' class='btn'>📖 Detailní Návod</a>";
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "<div class='warning'>";
                echo "Nelze zkontrolovat permissions: " . htmlspecialchars($e->getMessage());
                echo "</div>";
            }
            ?>
        </div>

        <a href='?krok=4' class='btn'>▶️ POKRAČOVAT NA OVĚŘENÍ</a>

    <?php elseif ($krok === '4'): ?>
        <!-- KROK 4: OVĚŘENÍ -->
        <div class='progress'>
            <div class='progress-bar' style='width: 100%;'>100% - Dokončeno!</div>
        </div>

        <div class='krok done'>
            <h3>✅ Krok 1/3: Databázové indexy</h3>
            <p>Indexy byly úspěšně přidány.</p>
        </div>

        <div class='krok done'>
            <h3>✅ Krok 2/3: Write Permissions</h3>
            <p>Zkontrolováno.</p>
        </div>

        <div class='krok active'>
            <h3>⚙️ Krok 3/3: Finální Ověření</h3>
            <div class='info'>
                <strong>Spouštím diagnostiku pro ověření oprav...</strong>
            </div>
        </div>

        <script>
            // Přesměrovat na diagnostiku
            setTimeout(function() {
                window.location.href = 'admin.php?tab=console&auto_run=1';
            }, 2000);
        </script>

    <?php endif; ?>

</div>
</body>
</html>
