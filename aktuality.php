<?php
/**
 * Stránka Aktuality - Denní novinky o značce Natuzzi
 * Automaticky načítá aktuální obsah z databáze
 */

require_once __DIR__ . '/init.php';

// Získat dnešní aktualitu
try {
    $pdo = getDbConnection();

    // Nejprve zkusit dnešní datum
    $dnes = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM wgs_natuzzi_aktuality
        WHERE datum = :datum
        LIMIT 1
    ");
    $stmt->execute(['datum' => $dnes]);
    $aktualita = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pokud neexistuje dnešní, vzít poslední dostupnou
    if (!$aktualita) {
        $stmt = $pdo->query("
            SELECT * FROM wgs_natuzzi_aktuality
            ORDER BY datum DESC
            LIMIT 1
        ");
        $aktualita = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Získat všechny dostupné datumy pro archiv
    $stmtArchiv = $pdo->query("
        SELECT datum, svatek_cz
        FROM wgs_natuzzi_aktuality
        ORDER BY datum DESC
        LIMIT 30
    ");
    $archiv = $stmtArchiv->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Chyba při načítání aktualit: " . $e->getMessage());
    $aktualita = null;
    $archiv = [];
}

// Určit jazyk z URL parametru (default CZ)
$jazyk = $_GET['lang'] ?? 'cz';
$jazyk = in_array($jazyk, ['cz', 'en', 'it']) ? $jazyk : 'cz';

$obsahSloupec = 'obsah_' . $jazyk;
$obsah = $aktualita[$obsahSloupec] ?? 'Obsah se načítá...';

?>
<!DOCTYPE html>
<html lang="<?php echo $jazyk; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Natuzzi Aktuality | WGS Service</title>

    <meta name="description" content="Denní aktuality o značce Natuzzi - novinky, tipy na péči o luxusní nábytek, showroomy v ČR">
    <meta name="keywords" content="Natuzzi, aktuality, novinky, luxusní nábytek, kožené sedačky, péče o nábytek">

    <!-- Ikona -->
    <link rel="icon" href="https://www.wgs-service.cz/favicon.ico">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --c-primary: #2D5016;
            --c-primary-dark: #1a300d;
            --c-bg: #f5f5f5;
            --c-white: #ffffff;
            --c-grey: #666;
            --c-light-grey: #e0e0e0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--c-bg);
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: var(--c-primary);
            color: white;
            padding: 30px 20px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 10px;
        }

        header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .lang-switcher {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }

        .lang-btn {
            padding: 8px 20px;
            background: var(--c-white);
            color: var(--c-primary);
            border: 2px solid var(--c-primary);
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }

        .lang-btn.active,
        .lang-btn:hover {
            background: var(--c-primary);
            color: white;
        }

        .content-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .datum-badge {
            background: var(--c-primary);
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .obsah {
            font-size: 1.05em;
            line-height: 1.8;
        }

        .obsah h1 {
            color: var(--c-primary);
            margin: 30px 0 20px 0;
            font-size: 2em;
            border-bottom: 3px solid var(--c-primary);
            padding-bottom: 10px;
        }

        .obsah h2 {
            color: var(--c-primary);
            margin: 25px 0 15px 0;
            font-size: 1.5em;
        }

        .obsah h3 {
            color: var(--c-primary-dark);
            margin: 20px 0 10px 0;
        }

        .obsah p {
            margin: 15px 0;
        }

        .obsah a {
            color: var(--c-primary);
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px dotted var(--c-primary);
        }

        .obsah a:hover {
            border-bottom-style: solid;
        }

        .obsah ul {
            margin: 15px 0 15px 30px;
        }

        .obsah li {
            margin: 8px 0;
        }

        .archiv-sidebar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .archiv-sidebar h3 {
            color: var(--c-primary);
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        .archiv-link {
            display: block;
            padding: 10px;
            margin: 5px 0;
            background: var(--c-bg);
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }

        .archiv-link:hover {
            background: var(--c-primary);
            color: white;
        }

        .archiv-link.active {
            background: var(--c-primary);
            color: white;
            font-weight: bold;
        }

        .refresh-btn {
            display: inline-block;
            padding: 12px 30px;
            background: var(--c-primary);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            text-decoration: none;
            transition: all 0.3s;
        }

        .refresh-btn:hover {
            background: var(--c-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .info-box {
            background: #e8f5e9;
            border-left: 4px solid var(--c-primary);
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .info-box strong {
            color: var(--c-primary);
        }

        footer {
            text-align: center;
            padding: 20px;
            color: var(--c-grey);
            margin-top: 40px;
        }

        @media (max-width: 768px) {
            header h1 {
                font-size: 1.8em;
            }

            .content-card {
                padding: 20px;
            }

            .lang-switcher {
                flex-direction: column;
            }
        }
    </style>

    <!-- Analytics Tracker -->
    <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>
<body>

<div class="container">
    <header>
        <h1>🛋️ Natuzzi Aktuality</h1>
        <p>Denní novinky, tipy a zajímavosti o luxusním italském nábytku</p>
    </header>

    <!-- Přepínač jazyků -->
    <div class="lang-switcher">
        <a href="?lang=cz" class="lang-btn <?php echo $jazyk === 'cz' ? 'active' : ''; ?>">
            🇨🇿 Čeština
        </a>
        <a href="?lang=en" class="lang-btn <?php echo $jazyk === 'en' ? 'active' : ''; ?>">
            🇬🇧 English
        </a>
        <a href="?lang=it" class="lang-btn <?php echo $jazyk === 'it' ? 'active' : ''; ?>">
            🇮🇹 Italiano
        </a>
    </div>

    <?php if ($aktualita): ?>
        <div class="content-card">
            <div class="datum-badge">
                📅 <?php echo date('d.m.Y', strtotime($aktualita['datum'])); ?>
                <?php if ($aktualita['svatek_cz']): ?>
                    | Svátek: <?php echo htmlspecialchars($aktualita['svatek_cz']); ?>
                <?php endif; ?>
            </div>

            <div class="obsah">
                <?php
                // Převést Markdown na HTML (jednoduchý parser)
                $htmlObsah = parseMarkdown($obsah);
                echo $htmlObsah;
                ?>
            </div>

            <?php if ($aktualita['vygenerovano_ai']): ?>
                <div class="info-box">
                    <strong>ℹ️ Informace:</strong> Tento obsah byl automaticky vygenerován z aktuálních zdrojů na internetu a přeložen do <?php echo $jazyk === 'cz' ? 'češtiny' : ($jazyk === 'en' ? 'angličtiny' : 'italštiny'); ?>.
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($archiv) && count($archiv) > 1): ?>
            <div class="archiv-sidebar">
                <h3>📚 Archiv aktualit</h3>
                <?php foreach ($archiv as $polozka): ?>
                    <a href="?datum=<?php echo $polozka['datum']; ?>&lang=<?php echo $jazyk; ?>"
                       class="archiv-link <?php echo $polozka['datum'] === $aktualita['datum'] ? 'active' : ''; ?>">
                        <?php echo date('d.m.Y', strtotime($polozka['datum'])); ?>
                        <?php if ($polozka['svatek_cz']): ?>
                            - <?php echo htmlspecialchars($polozka['svatek_cz']); ?>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="content-card">
            <h2>⚠️ Žádné aktuality</h2>
            <p>Momentálně nejsou k dispozici žádné aktuality. Systém je možná třeba inicializovat.</p>

            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
                <p style="margin-top: 20px;">
                    <a href="/api/generuj_aktuality.php" class="refresh-btn">
                        🔄 Vygenerovat první aktualitu
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> WGS Service | Natuzzi Authorized Service Partner</p>
        <p><a href="index.php" style="color: var(--c-primary);">← Zpět na hlavní stránku</a></p>
    </footer>
</div>

</body>
</html>

<?php
/**
 * Jednoduchý Markdown → HTML parser
 */
function parseMarkdown(string $text): string
{
    // Nadpisy
    $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);

    // Tučný text
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

    // Odkazy
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $text);

    // Odstavce
    $text = preg_replace('/\n\n/', '</p><p>', $text);
    $text = '<p>' . $text . '</p>';

    // Prázdné odstavce
    $text = str_replace('<p></p>', '', $text);

    return $text;
}
?>
