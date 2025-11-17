-- ========================================================
-- SPRÁVNÝ INSERT STATEMENT PRO wgs_reklamace
-- ========================================================
-- Datum: 2025-11-17
-- Účel: Použít pro testování nebo vkládání dat
-- ========================================================

INSERT INTO `wgs_reklamace` (
    -- ========================================
    -- POZNÁMKA: `id` vynecháno - AUTO_INCREMENT
    -- POZNÁMKA: `reklamace_id` generuje automaticky save.php
    -- ========================================

    -- Základní údaje
    `typ`,                    -- ENUM('REKLAMACE', 'INSTALACE', 'SERVIS')
    `cislo`,                  -- VARCHAR(100) - Číslo objednávky
    `datum_prodeje`,          -- DATE
    `datum_reklamace`,        -- DATE

    -- Kontaktní údaje zákazníka (POVINNÉ)
    `jmeno`,                  -- VARCHAR(255) NOT NULL
    `email`,                  -- VARCHAR(255) NOT NULL
    `telefon`,                -- VARCHAR(50) NOT NULL

    -- Adresa (složená + komponenty)
    `adresa`,                 -- VARCHAR(500) - Složená "ulice, město, PSČ"
    `ulice`,                  -- VARCHAR(255) ✅ POTŘEBA!
    `mesto`,                  -- VARCHAR(255) ✅ POTŘEBA!
    `psc`,                    -- VARCHAR(20) ✅ POTŘEBA!

    -- Údaje o produktu
    `model`,                  -- VARCHAR(255)
    `seriove_cislo`,          -- VARCHAR(255)
    `provedeni`,              -- VARCHAR(255)
    `barva`,                  -- VARCHAR(100)

    -- Popis problému a řešení
    `popis_problemu`,         -- TEXT (POVINNÉ ve formuláři)
    `doplnujici_info`,        -- TEXT
    `popis_opravy`,           -- TEXT (vyplňuje se v protokolu)

    -- Stav a termín
    `stav`,                   -- ENUM('wait', 'open', 'done') DEFAULT 'wait'
    `termin`,                 -- DATE
    `cas_navstevy`,           -- VARCHAR(50) např. '9:00-12:00'
    `vyreseno`,               -- TINYINT(1) DEFAULT 0

    -- Zpracování a lidé
    `zpracoval`,              -- VARCHAR(255) - DEPRECATED, používat zpracoval_id
    `zpracoval_id`,           -- INT(11) - ID uživatele z wgs_users
    `prodejce`,               -- VARCHAR(255) - Jméno prodejce
    `technik`,                -- VARCHAR(255) ✅ SPRÁVNÝ! např. 'Milan Kolín' nebo 'Radek Zikmund'

    -- Fakturace a částky
    `cena`,                   -- DECIMAL(10,2)
    `castka`,                 -- DECIMAL(10,2) - Duplikát ceny
    `fakturace_firma`,        -- ENUM('cz', 'sk') DEFAULT 'cz'
    `zeme`,                   -- VARCHAR(2) - Duplikát fakturace_firma

    -- Protokol a datumy
    `datum_protokolu`,        -- TIMESTAMP NULL
    `datum_dokonceni`,        -- TIMESTAMP NULL

    -- Metadata
    `poznamky`,               -- TEXT - Interní poznámky
    `email_zadavatele`,       -- VARCHAR(255)
    `created_by`,             -- INT(11) - ID tvůrce záznamu
    `created_by_role`,        -- VARCHAR(20) - Role ('admin', 'user', 'guest')
    `created_at`,             -- TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    `updated_at`              -- TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

) VALUES (
    -- Základní údaje
    'REKLAMACE',                           -- typ
    'OBJ-2025-001',                        -- cislo
    '2024-06-15',                          -- datum_prodeje
    '2025-11-17',                          -- datum_reklamace

    -- Kontaktní údaje
    'Jan Novák',                           -- jmeno
    'jan.novak@example.cz',                -- email
    '+420 777 123 456',                    -- telefon

    -- Adresa
    'Hlavní 123, Praha 1, 110 00',        -- adresa (složená)
    'Hlavní 123',                          -- ulice
    'Praha 1',                             -- mesto
    '110 00',                              -- psc

    -- Produkt
    'Natuzzi Sofa XL',                     -- model
    'SN-2024-ABCD-1234',                   -- seriove_cislo
    'Kožené, hnědé provedení',             -- provedeni
    'Hnědá (BF12)',                        -- barva

    -- Problém
    'Propadlé sedadlo po 6 měsících používání', -- popis_problemu
    'Zákazník velmi nespokojený, požaduje opravu nebo výměnu. GDPR souhlas udělen 2025-11-17 12:34:56', -- doplnujici_info
    NULL,                                  -- popis_opravy (vyplní technik v protokolu)

    -- Stav
    'wait',                                -- stav (DB hodnota: wait/open/done)
    NULL,                                  -- termin (nastaví se později)
    NULL,                                  -- cas_navstevy
    0,                                     -- vyreseno (0 = ne, 1 = ano)

    -- Zpracování
    NULL,                                  -- zpracoval (deprecated)
    1,                                     -- zpracoval_id (ID uživatele)
    'Marie Svobodová',                     -- prodejce
    'Milan Kolín',                         -- ✅ technik VARCHAR(255)

    -- Fakturace
    5000.00,                               -- cena
    5000.00,                               -- castka (duplikát)
    'cz',                                  -- fakturace_firma (DB hodnota: cz/sk)
    'cz',                                  -- zeme (duplikát)

    -- Protokol
    NULL,                                  -- datum_protokolu
    NULL,                                  -- datum_dokonceni

    -- Metadata
    'Prvotní kontrola doporučena co nejdříve', -- poznamky
    'admin@wgs-service.cz',                -- email_zadavatele
    1,                                     -- created_by
    'admin',                               -- created_by_role
    NOW(),                                 -- created_at
    NOW()                                  -- updated_at
);

-- ========================================================
-- POZNÁMKY K POUŽÍVÁNÍ
-- ========================================================

/*
✅ CO JE SPRÁVNĚ:
==================
- `technik` je VARCHAR(255) - obsahuje jméno technika jako text
- `ulice`, `mesto`, `psc` jsou samostatné sloupce + `adresa` je složená
- `stav` používá DB hodnoty: 'wait', 'open', 'done' (ne české!)
- `fakturace_firma` používá: 'cz', 'sk' (lowercase!)

❌ CO UŽ NEPOUŽÍVAT:
====================
- `technik_milan_kolin` a `technik_radek_zikmund` - LEGACY sloupce
  Tyto sloupce EXISTUJÍ v tabulce pro zpětnou kompatibilitu, ale:
  • NEMĚLY BY SE POUŽÍVAT v nových záznamech
  • Statistiky je potřeba přepsat aby používaly sloupec `technik`
  • V novém kódu VŽDY používat `technik` VARCHAR(255)

📊 MAPOVÁNÍ HODNOT (Frontend ↔ Database):
==========================================
Frontend (JavaScript):        Database (SQL):
- 'ČEKÁ'           →          'wait'
- 'DOMLUVENÁ'      →          'open'
- 'HOTOVO'         →          'done'
- 'CZ'             →          'cz'
- 'SK'             →          'sk'

save.php automaticky provádí toto mapování!

📋 DUPLIKÁTY (pro zpětnou kompatibilitu):
==========================================
- cena = castka (stejná hodnota)
- fakturace_firma = zeme (stejná hodnota)
- adresa = složená z (ulice + mesto + psc)
- zpracoval_id místo zpracoval (text je deprecated)
*/

-- ========================================================
-- PŘÍKLAD 2: Minimální INSERT (pouze povinná pole)
-- ========================================================

INSERT INTO `wgs_reklamace` (
    `jmeno`,
    `email`,
    `telefon`,
    `popis_problemu`,
    `created_at`,
    `updated_at`
) VALUES (
    'Petr Dvořák',
    'petr.dvorak@example.cz',
    '+420 606 123 456',
    'Poškozené opěradlo',
    NOW(),
    NOW()
);

-- ========================================================
-- HOTOVO!
-- ========================================================
