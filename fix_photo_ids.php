<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();

echo "=== OPRAVA REKLAMACE_ID U FOTEK ===\n\n";

// Najdi všechny reklamace s jejich ID
$reklamace = $db->query("SELECT id, reklamace_id FROM wgs_reklamace")->fetchAll(PDO::FETCH_ASSOC);

echo "Mapování:\n";
foreach ($reklamace as $r) {
    echo "  id={$r['id']} -> reklamace_id={$r['reklamace_id']}\n";
}

// Updatuj fotky kde reklamace_id je číslo
$stmt = $db->query("SELECT DISTINCT reklamace_id FROM wgs_photos WHERE reklamace_id REGEXP '^[0-9]+$'");
$photo_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "\nFotky k opravě (mají číselné reklamace_id):\n";
foreach ($photo_ids as $old_id) {
    echo "  Hledám nové ID pro: $old_id\n";
    
    // Najdi odpovídající reklamaci
    $stmt2 = $db->prepare("SELECT reklamace_id FROM wgs_reklamace WHERE id = ?");
    $stmt2->execute([$old_id]);
    $new_id = $stmt2->fetchColumn();
    
    if ($new_id) {
        echo "    -> Updatuju na: $new_id\n";
        $update = $db->prepare("UPDATE wgs_photos SET reklamace_id = ? WHERE reklamace_id = ?");
        $update->execute([$new_id, $old_id]);
        echo "    ✅ Opraveno!\n";
    } else {
        echo "    ⚠️  Nenalezena reklamace s id=$old_id\n";
    }
}

echo "\n=== HOTOVO ===\n";
