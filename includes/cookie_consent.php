<?php
/**
 * Cookie Consent Banner Include
 * Vložte tento soubor před </body> na všech stránkách pomocí:
 * <?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
 *
 * Banner se zobrazí pouze nepřihlášeným uživatelům bez souhlasu.
 * Přihlášení uživatelé mají právní základ ve smlouvě.
 */

// Kontrola, zda je uživatel přihlášen
$jePrihlasenCookie = isset($_SESSION['user_id']) || isset($_SESSION['is_admin']);

// Kontrola, zda již existuje souhlas
$maSouhlasCookie = isset($_COOKIE['wgs_cookie_consent']);

// Načíst cookie consent pouze pro nepřihlášené bez souhlasu
// JavaScript si poradí s logikou, ale můžeme ušetřit bandwidth
?>
<!-- WGS Cookie Consent -->
<link rel="stylesheet" href="/assets/css/cookie-consent.min.css">
<script src="/assets/js/cookie-consent.min.js" defer></script>
