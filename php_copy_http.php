<?php
$url = 'https://wgs-service.cz/uploads/photos/IMG-20251020-WA0005.jpg';
$dst = '/home/www/wgs-service.cz/www/uploads/photos/IMG-20251020-WA0005.jpg';

$data = file_get_contents($url);
if ($data === false) {
    die("❌ Nelze načíst $url\n");
}

if (!is_dir(dirname($dst))) {
    mkdir(dirname($dst), 0775, true);
}

if (file_put_contents($dst, $data)) {
    echo "✅ Soubor stažen a uložen do $dst\n";
} else {
    echo "❌ Nepodařilo se zapsat $dst\n";
}
