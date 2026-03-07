<?php
/**
 * Migrace: Vytvoření tabulky wgs_wl_poptavky
 * Ukládá White Label poptávky z wl.wgs-service.cz
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

$pdo    = getDbConnection();
$zprava = null;
$typ    = null;

// Kontrola existence tabulky
$stmt = $pdo->query("SHOW TABLES LIKE 'wgs_wl_poptavky'");
$tabulkaExistuje = (bool) $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akce']) && $_POST['akce'] === 'vytvorit') {
    require_once __DIR__ . '/includes/csrf_helper.php';
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $zprava = 'Neplatný CSRF token.';
        $typ = 'chyba';
    } elseif ($tabulkaExistuje) {
        $zprava = 'Tabulka wgs_wl_poptavky již existuje.';
        $typ = 'info';
    } else {
        try {
            $pdo->exec("
                CREATE TABLE wgs_wl_poptavky (
                    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    jmeno           VARCHAR(120)  NOT NULL,
                    firma           VARCHAR(200)  NOT NULL,
                    email           VARCHAR(200)  NOT NULL,
                    telefon         VARCHAR(30)   DEFAULT NULL,
                    pocet_techniku  VARCHAR(20)   DEFAULT NULL,
                    segment         VARCHAR(60)   DEFAULT NULL,
                    zprava          TEXT          DEFAULT NULL,
                    ip_adresa       VARCHAR(45)   DEFAULT NULL,
                    zpracovano      TINYINT(1)    NOT NULL DEFAULT 0,
                    datum_vytvoreni DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_datum (datum_vytvoreni)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $tabulkaExistuje = true;
            $zprava = 'Tabulka wgs_wl_poptavky byla úspěšně vytvořena.';
            $typ = 'ok';
        } catch (PDOException $e) {
            $zprava = 'Chyba: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $typ = 'chyba';
        }
    }
}

require_once __DIR__ . '/includes/csrf_helper.php';
$csrfToken = generateCSRFToken();
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Migrace: Tabulka wgs_wl_poptavky</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; background: #f5f5f5; color: #222; }
        .container { background: #fff; padding: 35px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.12); }
        h1 { font-size: 1.3rem; border-bottom: 2px solid #222; padding-bottom: 10px; margin-bottom: 25px; }
        .zprava-ok    { background: #eee; border: 1px solid #999; color: #222; padding: 12px 16px; border-radius: 5px; margin: 15px 0; }
        .zprava-chyba { background: #222; color: #fff; padding: 12px 16px; border-radius: 5px; margin: 15px 0; }
        .zprava-info  { background: #f0f0f0; border: 1px solid #ccc; color: #444; padding: 12px 16px; border-radius: 5px; margin: 15px 0; }
        .btn { padding: 10px 22px; background: #222; color: #fff; border: none; border-radius: 5px; font-size: 0.95rem; cursor: pointer; }
        .btn:hover { background: #444; }
        .btn-sek { background: #888; margin-left: 8px; }
        .stav-ok   { display: inline-block; background: #eee; border: 1px solid #999; padding: 2px 10px; border-radius: 4px; font-size: 0.85rem; }
        .stav-ne   { display: inline-block; background: #222; color: #fff; padding: 2px 10px; border-radius: 4px; font-size: 0.85rem; }
        p { font-size: 0.9rem; color: #555; margin: 12px 0; }
        code { font-family: monospace; background: #f0f0f0; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Migrace: Tabulka <code>wgs_wl_poptavky</code></h1>

    <?php if ($zprava): ?>
        <div class="zprava-<?= $typ ?>"><?= $zprava ?></div>
    <?php endif; ?>

    <p>Stav tabulky:
        <?php if ($tabulkaExistuje): ?>
            <span class="stav-ok">Existuje</span>
        <?php else: ?>
            <span class="stav-ne">Neexistuje</span>
        <?php endif; ?>
    </p>

    <?php if (!$tabulkaExistuje): ?>
    <p>Tabulka ukládá poptávky z White Label landing page (<code>wl.wgs-service.cz</code>).</p>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="akce" value="vytvorit">
        <button type="submit" class="btn">Vytvořit tabulku</button>
        <a href="/admin" class="btn btn-sek" style="text-decoration:none;">Zrušit</a>
    </form>
    <?php else: ?>
    <a href="/admin" class="btn">Zpět do adminu</a>
    <?php endif; ?>
</div>
</body>
</html>
