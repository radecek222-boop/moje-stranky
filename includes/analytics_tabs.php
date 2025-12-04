<?php
/**
 * Analytics Tabs Navigation
 *
 * Sdílená navigace pro všechny Analytics moduly
 *
 * @version 1.0.0
 * @date 2025-11-23
 */

$currentPage = basename($_SERVER['PHP_SELF']);

$analyticsTabs = [
    [
        'file' => 'analytics.php',
        'label' => 'Overview',
        'description' => 'Hlavní dashboard'
    ],
    [
        'file' => 'analytics-heatmap.php',
        'label' => 'Heatmapy',
        'description' => 'Click & Scroll mapy'
    ],
    [
        'file' => 'analytics-replay.php',
        'label' => 'Session Replay',
        'description' => 'Záznamy návštěv'
    ],
    [
        'file' => 'analytics-campaigns.php',
        'label' => 'Kampaně',
        'description' => 'UTM tracking'
    ],
    [
        'file' => 'analytics-conversions.php',
        'label' => 'Konverze',
        'description' => 'Conversion funnels'
    ],
    [
        'file' => 'analytics-user-scores.php',
        'label' => 'User Scoring',
        'description' => 'AI analýza chování'
    ],
    [
        'file' => 'analytics-realtime.php',
        'label' => 'Real-time',
        'description' => 'Live dashboard'
    ],
    [
        'file' => 'analytics-reports.php',
        'label' => 'AI Reporty',
        'description' => 'Automatické reporty'
    ],
    [
        'file' => 'gdpr-portal.php',
        'label' => 'GDPR',
        'description' => 'Compliance & Privacy'
    ],
    [
        'file' => 'sprava_ip_blacklist.php',
        'label' => 'IP Adresy',
        'description' => 'Správa blacklistu IP'
    ],
];
?>

<div class="analytics-tabs-container" style="background: white; border-radius: 8px; padding: 1rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow-x: auto;">
    <div style="display: flex; gap: 0.5rem; min-width: max-content;">
        <?php foreach ($analyticsTabs as $tab):
            $isActive = ($currentPage === $tab['file']);
            $activeStyle = $isActive
                ? 'background: #000; color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.2);'
                : 'background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb;';
        ?>
            <a href="<?php echo htmlspecialchars($tab['file'], ENT_QUOTES, 'UTF-8'); ?>"
               style="<?php echo $activeStyle; ?> padding: 0.75rem 1.25rem; border-radius: 6px; text-decoration: none; transition: all 0.3s; font-weight: 500; font-size: 0.9rem; white-space: nowrap;"
               title="<?php echo htmlspecialchars($tab['description'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<style>
.analytics-tabs-container a:not([style*="background: #000"]):hover {
    background: #e5e7eb !important;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>
