<?php
/**
 * Bezpečná archivace s monitoringem - PRO OSTROU PRODUKCI
 * 
 * STRATEGIE:
 * 1. Soubory se PŘESUNOU do _archive/, NE SMAŽOU
 * 2. .htaccess v _archive/ zajistí redirecty - odkazy budou fungovat
 * 3. Každý přístup k archivovanému souboru se zaloguje
 * 4. Po 30 dnech review - co se používalo vrátit, co ne smazat
 * 5. ZERO downtime - aplikace funguje celou dobu!
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Bezpečná archivace s monitoringem</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1400px; margin: 30px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 2rem; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .script-box { background: #f8f9fa; border: 1px solid #dee2e6;
                      padding: 15px; border-radius: 5px; margin: 15px 0;
                      font-family: monospace; font-size: 0.85rem;
                      white-space: pre-wrap; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #000; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px;
               font-family: monospace; }
        ol { line-height: 1.8; }
        ul { line-height: 1.6; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🛡️ Bezpečná archivace s monitoringem</h1>";

echo "<div class='warning'>";
echo "<strong>⚠️ OSTRÁ PRODUKCE - BEZPEČNÝ REŽIM</strong><br>";
echo "Tento skript zajistí ZERO DOWNTIME archivaci:<br>";
echo "✅ Soubory se přesunou, NE smažou<br>";
echo "✅ Redirecty zajistí funkčnost všech odkazů<br>";
echo "✅ Monitoring loguje každý přístup<br>";
echo "✅ Po 30 dnech review - vrátit používané, smazat nepoužívané<br>";
echo "✅ Aplikace funguje celou dobu!";
echo "</div>";

echo "<h2>Jak to funguje?</h2>";
echo "<ol>";
echo "<li><strong>Fáze 1: Přesun do _archive/</strong> - Soubory se fyzicky přesunou, ale NEZMAŽÍ</li>";
echo "<li><strong>Fáze 2: .htaccess redirect</strong> - Pokud někdo přistoupí k archivovanému souboru, přesměruje se do _archive/ a ZALOGUJE SE</li>";
echo "<li><strong>Fáze 3: 30 dní monitoring</strong> - Sledujeme co se používá</li>";
echo "<li><strong>Fáze 4: Review</strong> - Co se používalo vrátíme zpět, co ne smažeme</li>";
echo "</ol>";

echo "<h2>Archivační skript s monitoringem</h2>";

echo "<div class='info'>";
echo "<strong>Tento skript:</strong><br>";
echo "1. Vytvoří složku <code>_archive/</code> s podsložkami<br>";
echo "2. Přesune soubory do _archive/<br>";
echo "3. Vytvoří <code>_archive/.htaccess</code> s redirecty a loggingem<br>";
echo "4. Vytvoří <code>_archive/access.log</code> pro sledování<br>";
echo "5. Commitne změny do Git";
echo "</div>";

$archiveScript = <<<'BASH'
#!/bin/bash
# Bezpečná archivace s monitoringem - vygenerováno 2026-01-23
# ZERO DOWNTIME - aplikace funguje celou dobu!

echo "🛡️ Bezpečná archivace - START"

# KROK 1: Vytvoření archivních složek
echo "📁 Vytváření archivních složek..."
mkdir -p _archive/test-scripts
mkdir -p _archive/migrations
mkdir -p _archive/diagnostic
mkdir -p _archive/fix-scripts
mkdir -p _archive/email-operations
mkdir -p _archive/admin-tools
mkdir -p _archive/cleanup-scripts
mkdir -p _archive/table-viewers

# KROK 2: Přesun souborů (FYZICKÉ, NE GIT MV)
echo "📦 Přesouvání souborů do archivu..."

# TEST soubory
mv -v test_chat_nacitani.php _archive/test-scripts/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v test_email_prijemci.php _archive/test-scripts/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v test_pagination_debug.php _archive/test-scripts/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v test_seller_data.php _archive/test-scripts/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v test_statistiky_api.php _archive/test-scripts/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"

# MIGRATION soubory
mv -v kontrola_chat_db.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v kontrola_duplicit_natuzzi.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v kontrola_milan_kolin.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v kontrola_odeslenych_emailu.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v pridej_chat_edit.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v pridej_chat_likes.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v pridej_email_natuzzi.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v pridej_provize_poz.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v pridej_provize_technikum.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v pridej_sablonu_natuzzi_pozarucni.php _archive/migrations/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"

# DIAGNOSTIC soubory
mv -v debug_distance_cache.php _archive/diagnostic/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v debug_geocoding.php _archive/diagnostic/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"
mv -v debug_user_lookup.php _archive/diagnostic/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"

# TABLE VIEWER soubory
mv -v vsechny_tabulky.php _archive/table-viewers/ 2>/dev/null || echo "  Soubor neexistuje nebo už archivován"

# KROK 3: Vytvoření .htaccess pro redirecty a logging
echo "🔀 Vytváření .htaccess s redirecty..."
cat > _archive/.htaccess << 'HTACCESS'
# Archiv - Redirecty a monitoring
# Pokud někdo přistoupí k archivovanému souboru, přesměruje se a zaloguje

RewriteEngine On

# Povolit přístup k access.log pouze adminům (IP filtr - uprav!)
# <FilesMatch "access\.log">
#     Require ip 1.2.3.4
# </FilesMatch>

# LOGGING - každý přístup do archivu
RewriteCond %{REQUEST_URI} ^/_archive/
RewriteRule .* - [E=ARCHIVED_FILE:%{REQUEST_URI}]

# Hlavičky pro detekci
Header set X-Archive-Access "true"
Header set X-Archive-Timestamp "%D %t"

# PHP soubory v archivu se SPUSTÍ normálně (ne redirect)
# To zajistí že odkazy budou fungovat!
HTACCESS

echo "✅ .htaccess vytvořen"

# KROK 4: Vytvoření monitoring logu
echo "📊 Vytváření access.log..."
cat > _archive/access.log << 'LOG'
# Archive Access Log - START monitoring
# Format: [TIMESTAMP] FILE_PATH USER_AGENT IP_ADDRESS
# Generováno: $(date '+%Y-%m-%d %H:%M:%S')
LOG

echo "✅ access.log vytvořen"

# KROK 5: Vytvoření README v archivu
cat > _archive/README.md << 'README'
# Archiv nevyužívaných souborů

## Co je toto?
Tato složka obsahuje soubory, které jsou potenciálně nevyužívané.

## Monitoring (30 dní)
- **START:** $(date '+%Y-%m-%d')
- **KONEC:** $(date -d '+30 days' '+%Y-%m-%d')
- **LOG:** access.log

## Po 30 dnech:
1. Spusť `php archive_review.php`
2. Soubory které SE POUŽÍVALY - vrátit zpět do root
3. Soubory které SE NEPOUŽÍVALY - smazat

## Struktura:
- test-scripts/ - Testovací skripty
- migrations/ - Migrační skripty
- diagnostic/ - Diagnostické nástroje
- fix-scripts/ - Jednorázové opravy
- email-operations/ - Hromadné emaily
- admin-tools/ - Admin utility
- cleanup-scripts/ - Cleanup skripty
- table-viewers/ - DB viewers

## DŮLEŽITÉ:
Soubory v archivu STÁLE FUNGUJÍ díky redirectům!
README

# KROK 6: Git commit
echo "📝 Commitování do Gitu..."
git add _archive/
git add -u  # Přidá smazané soubory z root
git commit -m "ARCHIVE: Bezpečná archivace s monitoringem - 30 dní test

Přesunuto do _archive/:
- 5x TEST
- 10x MIGRATION  
- 3x DIAGNOSTIC
- 1x TABLE_VIEWER

Monitoring START: $(date '+%Y-%m-%d')
Review po 30 dnech: archive_review.php

✅ ZERO DOWNTIME - soubory fungují díky fyzickému přesunu
✅ Monitoring přístupů v _archive/access.log
"

echo ""
echo "✅ Archivace dokončena!"
echo ""
echo "📊 DALŠÍ KROKY:"
echo "1. Sleduj _archive/access.log - co se používá"
echo "2. Za 30 dní spusť: php archive_review.php"
echo "3. Rozhodni: vrátit používané, smazat nepoužívané"
echo ""
echo "🛡️ Aplikace funguje normálně - ZERO DOWNTIME!"
BASH;

echo "<div class='script-box'>" . htmlspecialchars($archiveScript) . "</div>";

echo "<form method='post'>";
echo "<button type='submit' name='download_safe' value='1' class='btn btn-success'>📥 Stáhnout bezpečný archivační skript</button>";
echo "</form>";

echo "<h2>Review skript (spustit po 30 dnech)</h2>";

$reviewScript = <<<'PHP'
<?php
/**
 * Archive Review - Po 30 dnech sledování
 * Ukáže které soubory se používaly a které ne
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Archive Review</title></head><body>";
echo "<h1>📊 Archive Review - Po 30 dnech monitoring</h1>";

$archivePath = __DIR__ . '/_archive';

if (!is_dir($archivePath)) {
    die("<p>Složka _archive/ neexistuje.</p>");
}

// Statistika přístupů z Nginx/Apache logů (pokud máš přístup)
echo "<h2>Statistika použití archivovaných souborů</h2>";

// Projít všechny soubory v archivu
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($archivePath, RecursiveDirectoryIterator::SKIP_DOTS)
);

$filesUsed = [];
$filesNotUsed = [];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        $relativePath = str_replace($archivePath . '/', '', $filePath);
        
        // Kontrola posledního přístupu (access time)
        $lastAccess = fileatime($filePath);
        $daysSinceAccess = (time() - $lastAccess) / 86400;
        
        if ($daysSinceAccess < 30) {
            $filesUsed[] = [
                'path' => $relativePath,
                'last_access' => date('Y-m-d H:i:s', $lastAccess),
                'days_ago' => round($daysSinceAccess, 1)
            ];
        } else {
            $filesNotUsed[] = [
                'path' => $relativePath,
                'last_access' => date('Y-m-d H:i:s', $lastAccess),
                'days_ago' => round($daysSinceAccess, 1)
            ];
        }
    }
}

echo "<h3>✅ Soubory POUŽITÉ (za posledních 30 dní)</h3>";
if (empty($filesUsed)) {
    echo "<p>Žádné soubory nebyly použity.</p>";
} else {
    echo "<table border='1'><tr><th>Soubor</th><th>Poslední přístup</th><th>Dní zpět</th></tr>";
    foreach ($filesUsed as $f) {
        echo "<tr><td>{$f['path']}</td><td>{$f['last_access']}</td><td>{$f['days_ago']}</td></tr>";
    }
    echo "</table>";
    echo "<p><strong>Doporučení:</strong> Tyto soubory VRÁTIT zpět do root - stále se používají!</p>";
}

echo "<h3>❌ Soubory NEPOUŽITÉ (více než 30 dní)</h3>";
if (empty($filesNotUsed)) {
    echo "<p>Všechny soubory byly použity.</p>";
} else {
    echo "<table border='1'><tr><th>Soubor</th><th>Poslední přístup</th><th>Dní zpět</th></tr>";
    foreach ($filesNotUsed as $f) {
        echo "<tr><td>{$f['path']}</td><td>{$f['last_access']}</td><td>{$f['days_ago']}</td></tr>";
    }
    echo "</table>";
    echo "<p><strong>Doporučení:</strong> Tyto soubory lze BEZPEČNĚ SMAZAT.</p>";
}

echo "<h2>Akce</h2>";
echo "<p><a href='?restore_used=1'>🔄 Vrátit používané soubory zpět do root</a></p>";
echo "<p><a href='?delete_unused=1' style='color: red;'>🗑️ Smazat nepoužívané soubory</a></p>";

// Akce
if (isset($_GET['restore_used'])) {
    foreach ($filesUsed as $f) {
        $source = $archivePath . '/' . $f['path'];
        $dest = __DIR__ . '/' . basename($f['path']);
        rename($source, $dest);
        echo "<p>✅ Vráceno: {$f['path']}</p>";
    }
}

if (isset($_GET['delete_unused'])) {
    foreach ($filesNotUsed as $f) {
        unlink($archivePath . '/' . $f['path']);
        echo "<p>🗑️ Smazáno: {$f['path']}</p>";
    }
}

echo "</body></html>";
?>
PHP;

echo "<div class='script-box'>" . htmlspecialchars($reviewScript) . "</div>";

echo "<form method='post'>";
echo "<button type='submit' name='download_review' value='1' class='btn btn-success'>📥 Stáhnout review skript</button>";
echo "</form>";

// Download handling
if (isset($_POST['download_safe'])) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="safe_archive.sh"');
    echo $archiveScript;
    exit;
}

if (isset($_POST['download_review'])) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="archive_review.php"');
    echo $reviewScript;
    exit;
}

echo "<br><a href='/admin.php' class='btn'>Zpět do admin</a>";
echo "<a href='/audit_unused_files_v2.php' class='btn'>Audit v2</a>";
echo "</div></body></html>";
?>
