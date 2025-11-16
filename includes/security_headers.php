<?php
/**
 * Security Headers
 * Nastavení HTTP bezpečnostních hlaviček pro ochranu proti běžným útokům
 */

// Ochrana proti clickjackingu - stránka nemůže být vložena do iframe z jiné domény
header("X-Frame-Options: SAMEORIGIN");

// Ochrana proti MIME type sniffing - browser musí respektovat Content-Type
header("X-Content-Type-Options: nosniff");

// XSS Protection pro starší prohlížeče (moderní browsery mají built-in)
header("X-XSS-Protection: 1; mode=block");

// Referrer Policy - omezení posílání referrer informací
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions Policy - zakázat nepotřebné browser features
header("Permissions-Policy: geolocation=(), microphone=(), camera=(self)");

// Strict-Transport-Security (pouze pokud je HTTPS)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// Content Security Policy - ochrana proti XSS a injection útokům
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com",
    "font-src 'self' https://fonts.gstatic.com",
    "img-src 'self' data: https: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org",
    "connect-src 'self' https://api.geoapify.com https://router.project-osrm.org",
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'"
];

header("Content-Security-Policy: " . implode("; ", $csp));
