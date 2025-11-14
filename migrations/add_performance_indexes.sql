-- ==========================================
-- PERFORMANCE FIX: Přidání chybějících indexů
-- ==========================================
-- Datum: 2025-11-14
-- Popis: Přidává 21 databázových indexů pro zrychlení dotazů
-- Očekávaný výkon: 5-20x rychlejší načítání stránek
-- Čas zpracování: 1-5 minut (závisí na velikosti DB)
-- ==========================================

-- BEZPEČNOSTNÍ KONTROLA: Zkontrolujte že jste na správné databázi!
-- SELECT DATABASE();

-- ==========================================
-- wgs_reklamace - hlavní tabulka reklamací
-- ==========================================

-- Index pro vyhledávání podle reklamace_id (často používaný)
ALTER TABLE `wgs_reklamace` ADD INDEX IF NOT EXISTS `idx_reklamace_id` (`reklamace_id`);

-- Index pro vyhledávání podle čísla objednávky
ALTER TABLE `wgs_reklamace` ADD INDEX IF NOT EXISTS `idx_cislo` (`cislo`);

-- Index pro filtrování podle stavu (pending, completed, atd.)
ALTER TABLE `wgs_reklamace` ADD INDEX IF NOT EXISTS `idx_stav` (`stav`);

-- Index pro filtrování podle vytvářejícího uživatele
ALTER TABLE `wgs_reklamace` ADD INDEX IF NOT EXISTS `idx_created_by` (`created_by`);

-- Index pro řazení podle data vytvoření (DESC pro nejnovější první)
ALTER TABLE `wgs_reklamace` ADD INDEX IF NOT EXISTS `idx_created_at_desc` (`created_at` DESC);

-- Index pro filtrování podle přiřazeného technika
ALTER TABLE `wgs_reklamace` ADD INDEX IF NOT EXISTS `idx_assigned_to` (`assigned_to`);

-- Kompozitní index pro časté dotazy: stav + datum
ALTER TABLE `wgs_reklamace` ADD INDEX IF NOT EXISTS `idx_stav_created` (`stav`, `created_at` DESC);

-- ==========================================
-- wgs_photos - fotky k reklamacím
-- ==========================================

-- Index pro načítání fotek podle reklamace (N+1 query fix!)
ALTER TABLE `wgs_photos` ADD INDEX IF NOT EXISTS `idx_reklamace_id` (`reklamace_id`);

-- Index pro filtrování podle sekce (before, after, atd.)
ALTER TABLE `wgs_photos` ADD INDEX IF NOT EXISTS `idx_section_name` (`section_name`);

-- Kompozitní index pro řazení fotek v sekci
ALTER TABLE `wgs_photos` ADD INDEX IF NOT EXISTS `idx_reklamace_section_order` (`reklamace_id`, `section_name`, `photo_order`);

-- Index pro časové řazení
ALTER TABLE `wgs_photos` ADD INDEX IF NOT EXISTS `idx_uploaded_at` (`uploaded_at` DESC);

-- ==========================================
-- wgs_documents - dokumenty k reklamacím
-- ==========================================

-- Index pro načítání dokumentů podle ID reklamace (N+1 query fix!)
ALTER TABLE `wgs_documents` ADD INDEX IF NOT EXISTS `idx_claim_id` (`claim_id`);

-- Index pro načítání podle reklamace_id (alternativní lookup)
ALTER TABLE `wgs_documents` ADD INDEX IF NOT EXISTS `idx_reklamace_id` (`reklamace_id`);

-- Index pro časové řazení dokumentů
ALTER TABLE `wgs_documents` ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at` DESC);

-- ==========================================
-- wgs_users - uživatelé
-- ==========================================

-- Index pro přihlášení podle emailu (LOGIN query!)
ALTER TABLE `wgs_users` ADD INDEX IF NOT EXISTS `idx_email` (`email`);

-- Index pro filtrování podle role (admin, technik, atd.)
ALTER TABLE `wgs_users` ADD INDEX IF NOT EXISTS `idx_role` (`role`);

-- ==========================================
-- wgs_email_queue - fronta e-mailů
-- ==========================================

-- Index pro výběr pending e-mailů (processQueue!)
ALTER TABLE `wgs_email_queue` ADD INDEX IF NOT EXISTS `idx_status` (`status`);

-- Index pro naplánované e-maily
ALTER TABLE `wgs_email_queue` ADD INDEX IF NOT EXISTS `idx_scheduled_at` (`scheduled_at`);

-- Index pro priority řazení
ALTER TABLE `wgs_email_queue` ADD INDEX IF NOT EXISTS `idx_priority` (`priority` DESC);

-- Kompozitní index pro výběr e-mailů k odeslání
ALTER TABLE `wgs_email_queue` ADD INDEX IF NOT EXISTS `idx_queue_processing` (`status`, `scheduled_at`, `priority` DESC);

-- ==========================================
-- wgs_notes - poznámky
-- ==========================================

-- Index pro načítání poznámek podle reklamace
ALTER TABLE `wgs_notes` ADD INDEX IF NOT EXISTS `idx_claim_id` (`claim_id`);

-- ==========================================
-- HOTOVO!
-- ==========================================

-- Optimalizujte tabulky po přidání indexů (volitelné, ale doporučené)
-- OPTIMIZE TABLE `wgs_reklamace`;
-- OPTIMIZE TABLE `wgs_photos`;
-- OPTIMIZE TABLE `wgs_documents`;
-- OPTIMIZE TABLE `wgs_users`;
-- OPTIMIZE TABLE `wgs_email_queue`;
-- OPTIMIZE TABLE `wgs_notes`;

-- Ověřte že indexy byly vytvořeny:
-- SHOW INDEX FROM `wgs_reklamace`;
-- SHOW INDEX FROM `wgs_photos`;
-- SHOW INDEX FROM `wgs_documents`;
-- SHOW INDEX FROM `wgs_users`;
-- SHOW INDEX FROM `wgs_email_queue`;
-- SHOW INDEX FROM `wgs_notes`;
