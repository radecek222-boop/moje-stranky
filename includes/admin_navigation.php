<?php

if (!function_exists('loadAdminNavigation')) {
    function loadAdminNavigation(): array
    {
        return [
            'dashboard' => [
                'href' => 'admin.php',
                'header_label' => 'DASHBOARD',
                'tab_label' => 'Přehled',
                'tab' => 'dashboard',
            ],
            'notifications' => [
                'href' => 'admin.php?tab=notifications',
                'header_label' => 'EMAILY & SMS',
                'tab_label' => 'Notifikace',
                'tab' => 'notifications',
            ],
            'keys' => [
                'href' => 'admin.php?tab=keys',
                'header_label' => 'REGISTRAČNÍ KLÍČE',
                'tab_label' => 'Registrační klíče',
                'tab' => 'keys',
            ],
            'users' => [
                'href' => 'admin.php?tab=users',
                'header_label' => 'UŽIVATELÉ',
                'tab_label' => 'Uživatelé',
                'tab' => 'users',
            ],
            'control_center' => [
                'href' => 'admin.php?tab=control_center',
                'header_label' => 'CONTROL CENTER',
                'tab_label' => 'Control Center',
                'tab' => 'control_center',
            ],
            'tools' => [
                'href' => 'admin.php?tab=tools',
                'header_label' => 'NÁSTROJE',
                'tab_label' => 'Nástroje & Migrace',
                'tab' => 'tools',
            ],
            'online' => [
                'href' => 'admin.php?tab=online',
                'header_label' => 'ONLINE',
                'tab_label' => 'Online',
                'tab' => 'online',
            ],
            'statistics' => [
                'href' => 'statistiky.php',
                'header_label' => 'STATISTIKY',
            ],
            'analytics' => [
                'href' => 'analytics.php',
                'header_label' => 'ANALYTICS',
            ],
            'complaints' => [
                'href' => 'seznam.php',
                'header_label' => 'REKLAMACE',
            ],
            'psa' => [
                'href' => 'psa.php',
                'header_label' => 'PSA',
            ],
        ];
    }
}

if (!function_exists('loadAdminTabNavigation')) {
    function loadAdminTabNavigation(): array
    {
        return array_filter(
            loadAdminNavigation(),
            static fn(array $item): bool => !empty($item['tab_label'])
        );
    }
}

if (!function_exists('isAdminNavigationActive')) {
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
