<?php
/**
 * Přehození zakázky na jiného technika
 *
 * URL: https://www.wgs-service.cz/prehod_zakazku_na_technika.php
 *
 * Tento skript umožní:
 * 1. Vyhledat zakázku podle reklamace_id
 * 2. Vyhledat technika podle jména
 * 3. Přehodit zakázku na vybraného technika
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Přehození zakázky na technika</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .claim-card, .user-card { background: #f9f9f9; border-left: 4px solid #2D5016;
                                   padding: 15px; margin: 15px 0; border-radius: 5px; }
        .label { font-weight: bold; color: #555; display: inline-block;
                 width: 150px; }
        .value { color: #000; }
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
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        form { margin: 20px 0; }
        input[type='text'] { padding: 8px; width: 300px; font-size: 14px;
                             border: 1px solid #ddd; border-radius: 4px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Přehození zakázky na technika</h1>";

    // ==================================================
    // KROK 1: VYHLEDAT ZAKÁZKU
    // ==================================================

    $reklamaceId = $_GET['reklamace_id'] ?? '';
    $technikJmeno = $_GET['technik'] ?? '';
    $potvrdit = $_GET['potvrdit'] ?? '';

    if (empty($reklamaceId)) {
        // Formulář pro zadání zakázky
        echo "<h2>1. Vyhledat zakázku</h2>";
        echo "<form method='get'>";
        echo "<label for='reklamace_id'><strong>Reklamace ID, jméno zákazníka nebo adresa:</strong></label><br>";
        echo "<input type='text' name='reklamace_id' id='reklamace_id' placeholder='NCE25-00002370-34 nebo Nádvorník' required style='width: 400px;'>";
        echo "<button type='submit' class='btn'>Vyhledat</button>";
        echo "</form>";

        echo "<div class='info'>";
        echo "<strong>💡 PŘÍKLADY:</strong><br>";
        echo "• Podle ID: <code>NCE25-00002370-34</code><br>";
        echo "• Podle jména: <code>Nádvorník</code><br>";
        echo "• Podle adresy: <code>Micikova</code>";
        echo "</div>";

    } else {
        // Vyhledat zakázku
        $stmt = $pdo->prepare("
            SELECT
                r.id,
                r.reklamace_id,
                r.jmeno as zakaznik,
                r.adresa,
                r.model,
                r.assigned_to,
                r.stav,
                r.created_at as datum_vytvoreni,
                u.name as technik_jmeno,
                u.email as technik_email
            FROM wgs_reklamace r
            LEFT JOIN wgs_users u ON r.assigned_to = u.id
            WHERE r.reklamace_id = :reklamace_id
        ");
        $stmt->execute(['reklamace_id' => $reklamaceId]);
        $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$zakazka) {
            echo "<div class='warning'>";
            echo "<strong>POZOR: NENALEZENO:</strong> Zakázka s přesným ID <code>$reklamaceId</code> nebyla nalezena.";
            echo "</div>";

            // Zkusit vyhledat podle částečné shody
            echo "<h2>Vyhledávání podobných zakázek</h2>";

            $stmt = $pdo->prepare("
                SELECT
                    r.id,
                    r.reklamace_id,
                    r.jmeno as zakaznik,
                    r.adresa,
                    r.model,
                    r.assigned_to,
                    r.stav,
                    r.created_at as datum_vytvoreni,
                    u.name as technik_jmeno
                FROM wgs_reklamace r
                LEFT JOIN wgs_users u ON r.assigned_to = u.id
                WHERE r.reklamace_id LIKE :search
                   OR r.jmeno LIKE :search
                   OR r.adresa LIKE :search
                ORDER BY r.created_at DESC
                LIMIT 20
            ");
            $searchTerm = '%' . $reklamaceId . '%';
            $stmt->execute(['search' => $searchTerm]);
            $podobne = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($podobne)) {
                echo "<div class='error'>";
                echo "<strong>CHYBA: Žádné zakázky nenalezeny</strong> obsahující \"$reklamaceId\"";
                echo "</div>";
            } else {
                echo "<div class='info'>";
                echo "<strong>Nalezeno " . count($podobne) . " zakázek obsahujících:</strong> \"$reklamaceId\"";
                echo "</div>";

                echo "<table>";
                echo "<thead><tr>";
                echo "<th>Reklamace ID</th><th>Zákazník</th><th>Adresa</th><th>Model</th><th>Technik</th><th>Akce</th>";
                echo "</tr></thead>";
                echo "<tbody>";

                foreach ($podobne as $z) {
                    echo "<tr>";
                    echo "<td><strong>{$z['reklamace_id']}</strong></td>";
                    echo "<td>{$z['zakaznik']}</td>";
                    echo "<td>" . (strlen($z['adresa']) > 50 ? substr($z['adresa'], 0, 50) . '...' : $z['adresa']) . "</td>";
                    echo "<td>{$z['model']}</td>";
                    echo "<td>" . ($z['technik_jmeno'] ?: '<em>Nepřiřazeno</em>') . "</td>";
                    echo "<td><a href='?reklamace_id=" . urlencode($z['reklamace_id']) . "' class='btn'>Vybrat</a></td>";
                    echo "</tr>";
                }

                echo "</tbody>";
                echo "</table>";
            }

            echo "<a href='?' class='btn'>← Zkusit jiné hledání</a>";
        } else {
            // Zobrazit zakázku
            echo "<h2>Zakázka nalezena</h2>";
            echo "<div class='claim-card'>";
            echo "<h3>{$zakazka['reklamace_id']}</h3>";
            echo "<p><span class='label'>ID v databázi:</span> <span class='value'>{$zakazka['id']}</span></p>";
            echo "<p><span class='label'>Zákazník:</span> <span class='value'>{$zakazka['zakaznik']}</span></p>";
            echo "<p><span class='label'>Adresa:</span> <span class='value'>{$zakazka['adresa']}</span></p>";
            echo "<p><span class='label'>Model:</span> <span class='value'>{$zakazka['model']}</span></p>";
            echo "<p><span class='label'>Stav:</span> <span class='value'>{$zakazka['stav']}</span></p>";
            echo "<p><span class='label'>Vytvořeno:</span> <span class='value'>{$zakazka['datum_vytvoreni']}</span></p>";
            echo "<hr style='margin: 15px 0;'>";
            echo "<p><span class='label'>Současný technik:</span> <span class='value'>";
            if ($zakazka['assigned_to']) {
                echo "<strong>{$zakazka['technik_jmeno']}</strong> ({$zakazka['technik_email']})";
                echo "<br><small>User ID: {$zakazka['assigned_to']}</small>";
            } else {
                echo "<em style='color: #999;'>Žádný technik nepřiřazen</em>";
            }
            echo "</span></p>";
            echo "</div>";

            // ==================================================
            // KROK 2: VYBRAT NOVÉHO TECHNIKA
            // ==================================================

            if (empty($technikJmeno)) {
                echo "<h2>2. Vyberte nového technika</h2>";

                // Načíst všechny techniky
                $stmt = $pdo->query("
                    SELECT user_id, name, email, role
                    FROM wgs_users
                    WHERE role LIKE '%technik%' OR role LIKE '%technician%'
                    ORDER BY name ASC
                ");
                $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($technicians)) {
                    echo "<div class='warning'>";
                    echo "<strong>POZOR: VAROVÁNÍ:</strong> V databázi nejsou žádní technici.";
                    echo "</div>";
                } else {
                    echo "<table>";
                    echo "<thead><tr>";
                    echo "<th>ID</th><th>Jméno</th><th>Email</th><th>Role</th><th>Akce</th>";
                    echo "</tr></thead>";
                    echo "<tbody>";

                    foreach ($technicians as $tech) {
                        echo "<tr>";
                        echo "<td>{$tech['user_id']}</td>";
                        echo "<td><strong>{$tech['name']}</strong></td>";
                        echo "<td>{$tech['email']}</td>";
                        echo "<td>{$tech['role']}</td>";
                        echo "<td>";

                        // Zkontrolovat, jestli to není současný technik
                        if ($zakazka['assigned_to'] == $tech['user_id']) {
                            echo "<em style='color: #999;'>Současný technik</em>";
                        } else {
                            echo "<a href='?reklamace_id=" . urlencode($reklamaceId) . "&technik=" . urlencode($tech['name']) . "&technik_id=" . $tech['user_id'] . "' class='btn'>Vybrat</a>";
                        }

                        echo "</td>";
                        echo "</tr>";
                    }

                    echo "</tbody>";
                    echo "</table>";
                }

                echo "<a href='?' class='btn'>← Zpět</a>";

            } else {
                // ==================================================
                // KROK 3: POTVRZENÍ A PROVEDENÍ ZMĚNY
                // ==================================================

                $novyTechnikId = $_GET['technik_id'] ?? null;

                if (!$novyTechnikId) {
                    echo "<div class='error'>";
                    echo "<strong>CHYBA:</strong> Chybí ID nového technika.";
                    echo "</div>";
                    echo "<a href='?reklamace_id=" . urlencode($reklamaceId) . "' class='btn'>← Zpět</a>";
                } else {
                    // Načíst info o novém technikovi
                    $stmt = $pdo->prepare("SELECT user_id, name, email FROM wgs_users WHERE user_id = :id");
                    $stmt->execute(['id' => $novyTechnikId]);
                    $novyTechnik = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$novyTechnik) {
                        echo "<div class='error'>";
                        echo "<strong>CHYBA:</strong> Technik s ID $novyTechnikId nebyl nalezen.";
                        echo "</div>";
                    } else {
                        echo "<h2>3. Potvrzení změny</h2>";

                        if ($potvrdit === '1') {
                            // PROVÉST ZMĚNU
                            $pdo->beginTransaction();

                            try {
                                $stmt = $pdo->prepare("
                                    UPDATE wgs_reklamace
                                    SET assigned_to = :novy_technik_id,
                                        updated_at = NOW()
                                    WHERE id = :zakazka_id
                                ");

                                $stmt->execute([
                                    'novy_technik_id' => $novyTechnikId,
                                    'zakazka_id' => $zakazka['id']
                                ]);

                                $pdo->commit();

                                echo "<div class='success'>";
                                echo "<strong>ÚSPĚCH!</strong> Zakázka byla úspěšně přehozena.";
                                echo "</div>";

                                echo "<div class='info'>";
                                echo "<strong>ZMĚNA:</strong><br>";
                                echo "Zakázka: <code>{$zakazka['reklamace_id']}</code> ({$zakazka['zakaznik']})<br>";
                                echo "Ze: <strong>" . ($zakazka['technik_jmeno'] ?: 'Nepřiřazeno') . "</strong><br>";
                                echo "Na: <strong>{$novyTechnik['name']}</strong> ({$novyTechnik['email']})";
                                echo "</div>";

                                echo "<a href='seznam.php' class='btn'>← Zpět na seznam zakázek</a>";
                                echo "<a href='?' class='btn'>Přehodit další zakázku</a>";

                            } catch (PDOException $e) {
                                $pdo->rollBack();
                                echo "<div class='error'>";
                                echo "<strong>CHYBA PŘI UKLÁDÁNÍ:</strong><br>";
                                echo htmlspecialchars($e->getMessage());
                                echo "</div>";
                            }

                        } else {
                            // ZOBRAZIT POTVRZENÍ
                            echo "<div class='warning'>";
                            echo "<strong>POZOR:</strong> Chystáte se přehodit zakázku na jiného technika.";
                            echo "</div>";

                            echo "<div class='user-card'>";
                            echo "<h3>Změna přiřazení</h3>";
                            echo "<p><span class='label'>Zakázka:</span> <span class='value'><strong>{$zakazka['reklamace_id']}</strong></span></p>";
                            echo "<p><span class='label'>Zákazník:</span> <span class='value'>{$zakazka['zakaznik']}</span></p>";
                            echo "<hr style='margin: 15px 0;'>";
                            echo "<p><span class='label'>Ze technika:</span> <span class='value'>";
                            echo $zakazka['technik_jmeno'] ? "<strong>{$zakazka['technik_jmeno']}</strong>" : "<em>Nepřiřazeno</em>";
                            echo "</span></p>";
                            echo "<p><span class='label'>Na technika:</span> <span class='value'><strong style='color: #2D5016;'>{$novyTechnik['name']}</strong> ({$novyTechnik['email']})</span></p>";
                            echo "</div>";

                            echo "<form method='get'>";
                            echo "<input type='hidden' name='reklamace_id' value='" . htmlspecialchars($reklamaceId) . "'>";
                            echo "<input type='hidden' name='technik' value='" . htmlspecialchars($technikJmeno) . "'>";
                            echo "<input type='hidden' name='technik_id' value='" . htmlspecialchars($novyTechnikId) . "'>";
                            echo "<input type='hidden' name='potvrdit' value='1'>";
                            echo "<button type='submit' class='btn'>OK Potvrdit přehození</button>";
                            echo "<a href='?reklamace_id=" . urlencode($reklamaceId) . "' class='btn btn-danger'>CHYBA Zrušit</a>";
                            echo "</form>";
                        }
                    }
                }
            }
        }
    }

    echo "<hr style='margin-top: 40px;'>";
    echo "<a href='admin.php' class='btn'>← Zpět do Admin panelu</a>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
