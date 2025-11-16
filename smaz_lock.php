<?php
/**
 * SMAZÁNÍ LOCK SOUBORU - pro opakované spuštění aktualizuj_databazi.php
 */

// Bezpečnostní kontrola - pouze admin
session_start();
$jeAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$jeAdmin && !isset($_GET['force'])) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center;"><h1 style="color: #000;">❌ PŘÍSTUP ODEPŘEN</h1><p>Pouze administrátor může smazat lock soubor.</p><p><a href="admin.php" style="color: #000; border-bottom: 2px solid #000;">← Zpět</a></p></body></html>');
}

$lockFile = __DIR__ . '/.env_update_lock';
$deleted = false;

if (file_exists($lockFile)) {
    if (unlink($lockFile)) {
        $deleted = true;
        $message = "✓ Lock soubor byl úspěšně smazán";
    } else {
        $message = "✗ Nepodařilo se smazat lock soubor (kontroluj oprávnění)";
    }
} else {
    $message = "⊙ Lock soubor neexistuje (již byl smazán)";
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smazání lock souboru | WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #000;
            padding: 2rem;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 3rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1.5rem;
        }
        .message {
            font-size: 1.1rem;
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f5f5f5;
            border-left: 4px solid <?php echo $deleted ? '#006600' : '#555'; ?>;
        }
        .btn {
            background: #000;
            color: #fff;
            border: 2px solid #000;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #fff;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Smazání lock souboru</h1>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <a href="aktualizuj_databazi.php" class="btn">→ SPUSTIT AKTUALIZACI</a>
        <a href="admin.php" class="btn" style="background: #fff; color: #000;">← ZPĚT DO ADMIN PANELU</a>
    </div>
</body>
</html>
