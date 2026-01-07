<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pouze admin");
}

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Oprava assigned_to pro zakázku NCE25-00002429-38</h1>";

$pdo = getDbConnection();

// Najít Milana
$stmt = $pdo->query("SELECT id, name, user_id FROM wgs_users WHERE name LIKE '%Milan%' AND role = 'technik'");
$milan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$milan) {
    die("<p style='color:red;'>Milan nebyl nalezen v systému.</p>");
}

echo "<p><strong>Milan:</strong> ID = {$milan['id']}, name = {$milan['name']}</p>";

// Najít zakázku
$stmt = $pdo->prepare("SELECT id, cislo, technik, assigned_to FROM wgs_reklamace WHERE cislo = :cislo");
$stmt->execute(['cislo' => 'NCE25-00002429-38']);
$zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$zakazka) {
    die("<p style='color:red;'>Zakázka nebyla nalezena.</p>");
}

echo "<p><strong>Zakázka:</strong> {$zakazka['cislo']}</p>";
echo "<p>Aktuální assigned_to: {$zakazka['assigned_to']}</p>";
echo "<p>Aktuální technik: {$zakazka['technik']}</p>";

if (isset($_GET['fix']) && $_GET['fix'] === '1') {
    $stmt = $pdo->prepare("UPDATE wgs_reklamace SET assigned_to = :milan_id WHERE id = :id");
    $stmt->execute(['milan_id' => $milan['id'], 'id' => $zakazka['id']]);

    echo "<p style='color:green;font-weight:bold;'>OPRAVENO! assigned_to změněno na {$milan['id']} (Milan)</p>";
    echo "<a href='debug_provize_radek.php'>Zkontrolovat Radka</a>";
} else {
    echo "<p>Nový assigned_to bude: <strong>{$milan['id']}</strong> (Milan)</p>";
    echo "<a href='?fix=1' style='background:#333;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;'>OPRAVIT</a>";
}
?>
