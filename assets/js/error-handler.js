/**
 * JavaScript Error Handler
 * Zachytává všechny JS chyby a zobrazuje detailní informace
 */

// Global error handler
window.addEventListener('error', function(event) {
    const error = {
        type: 'JavaScript Error',
        message: event.message,
        file: event.filename,
        line: event.lineno,
        column: event.colno,
        stack: event.error ? event.error.stack : null,
        timestamp: new Date().toISOString(),
        url: window.location.href,
        userAgent: navigator.userAgent
    };

    console.error('🔴 JavaScript Error Caught:', error);

    // Zobrazit error v UI
    displayJSError(error);

    // Logovat na server (volitelné)
    logErrorToServer(error);
});

// Promise rejection handler
window.addEventListener('unhandledrejection', function(event) {
    const error = {
        type: 'Unhandled Promise Rejection',
        message: event.reason ? event.reason.message || event.reason : 'Unknown rejection',
        stack: event.reason ? event.reason.stack : null,
        timestamp: new Date().toISOString(),
        url: window.location.href,
        userAgent: navigator.userAgent
    };

    console.error('🔴 Unhandled Promise Rejection:', error);

    // Zobrazit error v UI
    displayJSError(error);

    // Logovat na server
    logErrorToServer(error);
});

// Console error override pro zachycení console.error volání
const originalConsoleError = console.error;
console.error = function(...args) {
    // Zavolat původní console.error
    originalConsoleError.apply(console, args);

    // Pokud je první argument Error objekt, zobrazit detail
    if (args[0] instanceof Error) {
        const error = {
            type: 'Console Error',
            message: args[0].message,
            stack: args[0].stack,
            timestamp: new Date().toISOString(),
            url: window.location.href
        };

        displayJSError(error);
    }
};

/**
 * Zobrazení JS chyby v UI
 */
function displayJSError(error) {
    // Pokud už existuje error container, nepřidávat další
    if (document.getElementById('js-error-container')) {
        return;
    }

    const container = document.createElement('div');
    container.id = 'js-error-container';
    container.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        max-width: 500px;
        background: #2d2d2d;
        border: 3px solid #dc3545;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        z-index: 999999;
        font-family: 'Courier New', monospace;
        color: #f0f0f0;
        overflow: hidden;
    `;

    const header = document.createElement('div');
    header.style.cssText = `
        background: #dc3545;
        color: white;
        padding: 12px 15px;
        font-weight: bold;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
    `;
    header.innerHTML = `
        <span>🔴 ${error.type}</span>
        <button onclick="document.getElementById('js-error-container').remove()"
                style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; padding: 0; line-height: 1;">
            ×
        </button>
    `;

    const body = document.createElement('div');
    body.style.cssText = `
        padding: 15px;
        max-height: 400px;
        overflow-y: auto;
        font-size: 12px;
    `;

    let bodyHTML = `
        <div style="margin-bottom: 10px;">
            <div style="color: #ffc107; font-weight: bold; margin-bottom: 5px;">📋 ZPRÁVA:</div>
            <div style="color: #ff6b6b; word-break: break-word;">${escapeHtml(error.message)}</div>
        </div>
    `;

    if (error.file) {
        bodyHTML += `
            <div style="margin-bottom: 10px;">
                <div style="color: #ffc107; font-weight: bold; margin-bottom: 5px;">📍 SOUBOR:</div>
                <div style="color: #4dabf7; word-break: break-all;">${escapeHtml(error.file)}</div>
            </div>
        `;
    }

    if (error.line) {
        bodyHTML += `
            <div style="margin-bottom: 10px;">
                <div style="color: #ffc107; font-weight: bold; margin-bottom: 5px;">📍 ŘÁDEK:</div>
                <div style="color: #51cf66; font-weight: bold;">
                    ${error.line}${error.column ? ':' + error.column : ''}
                </div>
            </div>
        `;
    }

    if (error.stack) {
        const stackLines = error.stack.split('\n').slice(0, 10);
        bodyHTML += `
            <div style="margin-bottom: 10px;">
                <div style="color: #ffc107; font-weight: bold; margin-bottom: 5px;">📚 STACK TRACE:</div>
                <div style="background: #1a1a1a; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 150px;">
                    <pre style="margin: 0; color: #868e96; font-size: 11px; white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(stackLines.join('\n'))}</pre>
                </div>
            </div>
        `;
    }

    bodyHTML += `
        <div style="text-align: center; margin-top: 15px;">
            <button onclick="copyJSError()"
                    style="background: #28a745; color: white; border: none; padding: 8px 15px;
                           border-radius: 4px; cursor: pointer; font-family: 'Courier New', monospace; font-size: 12px;">
                📋 Kopírovat pro Claude Code nebo Codex
            </button>
            <div id="js-copy-status" style="color: #28a745; margin-top: 8px; display: none; font-size: 11px;">
                ✅ Zkopírováno! Vložte CTRL+V do zprávy pro Claude/Codex
            </div>
        </div>
    `;

    body.innerHTML = bodyHTML;

    container.appendChild(header);
    container.appendChild(body);
    document.body.appendChild(container);

    // Uložit error data pro kopírování
    window._lastJSError = error;
}

/**
 * Kopírování JS chyby do schránky
 */
window.copyJSError = function() {
    const error = window._lastJSError;
    if (!error) return;

    const report = `
🔴 WGS JAVASCRIPT ERROR REPORT
${'='.repeat(80)}
Type: ${error.type}
Message: ${error.message}
${error.file ? `File: ${error.file}` : ''}
${error.line ? `Line: ${error.line}${error.column ? ':' + error.column : ''}` : ''}
${error.stack ? `\nStack Trace:\n${'-'.repeat(80)}\n${error.stack}` : ''}

Page URL: ${error.url}
Time: ${error.timestamp}
User Agent: ${error.userAgent}
${'='.repeat(80)}
    `.trim();

    navigator.clipboard.writeText(report).then(() => {
        const status = document.getElementById('js-copy-status');
        if (status) {
            status.style.display = 'block';
            setTimeout(() => {
                status.style.display = 'none';
            }, 3000);
        }
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Kopírování selhalo. Zkopírujte chybu ručně z konzole.');
    });
};

/**
 * Logování chyby na server
 */
function logErrorToServer(error) {
    // Nelogovat při vývoji na localhostu (volitelné)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        return;
    }

    try {
        fetch('/api/log_js_error.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(error)
        }).catch(err => {
            // Tiše ignorovat chyby logování
            console.warn('Failed to log error to server:', err);
        });
    } catch (e) {
        // Ignore
    }
}

/**
 * Escape HTML pro bezpečné zobrazení
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Enhanced fetch wrapper s error handlingem
 */
const originalFetch = window.fetch;
window.fetch = function(...args) {
    return originalFetch.apply(this, args)
        .then(response => {
            // Pokud je to error response, zkusit extrahovat detaily
            if (!response.ok) {
                return response.json().then(data => {
                    if (data.error || data.message) {
                        const error = new Error(data.message || data.error || 'HTTP Error');
                        error.httpStatus = response.status;
                        error.responseData = data;

                        // Pokud je to PHP error s detaily, zobrazit
                        if (data.file && data.line) {
                            console.error('🔴 API Error:', {
                                message: data.message || data.error,
                                file: data.file,
                                full_path: data.full_path,
                                line: data.line,
                                backtrace: data.backtrace
                            });

                            // Zobrazit v UI
                            displayAPIError(data);
                        }

                        throw error;
                    }
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }).catch(jsonError => {
                    // Pokud response není JSON
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                });
            }
            return response;
        })
        .catch(error => {
            console.error('🔴 Fetch Error:', error);
            throw error;
        });
};

/**
 * Zobrazení API chyby
 */
function displayAPIError(data) {
    const error = {
        type: 'API Error',
        message: data.message || data.error,
        file: data.full_path || data.file,
        line: data.line,
        stack: data.backtrace ? formatBacktrace(data.backtrace) : null,
        timestamp: new Date().toISOString(),
        url: window.location.href
    };

    displayJSError(error);
}

/**
 * Formátování backtrace
 */
function formatBacktrace(backtrace) {
    if (!Array.isArray(backtrace)) return null;

    return backtrace.map((trace, i) => {
        const className = trace.class ? trace.class + trace.type : '';
        const func = className + (trace.function || 'unknown');
        return `#${i} ${func}() at ${trace.full_path || trace.file}:${trace.line}`;
    }).join('\n');
}

console.log('✅ WGS Error Handler loaded - All errors will be caught and displayed');
