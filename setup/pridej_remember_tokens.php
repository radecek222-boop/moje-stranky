<?php
/**
 * Migrace: Přidání tabulky pro Remember Me tokeny
 *
 * Tento skript vytvoří tabulku wgs_remember_tokens pro bezpečné ukládání
 * "remember me" tokenů.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migrace: Remember Me Tokens</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 50px auto;
               padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
                 padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;
                padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016;
               color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Remember Me Tokens</h1>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            $sql = "CREATE TABLE IF NOT EXISTS `wgs_remember_tokens` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` VARCHAR(50) NOT NULL COMMENT 'ID uživatele (TCH2025001, PRT2025001)',
                `selector` VARCHAR(64) NOT NULL COMMENT 'Veřejný identifikátor tokenu',
                `hashed_validator` VARCHAR(255) NOT NULL COMMENT 'Hash validátoru tokenu',
                `expires_at` DATETIME NOT NULL COMMENT 'Kdy token vyprší',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ip_address` VARCHAR(45) NULL COMMENT 'IP adresa při vytvoření',
                `user_agent` VARCHAR(255) NULL COMMENT 'User agent při vytvoření',
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_selector` (`selector`),
                INDEX `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Remember Me tokeny pro automatické přihlášení'";

            $pdo->exec($sql);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Tabulka <code>wgs_remember_tokens</code> byla vytvořena.";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        echo "<div class='info'>";
        echo "<strong>KONTROLA...</strong><br>";
        echo "Migrace vytvoří tabulku <code>wgs_remember_tokens</code> pro ukládání Remember Me tokenů.";
        echo "</div>";
        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
