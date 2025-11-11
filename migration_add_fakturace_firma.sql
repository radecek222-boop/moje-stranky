-- Migration: Přidání sloupce fakturace_firma pro CZ/SK označení
-- Účel: Provázet zákazníka jako CZ nebo SK skrz celý workflow
-- Datum: 2025-01-11

-- Přidat sloupec fakturace_firma pokud neexistuje
ALTER TABLE wgs_reklamace
ADD COLUMN IF NOT EXISTS fakturace_firma VARCHAR(2) DEFAULT 'CZ'
COMMENT 'CZ nebo SK firma pro fakturaci';

-- Vytvořit index pro rychlé filtrování
CREATE INDEX IF NOT EXISTS idx_fakturace_firma ON wgs_reklamace(fakturace_firma);

-- Nastavit výchozí hodnotu CZ pro existující záznamy bez hodnoty
UPDATE wgs_reklamace
SET fakturace_firma = 'CZ'
WHERE fakturace_firma IS NULL OR fakturace_firma = '';
