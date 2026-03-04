<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Přístup odepřen");
}

$pdo = getDbConnection();

echo "<pre>";
echo "<h2>Kontrola zakázky NCE25-00002370-34</h2>\n\n";

// Zjistit ID zakázky
$stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE cislo = :cislo");
$stmt->execute(['cislo' => 'NCE25-00002370-34']);
$zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$zakazka) {
    die("Zakázka nenalezena!");
}

$id = $zakazka['id'];
echo "ID zakázky: $id\n\n";

// Kompletní výpis všech relevantních sloupců
$stmt = $pdo->prepare("
    SELECT
        id,
        cislo,
        assigned_to,
        dokonceno_kym,
        technik,
        stav,
        created_by
    FROM wgs_reklamace
    WHERE id = :id
");
$stmt->execute(['id' => $id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Data zakázky:\n";
print_r($data);
echo "\n";

// Najít technika podle assigned_to
if ($data['assigned_to']) {
    $stmt = $pdo->prepare("SELECT id, user_id, name, role FROM wgs_users WHERE id = :id");
    $stmt->execute(['id' => $data['assigned_to']]);
    $tech1 = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Technik podle assigned_to ({$data['assigned_to']}):\n";
    print_r($tech1);
    echo "\n";
}

// Najít technika podle dokonceno_kym
if ($data['dokonceno_kym']) {
    $stmt = $pdo->prepare("SELECT id, user_id, name, role FROM wgs_users WHERE id = :id");
    $stmt->execute(['id' => $data['dokonceno_kym']]);
    $tech2 = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Technik podle dokonceno_kym ({$data['dokonceno_kym']}):\n";
    print_r($tech2);
    echo "\n";
}

echo "\n=== ZÁVĚR ===\n";
if ($data['dokonceno_kym']) {
    echo "⚠️ Zakázka má nastaveno dokonceno_kym = {$data['dokonceno_kym']}\n";
    echo "Tabulka zobrazuje technika podle dokonceno_kym (priorita), ne assigned_to!\n\n";
    echo "ŘEŠENÍ: Změňte dokonceno_kym na správné ID technika, nebo nastavte na NULL.\n";
} else {
    echo "✅ dokonceno_kym je NULL, používá se assigned_to\n";
}

echo "</pre>";
?>
