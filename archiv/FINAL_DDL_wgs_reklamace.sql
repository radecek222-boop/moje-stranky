-- ========================================================
-- FINÁLNÍ SQL DDL PRO TABULKU wgs_reklamace
-- Vygenerováno: 2025-11-16
-- Stav: KOMPLETNÍ se všemi sloupci z novareklamace.php
-- ========================================================

CREATE TABLE IF NOT EXISTS `wgs_reklamace` (
  -- ========================================
  -- PRIMÁRNÍ KLÍČE A IDENTIFIKÁTORY
  -- ========================================
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Interní ID záznamu',
  `reklamace_id` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Veřejné ID reklamace (např. RK-2024-001)',

  -- ========================================
  -- TYP A ZÁKLADNÍ ÚDAJE
  -- ========================================
  `typ` ENUM('REKLAMACE', 'INSTALACE', 'SERVIS') NOT NULL DEFAULT 'REKLAMACE' COMMENT 'Typ požadavku',
  `cislo` VARCHAR(100) DEFAULT NULL COMMENT 'Číslo objednávky/faktury',
  `datum_prodeje` DATE DEFAULT NULL COMMENT 'Datum prodeje/nákupu',
  `datum_reklamace` DATE DEFAULT NULL COMMENT 'Datum podání reklamace',

  -- ========================================
  -- KONTAKTNÍ ÚDAJE ZÁKAZNÍKA
  -- ========================================
  `jmeno` VARCHAR(255) NOT NULL COMMENT 'Jméno a příjmení zákazníka',
  `email` VARCHAR(255) NOT NULL COMMENT 'Email zákazníka',
  `telefon` VARCHAR(50) NOT NULL COMMENT 'Telefonní číslo zákazníka',

  -- ========================================
  -- ADRESA (složená + komponenty)
  -- ========================================
  `adresa` VARCHAR(500) DEFAULT NULL COMMENT 'Kompletní adresa (ulice, město, PSČ)',
  `ulice` VARCHAR(255) DEFAULT NULL COMMENT 'Ulice a číslo popisné',
  `mesto` VARCHAR(255) DEFAULT NULL COMMENT 'Město',
  `psc` VARCHAR(20) DEFAULT NULL COMMENT 'PSČ',

  -- ========================================
  -- ÚDAJE O PRODUKTU
  -- ========================================
  `model` VARCHAR(255) DEFAULT NULL COMMENT 'Model výrobku',
  `seriove_cislo` VARCHAR(255) DEFAULT NULL COMMENT 'Sériové číslo výrobku',
  `provedeni` VARCHAR(255) DEFAULT NULL COMMENT 'Provedení (barva, materiál)',
  `barva` VARCHAR(100) DEFAULT NULL COMMENT 'Barva výrobku',

  -- ========================================
  -- POPIS PROBLÉMU A ŘEŠENÍ
  -- ========================================
  `popis_problemu` TEXT DEFAULT NULL COMMENT 'Popis problému od zákazníka',
  `doplnujici_info` TEXT DEFAULT NULL COMMENT 'Doplňující informace',
  `popis_opravy` TEXT DEFAULT NULL COMMENT 'Popis provedené opravy (protokol)',

  -- ========================================
  -- STAV A TERMÍN
  -- ========================================
  `stav` ENUM('wait', 'open', 'done') NOT NULL DEFAULT 'wait' COMMENT 'Stav reklamace: wait=čeká, open=domluven, done=hotovo',
  `termin` DATE DEFAULT NULL COMMENT 'Termín návštěvy technika',
  `cas_navstevy` VARCHAR(50) DEFAULT NULL COMMENT 'Čas návštěvy (např. 9:00-12:00)',
  `vyreseno` TINYINT(1) DEFAULT 0 COMMENT 'Bylo vyřešeno? (1=ano, 0=ne)',

  -- ========================================
  -- ZPRACOVÁNÍ A LIDÉ
  -- ========================================
  `zpracoval` VARCHAR(255) DEFAULT NULL COMMENT 'Jméno osoby která zpracovala (deprecated)',
  `zpracoval_id` INT(11) DEFAULT NULL COMMENT 'ID uživatele který zpracoval',
  `prodejce` VARCHAR(255) DEFAULT NULL COMMENT 'Jméno prodejce',
  `technik` VARCHAR(255) DEFAULT NULL COMMENT 'Jméno technika přiřazeného k zakázce',

  -- Legacy technik sloupce (pro zpětnou kompatibilitu)
  `technik_milan_kolin` TINYINT(1) DEFAULT 0 COMMENT 'Legacy: Technik Milan Kolín přiřazen',
  `technik_radek_zikmund` TINYINT(1) DEFAULT 0 COMMENT 'Legacy: Technik Radek Zikmund přiřazen',

  -- ========================================
  -- FAKTURACE A ČÁSTKY
  -- ========================================
  `cena` DECIMAL(10,2) DEFAULT NULL COMMENT 'Cena opravy/servisu',
  `castka` DECIMAL(10,2) DEFAULT NULL COMMENT 'Částka (duplikát ceny)',
  `fakturace_firma` ENUM('cz', 'sk') DEFAULT 'cz' COMMENT 'Fakturační země: cz=Česko, sk=Slovensko',
  `zeme` VARCHAR(2) DEFAULT NULL COMMENT 'Země (duplikát fakturace_firma)',

  -- ========================================
  -- PROTOKOL A DATUMY
  -- ========================================
  `datum_protokolu` TIMESTAMP NULL DEFAULT NULL COMMENT 'Kdy byl vyplněn protokol',
  `datum_dokonceni` TIMESTAMP NULL DEFAULT NULL COMMENT 'Kdy byla reklamace dokončena',

  -- ========================================
  -- POZNÁMKY A METADATA
  -- ========================================
  `poznamky` TEXT DEFAULT NULL COMMENT 'Interní poznámky',
  `email_zadavatele` VARCHAR(255) DEFAULT NULL COMMENT 'Email osoby která vytvořila záznam',

  -- ========================================
  -- VYTVOŘENÍ A ZMĚNY
  -- ========================================
  `created_by` INT(11) DEFAULT NULL COMMENT 'ID uživatele který vytvořil záznam',
  `created_by_role` VARCHAR(20) DEFAULT NULL COMMENT 'Role uživatele (admin, user, guest)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum vytvoření záznamu',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Datum poslední aktualizace',

  -- ========================================
  -- INDEXY PRO RYCHLOST
  -- ========================================
  INDEX `idx_stav` (`stav`),
  INDEX `idx_typ` (`typ`),
  INDEX `idx_email` (`email`),
  INDEX `idx_termin` (`termin`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_zpracoval_id` (`zpracoval_id`),
  INDEX `idx_technik` (`technik`),
  INDEX `idx_prodejce` (`prodejce`),
  INDEX `idx_zeme` (`zeme`),
  INDEX `idx_fakturace_firma` (`fakturace_firma`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hlavní tabulka pro reklamace, instalace a servisní požadavky';

-- ========================================
-- POZNÁMKY K SLOUPKŮM
-- ========================================

/*
ENUM MAPPING (důležité!):
==========================
Frontend (JavaScript) používá ČESKÁ VELKÁ písmena:
- Stav: 'ČEKÁ', 'DOMLUVENÁ', 'HOTOVO'
- Země: 'CZ', 'SK'

Backend (save.php) automaticky mapuje na databázové hodnoty:
- 'ČEKÁ' → 'wait'
- 'DOMLUVENÁ' → 'open'
- 'HOTOVO' → 'done'
- 'CZ' → 'cz'
- 'SK' → 'sk'

DUPLIKÁTY (pro zpětnou kompatibilitu):
=======================================
- adresa + ulice + mesto + psc: adresa je složená, komponenty se ukládají zvlášť
- cena + castka: duplikát (statistiky používají castka)
- fakturace_firma + zeme: duplikát (statistiky používají zeme)
- zpracoval + zpracoval_id: deprecated zpracoval, nově se používá zpracoval_id

LEGACY SLOUPKY:
===============
- technik_milan_kolin, technik_radek_zikmund: staré boolean sloupce
- Nově se používá jen sloupec 'technik' VARCHAR(255)
*/

-- ========================================
-- PŘÍKLAD VLOŽENÍ ZÁZNAMU
-- ========================================

/*
INSERT INTO wgs_reklamace (
    reklamace_id, typ, cislo, datum_prodeje, datum_reklamace,
    jmeno, email, telefon,
    adresa, ulice, mesto, psc,
    model, seriove_cislo, provedeni, barva,
    popis_problemu, doplnujici_info,
    stav, termin, cas_navstevy,
    zpracoval_id, prodejce, technik,
    cena, castka, fakturace_firma, zeme,
    created_by, created_by_role, email_zadavatele
) VALUES (
    'RK-2025-001',                          -- reklamace_id
    'REKLAMACE',                            -- typ
    'FAK-2024-12345',                       -- cislo
    '2024-01-15',                           -- datum_prodeje
    '2025-11-16',                           -- datum_reklamace
    'Jan Novák',                            -- jmeno
    'jan.novak@email.cz',                   -- email
    '+420 777 123 456',                     -- telefon
    'Hlavní 123, Praha 1, 110 00',         -- adresa (složená)
    'Hlavní 123',                           -- ulice
    'Praha 1',                              -- mesto
    '110 00',                               -- psc
    'Natuzzi Sofa XL',                      -- model
    'SN-2024-ABCD-1234',                    -- seriove_cislo
    'Kožené, hnědé',                        -- provedeni
    'Hnědá',                                -- barva
    'Propadlé sedadlo po 6 měsících',      -- popis_problemu
    'Zákazník velmi nespokojený',          -- doplnujici_info
    'wait',                                 -- stav (DB hodnota)
    '2025-11-20',                           -- termin
    '9:00-12:00',                           -- cas_navstevy
    1,                                      -- zpracoval_id
    'Marie Svobodová',                      -- prodejce
    'Milan Kolín',                          -- technik
    5000.00,                                -- cena
    5000.00,                                -- castka (duplikát)
    'cz',                                   -- fakturace_firma (DB hodnota)
    'cz',                                   -- zeme (duplikát)
    1,                                      -- created_by
    'admin',                                -- created_by_role
    'admin@wgs-service.cz'                  -- email_zadavatele
);
*/

-- ========================================
-- HOTOVO!
-- ========================================
-- Celkem sloupců: 42
-- Indexů: 10
-- Charset: utf8mb4 (podporuje emoji a všechny jazyky)
-- Engine: InnoDB (podpora transakcí a foreign keys)
-- ========================================
