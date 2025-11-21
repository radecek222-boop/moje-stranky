<?php

if (!function_exists('loadAdminNavigation')) {
    /**
     * LoadAdminNavigation
     */
    function loadAdminNavigation(): array
    {
        return [
            'homepage' => [
                'href' => 'index.php',
                'header_label' => 'DOMŮ',
            ],
            'seznam' => [
                'href' => 'seznam.php',
                'header_label' => 'SEZNAM REKLAMACÍ',
            ],
            'protokol' => [
                'href' => 'protokol.php',
                'header_label' => 'PROTOKOL',
            ],
            'statistiky' => [
                'href' => 'statistiky.php',
                'header_label' => 'STATISTIKY',
            ],
            'dashboard' => [
                'href' => 'admin.php',
                'header_label' => 'ADMIN',
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
            'admin_testing_simulator' => [
                'href' => 'admin.php?tab=admin_testing_simulator',
                'tab_label' => 'Testing',
                'tab' => 'admin_testing_simulator',
            ],
            'admin_appearance' => [
                'href' => 'admin.php?tab=admin_appearance',
                'tab_label' => 'Vzhled & Design',
                'tab' => 'admin_appearance',
            ],
            'admin_content' => [
                'href' => 'admin.php?tab=admin_content',
                'tab_label' => 'Obsah & Texty',
                'tab' => 'admin_content',
            ],
            'admin_configuration' => [
                'href' => 'admin.php?tab=admin_configuration',
                'tab_label' => 'Konfigurace systému',
                'tab' => 'admin_configuration',
            ],
            'admin_actions' => [
                'href' => 'admin.php?tab=admin_actions',
                'tab_label' => 'Akce & Úkoly',
                'tab' => 'admin_actions',
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

        if ($hrefPath !== $currentPath) {
            return false;
        }

        if ($hrefPath !== 'admin.php') {
            return true;
        }

        $targetTab = $item['tab'] ?? 'dashboard';
        return $currentTab === $targetTab;
    }
}
