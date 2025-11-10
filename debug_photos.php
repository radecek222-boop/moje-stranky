<?php
/**
 * DEBUG - kontrola fotek v databázi
 * BEZPEČNOST: Pouze pro přihlášené uživatele
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Kontrola přihlášení
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    http_response_code(401);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Arial; padding: 40px; text-align: center;"><h1>🔒 Přístup odepřen</h1><p>Musíte být přihlášeni pro zobrazení této stránky.</p><p><a href="/login" style="color: #2196F3;">Přihlásit se</a></p></body></html>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Debug Fotek - WGS Service</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:hover { background: #f9f9f9; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .null { color: #999; font-style: italic; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; }
        .badge.success { background: #4CAF50; color: white; }
        .badge.error { background: #f44336; color: white; }
        .badge.warning { background: #ff9800; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Fotek - WGS Service</h1>

        <?php
        try {
            $pdo = getDbConnection();

            // 1. Celkový počet fotek
            echo '<h2>📊 Statistiky</h2>';

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_photos");
            $totalPhotos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_reklamace");
            $totalClaims = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            echo "<p><strong>Celkem fotek v DB:</strong> {$totalPhotos}</p>";
            echo "<p><strong>Celkem reklamací v DB:</strong> {$totalClaims}</p>";

            // 2. Všechny fotky
            echo '<h2>📸 Všechny fotky v databázi</h2>';
            $stmt = $pdo->query("
                SELECT
                    id,
                    photo_id,
                    reklamace_id,
                    section_name,
                    photo_path,
                    file_path,
                    file_name,
                    photo_type,
                    created_at,
                    uploaded_at
                FROM wgs_photos
                ORDER BY created_at DESC, uploaded_at DESC
            ");
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($photos)) {
                echo '<p class="warning">⚠️ Žádné fotky v databázi!</p>';
            } else {
                echo '<table>';
                echo '<thead><tr>';
                echo '<th>ID</th><th>Photo ID</th><th>Reklamace ID</th><th>Section</th>';
                echo '<th>Photo Path</th><th>File Name</th><th>Type</th><th>Uploaded</th>';
                echo '</tr></thead><tbody>';

                foreach ($photos as $p) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($p['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($p['photo_id'] ?? '-') . '</td>';
                    echo '<td><strong>' . htmlspecialchars($p['reklamace_id'] ?? 'NULL') . '</strong></td>';
                    echo '<td>' . htmlspecialchars($p['section_name'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($p['photo_path'] ?? $p['file_path'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($p['file_name'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($p['photo_type'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($p['uploaded_at'] ?? $p['created_at'] ?? '-') . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }

            // 3. Reklamace a jejich fotky
            echo '<h2>🔗 Propojení reklamací s fotkami</h2>';
            $stmt = $pdo->query("
                SELECT
                    r.id,
                    r.reklamace_id,
                    r.cislo,
                    r.jmeno,
                    r.email,
                    r.created_at,
                    (SELECT COUNT(*) FROM wgs_photos WHERE reklamace_id = r.reklamace_id) as photo_count_by_reklamace_id,
                    (SELECT COUNT(*) FROM wgs_photos WHERE reklamace_id = r.cislo) as photo_count_by_cislo,
                    (SELECT COUNT(*) FROM wgs_photos WHERE reklamace_id = r.id) as photo_count_by_id
                FROM wgs_reklamace r
                ORDER BY r.created_at DESC
            ");
            $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo '<table>';
            echo '<thead><tr>';
            echo '<th>ID</th><th>Reklamace ID</th><th>Číslo</th><th>Jméno</th><th>Email</th>';
            echo '<th>Fotek (by reklamace_id)</th><th>Fotek (by cislo)</th><th>Fotek (by id)</th><th>Status</th>';
            echo '</tr></thead><tbody>';

            foreach ($claims as $c) {
                $totalFound = max($c['photo_count_by_reklamace_id'], $c['photo_count_by_cislo'], $c['photo_count_by_id']);
                $statusClass = $totalFound > 0 ? 'success' : 'error';
                $statusText = $totalFound > 0 ? '✅ Má fotky' : '❌ Bez fotek';

                echo '<tr>';
                echo '<td>' . htmlspecialchars($c['id']) . '</td>';
                echo '<td><strong>' . htmlspecialchars($c['reklamace_id'] ?? 'NULL') . '</strong></td>';
                echo '<td>' . htmlspecialchars($c['cislo'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($c['jmeno']) . '</td>';
                echo '<td>' . htmlspecialchars($c['email'] ?? '-') . '</td>';
                echo '<td>' . $c['photo_count_by_reklamace_id'] . '</td>';
                echo '<td>' . $c['photo_count_by_cislo'] . '</td>';
                echo '<td>' . $c['photo_count_by_id'] . '</td>';
                echo '<td><span class="badge ' . $statusClass . '">' . $statusText . '</span></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            // 4. Detail propojení pro každou reklamaci
            echo '<h2>🔍 Detail propojení (load.php logika)</h2>';
            echo '<p>Toto simuluje, jak load.php načítá fotky.</p>';

            foreach ($claims as $c) {
                $reklamaceId = $c['reklamace_id'] ?? $c['cislo'] ?? $c['id'];

                echo '<div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #2196F3;">';
                echo '<strong>Reklamace #' . $c['id'] . ' - ' . htmlspecialchars($c['jmeno']) . '</strong><br>';
                echo '<code>reklamace_id=' . htmlspecialchars($c['reklamace_id'] ?? 'NULL') . '</code> | ';
                echo '<code>cislo=' . htmlspecialchars($c['cislo'] ?? 'NULL') . '</code> | ';
                echo '<code>id=' . htmlspecialchars($c['id']) . '</code><br>';
                echo '<strong>load.php použije:</strong> <code>$reklamaceId = ' . htmlspecialchars($reklamaceId) . '</code>';

                // Načtení fotek stejně jako load.php
                $stmt = $pdo->prepare("
                    SELECT
                        id, photo_id, reklamace_id, section_name,
                        photo_path, file_path, file_name,
                        photo_order, photo_type, uploaded_at
                    FROM wgs_photos
                    WHERE reklamace_id = :reklamace_id
                    ORDER BY photo_order ASC, uploaded_at ASC
                ");
                $stmt->execute([':reklamace_id' => $reklamaceId]);
                $foundPhotos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($foundPhotos)) {
                    echo '<p class="error">❌ NENALEZENY ŽÁDNÉ FOTKY!</p>';

                    // Debug - zkusme najít fotky jiným způsobem
                    echo '<p><strong>Debug - hledám fotky všemi způsoby:</strong></p>';
                    $debugMethods = [
                        'reklamace_id' => $c['reklamace_id'],
                        'cislo' => $c['cislo'],
                        'id' => $c['id']
                    ];

                    foreach ($debugMethods as $method => $value) {
                        if ($value) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM wgs_photos WHERE reklamace_id = :val");
                            $stmt->execute([':val' => $value]);
                            $cnt = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
                            $icon = $cnt > 0 ? '✅' : '❌';
                            echo "<code>{$icon} WHERE reklamace_id = '{$value}': {$cnt} fotek</code><br>";
                        }
                    }
                } else {
                    echo '<p class="ok">✅ Nalezeno fotek: ' . count($foundPhotos) . '</p>';
                    echo '<ul>';
                    foreach ($foundPhotos as $fp) {
                        echo '<li>' . htmlspecialchars($fp['file_name'] ?? $fp['photo_path'] ?? 'unknown') . '</li>';
                    }
                    echo '</ul>';
                }

                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<div style="color: red; padding: 20px; background: #ffebee; border-radius: 4px;">';
            echo '<h2>❌ CHYBA</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        ?>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999;">
            <small>WGS Service Debug © 2025</small>
        </div>
    </div>
</body>
</html>
