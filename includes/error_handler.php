<?php
/**
 * Advanced Error Handler
 * Zachytává všechny PHP chyby a zobrazuje detailní informace pro debugging
 */

// Detekce produkčního prostředí
if (!defined('IS_PRODUCTION')) {
    define('IS_PRODUCTION', isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'www.wgs-service.cz');
}

// Nastavení error reportingu
error_reporting(E_ALL);
ini_set('display_errors', IS_PRODUCTION ? 0 : 1);
ini_set('display_startup_errors', IS_PRODUCTION ? 0 : 1);

// Global error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Respektuj @ operátor - pokud je chyba potlačena, předat ji zpět PHP
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorType = match($errno) {
        E_ERROR => 'FATAL ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE ERROR',
        E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE ERROR',
        E_COMPILE_WARNING => 'COMPILE WARNING',
        E_USER_ERROR => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE => 'USER NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER DEPRECATED',
        default => 'UNKNOWN ERROR'
    };

    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

    // Formátovaná chybová zpráva
    $errorMessage = formatErrorMessage([
        'type' => $errorType,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'backtrace' => $backtrace
    ]);

    // Logování do souboru
    logErrorToFile($errorMessage);

    // Detekce API requestů - modernější approach
    $isApiRequest = (
        // 1. Klasický AJAX header (jQuery, axios)
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||

        // 2. URL obsahuje /api/
        (isset($_SERVER['REQUEST_URI']) &&
         strpos($_SERVER['REQUEST_URI'], '/api/') !== false) ||

        // 3. Request s Content-Type: application/json
        (isset($_SERVER['CONTENT_TYPE']) &&
         strpos(strtolower($_SERVER['CONTENT_TYPE']), 'application/json') !== false) ||

        // 4. Accept header preferuje JSON
        (isset($_SERVER['HTTP_ACCEPT']) &&
         strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false)
    );

    // Pokud je API request, vrátit JSON
    if ($isApiRequest) {

        header('Content-Type: application/json');

        $response = [
            'success' => false,
            'error' => IS_PRODUCTION ? 'Server Error' : $errorType,
            'message' => IS_PRODUCTION ? 'Došlo k chybě serveru. Kontaktujte podporu.' : $errstr
        ];

        // Detaily pouze v development režimu
        if (!IS_PRODUCTION) {
            $response['debug'] = [
                'file' => basename($errfile),
                'line' => $errline,
                'backtrace' => array_slice(formatBacktrace($backtrace), 0, 5)
            ];
        }

        echo json_encode($response);
        exit;
    }

    // Jinak zobrazit HTML error
    displayErrorHTML([
        'type' => $errorType,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'backtrace' => $backtrace
    ]);

    return true;
});

// Exception handler
set_exception_handler(function($exception) {
    $backtrace = $exception->getTrace();

    $errorMessage = formatErrorMessage([
        'type' => 'UNCAUGHT EXCEPTION: ' . get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'backtrace' => $backtrace
    ]);

    logErrorToFile($errorMessage);

    // Detekce API requestů - stejně jako v error handleru
    $isApiRequest = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
        (isset($_SERVER['REQUEST_URI']) &&
         strpos($_SERVER['REQUEST_URI'], '/api/') !== false) ||
        (isset($_SERVER['CONTENT_TYPE']) &&
         strpos(strtolower($_SERVER['CONTENT_TYPE']), 'application/json') !== false) ||
        (isset($_SERVER['HTTP_ACCEPT']) &&
         strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false)
    );

    // Pokud je API request
    if ($isApiRequest) {

        header('Content-Type: application/json');
        http_response_code(500);

        $response = [
            'success' => false,
            'error' => IS_PRODUCTION ? 'Exception' : get_class($exception),
            'message' => IS_PRODUCTION ? 'Došlo k neočekávané chybě. Kontaktujte podporu.' : $exception->getMessage()
        ];

        // Detaily pouze v development režimu
        if (!IS_PRODUCTION) {
            $response['debug'] = [
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
                'backtrace' => array_slice(formatBacktrace($backtrace), 0, 5)
            ];
        }

        echo json_encode($response);
        exit;
    }

    displayErrorHTML([
        'type' => 'UNCAUGHT EXCEPTION: ' . get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'backtrace' => $backtrace
    ]);
});

// Fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $errorMessage = formatErrorMessage([
            'type' => 'FATAL ERROR',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'backtrace' => []
        ]);

        logErrorToFile($errorMessage);

        // Pro AJAX requesty
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

            header('Content-Type: application/json');
            http_response_code(500);

            $response = [
                'success' => false,
                'error' => 'FATAL ERROR',
                'message' => IS_PRODUCTION ? 'Kritická chyba serveru. Kontaktujte podporu.' : $error['message']
            ];

            // Detaily pouze v development režimu
            if (!IS_PRODUCTION) {
                $response['debug'] = [
                    'file' => basename($error['file']),
                    'line' => $error['line']
                ];
            }

            echo json_encode($response);
            exit;
        }

        displayErrorHTML([
            'type' => 'FATAL ERROR',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'backtrace' => []
        ]);
    }
});

/**
 * Formátování chybové zprávy pro textový log soubor
 *
 * Vytváří detailní log zprávu obsahující:
 * - Typ chyby, čas, zprávu
 * - Soubor a řádek
 * - Stack trace (pokud je k dispozici)
 * - Request info (URL, metoda, IP, User-Agent)
 *
 * @param array $error Error data ['type' => string, 'message' => string, 'file' => string, 'line' => int, 'backtrace' => array|null]
 * @return string Formátovaná chybová zpráva pro log
 */
/**
 * FormatErrorMessage
 *
 * @param mixed $error Error
 */
function formatErrorMessage($error) {
    $message = "\n" . str_repeat('=', 80) . "\n";
    $message .= "{$error['type']}\n";
    $message .= str_repeat('=', 80) . "\n";
    $message .= "Čas: " . date('Y-m-d H:i:s') . "\n";
    $message .= "Zpráva: {$error['message']}\n";
    $message .= "Soubor: {$error['file']}\n";
    $message .= "Řádek: {$error['line']}\n";

    if (!empty($error['backtrace'])) {
        $message .= "\nStack Trace:\n";
        $message .= str_repeat('-', 80) . "\n";

        foreach ($error['backtrace'] as $i => $trace) {
            $file = $trace['file'] ?? 'unknown';
            $line = $trace['line'] ?? 0;
            $function = $trace['function'] ?? 'unknown';
            $class = isset($trace['class']) ? $trace['class'] . $trace['type'] : '';

            $message .= sprintf("#%d %s%s() called at [%s:%d]\n",
                $i, $class, $function, $file, $line
            );
        }
    }

    $message .= "\nRequest Info:\n";
    $message .= str_repeat('-', 80) . "\n";
    $message .= "URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
    $message .= "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n";
    $message .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";
    $message .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";
    $message .= str_repeat('=', 80) . "\n\n";

    return $message;
}

/**
 * Formátování backtrace do strukturovaného pole pro JSON output
 *
 * Převádí PHP backtrace do čitelné struktury obsahující:
 * - Číslo záznamu
 * - Soubor (basename a plná cesta)
 * - Řádek
 * - Funkci/metodu
 * - Třídu a typ volání (-> nebo ::)
 *
 * @param array $backtrace PHP backtrace z debug_backtrace()
 * @return array Formátovaný backtrace vhodný pro JSON encoding
 */
/**
 * FormatBacktrace
 *
 * @param mixed $backtrace Backtrace
 */
function formatBacktrace($backtrace) {
    $formatted = [];

    foreach ($backtrace as $i => $trace) {
        $formatted[] = [
            'number' => $i,
            'file' => basename($trace['file'] ?? 'unknown'),
            'full_path' => $trace['file'] ?? 'unknown',
            'line' => $trace['line'] ?? 0,
            'function' => $trace['function'] ?? 'unknown',
            'class' => $trace['class'] ?? null,
            'type' => $trace['type'] ?? null
        ];
    }

    return $formatted;
}

/**
 * Uloží chybovou zprávu do log souboru
 *
 * Zapisuje do /logs/php_errors.log
 * Vytvoří adresář pokud neexistuje (oprávnění 0755).
 *
 * WARNING: Používá @ operator - mělo by být nahrazeno safe_file_operations.php
 *
 * @param string $message Chybová zpráva k uložení
 * @return void
 */
/**
 * LogErrorToFile
 *
 * @param mixed $message Message
 */
function logErrorToFile($message) {
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log('Failed to create log directory: ' . $logDir);
        }
    }

    $logFile = $logDir . '/php_errors.log';
    if (file_put_contents($logFile, $message, FILE_APPEND) === false) {
    error_log('Failed to write file');
}
}

/**
 * Zobrazí chybu v přehledném HTML formátu pro development
 *
 * Vytváří debug stránku s tmavým Apple-style designem obsahující:
 * - Typ chyby a zprávu
 * - Soubor a řádek
 * - Stack trace s možností rozbalení
 * - Request informace (URL, metoda, IP, User-Agent)
 * - Barevné rozlišení podle typu chyby
 *
 * WARNING: Mělo by být použito POUZE v development módu!
 *
 * @param array $error Error data ['type' => string, 'message' => string, 'file' => string, 'line' => int, 'backtrace' => array|null]
 * @return void Vypisuje HTML přímo do outputu
 */
/**
 * DisplayErrorHTML
 *
 * @param mixed $error Error
 */
function displayErrorHTML($error) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Chyba - WGS Debug</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Courier New', monospace;
                background: #1a1a1a;
                color: #f0f0f0;
                padding: 20px;
                line-height: 1.6;
            }
            .error-container {
                max-width: 1200px;
                margin: 0 auto;
                background: #2d2d2d;
                border: 3px solid #dc3545;
                border-radius: 8px;
                overflow: hidden;
            }
            .error-header {
                background: #dc3545;
                color: white;
                padding: 20px;
                font-size: 24px;
                font-weight: bold;
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .error-icon {
                font-size: 36px;
            }
            .error-body {
                padding: 20px;
            }
            .error-section {
                background: #1a1a1a;
                border: 1px solid #444;
                border-radius: 4px;
                padding: 15px;
                margin-bottom: 15px;
            }
            .error-label {
                color: #ffc107;
                font-weight: bold;
                margin-bottom: 8px;
                text-transform: uppercase;
                font-size: 12px;
                letter-spacing: 1px;
            }
            .error-value {
                color: #f0f0f0;
                font-size: 14px;
                word-break: break-all;
            }
            .error-message {
                color: #ff6b6b;
                font-size: 16px;
                font-weight: bold;
            }
            .error-file {
                color: #4dabf7;
            }
            .error-line {
                color: #51cf66;
                font-weight: bold;
            }
            .backtrace {
                background: #0d0d0d;
                border: 1px solid #333;
                border-radius: 4px;
                padding: 15px;
                max-height: 400px;
                overflow-y: auto;
            }
            .backtrace-item {
                padding: 10px;
                border-bottom: 1px solid #222;
                margin-bottom: 10px;
            }
            .backtrace-item:last-child {
                border-bottom: none;
            }
            .backtrace-number {
                color: #868e96;
                font-weight: bold;
            }
            .backtrace-function {
                color: #4dabf7;
                font-weight: bold;
            }
            .backtrace-file {
                color: #868e96;
                font-size: 12px;
                margin-top: 5px;
            }
            .copy-btn {
                background: #28a745;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                font-family: 'Courier New', monospace;
                font-size: 14px;
                margin-top: 10px;
            }
            .copy-btn:hover {
                background: #218838;
            }
            .copy-btn:active {
                background: #1e7e34;
            }
            .request-info {
                font-size: 12px;
                color: #868e96;
                margin-top: 10px;
            }
            .highlight {
                background: #ffc107;
                color: #000;
                padding: 2px 6px;
                border-radius: 3px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-header">
                <div>
                    <div><?= htmlspecialchars($error['type']) ?></div>
                    <div style="font-size: 14px; font-weight: normal; opacity: 0.9; margin-top: 5px;">
                        WGS Debug Mode - Detailní informace o chybě
                    </div>
                </div>
            </div>

            <div class="error-body">
                <!-- Chybová zpráva -->
                <div class="error-section">
                    <div class="error-label">Chybová zpráva:</div>
                    <div class="error-value error-message"><?= htmlspecialchars($error['message']) ?></div>
                </div>

                <!-- Umístění -->
                <div class="error-section">
                    <div class="error-label">Umístění:</div>
                    <div class="error-value">
                        <div style="margin-bottom: 8px;">
                            <span style="color: #ffc107;">Soubor:</span>
                            <span class="error-file"><?= htmlspecialchars($error['file']) ?></span>
                        </div>
                        <div>
                            <span style="color: #ffc107;">Řádek:</span>
                            <span class="error-line highlight"><?= $error['line'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Stack Trace -->
                <?php if (!empty($error['backtrace'])): ?>
                <div class="error-section">
                    <div class="error-label">Stack Trace (Posloupnost volání):</div>
                    <div class="backtrace">
                        <?php foreach ($error['backtrace'] as $i => $trace): ?>
                            <div class="backtrace-item">
                                <div>
                                    <span class="backtrace-number">#<?= $i ?></span>
                                    <?php if (isset($trace['class'])): ?>
                                        <span class="backtrace-function">
                                            <?= htmlspecialchars($trace['class'] . $trace['type'] . $trace['function']) ?>()
                                        </span>
                                    <?php else: ?>
                                        <span class="backtrace-function">
                                            <?= htmlspecialchars($trace['function'] ?? 'unknown') ?>()
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="backtrace-file">
                                    <?= htmlspecialchars($trace['file'] ?? 'unknown') ?>:<?= $trace['line'] ?? 0 ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Request Info -->
                <div class="error-section">
                    <div class="error-label">Request Info:</div>
                    <div class="error-value request-info">
                        <div><strong>URL:</strong> <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') ?></div>
                        <div><strong>Method:</strong> <?= htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'N/A') ?></div>
                        <div><strong>IP:</strong> <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'N/A') ?></div>
                        <div><strong>Čas:</strong> <?= date('Y-m-d H:i:s') ?></div>
                    </div>
                </div>

                <!-- Copy button -->
                <div style="text-align: center; margin-top: 20px;">
                    <button class="copy-btn" onclick="copyErrorReport()">
                        Kopírovat pro Claude Code nebo Codex
                    </button>
                    <div id="copyStatus" style="color: #28a745; margin-top: 10px; display: none;">
                        Zkopírováno! Vložte CTRL+V do zprávy pro Claude/Codex
                    </div>
                </div>
            </div>
        </div>

        <script>
                /**
         * CopyErrorReport
         */
function copyErrorReport() {
            const separator = '='.repeat(80);
            const report = `
WGS ERROR REPORT
${separator}
Type: <?= addslashes($error['type']) ?>

Message: <?= addslashes($error['message']) ?>

File: <?= addslashes($error['file']) ?>

Line: <?= $error['line'] ?>

<?php if (!empty($error['backtrace'])): ?>
Stack Trace:
<?= str_repeat('-', 80) ?>

<?php foreach ($error['backtrace'] as $i => $trace): ?>
#<?= $i ?> <?= isset($trace['class']) ? addslashes($trace['class'] . $trace['type']) : '' ?><?= addslashes($trace['function'] ?? 'unknown') ?>()
   at <?= addslashes($trace['file'] ?? 'unknown') ?>:<?= $trace['line'] ?? 0 ?>

<?php endforeach; ?>
<?php endif; ?>
Request Info:
<?= str_repeat('-', 80) ?>

URL: <?= addslashes($_SERVER['REQUEST_URI'] ?? 'N/A') ?>

Method: <?= addslashes($_SERVER['REQUEST_METHOD'] ?? 'N/A') ?>

Time: <?= date('Y-m-d H:i:s') ?>

${separator}
            `.trim();

            navigator.clipboard.writeText(report).then(() => {
                const status = document.getElementById('copyStatus');
                status.style.display = 'block';
                setTimeout(() => {
                    status.style.display = 'none';
                }, 3000);
            });
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}
