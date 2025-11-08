<?php
require __DIR__ . '/init.php';
$pdo = getDbConnection();

$pdo->query("
UPDATE wgs_photos
SET file_path = '/wgs-service.cz/www/uploads/photos/IMG-20251020-WA0005.jpg',
    photo_path = 'uploads/photos/IMG-20251020-WA0005.jpg'
WHERE reklamace_id = 2
");
echo "✅ Cesta k fotce opravena na IMG-20251020-WA0005.jpg\n";
