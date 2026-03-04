<?php
/**
 * Migrace: Přidání sloupce expires_at do wgs_registration_keys
 *
 * Přidává volitelnou expiraci registračních klíčů.
 * Existující klíče zůstanou bez expirace (NULL = bez omezení).
 * Skript je idempotentní — lze spustit vícekrát bezpečně.
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Přístup odepřen.');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Migrace: Expirace registračních klíčů</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .box { background: #fff; padding: 30px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { font-size: 1.3rem; margin: 0 0 1.5rem 0; border-bottom: 2px solid #111; padding-bottom: 0.75rem; }
        .ok { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 0.75rem 1rem; border-radius: 4px; margin: 0.5rem 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 0.75rem 1rem; border-radius: 4px; margin: 0.5rem 0; }
        .err { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 0.75rem 1rem; border-radius: 4px; margin: 0.5rem 0; }
        .btn { display: inline-block; padding: 0.6rem 1.4rem; background: #111; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; text-decoration: none; margin-top: 1rem; }
        .btn:hover { background: #333; }
        code { background: #f0f0f0; padding: 0.15rem 0.4rem; border-radius: 2px; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="box">
    <h1>Migrace: Expirace registračních klíčů</h1>
<?php
try {
    $pdo = getDbConnection();

    // Zkontrolovat zda sloupec již existuje
    $stmt = $pdo->prepare("SHOW COLUMNS FROM wgs_registration_keys LIKE 'expires_at'");
    $stmt->execute();
    $sloupecExistuje = $stmt->rowCount() > 0;

    if ($sloupecExistuje) {
        echo '<div class="ok">Sloupec <code>expires_at</code> již existuje — migrace není potřeba.</div>';
    } elseif (isset($_GET['execute']) && $_GET['execute'] === '1') {
        // Provést migraci
        $pdo->exec("ALTER TABLE wgs_registration_keys ADD COLUMN expires_at DATETIME NULL DEFAULT NULL COMMENT 'Datum expirace klíče, NULL = bez omezení'");
        echo '<div class="ok">Sloupec <code>expires_at</code> byl úspěšně přidán do tabulky <code>wgs_registration_keys</code>.</div>';
        echo '<div class="ok">Existující klíče mají <code>expires_at = NULL</code> (bez expirace) — beze změny chování.</div>';
        echo '<div class="info">Migrace dokončena. Tuto stránku můžete zavřít.</div>';
    } else {
        // Náhled
        echo '<div class="info">Skript přidá sloupec <code>expires_at DATETIME NULL</code> do tabulky <code>wgs_registration_keys</code>.</div>';
        echo '<div class="info">Existující klíče zůstanou nezměněné — <code>NULL</code> znamená bez expirace.</div>';
        echo '<a href="?execute=1" class="btn">Spustit migraci</a>';
    }

} catch (Exception $e) {
    echo '<div class="err"><strong>Chyba:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
}
?>
    <p style="margin-top:1.5rem;font-size:0.8rem;color:#888;">
        <a href="admin.php?tab=keys" style="color:#555;">Zpět na správu klíčů</a>
    </p>
</div>
</body>
</html>
