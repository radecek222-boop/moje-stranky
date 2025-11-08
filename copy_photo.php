<?php
$src = '/wgs-service.cz/www/uploads/photos/IMG-20251020-WA0005.jpg';
$dst = '/home/www/wgs-service.cz/www/uploads/photos/IMG-20251020-WA0005.jpg';

@mkdir('/home/www/wgs-service.cz/www/uploads/photos/', 0775, true);

if (copy($src, $dst)) {
    echo "✅ Soubor úspěšně zkopírován do open_basedir cesty\n";
} else {
    echo "❌ Kopírování selhalo\n";
    var_dump(error_get_last());
}
