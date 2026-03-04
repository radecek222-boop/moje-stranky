<?php
/**
 * router.php - Front controller centrálního routeru WGS Service
 *
 * Tento soubor zpracovává POUZE požadavky explicitně nasměrované z .htaccess.
 * Existující PHP stránky (seznam.php, admin.php, …) fungují beze změny.
 *
 * URL pravidla v .htaccess:
 *   /r/*       → router.php (zkrácené linky na reklamace)
 *   /qr/*      → router.php (QR kódy)
 *   /zdravi    → router.php (health check)
 *   /api/v2/*  → router.php (nové API endpointy)
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/Router.php';
require_once __DIR__ . '/includes/TenantManager.php';
require_once __DIR__ . '/routes.php';

// Dispatch — najde odpovídající trasu a zavolá obsluhu
Router::odeslat(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
