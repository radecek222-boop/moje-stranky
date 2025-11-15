<?php}

// Pokud je skript spuštěn z příkazové řádky
if (php_sapi_name() === 'cli') {
    $reason = $argv[1] ?? 'manual';
    $backup = new DatabaseBackup();
    $result = $backup->createBackup($reason);

    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        echo "  Soubor: " . $result['file'] . "\n";
        exit(0);
    } else {
        echo "✗ " . $result['message'] . "\n";
        exit(1);
    }
}
