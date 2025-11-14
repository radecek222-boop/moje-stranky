-- ========================================
-- PŘIDÁNÍ PRODUKČNÍCH AKCÍ DO CONTROL CENTER
-- ========================================
-- Použití: Spusť tento SQL v phpMyAdmin nebo mysql CLI
--
-- Po spuštění se tyto akce objeví v Control Center -> Akce & Úkoly
-- Můžeš je tam spustit jedním kliknutím
-- ========================================

-- KROK 1: Vyčistit všechny dokončené/selhavší úkoly (aby seznam byl čistý)
DELETE FROM wgs_pending_actions
WHERE status IN ('completed', 'failed', 'cancelled');

-- ========================================
-- KROK 2: Přidat 3 nové produkční úkoly
-- ========================================

-- 1. PRODUKCE: Přidat databázové indexy (47 indexů)
INSERT INTO wgs_pending_actions (
    action_type,
    action_title,
    action_description,
    action_url,
    priority,
    status,
    created_at
) VALUES (
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
    'pending',
    NOW()
);

-- 2. PRODUKCE: Přidat Foreign Key constraints
INSERT INTO wgs_pending_actions (
    action_type,
    action_title,
    action_description,
    action_url,
    priority,
    status,
    created_at
) VALUES (
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
    'pending',
    NOW()
);

-- 3. PRODUKCE: Zabezpečit setup/ adresář
INSERT INTO wgs_pending_actions (
    action_type,
    action_title,
    action_description,
    action_url,
    priority,
    status,
    created_at
) VALUES (
    'config',
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
    'setup/.htaccess.production',
    'critical',
    'pending',
    NOW()
);

-- ========================================
-- HOTOVO!
-- ========================================
-- Po spuštění tohoto SQL:
-- 1. Jdi do Control Center -> Akce & Úkoly
-- 2. Uvidíš tam 3 nové pending actions
-- 3. Klikni na akci a "Spustit"
-- 4. Control Center spustí příslušný script
-- ========================================
