<?php

if (!function_exists('loadAdminNavigation')) {
    function loadAdminNavigation(): array
    {
        return [
            'control_center' => [
                'href' => 'admin.php?tab=control_center',
                'header_label' => 'CONTROL CENTER',
                'tab_label' => 'Control Center',
                'tab' => 'control_center',
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
