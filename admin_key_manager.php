<?php
/**
 * ADMIN KEY MANAGER
 * Script pro správu admin klíče
 * 
 * Použití:
 * php admin_key_manager.php generate [nový_klíč]
 * php admin_key_manager.php show
 */

if (php_sapi_name() !== 'cli') {
    die('Tento script lze spustit POUZE z terminálu (CLI)!');
}

require_once 'init.php';

$action = $argv[1] ?? 'help';

switch ($action) {
    case 'generate':
        // Generovat NOVÝ admin klíč
        if (!isset($argv[2])) {
            $newKey = bin2hex(random_bytes(32)); // Náhodný 64-znakový klíč
            echo "Vygenerován nový admin klíč:\n";
        } else {
            $newKey = $argv[2];
            echo "Použit poskytnutý klíč:\n";
        }

        $hash = hash('sha256', $newKey);

        echo "   Klíč: $newKey\n";
        echo "   Hash: $hash\n";
        echo "\nPostup:\n";
        echo "   1. Zkopíruj hash výše\n";
        echo "   2. Otevři .env\n";
        echo "   3. Aktualizuj: ADMIN_KEY_HASH=$hash\n";
        echo "   4. Ulož a restartuj server\n";
        echo "\nBEZPEČNOST:\n";
        echo "   - Ulož si nový klíč do bezpečného místa!\n";
        echo "   - Sdělej ho administrátorům přes bezpečný kanál\n";
        echo "   - Nikdy necommituj .env do gitu!\n";
        break;

    case 'show':
        // Ukázat aktuální hash
        echo "Aktuální admin key hash z .env:\n";
        echo "   " . (defined('ADMIN_KEY_HASH') ? ADMIN_KEY_HASH : 'NENALEZEN') . "\n";
        break;

    case 'help':
    default:
        echo "ADMIN KEY MANAGER\n";
        echo "\nPříkazy:\n";
        echo "  php admin_key_manager.php generate          - Generovat NOVÝ náhodný klíč\n";
        echo "  php admin_key_manager.php generate ABC123   - Zahashovat vlastní klíč\n";
        echo "  php admin_key_manager.php show              - Ukázat aktuální hash\n";
        echo "\nPříklad:\n";
        echo "  \$ php admin_key_manager.php generate\n";
        echo "  Vygenerován nový admin klíč:\n";
        echo "     Klíč: a1b2c3d4e5f6...\n";
        echo "     Hash: 7bed2ee54bf4...\n";
}
?>
