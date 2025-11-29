<?php
/**
 * Video Download API - Stahovani videi pomoci tokenu
 *
 * Endpointy:
 * - GET ?token=XXX - Zobrazit stranku se seznamem videi
 * - GET ?token=XXX&video_id=123 - Stahnout konkretni video
 * - GET ?token=XXX&action=zip - Stahnout vsechna videa jako ZIP
 */

require_once __DIR__ . '/../init.php';

// Ziskat token
$token = $_GET['token'] ?? null;
$videoId = $_GET['video_id'] ?? null;
$action = $_GET['action'] ?? null;

if (!$token) {
    zobrazChybu('Chybejici token', 'Pro pristup k videodokumentaci je potreba platny odkaz.');
    exit;
}

try {
    $pdo = getDbConnection();

    // Overit token
    $stmt = $pdo->prepare("
        SELECT t.*, r.jmeno, r.reklamace_id as cislo_reklamace
        FROM wgs_video_tokens t
        JOIN wgs_reklamace r ON t.claim_id = r.id
        WHERE t.token = :token
    ");
    $stmt->execute([':token' => $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        zobrazChybu('Neplatny odkaz', 'Tento odkaz neni platny nebo byl smazan.');
        exit;
    }

    // Kontrola expirace
    if (strtotime($tokenData['expires_at']) < time()) {
        zobrazChybu('Odkaz vyprsel', 'Platnost tohoto odkazu vyprsela. Kontaktujte prosim technika pro novy odkaz.');
        exit;
    }

    // Kontrola aktivniho tokenu
    if (!$tokenData['is_active']) {
        zobrazChybu('Odkaz deaktivovan', 'Tento odkaz byl deaktivovan.');
        exit;
    }

    // Kontrola poctu stazeni
    if ($tokenData['download_count'] >= $tokenData['max_downloads']) {
        zobrazChybu('Limit stazeni', 'Byl dosazen maximalni pocet stazeni pro tento odkaz.');
        exit;
    }

    // Nacist videa pro danou zakazku
    $stmt = $pdo->prepare("
        SELECT id, video_name, video_path, file_size, uploaded_at
        FROM wgs_videos
        WHERE claim_id = :claim_id
        ORDER BY uploaded_at ASC
    ");
    $stmt->execute([':claim_id' => $tokenData['claim_id']]);
    $videa = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($videa)) {
        zobrazChybu('Zadna videa', 'Pro tuto zakazku nejsou k dispozici zadna videa.');
        exit;
    }

    // Akce: Stahnout konkretni video
    if ($videoId) {
        $video = null;
        foreach ($videa as $v) {
            if ($v['id'] == $videoId) {
                $video = $v;
                break;
            }
        }

        if (!$video) {
            zobrazChybu('Video nenalezeno', 'Pozadovane video neexistuje.');
            exit;
        }

        $filePath = __DIR__ . '/../' . $video['video_path'];
        if (!file_exists($filePath)) {
            zobrazChybu('Soubor nenalezen', 'Video soubor neni k dispozici.');
            exit;
        }

        // Inkrementovat pocet stazeni
        inkrementovatStazeni($pdo, $token);

        // Stahnout soubor
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . basename($video['video_name']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
        readfile($filePath);
        exit;
    }

    // Akce: Stahnout vse jako ZIP
    if ($action === 'zip') {
        // Kontrola ZipArchive
        if (!class_exists('ZipArchive')) {
            zobrazChybu('ZIP neni dostupny', 'Server nepodporuje vytvareni ZIP archivu.');
            exit;
        }

        $zipName = 'videodokumentace_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $tokenData['cislo_reklamace']) . '.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            zobrazChybu('Chyba ZIP', 'Nelze vytvorit ZIP archiv.');
            exit;
        }

        $celkovaVelikost = 0;
        foreach ($videa as $video) {
            $filePath = __DIR__ . '/../' . $video['video_path'];
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $video['video_name']);
                $celkovaVelikost += filesize($filePath);
            }
        }
        $zip->close();

        // Inkrementovat pocet stazeni
        inkrementovatStazeni($pdo, $token);

        // Stahnout ZIP
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-cache');
        readfile($zipPath);
        unlink($zipPath); // Smazat docasny soubor
        exit;
    }

    // Zobrazit stranku se seznamem videi
    zobrazStranku($tokenData, $videa, $token);

} catch (Exception $e) {
    error_log("Video download error: " . $e->getMessage());
    zobrazChybu('Chyba serveru', 'Doslo k chybe pri zpracovani pozadavku.');
}

/**
 * Inkrementuje pocet stazeni pro token
 */
function inkrementovatStazeni($pdo, $token) {
    $stmt = $pdo->prepare("UPDATE wgs_video_tokens SET download_count = download_count + 1 WHERE token = :token");
    $stmt->execute([':token' => $token]);
}

/**
 * Zobrazi chybovou stranku
 */
function zobrazChybu($nadpis, $zprava) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($nadpis) ?> - WGS Service</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #1a1a1a; color: #fff; min-height: 100vh;
                display: flex; align-items: center; justify-content: center;
                padding: 20px;
            }
            .container {
                background: #2a2a2a; border-radius: 12px; padding: 40px;
                max-width: 500px; text-align: center;
                border: 1px solid #444;
            }
            h1 { font-size: 1.5rem; margin-bottom: 16px; color: #fff; }
            p { color: #999; line-height: 1.6; }
            .logo { font-size: 2rem; margin-bottom: 20px; opacity: 0.5; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">WGS</div>
            <h1><?= htmlspecialchars($nadpis) ?></h1>
            <p><?= htmlspecialchars($zprava) ?></p>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Zobrazi stranku se seznamem videi
 */
function zobrazStranku($tokenData, $videa, $token) {
    $celkovaVelikost = 0;
    foreach ($videa as $v) {
        $celkovaVelikost += $v['file_size'];
    }
    $pocetVidei = count($videa);
    $expirace = date('d.m.Y', strtotime($tokenData['expires_at']));
    $zbyvajiciStazeni = $tokenData['max_downloads'] - $tokenData['download_count'];
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Videodokumentace - <?= htmlspecialchars($tokenData['cislo_reklamace']) ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #1a1a1a; color: #fff; min-height: 100vh;
                padding: 20px;
            }
            .container {
                max-width: 800px; margin: 0 auto;
            }
            .header {
                background: #2a2a2a; border-radius: 12px; padding: 30px;
                margin-bottom: 20px; border: 1px solid #444;
            }
            .logo { font-size: 1.5rem; font-weight: bold; margin-bottom: 8px; }
            .header h1 { font-size: 1.2rem; color: #ccc; font-weight: normal; margin-bottom: 16px; }
            .meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 0.85rem; color: #888; }
            .meta-item { display: flex; align-items: center; gap: 6px; }

            .info-bar {
                background: #333; border-radius: 8px; padding: 16px 20px;
                margin-bottom: 20px; display: flex; justify-content: space-between;
                flex-wrap: wrap; gap: 12px; font-size: 0.9rem;
            }
            .info-bar .warning { color: #f0ad4e; }

            .video-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px; }
            .video-item {
                background: #2a2a2a; border-radius: 8px; padding: 16px 20px;
                display: flex; align-items: center; gap: 16px;
                border: 1px solid #444;
            }
            .video-icon { font-size: 1.5rem; opacity: 0.5; }
            .video-info { flex: 1; min-width: 0; }
            .video-name {
                font-weight: 500; margin-bottom: 4px;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            .video-meta { font-size: 0.8rem; color: #888; }
            .btn {
                background: #444; color: #fff; border: none; padding: 10px 20px;
                border-radius: 6px; cursor: pointer; font-size: 0.9rem;
                text-decoration: none; display: inline-block;
                transition: background 0.2s;
            }
            .btn:hover { background: #555; }
            .btn-primary { background: #333; border: 1px solid #555; }
            .btn-primary:hover { background: #444; }

            .download-all {
                background: #2a2a2a; border-radius: 12px; padding: 24px;
                text-align: center; border: 1px solid #444;
            }
            .download-all h2 { font-size: 1rem; margin-bottom: 12px; font-weight: normal; }
            .download-all .btn { padding: 14px 28px; font-size: 1rem; }

            @media (max-width: 600px) {
                .video-item { flex-direction: column; align-items: stretch; gap: 12px; }
                .video-item .btn { width: 100%; text-align: center; }
                .meta { flex-direction: column; gap: 8px; }
                .info-bar { flex-direction: column; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">WGS Service</div>
                <h1>Videodokumentace zakazky <?= htmlspecialchars($tokenData['cislo_reklamace']) ?></h1>
                <div class="meta">
                    <div class="meta-item">Zakaznik: <?= htmlspecialchars($tokenData['jmeno']) ?></div>
                    <div class="meta-item">Pocet videi: <?= $pocetVidei ?></div>
                    <div class="meta-item">Celkova velikost: <?= formatovatVelikost($celkovaVelikost) ?></div>
                </div>
            </div>

            <div class="info-bar">
                <span>Platnost odkazu: do <?= $expirace ?></span>
                <span class="warning">Zbyvajici stazeni: <?= $zbyvajiciStazeni ?></span>
            </div>

            <div class="video-list">
                <?php foreach ($videa as $index => $video): ?>
                <div class="video-item">
                    <div class="video-icon">&#9658;</div>
                    <div class="video-info">
                        <div class="video-name"><?= htmlspecialchars($video['video_name']) ?></div>
                        <div class="video-meta">
                            <?= formatovatVelikost($video['file_size']) ?> |
                            <?= date('d.m.Y H:i', strtotime($video['uploaded_at'])) ?>
                        </div>
                    </div>
                    <a href="?token=<?= htmlspecialchars($token) ?>&video_id=<?= $video['id'] ?>" class="btn">
                        Stahnout
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pocetVidei > 1): ?>
            <div class="download-all">
                <h2>Stahnout vsechna videa najednou</h2>
                <a href="?token=<?= htmlspecialchars($token) ?>&action=zip" class="btn btn-primary">
                    Stahnout ZIP (<?= formatovatVelikost($celkovaVelikost) ?>)
                </a>
            </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Formatuje velikost souboru
 */
function formatovatVelikost($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}
?>
