<?php
/**
 * Alpine.js Modal Sandbox - Step 35
 * Testovací stránka pro wgsModal komponentu
 *
 * POUZE PRO VÝVOJ - není součástí produkčního UI
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: /login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpine.js Modal Sandbox - WGS</title>
    <style>
        :root {
            --c-bg-dark: #000;
            --c-bg-modal: #111;
            --c-text: #fff;
            --c-text-muted: #999;
            --c-border: #333;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--c-bg-dark);
            color: var(--c-text);
            min-height: 100vh;
        }

        .sandbox-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--c-border);
            padding-bottom: 1rem;
        }

        .sandbox-info {
            background: #1a1a1a;
            border: 1px solid var(--c-border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .sandbox-info h2 {
            font-size: 1rem;
            color: var(--c-text-muted);
            margin-bottom: 0.5rem;
        }

        .sandbox-info ul {
            list-style: none;
            color: var(--c-text-muted);
            font-size: 0.9rem;
        }

        .sandbox-info li {
            padding: 0.25rem 0;
        }

        .sandbox-info li::before {
            content: "- ";
            color: #666;
        }

        /* Test tlačítko */
        .btn-test-modal {
            background: #333;
            color: #fff;
            border: 1px solid #555;
            padding: 1rem 2rem;
            font-size: 1rem;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .btn-test-modal:hover {
            background: #444;
            border-color: #666;
        }

        /* ========================================
           WGS Modal Styles (Step 35)
           ======================================== */

        .wgs-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9998;
        }

        .wgs-modal-window {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--c-bg-modal);
            border: 1px solid var(--c-border);
            border-radius: 8px;
            padding: 2rem;
            min-width: 300px;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            z-index: 9999;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        .wgs-modal-window h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--c-border);
        }

        .wgs-modal-window p {
            color: var(--c-text-muted);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .wgs-modal-window .btn-close {
            background: #333;
            color: #fff;
            border: 1px solid #555;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .wgs-modal-window .btn-close:hover {
            background: #444;
            border-color: #666;
        }

        /* Alpine.js transition helpers */
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/hamburger-menu.php'; ?>

<div class="sandbox-container">
    <h1>Alpine.js Modal Sandbox (Step 35)</h1>

    <div class="sandbox-info">
        <h2>Testovací kritéria:</h2>
        <ul>
            <li>Modal se otevře kliknutím na tlačítko</li>
            <li>Zavře se klikem na overlay (tmavé pozadí)</li>
            <li>Zavře se klávesou ESC</li>
            <li>Žádné Alpine / CSP chyby v konzoli</li>
            <li>Animace fungují plynule</li>
            <li>Scroll-lock při otevřeném modalu</li>
        </ul>
    </div>

    <!-- DEMO MODAL -->
    <div x-data="wgsModal">

        <!-- TEST BUTTON -->
        <button @click="toggle" class="btn-test-modal">
            Test Modal
        </button>

        <!-- OVERLAY -->
        <template x-if="open">
            <div
                class="wgs-modal-overlay"
                @click="close"
            ></div>
        </template>

        <!-- MODAL WINDOW -->
        <template x-if="open">
            <div class="wgs-modal-window">
                <h2>WGS Modal Framework</h2>
                <p>
                    Toto je testovací modal vytvořený pomocí Alpine.js CSP-safe buildu.
                    Klikněte na overlay nebo stiskněte ESC pro zavření.
                </p>
                <button @click="close" class="btn-close">Zavřít</button>
            </div>
        </template>

    </div>

    <div class="sandbox-info" style="margin-top: 2rem;">
        <h2>Konzolové logy:</h2>
        <ul>
            <li>[wgsModal] Inicializován (Alpine.js CSP-safe)</li>
            <li>Otevřít DevTools (F12) pro kontrolu CSP chyb</li>
        </ul>
    </div>

</div>

</body>
</html>
