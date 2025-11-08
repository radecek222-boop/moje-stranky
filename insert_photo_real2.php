<?php
require __DIR__ . '/init.php';
$pdo = getDbConnection();

$pdo->query("
INSERT INTO wgs_photos (reklamace_id, section_name, photo_path, photo_type, created_at)
VALUES (2, 'problem', 'uploads/test.jpg', 'image', NOW())
");
echo "✅ Testovací fotka vložena pro reklamaci 2\n";
