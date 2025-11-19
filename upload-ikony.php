<?php
/**
 * Jednoduchý nástroj pro upload PWA ikon
 * BEZPEČNOST: Po použití tento soubor SMAŽTE!
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může uploadovat ikony.");
}

$uploadStatus = [];

// Zpracování uploadu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['icon192']) && isset($_FILES['icon512'])) {

    // icon192.png
    if ($_FILES['icon192']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['icon192']['tmp_name'];
        $targetPath = __DIR__ . '/icon192.png';

        // Ověření, že je to PNG
        $imageInfo = getimagesize($tmpName);
        if ($imageInfo && $imageInfo['mime'] === 'image/png' && $imageInfo[0] === 192 && $imageInfo[1] === 192) {
            if (move_uploaded_file($tmpName, $targetPath)) {
                chmod($targetPath, 0644);
                $uploadStatus['icon192'] = [
                    'success' => true,
                    'message' => 'icon192.png úspěšně nahrán',
                    'size' => filesize($targetPath),
                    'md5' => md5_file($targetPath)
                ];
            } else {
                $uploadStatus['icon192'] = ['success' => false, 'message' => 'Nepodařilo se přesunout soubor'];
            }
        } else {
            $uploadStatus['icon192'] = ['success' => false, 'message' => 'Neplatný soubor - musí být PNG 192x192'];
        }
    } else {
        $uploadStatus['icon192'] = ['success' => false, 'message' => 'Chyba uploadu: ' . $_FILES['icon192']['error']];
    }

    // icon512.png
    if ($_FILES['icon512']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['icon512']['tmp_name'];
        $targetPath = __DIR__ . '/icon512.png';

        // Ověření, že je to PNG
        $imageInfo = getimagesize($tmpName);
        if ($imageInfo && $imageInfo['mime'] === 'image/png' && $imageInfo[0] === 512 && $imageInfo[1] === 512) {
            if (move_uploaded_file($tmpName, $targetPath)) {
                chmod($targetPath, 0644);
                $uploadStatus['icon512'] = [
                    'success' => true,
                    'message' => 'icon512.png úspěšně nahrán',
                    'size' => filesize($targetPath),
                    'md5' => md5_file($targetPath)
                ];
            } else {
                $uploadStatus['icon512'] = ['success' => false, 'message' => 'Nepodařilo se přesunout soubor'];
            }
        } else {
            $uploadStatus['icon512'] = ['success' => false, 'message' => 'Neplatný soubor - musí být PNG 512x512'];
        }
    } else {
        $uploadStatus['icon512'] = ['success' => false, 'message' => 'Chyba uploadu: ' . $_FILES['icon512']['error']];
    }
}

// Aktuální ikony info
$current192 = file_exists(__DIR__ . '/icon192.png') ? [
    'exists' => true,
    'size' => filesize(__DIR__ . '/icon192.png'),
    'md5' => md5_file(__DIR__ . '/icon192.png'),
    'modified' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/icon192.png'))
] : ['exists' => false];

$current512 = file_exists(__DIR__ . '/icon512.png') ? [
    'exists' => true,
    'size' => filesize(__DIR__ . '/icon512.png'),
    'md5' => md5_file(__DIR__ . '/icon512.png'),
    'modified' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/icon512.png'))
] : ['exists' => false];

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload PWA Ikon</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1a1a1a;
            color: #ffffff;
            padding: 2rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #2d2d2d;
            padding: 2rem;
            border-radius: 10px;
        }
        h1 { color: #00FF88; margin-bottom: 1rem; }
        .warning {
            background: #ff6b6b;
            color: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        .info {
            background: #4ecdc4;
            color: #1a1a1a;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        .current-icons {
            background: #3d3d3d;
            padding: 1.5rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        .icon-info {
            margin-bottom: 1rem;
            padding: 1rem;
            background: #4d4d4d;
            border-radius: 5px;
        }
        .icon-info h3 { color: #00FF88; margin-bottom: 0.5rem; }
        .icon-info p { margin: 0.25rem 0; font-size: 0.9rem; }
        .upload-form {
            background: #3d3d3d;
            padding: 2rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #00FF88;
        }
        input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            background: #2d2d2d;
            border: 2px solid #4d4d4d;
            border-radius: 5px;
            color: white;
        }
        button {
            background: #00FF88;
            color: #1a1a1a;
            padding: 1rem 2rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
        }
        button:hover { background: #00CC6A; }
        .success {
            background: #51cf66;
            color: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .error {
            background: #ff6b6b;
            color: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        code {
            background: #1a1a1a;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📤 Upload PWA Ikon</h1>

        <div class="warning">
            <strong>⚠️ BEZPEČNOST:</strong> Po uploadu ikon tento soubor <strong>SMAŽTE</strong>!<br>
            Soubor k odstranění: <code>upload-ikony.php</code>
        </div>

        <?php if (!empty($uploadStatus)): ?>
            <?php foreach ($uploadStatus as $icon => $status): ?>
                <div class="<?= $status['success'] ? 'success' : 'error' ?>">
                    <strong><?= $icon ?>:</strong> <?= $status['message'] ?><br>
                    <?php if ($status['success']): ?>
                        Velikost: <?= number_format($status['size']) ?> bytes<br>
                        MD5: <code><?= $status['md5'] ?></code>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($uploadStatus['icon192']['success'] && $uploadStatus['icon512']['success']): ?>
                <div class="info">
                    <strong>✅ Ikony úspěšně nahrány!</strong><br>
                    Nyní můžete:
                    <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                        <li>Commitnout změny do Gitu</li>
                        <li>SMAZAT tento soubor (<code>upload-ikony.php</code>)</li>
                        <li>Otestovat PWA na mobilu</li>
                    </ol>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="current-icons">
            <h2 style="margin-bottom: 1rem;">📊 Aktuální ikony na serveru</h2>

            <div class="icon-info">
                <h3>icon192.png</h3>
                <?php if ($current192['exists']): ?>
                    <p>✅ Existuje</p>
                    <p>Velikost: <?= number_format($current192['size']) ?> bytes</p>
                    <p>MD5: <code><?= $current192['md5'] ?></code></p>
                    <p>Naposledy změněno: <?= $current192['modified'] ?></p>
                <?php else: ?>
                    <p>❌ Neexistuje</p>
                <?php endif; ?>
            </div>

            <div class="icon-info">
                <h3>icon512.png</h3>
                <?php if ($current512['exists']): ?>
                    <p>✅ Existuje</p>
                    <p>Velikost: <?= number_format($current512['size']) ?> bytes</p>
                    <p>MD5: <code><?= $current512['md5'] ?></code></p>
                    <p>Naposledy změněno: <?= $current512['modified'] ?></p>
                <?php else: ?>
                    <p>❌ Neexistuje</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="upload-form">
            <h2 style="margin-bottom: 1.5rem;">📁 Nahrát nové ikony</h2>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="icon192">icon192.png (192x192 pixels)</label>
                    <input type="file" id="icon192" name="icon192" accept="image/png" required>
                </div>

                <div class="form-group">
                    <label for="icon512">icon512.png (512x512 pixels)</label>
                    <input type="file" id="icon512" name="icon512" accept="image/png" required>
                </div>

                <button type="submit">🚀 Nahrát ikony</button>
            </form>
        </div>

        <div class="info">
            <strong>💡 Postup:</strong>
            <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                <li>Vyberte ikony z <code>~/Downloads/</code></li>
                <li>Klikněte na "Nahrát ikony"</li>
                <li>Po úspěšném uploadu commitněte změny</li>
                <li>SMAZAT tento soubor!</li>
            </ol>
        </div>
    </div>
</body>
</html>
