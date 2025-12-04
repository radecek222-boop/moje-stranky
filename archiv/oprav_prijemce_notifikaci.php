<?php
/**
 * Migrace: Oprava příjemců u aktivních notifikací
 *
 * Tento skript opraví šablony, které jsou aktivní ale nemají nastavené příjemce.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

$pdo = getDbConnection();

// Definice oprav - pouze aktivní šablony s chybějícími příjemci
$opravy = [
    'appointment_assigned_technician' => [
        'popis' => 'Přiřazení termínu technikovi',
        'recipients' => [
            'customer' => ['enabled' => false, 'type' => 'to'],
            'seller' => ['enabled' => false, 'type' => 'cc'],
            'technician' => ['enabled' => true, 'type' => 'to'],
            'importer' => ['enabled' => false, 'type' => 'cc'],
            'other' => ['enabled' => false, 'type' => 'cc']
        ]
    ],
    'appointment_reminder_customer' => [
        'popis' => 'Připomenutí termínu zákazníkovi',
        'recipients' => [
            'customer' => ['enabled' => true, 'type' => 'to'],
            'seller' => ['enabled' => false, 'type' => 'cc'],
            'technician' => ['enabled' => false, 'type' => 'cc'],
            'importer' => ['enabled' => false, 'type' => 'cc'],
            'other' => ['enabled' => false, 'type' => 'cc']
        ]
    ]
];

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava příjemců notifikací</title>
    <style>
        body { font-family: 'Poppins', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #000; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .enabled { color: green; font-weight: 600; }
        .disabled { color: #ccc; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Oprava příjemců notifikací</h1>";

if (isset($_GET['execute']) && $_GET['execute'] === '1') {
    echo "<div class='info'><strong>PROVÁDÍM OPRAVU...</strong></div>";

    $opraveno = 0;
    foreach ($opravy as $id => $data) {
        $stmt = $pdo->prepare("UPDATE wgs_notifications SET recipients = :recipients WHERE id = :id");
        $stmt->execute([
            'recipients' => json_encode($data['recipients']),
            'id' => $id
        ]);

        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>Opraveno: <strong>{$data['popis']}</strong> (ID: {$id})</div>";
            $opraveno++;
        } else {
            echo "<div class='warning'>Šablona nenalezena nebo nezměněna: {$id}</div>";
        }
    }

    echo "<div class='success' style='margin-top: 20px;'><strong>HOTOVO!</strong> Opraveno {$opraveno} šablon.</div>";
    echo "<a href='/test_notifikace.php' class='btn'>Ověřit v testu</a>";
    echo "<a href='/admin.php?tab=notifications' class='btn'>Zpět do admin</a>";

} else {
    echo "<div class='info'>Budou opraveny tyto aktivní šablony, které nemají nastavené příjemce:</div>";

    echo "<table>";
    echo "<tr><th>ID</th><th>Název</th><th>Nové nastavení příjemců</th></tr>";

    foreach ($opravy as $id => $data) {
        echo "<tr><td><code>{$id}</code></td><td>{$data['popis']}</td><td>";

        $prijemci = [];
        foreach ($data['recipients'] as $key => $config) {
            if ($config['enabled']) {
                $nazvy = ['customer' => 'Zákazník', 'seller' => 'Prodejce', 'technician' => 'Technik', 'importer' => 'Importér', 'other' => 'Ostatní'];
                $typ = ['to' => 'To', 'cc' => 'Cc', 'bcc' => 'Bcc'][$config['type']];
                $prijemci[] = "<span class='enabled'>{$nazvy[$key]} ({$typ})</span>";
            }
        }
        echo implode(', ', $prijemci);
        echo "</td></tr>";
    }

    echo "</table>";

    echo "<a href='?execute=1' class='btn'>Opravit příjemce</a>";
    echo "<a href='/admin.php?tab=notifications' class='btn' style='background: #666;'>Zrušit</a>";
}

echo "</div></body></html>";
?>
