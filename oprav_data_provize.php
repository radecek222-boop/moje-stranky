<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pouze admin");
}

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Oprava provizí - leden 2026</h1>";

$pdo = getDbConnection();

// Najít Milana
$stmt = $pdo->query("SELECT id FROM wgs_users WHERE name LIKE '%Milan%' AND role = 'technik' LIMIT 1");
$milanId = $stmt->fetchColumn();

// Najít Radka
$stmt = $pdo->query("SELECT id FROM wgs_users WHERE name LIKE '%Radek%' LIMIT 1");
$radekId = $stmt->fetchColumn();

echo "<p>Milan ID: {$milanId}, Radek ID: {$radekId}</p>";

// Zakázky dokončené v LEDNU - patří Milanovi
$lednoveMilan = [
    'NCE25-00002429-38',  // Andrea Beránková - leden
    'NCE25-00001923-52',  // Vladimír Marčan - leden
];

// Zakázky dokončené v PROSINCI - opravit datum
$prosincove = [
    'GREY M' => '2025-12-22 12:00:00',
    'POZ/2025/17-12/01' => '2025-12-17 12:00:00',
];

echo "<h2>Změny:</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
echo "<tr style='background:#333;color:#fff;'><th>Číslo</th><th>Akce</th><th>Stav</th></tr>";

if (isset($_GET['fix']) && $_GET['fix'] === '1') {

    // Lednové zakázky - přiřadit Milanovi
    foreach ($lednoveMilan as $cislo) {
        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET dokonceno_kym = :milan_id
            WHERE cislo = :cislo
        ");
        $stmt->execute(['milan_id' => $milanId, 'cislo' => $cislo]);
        echo "<tr><td>{$cislo}</td><td>dokonceno_kym = {$milanId} (Milan)</td><td style='color:green;'>OK</td></tr>";
    }

    // Prosincové zakázky - opravit datum na prosinec
    foreach ($prosincove as $cislo => $datum) {
        $stmt = $pdo->prepare("UPDATE wgs_reklamace SET datum_dokonceni = :datum WHERE cislo = :cislo");
        $stmt->execute(['datum' => $datum, 'cislo' => $cislo]);
        echo "<tr><td>{$cislo}</td><td>datum_dokonceni = {$datum} (prosinec)</td><td style='color:green;'>OK</td></tr>";
    }

    echo "</table>";
    echo "<p style='color:green;font-weight:bold;font-size:1.2em;'>OPRAVENO!</p>";
    echo "<p><strong>Výsledek:</strong></p>";
    echo "<ul>";
    echo "<li>Radek - leden: 0 €</li>";
    echo "<li>Milan - leden: provize z obou zakázek</li>";
    echo "</ul>";
    echo "<a href='debug_provize_radek.php' style='margin-right:10px;'>Zkontrolovat Radka</a>";

} else {
    foreach ($lednoveMilan as $cislo) {
        echo "<tr><td>{$cislo}</td><td>Přiřadit Milanovi (dokonceno_kym = {$milanId})</td><td>Čeká</td></tr>";
    }
    foreach ($prosincove as $cislo => $datum) {
        echo "<tr><td>{$cislo}</td><td>Opravit datum na {$datum} (prosinec)</td><td>Čeká</td></tr>";
    }
    echo "</table>";

    echo "<br><a href='?fix=1' style='background:#333;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;'>OPRAVIT DATA</a>";
}
?>
