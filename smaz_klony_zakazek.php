<?php
/**
 * Skript pro smazání zakázek vytvořených funkcí "Znovu otevřít"
 *
 * Tyto zakázky mají vyplněné original_reklamace_id
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Smazat klony zakazek</title>
    <style>
        body { font-family: 'Poppins', 'Segoe UI', sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; text-transform: uppercase; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; text-transform: uppercase; font-size: 0.8rem; }
        .btn { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; text-transform: uppercase; }
        .btn:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Smazat klony zakazek</h1>";

    // Najít klony
    $stmt = $pdo->query("
        SELECT r.id, r.reklamace_id, r.jmeno, r.original_reklamace_id, r.stav, r.created_at,
               (SELECT COUNT(*) FROM wgs_notes WHERE claim_id = r.id) as pocet_poznamek,
               (SELECT COUNT(*) FROM wgs_photos WHERE reklamace_id = r.id) as pocet_fotek
        FROM wgs_reklamace r
        WHERE r.original_reklamace_id IS NOT NULL
        ORDER BY r.id DESC
    ");
    $klony = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($klony)) {
        echo "<div class='success'><strong>Zadne klony nenalezeny.</strong><br>Neexistuji zadne zakazky vytvorene funkci 'Znovu otevrit'.</div>";
        echo "<a href='/admin.php' class='btn btn-secondary'>Zpet do admin</a>";
    } else {
        echo "<div class='warning'><strong>Nalezeno " . count($klony) . " klonu</strong><br>Tyto zakazky byly vytvoreny funkci 'Znovu otevrit zakázku'.</div>";

        // Tabulka klonů
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Workflow ID</th>
                <th>Zakaznik</th>
                <th>Puvodni ID</th>
                <th>Stav</th>
                <th>Vytvoreno</th>
                <th>Poznamky</th>
                <th>Fotky</th>
            </tr>";

        foreach ($klony as $k) {
            $stavText = match($k['stav']) {
                'wait' => 'CEKA',
                'open' => 'DOMLUVENA',
                'done' => 'HOTOVO',
                default => $k['stav']
            };
            echo "<tr>
                <td>{$k['id']}</td>
                <td>{$k['reklamace_id']}</td>
                <td>" . htmlspecialchars($k['jmeno']) . "</td>
                <td>{$k['original_reklamace_id']}</td>
                <td>{$stavText}</td>
                <td>{$k['created_at']}</td>
                <td>{$k['pocet_poznamek']}</td>
                <td>{$k['pocet_fotek']}</td>
            </tr>";
        }
        echo "</table>";

        // Pokud execute=1, smazat
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>MAZANI...</strong></div>";

            $pdo->beginTransaction();

            try {
                $smazano = 0;
                $smazanePoznamky = 0;

                foreach ($klony as $k) {
                    // Smazat poznámky
                    $stmtNotes = $pdo->prepare("DELETE FROM wgs_notes WHERE claim_id = :id");
                    $stmtNotes->execute(['id' => $k['id']]);
                    $smazanePoznamky += $stmtNotes->rowCount();

                    // Smazat reklamaci
                    $stmtDel = $pdo->prepare("DELETE FROM wgs_reklamace WHERE id = :id");
                    $stmtDel->execute(['id' => $k['id']]);
                    $smazano++;

                    echo "<div class='info'>Smazano: {$k['reklamace_id']} (ID: {$k['id']}) - " . htmlspecialchars($k['jmeno']) . "</div>";
                }

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>HOTOVO</strong><br>";
                echo "Smazano {$smazano} klonu a {$smazanePoznamky} poznamek.";
                echo "</div>";

                echo "<a href='/admin.php' class='btn btn-secondary'>Zpet do admin</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            // Zobrazit tlačítko
            echo "<div class='warning'><strong>POZOR:</strong> Tato akce je nevratna! Budou smazany vsechny vyse uvedene zakazky vcetne jejich poznamek.</div>";
            echo "<a href='?execute=1' class='btn' onclick=\"return confirm('Opravdu smazat " . count($klony) . " klonu? Tato akce je NEVRATNA!');\">Smazat " . count($klony) . " klonu</a>";
            echo "<a href='/admin.php' class='btn btn-secondary'>Zrusit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
