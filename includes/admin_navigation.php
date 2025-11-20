<?php

if (!function_exists('loadAdminNavigation')) {
    /**
     * LoadAdminNavigation
     */
    function loadAdminNavigation(): array
    {
        return [
            'index' => [
                'href' => 'index.php',
                'header_label' => 'DOMŮ',
                'tab_label' => null,
                'tab' => null,
            ],
            'seznam' => [
                'href' => 'seznam.php',
                'header_label' => 'SEZNAM REKLAMACÍ',
                'tab_label' => null,
                'tab' => null,
            ],
            'novareklamace' => [
                'href' => 'novareklamace.php',
                'header_label' => 'NOVÁ REKLAMACE',
                'tab_label' => null,
                'tab' => null,
            ],
            'protokol' => [
                'href' => 'protokol.php',
                'header_label' => 'PROTOKOL',
                'tab_label' => null,
                'tab' => null,
            ],
            'statistiky' => [
                'href' => 'statistiky.php',
                'header_label' => 'STATISTIKY',
                'tab_label' => null,
                'tab' => null,
            ],
            'nasesluzby' => [
                'href' => 'nasesluzby.php',
                'header_label' => 'NAŠE SLUŽBY',
                'tab_label' => null,
                'tab' => null,
            ],
            'onas' => [
                'href' => 'onas.php',
                'header_label' => 'O NÁS',
                'tab_label' => null,
                'tab' => null,
            ],
            'control_center' => [
                'href' => 'admin.php',
                'header_label' => 'CONTROL CENTER',
                'tab_label' => 'Control Center',
                'tab' => 'control_center',
            ],
            'dashboard' => [
                'href' => 'admin.php?tab=dashboard',
                'tab_label' => 'Dashboard',
                'tab' => 'dashboard',
            ],
            'notifications' => [
                'href' => 'admin.php?tab=notifications',
                'tab_label' => 'Email & SMS',
                'tab' => 'notifications',
            ],
            'keys' => [
                'href' => 'admin.php?tab=keys',
                'tab_label' => 'Security',
                'tab' => 'keys',
            ],
            'users' => [
                'href' => 'admin.php?tab=users',
                'tab_label' => 'Uživatelé',
                'tab' => 'users',
            ],
            'tools' => [
                'href' => 'admin.php?tab=tools',
                'tab_label' => 'Diagnostika',
                'tab' => 'tools',
            ],
            'online' => [
                'href' => 'admin.php?tab=online',
                'tab_label' => 'Online',
                'tab' => 'online',
            ],
            'admin_phpunit' => [
                'href' => 'admin.php?tab=admin_phpunit',
                'tab_label' => '🧪 PHPUnit Tests',
                'tab' => 'admin_phpunit',
            ],
            'admin_testing' => [
                'href' => 'admin.php?tab=admin_testing',
                'tab_label' => 'Testing (Old)',
                'tab' => 'admin_testing',
            ],
            'admin_testing_interactive' => [
                'href' => 'admin.php?tab=admin_testing_interactive',
                'tab_label' => 'Testing Interactive',
                'tab' => 'admin_testing_interactive',
            ],
            'admin_content' => [
                'href' => 'admin.php?tab=admin_content',
                'tab_label' => 'Obsah & Texty',
                'tab' => 'admin_content',
            ],
            'admin_console' => [
                'href' => 'admin.php?tab=admin_console',
                'tab_label' => 'Konzole',
                'tab' => 'admin_console',
            ],
        ];
    }
}

if (!function_exists('loadAdminTabNavigation')) {
    /**
     * LoadAdminTabNavigation
     */
    function loadAdminTabNavigation(): array
    {
        return array_filter(
            loadAdminNavigation(),
            static fn(array $item): bool => !empty($item['tab_label'])
        );
    }
}

if (!function_exists('isAdminNavigationActive')) {
    /**
     * IsAdminNavigationActive
     *
     * @param array $item Item
     * @param string $currentPath CurrentPath
     * @param string $currentTab CurrentTab
     */
    function isAdminNavigationActive(array $item, string $currentPath, string $currentTab): bool
    {
        $hrefPath = parse_url($item['href'], PHP_URL_PATH);
        if ($hrefPath === false || $hrefPath === null) {
            $hrefPath = $item['href'];
        }
        $hrefPath = basename($hrefPath);

        // Porovnání bez query stringu
        $currentPathClean = basename(parse_url($currentPath, PHP_URL_PATH) ?: $currentPath);

        if ($hrefPath !== $currentPathClean) {
            return false;
        }

        // Pro admin.php kontrola tabu
        if ($hrefPath === 'admin.php') {
            $targetTab = $item['tab'] ?? 'dashboard';
            return $currentTab === $targetTab;
        }

        // Pro ostatní stránky je aktivní pokud se shoduje název
        return true;
    }
}
