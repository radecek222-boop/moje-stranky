<?php
/**
 * SETUP: Přidání produkčních úkolů do Control Center
 *
 * Použití:
 * 1. Otevři v prohlížeči: https://your-domain.com/setup/add_production_tasks.php
 * 2. Script automaticky přidá 3 úkoly do Control Center
 * 3. Jdi do Control Center -> Akce & Úkoly
 * 4. Tam je spusť jedním kliknutím
 */

require_once __DIR__ . '/../init.php';

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('
        <h1>❌ Neautorizovaný přístup</h1>
        <p>Tento script může spustit pouze přihlášený admin.</p>
        <p><a href="/admin.php">Přihlásit se jako admin</a></p>
    ');
}

try {
    $pdo = getDbConnection();

    // KROK 1: Vyčistit staré dokončené úkoly
    $deleted = $pdo->exec("
        DELETE FROM wgs_pending_actions
        WHERE status IN ('completed', 'failed', 'cancelled')
    ");

    // KROK 2: Přidat 3 nové produkční úkoly

    // Úkol 1: Database indexy
    $stmt = $pdo->prepare("
        INSERT INTO wgs_pending_actions (
            action_type,
            action_title,
            action_description,
            action_url,
            priority,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        'migration',
        '🚀 PRODUKCE: Přidat databázové indexy (47 indexů)',
        'Přidá 47 performance indexů do databáze. Zrychlí WHERE/JOIN/ORDER BY queries o 2-10x.

Script: scripts/add_database_indexes.php

Co to dělá:
- Indexy na wgs_reklamace (stav, user_id, created_at, cislo)
- Indexy na wgs_users (email, is_active)
- Indexy na wgs_email_queue (status, scheduled_at, priority)
- Composite indexy pro složité queries

Riziko: NÍZKÉ - pouze přidává indexy, nemění data
Dopad: Výrazné zrychlení aplikace',
        'scripts/add_database_indexes.php',
        'high',
        'pending'
    ]);

    // Úkol 2: Foreign Keys
    $stmt->execute([
        'migration',
        '🔗 PRODUKCE: Přidat Foreign Key constraints',
        'Přidá FK constraints pro referenční integritu mezi tabulkami.

Script: scripts/add_foreign_keys.php

⚠️ DŮLEŽITÉ: Nejdřív vyčistit orphan záznamy!
Spusť tento script v safe módu, který nejdřív zkontroluje:
- wgs_reklamace.user_id → wgs_users.id
- wgs_email_queue.user_id → wgs_users.id
- wgs_notifications.user_id → wgs_users.id
- wgs_pending_actions.assigned_to → wgs_users.id

Pokud najde orphan záznamy, vypíše je a NEZRUŠÍ se constraint.

Riziko: STŘEDNÍ - může failnout pokud jsou orphan data
Dopad: Zajištění referenční integrity',
        'scripts/add_foreign_keys.php',
        'high',
        'pending'
    ]);

    // Úkol 3: Setup security
    $stmt->execute([
        'migration',
        '🔐 PRODUKCE: Zabezpečit setup/ adresář',
        'Zkopíruje setup/.htaccess.production → setup/.htaccess

Co to dělá:
- Zablokuje VEŠKERÝ přístup k /setup/ adresáři v produkci
- Zabrání spuštění setup scriptů (SQL migration, instalace, atd.)
- Apache 2.2 i 2.4 kompatibilní konfigurace

⚠️ KRITICKÉ: Po spuštění už nebudeš moci přistupovat k setup scriptům!
Pokud budeš potřebovat setup script, musíš:
1. Zkopírovat setup/.htaccess.localhost → setup/.htaccess
2. Spustit script
3. Vrátit setup/.htaccess.production → setup/.htaccess

Riziko: ŽÁDNÉ - jen kopíruje konfigurační soubor
Dopad: Zabezpečení proti neoprávněnému přístupu k setup scriptům',
        'scripts/secure_setup_directory.php',
        'critical',
        'pending'
    ]);

    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>✅ Produkční úkoly přidány</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 16px;
                padding: 40px;
                max-width: 600px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            h1 {
                font-size: 2rem;
                color: #1a1a1a;
                margin-bottom: 20px;
            }
            .success-icon {
                font-size: 4rem;
                text-align: center;
                margin-bottom: 20px;
            }
            .stats {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .stat-item {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #dee2e6;
            }
            .stat-item:last-child {
                border-bottom: none;
            }
            .stat-label {
                color: #6c757d;
            }
            .stat-value {
                font-weight: 600;
                color: #28a745;
            }
            .tasks-list {
                margin: 20px 0;
            }
            .task {
                padding: 15px;
                margin: 10px 0;
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                border-radius: 4px;
            }
            .task.high {
                background: #f8d7da;
                border-left-color: #dc3545;
            }
            .task.critical {
                background: #f8d7da;
                border-left-color: #721c24;
            }
            .btn {
                display: inline-block;
                padding: 15px 30px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                margin-top: 20px;
                transition: all 0.2s;
            }
            .btn:hover {
                background: #5568d3;
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            }
            .note {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                border-radius: 4px;
                padding: 15px;
                margin: 20px 0;
                color: #0c5460;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">✅</div>
            <h1>Produkční úkoly úspěšně přidány!</h1>

            <div class="stats">
                <div class="stat-item">
                    <span class="stat-label">Odstraněno starých úkolů:</span>
                    <span class="stat-value"><?= $deleted ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Přidáno nových úkolů:</span>
                    <span class="stat-value">3</span>
                </div>
            </div>

            <h2 style="margin: 30px 0 15px 0; font-size: 1.2rem;">📋 Přidané úkoly:</h2>
            <div class="tasks-list">
                <div class="task high">
                    <strong>🚀 Databázové indexy (47 indexů)</strong><br>
                    <small>Priorita: HIGH</small>
                </div>
                <div class="task high">
                    <strong>🔗 Foreign Key constraints</strong><br>
                    <small>Priorita: HIGH</small>
                </div>
                <div class="task critical">
                    <strong>🔐 Zabezpečit setup/ adresář</strong><br>
                    <small>Priorita: CRITICAL</small>
                </div>
            </div>

            <div class="note">
                <strong>💡 Co dělat teď:</strong><br>
                1. Jdi do Control Center → Akce & Úkoly<br>
                2. Uvidíš tam tyto 3 nové úkoly<br>
                3. Přečti si každý úkol (co dělá, rizika)<br>
                4. Spusť je jedním kliknutím<br>
                5. Hotovo! 🎉
            </div>

            <a href="/admin.php?tab=control_center" class="btn">
                → Otevřít Control Center
            </a>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>❌ Chyba</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
                background: #f8d7da;
                padding: 40px;
                text-align: center;
            }
            .error {
                background: white;
                border: 2px solid #dc3545;
                border-radius: 8px;
                padding: 30px;
                max-width: 600px;
                margin: 0 auto;
            }
            h1 { color: #dc3545; }
            code {
                background: #f8f9fa;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>❌ Chyba při přidávání úkolů</h1>
            <p><?= htmlspecialchars($e->getMessage()) ?></p>
            <p style="margin-top: 20px;">
                <a href="/admin.php" style="color: #667eea;">← Zpět na admin</a>
            </p>
        </div>
    </body>
    </html>
    <?php
}
