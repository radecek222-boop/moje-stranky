<?php
/**
 * DEBUG - kontrola fotek v databázi
 * BEZPEČNOST: Pouze pro přihlášené uživatele
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Kontrola přihlášení
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    http_response_code(401);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center; background: #fff;"><h1 style="color: #000; text-transform: uppercase; letter-spacing: 0.1em;">PŘÍSTUP ODEPŘEN</h1><p style="color: #555;">Musíte být přihlášeni pro zobrazení této stránky.</p><p><a href="/login" style="color: #000; border-bottom: 2px solid #000; text-decoration: none;">Přihlásit se</a></p></body></html>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Debug Fotek - WGS Service</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wgs-white: #FFFFFF;
            --wgs-black: #000000;
            --wgs-grey: #555555;
            --wgs-light-grey: #999999;
            --wgs-border: #E0E0E0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            padding: 2rem;
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--wgs-white);
            border: 2px solid var(--wgs-black);
        }

        .header {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 2rem;
            border-bottom: 2px solid var(--wgs-black);
        }

        h1 {
            color: inherit;
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0;
        }

        .content {
            padding: 2rem;
        }

        h2 {
            color: var(--wgs-black);
            margin-top: 2rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--wgs-black);
            padding-bottom: 0.75rem;
            font-size: 1.125rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        h2:first-child {
            margin-top: 0;
        }

        p {
            margin: 0.75rem 0;
            color: var(--wgs-grey);
            font-size: 0.875rem;
        }

        p strong {
            color: var(--wgs-black);
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.75rem;
            border: 2px solid var(--wgs-black);
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid var(--wgs-border);
        }

        th {
            background: var(--wgs-black);
            color: var(--wgs-white);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tr:hover {
            background: var(--wgs-border);
        }

        .ok {
            color: var(--wgs-black);
            font-weight: 600;
        }

        .error {
            color: var(--wgs-grey);
            font-weight: 600;
        }

        .warning {
            color: var(--wgs-grey);
            font-weight: 600;
        }

        .null {
            color: var(--wgs-light-grey);
            font-style: italic;
        }

        pre {
            background: var(--wgs-border);
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.75rem;
            border: 1px solid var(--wgs-black);
            margin: 1rem 0;
        }

        code {
            background: var(--wgs-border);
            padding: 0.25rem 0.5rem;
            font-family: monospace;
            color: var(--wgs-black);
            border: 1px solid var(--wgs-black);
            font-size: 0.75rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.625rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid var(--wgs-black);
        }

        .badge.success {
            background: var(--wgs-black);
            color: var(--wgs-white);
        }

        .badge.error {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        .detail-box {
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
        }

        .detail-box strong {
            color: var(--wgs-black);
            font-weight: 600;
        }

        .detail-box ul {
            margin: 1rem 0 0 1.5rem;
            color: var(--wgs-grey);
            font-size: 0.875rem;
        }

        .detail-box li {
            margin: 0.5rem 0;
        }

        .error-box {
            color: var(--wgs-black);
            padding: 1.5rem;
            background: var(--wgs-white);
            border: 2px solid var(--wgs-black);
            margin: 1.5rem 0;
        }

        .error-box h2 {
            border: none;
            margin: 0 0 1rem 0;
            padding: 0;
        }

        .footer {
            margin-top: 2rem;
            padding: 1.5rem 2rem;
            border-top: 2px solid var(--wgs-border);
            text-align: center;
            color: var(--wgs-light-grey);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DEBUG FOTEK - WGS SERVICE</h1>
        </div>

        <div class="content">
            <?php
            try {
                $pdo = getDbConnection();

                // 1. Celkový počet fotek
                echo '<h2>STATISTIKY</h2>';

                $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_photos");
                $totalPhotos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_reklamace");
                $totalClaims = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                echo "<p><strong>Celkem fotek v DB:</strong> {$totalPhotos}</p>";
                echo "<p><strong>Celkem reklamací v DB:</strong> {$totalClaims}</p>";

                // 2. Všechny fotky
                echo '<h2>VŠECHNY FOTKY V DATABÁZI</h2>';
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
                    echo '<p class="warning">[VAROVÁNÍ] Žádné fotky v databázi!</p>';
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
                echo '<h2>PROPOJENÍ REKLAMACÍ S FOTKAMI</h2>';
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
                    $statusText = $totalFound > 0 ? '[OK] MÁ FOTKY' : '[X] BEZ FOTEK';

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
                echo '<h2>DETAIL PROPOJENÍ (LOAD.PHP LOGIKA)</h2>';
                echo '<p>Toto simuluje, jak load.php načítá fotky.</p>';

                foreach ($claims as $c) {
                    $reklamaceId = $c['reklamace_id'] ?? $c['cislo'] ?? $c['id'];

                    echo '<div class="detail-box">';
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
                        echo '<p class="error">[X] NENALEZENY ŽÁDNÉ FOTKY!</p>';

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
                                $icon = $cnt > 0 ? '[OK]' : '[X]';
                                echo "<code>{$icon} WHERE reklamace_id = '{$value}': {$cnt} fotek</code><br>";
                            }
                        }
                    } else {
                        echo '<p class="ok">[OK] Nalezeno fotek: ' . count($foundPhotos) . '</p>';
                        echo '<ul>';
                        foreach ($foundPhotos as $fp) {
                            echo '<li>' . htmlspecialchars($fp['file_name'] ?? $fp['photo_path'] ?? 'unknown') . '</li>';
                        }
                        echo '</ul>';
                    }

                    echo '</div>';
                }

            } catch (Exception $e) {
                echo '<div class="error-box">';
                echo '<h2>CHYBA</h2>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                echo '</div>';
            }
            ?>
        </div>

        <div class="footer">
            <small>WGS SERVICE DEBUG © 2025</small>
        </div>
    </div>
</body>
</html>
