<?php

if (!function_exists('loadAdminNavigation')) {
    /**
     * LoadAdminNavigation
     */
    function loadAdminNavigation(): array
    {
        return [
            'control_center' => [
                'href' => 'admin.php?tab=control_center',
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
                'tab_label' => 'Registrační klíče',
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
            'control_center_testing' => [
                'href' => 'admin.php?tab=control_center_testing',
                'tab_label' => 'Testing (Old)',
                'tab' => 'control_center_testing',
            ],
            'control_center_testing_interactive' => [
                'href' => 'admin.php?tab=control_center_testing_interactive',
                'tab_label' => 'Testing Interactive',
                'tab' => 'control_center_testing_interactive',
            ],
            'control_center_testing_simulator' => [
                'href' => 'admin.php?tab=control_center_testing_simulator',
                'tab_label' => 'Testing',
                'tab' => 'control_center_testing_simulator',
            ],
            'control_center_appearance' => [
                'href' => 'admin.php?tab=control_center_appearance',
                'tab_label' => 'Vzhled & Design',
                'tab' => 'control_center_appearance',
            ],
            'control_center_content' => [
                'href' => 'vsechny_tabulky.php',
                'tab_label' => 'SQL',
                'tab' => 'control_center_content',
            ],
            'control_center_configuration' => [
                'href' => 'admin.php?tab=control_center_configuration',
                'tab_label' => 'Konfigurace systému',
                'tab' => 'control_center_configuration',
            ],
            'control_center_actions' => [
                'href' => 'admin.php?tab=control_center_actions',
                'tab_label' => 'Akce & Úkoly',
                'tab' => 'control_center_actions',
            ],
            'control_center_console' => [
                'href' => 'admin.php?tab=control_center_console',
                'tab_label' => 'Konzole',
                'tab' => 'control_center_console',
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
