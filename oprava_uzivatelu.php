<?php
/**
 * Oprava uživatelských dat
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze admin");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Oprava uživatelů</title>";
echo "<style>
body { font-family: 'Poppins', sans-serif; background: #1a1a1a; color: #ccc; padding: 20px; }
h1, h2 { color: #fff; }
table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
th, td { border: 1px solid #333; padding: 8px; text-align: left; }
th { background: #333; color: #39ff14; }
.success { background: #1a3d1a; color: #39ff14; padding: 10px; margin: 10px 0; }
.error { background: #3d1a1a; color: #ff3939; padding: 10px; margin: 10px 0; }
.info { background: #222; padding: 15px; border-left: 4px solid #39ff14; margin: 10px 0; }
.btn { background: #dc3545; color: #fff; padding: 10px 20px; border: none; cursor: pointer; font-size: 16px; }
.btn-success { background: #28a745; }
.btn:hover { opacity: 0.8; }
</style></head><body>";

try {
    $pdo = getDbConnection();
    $execute = isset($_GET['execute']) && $_GET['execute'] === '1';

    echo "<h1>OPRAVA UŽIVATELSKÝCH DAT</h1>";

    // 1. Změna jména ADMIN001
    echo "<h2>1. Změna ADMIN001 'Radek Zikmund' → 'Admin'</h2>";
    $stmt = $pdo->prepare("SELECT name FROM wgs_users WHERE user_id = 'ADMIN001'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        echo "<div class='info'>Aktuální jméno: <strong>{$admin['name']}</strong></div>";
        if ($execute) {
            $stmt = $pdo->prepare("UPDATE wgs_users SET name = 'Admin' WHERE user_id = 'ADMIN001'");
            $stmt->execute();
            echo "<div class='success'>OPRAVENO: ADMIN001 přejmenován na 'Admin'</div>";
        }
    }

    // 2. Reklamace s termínem ale bez technika
    echo "<h2>2. Reklamace s termínem návštěvy ale bez technika</h2>";
    $stmt = $pdo->query("
        SELECT id, reklamace_id, cislo, jmeno, termin, cas_navstevy, technik, stav
        FROM wgs_reklamace
        WHERE (termin IS NOT NULL AND termin != '')
        AND (technik IS NULL OR technik = '')
    ");
    $bezTechnika = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($bezTechnika) > 0) {
        echo "<table><tr><th>ID</th><th>Reklamace ID</th><th>Zákazník</th><th>Termín</th><th>Čas</th><th>Stav</th></tr>";
        foreach ($bezTechnika as $r) {
            echo "<tr>";
            echo "<td>{$r['id']}</td>";
            echo "<td>{$r['reklamace_id']}</td>";
            echo "<td>{$r['jmeno']}</td>";
            echo "<td>{$r['termin']}</td>";
            echo "<td>{$r['cas_navstevy']}</td>";
            echo "<td>{$r['stav']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        if ($execute) {
            // Přiřadit výchozího technika (Radek Zikmund - TCH20250002)
            $stmt = $pdo->prepare("
                UPDATE wgs_reklamace
                SET technik = 'Radek Zikmund', assigned_to = 'TCH20250002'
                WHERE (termin IS NOT NULL AND termin != '')
                AND (technik IS NULL OR technik = '')
            ");
            $stmt->execute();
            $count = $stmt->rowCount();
            echo "<div class='success'>OPRAVENO: {$count} reklamací přiřazeno technikovi Radek Zikmund</div>";
        }
    } else {
        echo "<div class='info'>Žádné reklamace s termínem bez technika.</div>";
    }

    // 3. Reklamace bez zadavatele (created_by)
    echo "<h2>3. Reklamace bez zadavatele (created_by)</h2>";
    $stmt = $pdo->query("
        SELECT id, reklamace_id, cislo, jmeno, created_by, stav, datum_vytvoreni
        FROM wgs_reklamace
        WHERE created_by IS NULL OR created_by = ''
    ");
    $bezZadavatele = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($bezZadavatele) > 0) {
        echo "<table><tr><th>ID</th><th>Reklamace ID</th><th>Zákazník</th><th>Stav</th><th>Vytvořeno</th></tr>";
        foreach ($bezZadavatele as $r) {
            echo "<tr>";
            echo "<td>{$r['id']}</td>";
            echo "<td>{$r['reklamace_id']}</td>";
            echo "<td>{$r['jmeno']}</td>";
            echo "<td>{$r['stav']}</td>";
            echo "<td>{$r['datum_vytvoreni']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        if ($execute) {
            // Přiřadit admina jako zadavatele (starší záznamy)
            $stmt = $pdo->prepare("
                UPDATE wgs_reklamace
                SET created_by = 'ADMIN001', created_by_role = 'admin'
                WHERE created_by IS NULL OR created_by = ''
            ");
            $stmt->execute();
            $count = $stmt->rowCount();
            echo "<div class='success'>OPRAVENO: {$count} reklamací přiřazeno zadavateli ADMIN001</div>";
        }
    } else {
        echo "<div class='info'>Žádné reklamace bez zadavatele.</div>";
    }

    // 4. Všechny reklamace bez technika (informativní)
    echo "<h2>4. Všechny reklamace bez technika (informativní)</h2>";
    $stmt = $pdo->query("
        SELECT id, reklamace_id, cislo, jmeno, termin, stav
        FROM wgs_reklamace
        WHERE technik IS NULL OR technik = ''
    ");
    $vsechnyBezTechnika = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($vsechnyBezTechnika) > 0) {
        echo "<table><tr><th>ID</th><th>Reklamace ID</th><th>Zákazník</th><th>Termín</th><th>Stav</th></tr>";
        foreach ($vsechnyBezTechnika as $r) {
            $termin = $r['termin'] ?: '(není)';
            echo "<tr>";
            echo "<td>{$r['id']}</td>";
            echo "<td>{$r['reklamace_id']}</td>";
            echo "<td>{$r['jmeno']}</td>";
            echo "<td>{$termin}</td>";
            echo "<td>{$r['stav']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Všechny reklamace mají technika.</div>";
    }

    // Tlačítko pro spuštění
    if (!$execute) {
        echo "<br><br>";
        echo "<a href='?execute=1' class='btn btn-success' onclick=\"return confirm('Opravdu spustit opravu?');\">SPUSTIT OPRAVU</a>";
        echo "<p style='color:#999;margin-top:10px;'>Kliknutím spustíte všechny opravy najednou.</p>";
    } else {
        echo "<br><br><div class='success'><strong>VŠECHNY OPRAVY DOKONČENY</strong></div>";
        echo "<a href='kontrola_uzivatelu.php' class='btn'>Zpět na kontrolu</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
