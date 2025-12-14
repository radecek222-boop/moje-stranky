<?php
/**
 * Vytvoreni 3 hlavnich Transport event karet
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Vytvoreni Transport Karet</title>
<style>body{font-family:sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}
.box{background:#fff;padding:20px;border-radius:8px;margin:10px 0;}
.ok{color:#155724;background:#d4edda;padding:10px;border-radius:4px;margin:5px 0;}
.err{color:#721c24;background:#f8d7da;padding:10px;border-radius:4px;margin:5px 0;}
.btn{background:#333;color:#fff;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:10px 5px 10px 0;text-decoration:none;display:inline-block;}
</style></head><body><div class='box'>";

try {
    $pdo = getDbConnection();
    echo "<h1>Vytvoreni Transport Karet</h1>";

    // Definice 3 hlavnich eventu
    $eventy = [
        [
            'nazev' => 'STVANICE 26',
            'popis' => 'Hlavni event Stvanice'
        ],
        [
            'nazev' => 'TECHMISSION',
            'popis' => 'Techmission transporty'
        ],
        [
            'nazev' => 'VIP TRANSPORT',
            'popis' => 'VIP a specialni transporty'
        ]
    ];

    foreach ($eventy as $event) {
        // Zkontrolovat jestli event uz existuje
        $stmt = $pdo->prepare("SELECT event_id FROM wgs_transport_akce WHERE nazev = :nazev");
        $stmt->execute(['nazev' => $event['nazev']]);

        if ($stmt->fetch()) {
            echo "<div class='ok'>Event '{$event['nazev']}' jiz existuje</div>";
        } else {
            // Vytvorit novy event
            $stmt = $pdo->prepare("INSERT INTO wgs_transport_akce (nazev, popis) VALUES (:nazev, :popis)");
            $stmt->execute([
                'nazev' => $event['nazev'],
                'popis' => $event['popis']
            ]);
            echo "<div class='ok'>Vytvoren event: <strong>{$event['nazev']}</strong></div>";
        }
    }

    echo "<h2>Hotovo!</h2>";
    echo "<p>Nyni muzete prejit na Transport a uvidite 3 karty.</p>";
    echo "<a href='admin.php?tab=transport' class='btn'>Prejit na Transport</a>";

} catch (Exception $e) {
    echo "<div class='err'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
