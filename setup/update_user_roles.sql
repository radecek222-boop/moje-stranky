-- UPDATE: Nastavení rolí pro existující uživatele
-- Datum: 2025-11-10
-- Účel: Připravit databázi pro role-based přístup

-- 1. Zkontroluj existující uživatele
SELECT
    id,
    email,
    role,
    is_admin,
    name
FROM wgs_users
ORDER BY id;

-- 2. Nastav role podle typu práce:

-- Příklad: Naty je prodejce (vytváří reklamace pro zákazníky)
-- UPRAV podle skutečnosti:
UPDATE wgs_users
SET role = 'prodejce'
WHERE email = 'naty@naty.cz';

-- Pokud máte techniky, nastavte jim role 'technik':
-- UPDATE wgs_users SET role = 'technik' WHERE email = 'milan@firma.cz';
-- UPDATE wgs_users SET role = 'technik' WHERE email = 'radek@firma.cz';

-- Pokud máte další prodejce:
-- UPDATE wgs_users SET role = 'prodejce' WHERE email = 'prodejce2@firma.cz';
-- UPDATE wgs_users SET role = 'prodejce' WHERE email = 'prodejce3@firma.cz';

-- Admini by měli mít is_admin=1 (role není tak důležitá)
-- UPDATE wgs_users SET is_admin = 1 WHERE email = 'admin@wgs-service.cz';

-- 3. Ověř změny
SELECT
    id,
    email,
    role,
    is_admin,
    name,
    CASE
        WHEN is_admin = 1 THEN 'ADMIN - vidí vše'
        WHEN role IN ('prodejce', 'user') THEN 'PRODEJCE - vidí všechny reklamace'
        WHEN role IN ('technik', 'technician') THEN 'TECHNIK - vidí pouze přiřazené'
        ELSE 'GUEST - vidí pouze své (email match)'
    END as access_level
FROM wgs_users
ORDER BY id;

-- Očekávaný výsledek:
-- naty@naty.cz → role='prodejce' → vidí VŠECHNY reklamace
-- Ostatní podle jejich skutečné role
