<?php
/**
 * SEO Meta Tags Helper
 * Centralizovaná správa SEO meta tagů pro všechny stránky
 */

if (!function_exists('get_page_seo_meta')) {
    /**
     * Vrátí SEO meta data pro konkrétní stránku
     *
     * @param string $page Název stránky (basename nebo URI)
     * @return array ['title' => string, 'description' => string, 'keywords' => string]
     */
    function get_page_seo_meta(string $page = ''): array
    {
        // Pokud není zadána stránka, určit z REQUEST_URI
        if (empty($page)) {
            $page = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        }

        // Odstranit .php extension pro snadnější matching
        $pageKey = str_replace('.php', '', $page);

        // Definice SEO meta tagů pro všechny stránky
        $seoMeta = [
            // === HLAVNÍ STRÁNKY ===
            'index' => [
                'title' => 'White Glove Service | Profesionální správa reklamací',
                'description' => 'WGS Service - komplexní správa reklamací, protokolů a dokumentace. Moderní řešení pro efektivní workflow.',
                'keywords' => 'reklamace, správa reklamací, protokoly, WGS, white glove service'
            ],

            'login' => [
                'title' => 'Přihlášení | WGS Service',
                'description' => 'Přihlaste se do systému White Glove Service pro správu reklamací a protokolů.',
                'keywords' => 'přihlášení, login, WGS'
            ],

            'admin' => [
                'title' => 'Administrace | WGS Service',
                'description' => 'Administrativní rozhraní pro správu systému WGS Service.',
                'keywords' => 'admin, administrace, správa'
            ],

            // === PSA KALKULÁTOR ===
            'psa-kalkulator' => [
                'title' => 'PSA Kalkulátor | White Glove Service',
                'description' => 'Online kalkulátor pro výpočet PSA hodnot. Rychlý a přesný nástroj pro profesionály.',
                'keywords' => 'PSA, kalkulátor, výpočet'
            ],

            'psa' => [
                'title' => 'PSA Kalkulátor | White Glove Service',
                'description' => 'Online kalkulátor pro výpočet PSA hodnot. Rychlý a přesný nástroj pro profesionály.',
                'keywords' => 'PSA, kalkulátor, výpočet'
            ],

            // === REKLAMACE ===
            'reklamace' => [
                'title' => 'Reklamace | WGS Service',
                'description' => 'Správa a evidence reklamací v systému White Glove Service.',
                'keywords' => 'reklamace, evidence, správa'
            ],

            'seznam' => [
                'title' => 'Seznam reklamací | WGS Service',
                'description' => 'Přehled všech reklamací a jejich stavu v systému WGS.',
                'keywords' => 'reklamace, seznam, přehled'
            ],

            // === PROTOKOLY ===
            'protokol' => [
                'title' => 'Protokol | WGS Service',
                'description' => 'Správa a tvorba protokolů v systému White Glove Service.',
                'keywords' => 'protokol, dokumentace, evidence'
            ],

            // === UTILITY & DIAGNOSTIC (NOINDEX) ===
            'diagnostic_tool' => [
                'title' => 'Diagnostic Tool | WGS Service',
                'description' => 'Internal diagnostic tool for system administrators.',
                'keywords' => 'diagnostic, admin, tool',
                'robots' => 'noindex, nofollow'
            ],

            'diagnostic_web' => [
                'title' => 'Web Diagnostics | WGS Service',
                'description' => 'Internal web diagnostic tool.',
                'keywords' => 'diagnostic, web, admin',
                'robots' => 'noindex, nofollow'
            ],

            'diagnostic_access_control' => [
                'title' => 'Access Control Diagnostics | WGS Service',
                'description' => 'Internal access control diagnostic tool.',
                'keywords' => 'diagnostic, access control, admin',
                'robots' => 'noindex, nofollow'
            ],

            'quick_debug' => [
                'title' => 'Quick Debug | WGS Service',
                'description' => 'Internal debugging tool.',
                'keywords' => 'debug, admin, tool',
                'robots' => 'noindex, nofollow'
            ],

            // === API ENDPOINTS (NOINDEX) ===
            'admin_api' => [
                'title' => 'Admin API | WGS Service',
                'description' => 'Internal API endpoint.',
                'keywords' => 'api, admin',
                'robots' => 'noindex, nofollow'
            ],

            'control_center_api' => [
                'title' => 'Control Center API | WGS Service',
                'description' => 'Internal API endpoint.',
                'keywords' => 'api, control center',
                'robots' => 'noindex, nofollow'
            ],

            'notification_api' => [
                'title' => 'Notification API | WGS Service',
                'description' => 'Internal API endpoint.',
                'keywords' => 'api, notifications',
                'robots' => 'noindex, nofollow'
            ],

            'protokol_api' => [
                'title' => 'Protokol API | WGS Service',
                'description' => 'Internal API endpoint.',
                'keywords' => 'api, protokol',
                'robots' => 'noindex, nofollow'
            ],

            'statistiky_api' => [
                'title' => 'Statistiky API | WGS Service',
                'description' => 'Internal API endpoint.',
                'keywords' => 'api, statistiky',
                'robots' => 'noindex, nofollow'
            ],

            // === UTILITY PAGES (NOINDEX) ===
            'phpinfo_test' => [
                'title' => 'PHP Info | WGS Service',
                'description' => 'PHP configuration information.',
                'keywords' => 'php, info, admin',
                'robots' => 'noindex, nofollow'
            ],

            'health' => [
                'title' => 'Health Check | WGS Service',
                'description' => 'System health monitoring endpoint.',
                'keywords' => 'health, monitoring',
                'robots' => 'noindex, nofollow'
            ],

            'logout' => [
                'title' => 'Odhlášení | WGS Service',
                'description' => 'Odhlášení ze systému WGS Service.',
                'keywords' => 'logout, odhlášení',
                'robots' => 'noindex, nofollow'
            ],

            // === PHOTO UTILITIES (NOINDEX) ===
            'check_photos_db' => [
                'title' => 'Photo Database Check | WGS Service',
                'description' => 'Internal photo database diagnostic tool.',
                'keywords' => 'photos, database, diagnostic',
                'robots' => 'noindex, nofollow'
            ],

            'copy_photo' => [
                'title' => 'Copy Photo Utility | WGS Service',
                'description' => 'Internal photo copy utility.',
                'keywords' => 'photos, copy, utility',
                'robots' => 'noindex, nofollow'
            ],

            'count_photos' => [
                'title' => 'Photo Counter | WGS Service',
                'description' => 'Internal photo counting utility.',
                'keywords' => 'photos, count, utility',
                'robots' => 'noindex, nofollow'
            ],

            'photos_list' => [
                'title' => 'Photos List | WGS Service',
                'description' => 'Internal photo listing utility.',
                'keywords' => 'photos, list, utility',
                'robots' => 'noindex, nofollow'
            ],

            'photos_test' => [
                'title' => 'Photos Test | WGS Service',
                'description' => 'Internal photo testing utility.',
                'keywords' => 'photos, test, utility',
                'robots' => 'noindex, nofollow'
            ],

            // === DATABASE UTILITIES (NOINDEX) ===
            'db_test' => [
                'title' => 'Database Test | WGS Service',
                'description' => 'Internal database testing utility.',
                'keywords' => 'database, test, utility',
                'robots' => 'noindex, nofollow'
            ],

            'create_missing_tables' => [
                'title' => 'Create Tables | WGS Service',
                'description' => 'Internal database setup utility.',
                'keywords' => 'database, tables, setup',
                'robots' => 'noindex, nofollow'
            ],

            'show_table_structure' => [
                'title' => 'Table Structure | WGS Service',
                'description' => 'Internal database structure viewer.',
                'keywords' => 'database, structure, admin',
                'robots' => 'noindex, nofollow'
            ],

            // === SMTP UTILITIES (NOINDEX) ===
            'add_smtp_task' => [
                'title' => 'Add SMTP Task | WGS Service',
                'description' => 'Internal SMTP task utility.',
                'keywords' => 'smtp, task, admin',
                'robots' => 'noindex, nofollow'
            ],

            'check_and_add_smtp_task' => [
                'title' => 'Check SMTP Task | WGS Service',
                'description' => 'Internal SMTP task checker.',
                'keywords' => 'smtp, task, check',
                'robots' => 'noindex, nofollow'
            ],

            'run_add_smtp_task' => [
                'title' => 'Run SMTP Task | WGS Service',
                'description' => 'Internal SMTP task runner.',
                'keywords' => 'smtp, task, run',
                'robots' => 'noindex, nofollow'
            ],

            // === FIX UTILITIES (NOINDEX) ===
            'fix_photo_ids' => [
                'title' => 'Fix Photo IDs | WGS Service',
                'description' => 'Internal photo ID repair utility.',
                'keywords' => 'photos, fix, utility',
                'robots' => 'noindex, nofollow'
            ],

            'fix_photo_path' => [
                'title' => 'Fix Photo Paths | WGS Service',
                'description' => 'Internal photo path repair utility.',
                'keywords' => 'photos, fix, paths',
                'robots' => 'noindex, nofollow'
            ],

            'fix_visibility' => [
                'title' => 'Fix Visibility | WGS Service',
                'description' => 'Internal visibility repair utility.',
                'keywords' => 'visibility, fix, utility',
                'robots' => 'noindex, nofollow'
            ],

            'cleanup_history_record' => [
                'title' => 'Cleanup History | WGS Service',
                'description' => 'Internal history cleanup utility.',
                'keywords' => 'cleanup, history, utility',
                'robots' => 'noindex, nofollow'
            ],

            'verify_and_cleanup' => [
                'title' => 'Verify & Cleanup | WGS Service',
                'description' => 'Internal verification and cleanup utility.',
                'keywords' => 'verify, cleanup, utility',
                'robots' => 'noindex, nofollow'
            ],

            // === TESTING UTILITIES (NOINDEX) ===
            'api_test' => [
                'title' => 'API Test | WGS Service',
                'description' => 'Internal API testing utility.',
                'keywords' => 'api, test, utility',
                'robots' => 'noindex, nofollow'
            ],

            'path_test' => [
                'title' => 'Path Test | WGS Service',
                'description' => 'Internal path testing utility.',
                'keywords' => 'path, test, utility',
                'robots' => 'noindex, nofollow'
            ],

            'role_testing_tool' => [
                'title' => 'Role Testing Tool | WGS Service',
                'description' => 'Internal role testing utility.',
                'keywords' => 'roles, testing, utility',
                'robots' => 'noindex, nofollow'
            ],

            'whereami' => [
                'title' => 'Where Am I | WGS Service',
                'description' => 'Internal location testing utility.',
                'keywords' => 'location, test, utility',
                'robots' => 'noindex, nofollow'
            ],

            // === MIGRATION UTILITIES (NOINDEX) ===
            'migrate_photos' => [
                'title' => 'Migrate Photos | WGS Service',
                'description' => 'Internal photo migration utility.',
                'keywords' => 'photos, migration, utility',
                'robots' => 'noindex, nofollow'
            ],

            'insert_photo' => [
                'title' => 'Insert Photo | WGS Service',
                'description' => 'Internal photo insertion utility.',
                'keywords' => 'photos, insert, utility',
                'robots' => 'noindex, nofollow'
            ],

            'create_upload_dir' => [
                'title' => 'Create Upload Dir | WGS Service',
                'description' => 'Internal directory creation utility.',
                'keywords' => 'upload, directory, utility',
                'robots' => 'noindex, nofollow'
            ],

            'find_all_photos' => [
                'title' => 'Find All Photos | WGS Service',
                'description' => 'Internal photo finder utility.',
                'keywords' => 'photos, find, utility',
                'robots' => 'noindex, nofollow'
            ],

            'last_claim_id' => [
                'title' => 'Last Claim ID | WGS Service',
                'description' => 'Internal claim ID utility.',
                'keywords' => 'claim, id, utility',
                'robots' => 'noindex, nofollow'
            ],

            'diagnostic_reklamace' => [
                'title' => 'Diagnostika Reklamací | WGS Service',
                'description' => 'Internal reklamace diagnostic tool.',
                'keywords' => 'diagnostic, reklamace, admin',
                'robots' => 'noindex, nofollow'
            ],

            'admin_key_manager' => [
                'title' => 'Admin Key Manager | WGS Service',
                'description' => 'Internal admin key management.',
                'keywords' => 'admin, keys, security',
                'robots' => 'noindex, nofollow'
            ],

            'php_copy_http' => [
                'title' => 'PHP Copy HTTP | WGS Service',
                'description' => 'Internal HTTP copy utility.',
                'keywords' => 'php, copy, http',
                'robots' => 'noindex, nofollow'
            ],
        ];

        // Default meta tags pokud stránka není definovaná
        $default = [
            'title' => 'White Glove Service | Profesionální správa reklamací',
            'description' => 'WGS Service - moderní systém pro správu reklamací, protokolů a dokumentace.',
            'keywords' => 'WGS, reklamace, protokoly, správa',
            'robots' => 'index, follow'
        ];

        // Vrátit meta data pro stránku nebo default
        $meta = $seoMeta[$pageKey] ?? $default;

        // Přidat robots pokud není definováno
        if (!isset($meta['robots'])) {
            $meta['robots'] = 'index, follow';
        }

        return $meta;
    }
}

if (!function_exists('render_seo_meta_tags')) {
    /**
     * Vykreslí SEO meta tagy jako HTML
     *
     * @param string $page Název stránky
     * @return void (echoes HTML)
     */
    function render_seo_meta_tags(string $page = ''): void
    {
        $meta = get_page_seo_meta($page);

        echo '<meta charset="UTF-8">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        echo '<title>' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
        echo '<meta name="description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="keywords" content="' . htmlspecialchars($meta['keywords'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="robots" content="' . htmlspecialchars($meta['robots'], ENT_QUOTES, 'UTF-8') . '">' . "\n";

        // Open Graph tags (pro social media)
        echo '<meta property="og:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";

        // Twitter Card tags
        echo '<meta name="twitter:card" content="summary">' . "\n";
        echo '<meta name="twitter:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="twitter:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
}
