<?php
/**
 * Video Download API - Stahovani videi pomoci tokenu
 *
 * Endpointy:
 * - GET ?token=XXX - Zobrazit stranku se seznamem videi
 * - GET ?token=XXX&video_id=123 - Stahnout konkretni video
 * - GET ?token=XXX&video_id=123&stream=1 - Streamovat video pro prehravani
 * - GET ?token=XXX&action=zip - Stahnout vsechna videa jako ZIP
 */

require_once __DIR__ . '/../init.php';

// Ziskat token
$token = $_GET['token'] ?? null;
$videoId = $_GET['video_id'] ?? null;
$action = $_GET['action'] ?? null;
$stream = isset($_GET['stream']);

if (!$token) {
    zobrazChybu('Chybejici token', 'Pro pristup k videodokumentaci je potreba platny odkaz.');
    exit;
}

try {
    $pdo = getDbConnection();

    // Overit token
    $stmt = $pdo->prepare("
        SELECT t.*, r.jmeno, COALESCE(r.cislo, r.reklamace_id) as cislo_reklamace
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

    // Limit stazeni odstranen - neomezene stahovani pro vsechny uzivatele

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

    // Akce: Streamovat nebo stahnout konkretni video
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

        // SECURITY: Path traversal ochrana - validace že cesta je v uploads adresáři
        $uploadsRoot = realpath(__DIR__ . '/../uploads');
        $filePath = realpath(__DIR__ . '/../' . $video['video_path']);

        // Kontrola: cesta musí existovat a musí být uvnitř uploads adresáře
        if (!$filePath || !$uploadsRoot || strpos($filePath, $uploadsRoot) !== 0) {
            zobrazChybu('Neplatná cesta', 'Přístup k souboru byl odmítnut.');
            exit;
        }

        if (!file_exists($filePath)) {
            zobrazChybu('Soubor nenalezen', 'Video soubor neni k dispozici.');
            exit;
        }

        // Streamovani pro prehravani (nepocita se do limitu stazeni)
        if ($stream) {
            $fileSize = filesize($filePath);
            $mimeType = 'video/mp4';

            // Podpora Range requests pro seekovani ve videu
            if (isset($_SERVER['HTTP_RANGE'])) {
                $range = $_SERVER['HTTP_RANGE'];
                list($unit, $range) = explode('=', $range, 2);
                list($start, $end) = explode('-', $range, 2);

                $start = intval($start);
                $end = $end === '' ? $fileSize - 1 : intval($end);

                if ($start > $end || $start >= $fileSize) {
                    header('HTTP/1.1 416 Requested Range Not Satisfiable');
                    header("Content-Range: bytes */$fileSize");
                    exit;
                }

                $length = $end - $start + 1;
                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes $start-$end/$fileSize");
                header("Content-Length: $length");
                header("Content-Type: $mimeType");
                header('Accept-Ranges: bytes');
                header('Cache-Control: public, max-age=3600');

                $fp = fopen($filePath, 'rb');
                fseek($fp, $start);
                echo fread($fp, $length);
                fclose($fp);
                exit;
            }

            // Bez Range - vratit cele video
            header("Content-Type: $mimeType");
            header("Content-Length: $fileSize");
            header('Accept-Ranges: bytes');
            header('Cache-Control: public, max-age=3600');
            readfile($filePath);
            exit;
        }

        // Stahnout soubor (bez pocitani stazeni)
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
        $uploadsRootZip = realpath(__DIR__ . '/../uploads');
        foreach ($videa as $video) {
            $filePath = realpath(__DIR__ . '/../' . $video['video_path']);
            // SECURITY: Path traversal ochrana - přidej jen soubory z uploads adresáře
            if ($filePath && $uploadsRootZip && strpos($filePath, $uploadsRootZip) === 0 && file_exists($filePath)) {
                $zip->addFile($filePath, $video['video_name']);
                $celkovaVelikost += filesize($filePath);
            }
        }
        $zip->close();

        // Stahnout ZIP (bez pocitani stazeni)
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
            .video-icon {
                font-size: 1.5rem; opacity: 0.7; cursor: pointer;
                width: 48px; height: 48px; display: flex; align-items: center;
                justify-content: center; background: #333; border-radius: 8px;
                transition: all 0.2s;
            }
            .video-icon:hover { background: #444; opacity: 1; }
            .video-info { flex: 1; min-width: 0; }
            .video-name {
                font-weight: 500; margin-bottom: 4px;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            .video-meta { font-size: 0.8rem; color: #888; }
            .video-actions { display: flex; gap: 8px; }
            .btn {
                background: #444; color: #fff; border: none; padding: 10px 20px;
                border-radius: 6px; cursor: pointer; font-size: 0.9rem;
                text-decoration: none; display: inline-block;
                transition: background 0.2s;
            }
            .btn:hover { background: #555; }
            .btn-play { background: #333; padding: 10px 16px; }
            .btn-play:hover { background: #444; }
            .btn-primary { background: #333; border: 1px solid #555; }
            .btn-primary:hover { background: #444; }

            /* Video Player Modal */
            .video-modal {
                display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.95); z-index: 1000;
                align-items: center; justify-content: center; padding: 20px;
            }
            .video-modal.active { display: flex; }
            .video-modal-content {
                max-width: 900px; width: 100%; max-height: 90vh;
                display: flex; flex-direction: column;
            }
            .video-modal-header {
                display: flex; justify-content: space-between; align-items: center;
                padding: 12px 0; color: #fff;
            }
            .video-modal-title { font-size: 1rem; opacity: 0.8; }
            .video-modal-close {
                background: none; border: none; color: #fff; font-size: 2rem;
                cursor: pointer; opacity: 0.7; line-height: 1;
            }
            .video-modal-close:hover { opacity: 1; }
            .video-player {
                width: 100%; background: #000; border-radius: 8px;
                max-height: calc(90vh - 100px);
            }

            .download-all {
                background: #2a2a2a; border-radius: 12px; padding: 24px;
                text-align: center; border: 1px solid #444;
            }
            .download-all h2 { font-size: 1rem; margin-bottom: 12px; font-weight: normal; }
            .download-all .btn { padding: 14px 28px; font-size: 1rem; }

            @media (max-width: 600px) {
                .video-item { flex-direction: column; align-items: stretch; gap: 12px; }
                .video-actions { flex-direction: column; }
                .video-actions .btn { width: 100%; text-align: center; }
                .meta { flex-direction: column; gap: 8px; }
                .info-bar { flex-direction: column; text-align: center; }
                .video-modal-content { max-height: 100vh; }
                .video-player { max-height: calc(100vh - 80px); }
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
                <span>Neomezene stahovani</span>
            </div>

            <div class="video-list">
                <?php foreach ($videa as $index => $video): ?>
                <div class="video-item">
                    <div class="video-icon" data-action="prehratVideo" data-id="<?= $video['id'] ?>" data-name="<?= htmlspecialchars($video['video_name']) ?>" title="Prehrat video">&#9658;</div>
                    <div class="video-info">
                        <div class="video-name"><?= htmlspecialchars($video['video_name']) ?></div>
                        <div class="video-meta">
                            <?= formatovatVelikost($video['file_size']) ?> |
                            <?= date('d.m.Y H:i', strtotime($video['uploaded_at'])) ?>
                        </div>
                    </div>
                    <div class="video-actions">
                        <button class="btn btn-play" data-action="prehratVideo" data-id="<?= $video['id'] ?>" data-name="<?= htmlspecialchars($video['video_name']) ?>" title="Prehrat">
                            &#9658; Prehrat
                        </button>
                        <a href="?token=<?= htmlspecialchars($token) ?>&video_id=<?= $video['id'] ?>" class="btn" data-action="stahnoutAZavrit">
                            Stahnout
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pocetVidei > 1): ?>
            <div class="download-all">
                <h2>Stahnout vsechna videa najednou</h2>
                <a href="?token=<?= htmlspecialchars($token) ?>&action=zip" class="btn btn-primary" data-action="stahnoutAZavrit">
                    Stahnout ZIP (<?= formatovatVelikost($celkovaVelikost) ?>)
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Video Player Modal -->
        <div class="video-modal" id="videoModal">
            <div class="video-modal-content">
                <div class="video-modal-header">
                    <span class="video-modal-title" id="videoModalTitle"></span>
                    <button class="video-modal-close" data-action="zavritVideo" aria-label="Zavřít video">&times;</button>
                </div>
                <video class="video-player" id="videoPlayer" controls playsinline>
                    Vas prohlizec nepodporuje prehravani videa.
                </video>
            </div>
        </div>

        <script>
            const token = '<?= htmlspecialchars($token) ?>';
            const videoModal = document.getElementById('videoModal');
            const videoPlayer = document.getElementById('videoPlayer');
            const videoModalTitle = document.getElementById('videoModalTitle');

            // Stahnout a zavrit stranku
            function stahnoutAZavrit(event, url) {
                event.preventDefault();

                // Vytvorit neviditelny iframe pro stazeni
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = url;
                document.body.appendChild(iframe);

                // Zobrazit zpravu
                document.querySelector('.container').innerHTML =
                    '<div style="text-align: center; padding: 60px 20px;">' +
                    '<div style="font-size: 3rem; margin-bottom: 20px;">&#10003;</div>' +
                    '<h1 style="margin-bottom: 16px;">Stahovani zahajeno</h1>' +
                    '<p style="color: #888; margin-bottom: 30px;">Video se stahuje. Tato stranka se za chvili zavre.</p>' +
                    '<p style="color: #666; font-size: 0.85rem;">Pokud se okno nezavrelo, muzete jej zavrit rucne.</p>' +
                    '</div>';

                // Zkusit zavrit okno po 3 sekundach
                setTimeout(function() {
                    window.close();
                    // Pokud se nezavrelo (nektere prohlizece to blokuji),
                    // presmerovat na prazdnou stranku
                    setTimeout(function() {
                        window.location.href = 'about:blank';
                    }, 500);
                }, 3000);
            }

            function prehratVideo(videoId, nazev) {
                const streamUrl = '?token=' + token + '&video_id=' + videoId + '&stream=1';
                videoPlayer.src = streamUrl;
                videoModalTitle.textContent = nazev;
                videoModal.classList.add('active');
                videoPlayer.play().catch(function(e) {
                    console.log('Autoplay blocked:', e);
                });
            }

            function zavritVideo() {
                videoPlayer.pause();
                videoPlayer.src = '';
                videoModal.classList.remove('active');
            }

            // Zavrit modal kliknutim mimo video
            videoModal.addEventListener('click', function(e) {
                if (e.target === videoModal) {
                    zavritVideo();
                }
            });

            // Zavrit modal klavesou Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && videoModal.classList.contains('active')) {
                    zavritVideo();
                }
            });

            // Event handlery pro data-action atributy
            document.addEventListener('click', function(e) {
                const target = e.target.closest('[data-action]');
                if (!target) return;

                const action = target.getAttribute('data-action');
                const videoId = target.getAttribute('data-id');
                const videoName = target.getAttribute('data-name');
                const href = target.getAttribute('href');

                switch (action) {
                    case 'prehratVideo':
                        e.preventDefault();
                        prehratVideo(videoId, videoName);
                        break;
                    case 'zavritVideo':
                        e.preventDefault();
                        zavritVideo();
                        break;
                    case 'stahnoutAZavrit':
                        stahnoutAZavrit(e, href);
                        break;
                }
            });
        </script>
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
