<?php
/**
 * Migrace: Automatická oprava write permissions
 *
 * Tento skript AUTOMATICKY opraví permissions na všech důležitých složkách.
 * Můžete jej spustit vícekrát - bezpečně zkontroluje a opraví pouze co je potřeba.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit opravu permissions.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava Permissions</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
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
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: 600; }
    </style>
</head>
<body>
<div class='container'>";

try {
    echo "<h1>Automatická Oprava Write Permissions</h1>";

    // Definice složek, které potřebují write permissions
    $slozky = [
        'logs',
        'uploads',
        'temp',
        'uploads/photos',
        'uploads/protokoly'
    ];

    // Kontrolní fáze
    echo "<div class='info'><strong>KONTROLA AKTUÁLNÍHO STAVU...</strong></div>";

    echo "<table>";
    echo "<tr><th>Složka</th><th>Existuje</th><th>Writable</th><th>Permissions</th><th>Status</th></tr>";

    $slozkyKOprave = [];
    $slozkyKVytvoreni = [];
    $jizVPoradku = 0;

    foreach ($slozky as $slozka) {
        $cesta = __DIR__ . '/' . $slozka;
        $existuje = file_exists($cesta);
        $jeWritable = $existuje && is_writable($cesta);
        $perms = $existuje ? substr(sprintf('%o', fileperms($cesta)), -4) : 'N/A';

        echo "<tr>";
        echo "<td><strong>{$slozka}</strong></td>";
        echo "<td>" . ($existuje ? '✅ Ano' : '❌ Ne') . "</td>";
        echo "<td>" . ($jeWritable ? '✅ Ano' : '❌ Ne') . "</td>";
        echo "<td>" . $perms . "</td>";

        if (!$existuje) {
            echo "<td style='color: #dc3545;'>⚠️ Bude vytvořena</td>";
            $slozkyKVytvoreni[] = $slozka;
        } elseif (!$jeWritable) {
            echo "<td style='color: #ffc107;'>⚠️ Bude opraven</td>";
            $slozkyKOprave[] = $slozka;
        } else {
            echo "<td style='color: #28a745;'>✅ V pořádku</td>";
            $jizVPoradku++;
        }

        echo "</tr>";
    }

    echo "</table>";

    if (empty($slozkyKOprave) && empty($slozkyKVytvoreni)) {
        echo "<div class='success'>";
        echo "<strong>✅ VŠECHNY SLOŽKY MAJÍ SPRÁVNÁ OPRÁVNĚNÍ</strong><br>";
        echo "Není třeba provádět žádné změny. Všech {$jizVPoradku} složek má write permissions.";
        echo "</div>";
    } else {
        $celkemKOprave = count($slozkyKOprave) + count($slozkyKVytvoreni);
        echo "<div class='warning'>";
        echo "<strong>⚠️ NALEZENO {$celkemKOprave} PROBLÉMŮ</strong><br>";
        echo "Složek k vytvoření: " . count($slozkyKVytvoreni) . "<br>";
        echo "Složek k opravě permissions: " . count($slozkyKOprave) . "<br>";
        echo "Kliknutím na tlačítko níže opravíte permissions automaticky.";
        echo "</div>";

        // Automatický režim - pokud je ?auto=1, automaticky provést
        $autoMode = isset($_GET['auto']) && $_GET['auto'] === '1';
        $executeMode = isset($_GET['execute']) && $_GET['execute'] === '1';

        // Pokud je auto režim a není execute, přesměrovat na execute
        if ($autoMode && !$executeMode) {
            echo "<div class='info'>";
            echo "<strong>🤖 AUTOMATICKÝ REŽIM AKTIVNÍ</strong><br>";
            echo "Spouštím opravu automaticky...";
            echo "</div>";
            echo "<script>window.location.href = '?execute=1';</script>";
            echo "<meta http-equiv='refresh' content='1;url=?execute=1'>";
            exit;
        }

        // Pokud je nastaveno ?execute=1, provést opravu
        if ($executeMode) {
            echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

            $uspesne = 0;
            $chyby = 0;

            // Vytvoření chybějících složek
            foreach ($slozkyKVytvoreni as $slozka) {
                $cesta = __DIR__ . '/' . $slozka;

                try {
                    echo "<div class='info'>";
                    echo "<strong>Vytvářím složku:</strong> {$slozka}";
                    echo "</div>";

                    if (mkdir($cesta, 0775, true)) {
                        echo "<div class='success'>";
                        echo "✅ Složka {$slozka} vytvořena s permissions 0775";
                        echo "</div>";
                        $uspesne++;
                    } else {
                        echo "<div class='error'>";
                        echo "❌ Nepodařilo se vytvořit složku {$slozka}";
                        echo "</div>";
                        $chyby++;
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>";
                    echo "<strong>❌ CHYBA při vytváření {$slozka}:</strong><br>";
                    echo htmlspecialchars($e->getMessage());
                    echo "</div>";
                    $chyby++;
                }
            }

            // Oprava permissions existujících složek
            foreach ($slozkyKOprave as $slozka) {
                $cesta = __DIR__ . '/' . $slozka;

                try {
                    echo "<div class='info'>";
                    echo "<strong>Opravuji permissions:</strong> {$slozka}";
                    echo "</div>";

                    // Zkusit nejprve 0775 (group writable)
                    if (chmod($cesta, 0775)) {
                        echo "<div class='success'>";
                        echo "✅ Permissions pro {$slozka} nastaveny na 0775";

                        // Ověřit, že je teď writable
                        clearstatcache(true, $cesta);
                        if (is_writable($cesta)) {
                            echo " - WRITABLE ✅";
                            $uspesne++;
                        } else {
                            echo " - STÁLE NOT WRITABLE ⚠️ (zkuste 0777 ručně)";
                            $chyby++;
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='error'>";
                        echo "❌ Nepodařilo se změnit permissions pro {$slozka}";
                        echo "<br><small>Možná nemáte oprávnění změnit permissions na serveru. Budete to muset udělat přes FTP.</small>";
                        echo "</div>";
                        $chyby++;
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>";
                    echo "<strong>❌ CHYBA při opravě {$slozka}:</strong><br>";
                    echo htmlspecialchars($e->getMessage());
                    echo "</div>";
                    $chyby++;
                }
            }

            // Finální shrnutí
            echo "<div class='success'>";
            echo "<h2>✅ OPRAVA DOKONČENA</h2>";
            echo "<strong>Úspěšně opraveno:</strong> {$uspesne}<br>";
            if ($chyby > 0) {
                echo "<strong style='color: #dc3545;'>Chyb:</strong> {$chyby}<br>";
                echo "<br>";
                echo "<div class='warning'>";
                echo "⚠️ Pokud některé složky stále nejsou writable, budete je muset opravit ručně přes FTP.<br>";
                echo "Návod: <a href='OPRAVA_PERMISSIONS.md' target='_blank'>OPRAVA_PERMISSIONS.md</a>";
                echo "</div>";
            }
            echo "<br>";
            echo "<strong>Výsledek:</strong> Aplikace by nyní měla fungovat správně.";
            echo "</div>";

            // Pokud je nastaveno redirect, automaticky přesměrovat
            $redirectUrl = $_GET['redirect'] ?? null;
            if ($redirectUrl && $autoMode) {
                echo "<div class='info'>";
                echo "<strong>✅ Hotovo! Přesměrovávám...</strong>";
                echo "</div>";
                echo "<script>setTimeout(function() { window.location.href = '" . htmlspecialchars($redirectUrl) . "'; }, 2000);</script>";
                echo "<meta http-equiv='refresh' content='2;url=" . htmlspecialchars($redirectUrl) . "'>";
            } else {
                echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";
                echo "<a href='?execute=1' class='btn' style='background: #17a2b8;'>🔄 Spustit znovu</a>";
            }

        } else {
            // Náhled co bude provedeno
            echo "<h2>Náhled změn:</h2>";

            if (!empty($slozkyKVytvoreni)) {
                echo "<div class='info'>";
                echo "<strong>Budou vytvořeny složky:</strong><br>";
                foreach ($slozkyKVytvoreni as $slozka) {
                    echo "• {$slozka} (permissions: 0775)<br>";
                }
                echo "</div>";
            }

            if (!empty($slozkyKOprave)) {
                echo "<div class='info'>";
                echo "<strong>Budou opraveny permissions:</strong><br>";
                foreach ($slozkyKOprave as $slozka) {
                    echo "• {$slozka} → 0775<br>";
                }
                echo "</div>";
            }

            echo "<a href='?execute=1' class='btn'>✅ SPUSTIT OPRAVU</a>";
            echo "<a href='admin.php' class='btn' style='background: #6c757d;'>← Zrušit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>KRITICKÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
