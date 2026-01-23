<?php
/**
 * Admin Panel - ÚDRŽBA SYSTÉMU
 * 
 * Nástroje pro údržbu a čištění projektu
 */

if (!defined('ADMIN_PHP_LOADED')) {
    die('Direct access not permitted');
}
?>

<div class="wgs-admin-tab-content">
    <h2 style="color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; margin-bottom: 30px;">
        🧹 Údržba systému
    </h2>

    <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 30px;">
        <strong>⚠️ DŮLEŽITÉ:</strong> Tyto nástroje slouží k bezpečnému čištění projektu.<br>
        ✅ Žádné soubory se nesmažou automaticky - vždy rozhoduješ TY<br>
        ✅ Archivace je bezpečná - soubory zůstávají v _archive/<br>
        ✅ ZERO DOWNTIME - aplikace funguje celou dobu
    </div>

    <!-- Grid s kartami -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">

        <!-- Interaktivní audit - NEJNOVĚJŠÍ -->
        <div style="background: white; border: 3px solid #6f42c1; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(111,66,193,0.2); position: relative;">
            <div style="position: absolute; top: 10px; right: 10px; background: #6f42c1; color: white; padding: 4px 8px; border-radius: 3px; font-size: 0.7rem; font-weight: 700;">
                NEJNOVĚJŠÍ
            </div>
            <h3 style="color: #6f42c1; margin-top: 0; font-size: 1.3rem;">
                🎯 Interaktivní audit
            </h3>
            <p style="color: #666; font-size: 0.9rem; line-height: 1.6;">
                <strong>Nejpokročilejší nástroj:</strong><br>
                📊 Třídění podle posledního použití<br>
                🖱️ Klikni na řádek → zobrazí závislosti<br>
                🔍 Vidíš kdo soubor includuje<br>
                📈 Statistiky: 90+ dní, 30-90 dní, < 30 dní<br>
                🎨 Barevné značení stáří
            </p>
            <p style="color: #666; font-size: 0.85rem; margin: 10px 0;">
                <strong>Pro:</strong> Laiky i pokročilé - hezky grafické!
            </p>
            <a href="audit_interactive.php" target="_blank"
               style="display: inline-block; background: #6f42c1; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; font-weight: 700; margin-top: 10px; box-shadow: 0 2px 6px rgba(111,66,193,0.3);">
                🚀 Spustit interaktivní audit
            </a>
        </div>

        <!-- Audit v2 - Vylepšená kategorizace -->
        <div style="background: white; border: 2px solid #17a2b8; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="color: #17a2b8; margin-top: 0; font-size: 1.2rem;">
                🔍 Audit souborů v2
            </h3>
            <p style="color: #666; font-size: 0.9rem; line-height: 1.6;">
                <strong>Vylepšená kategorizace:</strong><br>
                ✨ Automatická detekce landing pages<br>
                ✨ PWA soubory (sw.php...)<br>
                ✨ Fix skripty (oprav_*.php)<br>
                ✨ Email operace<br>
                ✨ Snížení UNKNOWN z 66 na 2 soubory
            </p>
            <p style="color: #666; font-size: 0.85rem; margin: 10px 0;">
                <strong>Výsledek:</strong> Kategorizuje 98 souborů do 14 skupin
            </p>
            <a href="audit_unused_files_v2.php" target="_blank" 
               style="display: inline-block; background: #17a2b8; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 600; margin-top: 10px;">
                Spustit Audit v2
            </a>
        </div>

        <!-- Bezpečná archivace s monitoringem -->
        <div style="background: white; border: 2px solid #28a745; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="color: #28a745; margin-top: 0; font-size: 1.2rem;">
                🛡️ Bezpečná archivace
            </h3>
            <p style="color: #666; font-size: 0.9rem; line-height: 1.6;">
                <strong>PRO OSTROU PRODUKCI:</strong><br>
                ✅ Soubory se PŘESUNOU, ne smažou<br>
                ✅ Redirecty - odkazy fungují<br>
                ✅ Monitoring 30 dní<br>
                ✅ Review - TY rozhoduješ co smazat
            </p>
            <p style="color: #666; font-size: 0.85rem; margin: 10px 0;">
                <strong>Workflow:</strong> Přesun → Sledování → Manuální rozhodnutí
            </p>
            <a href="safe_archive_with_monitoring.php" target="_blank" 
               style="display: inline-block; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 600; margin-top: 10px;">
                Spustit archivaci
            </a>
        </div>

        <!-- Audit v1 - Základní verze -->
        <div style="background: white; border: 2px solid #6c757d; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="color: #6c757d; margin-top: 0; font-size: 1.2rem;">
                📋 Audit souborů v1
            </h3>
            <p style="color: #666; font-size: 0.9rem; line-height: 1.6;">
                <strong>Základní kategorizace:</strong><br>
                • CRITICAL (11 souborů)<br>
                • TEST (5 souborů)<br>
                • MIGRATION (10 souborů)<br>
                • DIAGNOSTIC (3 soubory)<br>
                • UNKNOWN (66 souborů)
            </p>
            <p style="color: #666; font-size: 0.85rem; margin: 10px 0;">
                <strong>Doporučení:</strong> Použij raději Audit v2 (lepší)
            </p>
            <a href="audit_unused_files.php" target="_blank" 
               style="display: inline-block; background: #6c757d; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 600; margin-top: 10px;">
                Spustit Audit v1
            </a>
        </div>

    </div>

    <!-- Doporučený workflow -->
    <div style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 20px; border-radius: 5px; margin-top: 30px;">
        <h3 style="color: #0c5460; margin-top: 0;">📋 Doporučený postup:</h3>
        <ol style="line-height: 1.8; margin: 0; padding-left: 20px;">
            <li><strong>Spustit Audit v2</strong> - Podívej se kolik souborů v každé kategorii</li>
            <li><strong>Stáhnout archivační skript</strong> z "Bezpečná archivace"</li>
            <li><strong>Spustit archivaci</strong> - Soubory se přesunou do <code>_archive/</code></li>
            <li><strong>Sledovat 30 dní</strong> - Aplikace běží normálně, přístupy se logují</li>
            <li><strong>Review a rozhodnutí</strong> - Po 30 dnech spustit <code>archive_review.php</code></li>
            <li><strong>Manuální cleanup</strong> - TY rozhodneš co vrátit a co smazat</li>
        </ol>
    </div>

    <!-- Statistiky z posledního auditu -->
    <div style="background: white; border: 1px solid #dee2e6; border-radius: 5px; padding: 20px; margin-top: 30px;">
        <h3 style="color: #333; margin-top: 0;">📊 Očekávané výsledky (Audit v2):</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <div style="font-size: 2rem; font-weight: bold; color: #28a745;">60</div>
                <div style="font-size: 0.85rem; color: #666;">souborů k archivaci</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <div style="font-size: 2rem; font-weight: bold; color: #17a2b8;">35</div>
                <div style="font-size: 0.85rem; color: #666;">souborů ponechat</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <div style="font-size: 2rem; font-weight: bold; color: #dc3545;">2</div>
                <div style="font-size: 0.85rem; color: #666;">souborů kontrola</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <div style="font-size: 2rem; font-weight: bold; color: #6f42c1;">~50%</div>
                <div style="font-size: 0.85rem; color: #666;">redukce balastu</div>
            </div>
        </div>
    </div>

    <!-- Kategorie k archivaci -->
    <div style="margin-top: 30px;">
        <h3 style="color: #333;">📦 Kategorie k archivaci (60 souborů):</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px;">
            <div style="padding: 10px; background: #17a2b8; color: white; border-radius: 5px; font-size: 0.85rem;">
                <strong>5x</strong> TEST (test_*.php)
            </div>
            <div style="padding: 10px; background: #6c757d; color: white; border-radius: 5px; font-size: 0.85rem;">
                <strong>10x</strong> MIGRATION (pridej_*.php)
            </div>
            <div style="padding: 10px; background: #ffc107; color: #000; border-radius: 5px; font-size: 0.85rem;">
                <strong>3x</strong> DIAGNOSTIC (debug_*.php)
            </div>
            <div style="padding: 10px; background: #fd7e14; color: white; border-radius: 5px; font-size: 0.85rem;">
                <strong>15x</strong> FIX SCRIPTS (oprav_*.php)
            </div>
            <div style="padding: 10px; background: #20c997; color: white; border-radius: 5px; font-size: 0.85rem;">
                <strong>6x</strong> EMAIL OPS (odeslat_*.php)
            </div>
            <div style="padding: 10px; background: #6610f2; color: white; border-radius: 5px; font-size: 0.85rem;">
                <strong>12x</strong> ADMIN TOOLS (diagnostika_*.php)
            </div>
            <div style="padding: 10px; background: #e83e8c; color: white; border-radius: 5px; font-size: 0.85rem;">
                <strong>6x</strong> CLEANUP (vymaz_*.php)
            </div>
            <div style="padding: 10px; background: #28a745; color: white; border-radius: 5px; font-size: 0.85rem;">
                <strong>2x</strong> TABLE VIEWERS
            </div>
        </div>
    </div>

    <!-- Bezpečnostní záruky -->
    <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 5px; margin-top: 30px;">
        <h3 style="color: #155724; margin-top: 0;">🔒 Bezpečnostní záruky:</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <div>
                ✅ <strong>ZERO DOWNTIME</strong><br>
                <span style="font-size: 0.85rem;">Aplikace funguje celou dobu</span>
            </div>
            <div>
                ✅ <strong>NIC SE NEZTRATÍ</strong><br>
                <span style="font-size: 0.85rem;">Soubory v _archive/, ne smazané</span>
            </div>
            <div>
                ✅ <strong>ODKAZY FUNGUJÍ</strong><br>
                <span style="font-size: 0.85rem;">.htaccess redirecty zajistí funkčnost</span>
            </div>
            <div>
                ✅ <strong>MONITORING</strong><br>
                <span style="font-size: 0.85rem;">Víš co se používá</span>
            </div>
            <div>
                ✅ <strong>MANUÁLNÍ KONTROLA</strong><br>
                <span style="font-size: 0.85rem;">TY rozhoduješ co smazat</span>
            </div>
            <div>
                ✅ <strong>GIT HISTORIE</strong><br>
                <span style="font-size: 0.85rem;">Vše commitnuté, lze vrátit</span>
            </div>
        </div>
    </div>
</div>

<style>
.wgs-admin-tab-content a:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    transition: all 0.2s;
}
</style>
