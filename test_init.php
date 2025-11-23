<?php
/**
 * ULTRA JEDNODUCHÝ TEST - kde je Fatal Error?
 */

// Zapnout VŠECHNY errory
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "1. START\n";

try {
    echo "2. Loading init.php...\n";
    require_once __DIR__ . '/init.php';
    echo "3. init.php loaded OK\n";

    echo "4. Loading csrf_helper.php...\n";
    require_once __DIR__ . '/includes/csrf_helper.php';
    echo "5. csrf_helper.php loaded OK\n";

    echo "6. Loading api_response.php...\n";
    require_once __DIR__ . '/includes/api_response.php';
    echo "7. api_response.php loaded OK\n";

    echo "8. Getting DB connection...\n";
    $pdo = getDbConnection();
    echo "9. DB connection OK\n";

    echo "10. Generating CSRF token...\n";
    $token = generateCSRFToken();
    echo "11. CSRF token: $token\n";

    echo "12. Validating CSRF token...\n";
    $valid = validateCSRFToken($token);
    echo "13. Token valid: " . ($valid ? 'YES' : 'NO') . "\n";

    echo "\n✅ VŠECHNO FUNGUJE!\n";
    echo "Problem NENÍ v init.php, csrf_helper.php ani api_response.php\n";

} catch (Throwable $e) {
    echo "\n❌ CHYBA:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
