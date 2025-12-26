<?php
/**
 * WGS Company Configuration
 * Jediné místo pravdy pro firemní kontaktní údaje
 *
 * @author WGS Team
 * @version 1.0
 * @since 2025-12-26
 */

// ========================================
// FIREMNÍ E-MAILY
// ========================================
//
// DŮLEŽITÉ: Existují pouze tyto 2 veřejné e-maily:
//   - info@wgs-service.cz (obecný kontakt)
//   - reklamace@wgs-service.cz (reklamace/servis)
//
// Interní e-maily techniků (radek@, milan@) - používají se jen v interní logice
//
// NEEXISTUJÍ (nesmí se používat!):
//   - noreply@wgs-service.cz
//   - admin@wgs-service.cz
//   - technik@wgs-service.cz
//   - analytics@wgs-service.cz
//   - test-e2e@wgs-service.cz

/** Obecný kontakt - web, footer, úřady, obecné dotazy */
define('WGS_EMAIL_INFO', 'info@wgs-service.cz');

/** Reklamace a servis - komunikace se zákazníky/prodejci, systémové zprávy */
define('WGS_EMAIL_REKLAMACE', 'reklamace@wgs-service.cz');

// ========================================
// FIREMNÍ ÚDAJE
// ========================================

define('WGS_COMPANY_NAME', 'White Glove Service, s.r.o.');
define('WGS_COMPANY_PHONE', '+420 725 965 826');
define('WGS_COMPANY_ADDRESS', 'Do Dubče 364, 190 11 Praha 9 – Běchovice');
define('WGS_COMPANY_ADDRESS_SHORT', 'Do Dubče 364, Běchovice 190 11');
define('WGS_COMPANY_ICO', '09769684');
define('WGS_COMPANY_WEB', 'https://www.wgs-service.cz');

// ========================================
// HELPER FUNKCE
// ========================================

/**
 * Vrátí e-mail pro daný účel
 *
 * @param string $typ 'info' | 'reklamace' | 'system'
 * @return string E-mailová adresa
 */
function wgsEmail(string $typ = 'info'): string {
    return match($typ) {
        'reklamace', 'servis', 'claims', 'system', 'noreply' => WGS_EMAIL_REKLAMACE,
        default => WGS_EMAIL_INFO,
    };
}

/**
 * Vrátí konfiguraci pro JavaScript
 *
 * @return string JSON string
 */
function wgsConfigProJS(): string {
    return json_encode([
        'email' => [
            'info' => WGS_EMAIL_INFO,
            'reklamace' => WGS_EMAIL_REKLAMACE
        ],
        'telefon' => WGS_COMPANY_PHONE,
        'adresa' => WGS_COMPANY_ADDRESS_SHORT,
        'ico' => WGS_COMPANY_ICO,
        'web' => WGS_COMPANY_WEB
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Vrátí HTML footer link pro daný e-mail
 *
 * @param string $typ 'info' | 'reklamace'
 * @param string $class CSS třída
 * @return string HTML odkaz
 */
function wgsEmailLink(string $typ = 'info', string $class = 'footer-link'): string {
    $email = wgsEmail($typ);
    return '<a href="mailto:' . $email . '" class="' . htmlspecialchars($class) . '">' . $email . '</a>';
}

/**
 * Vrátí kompletní kontaktní info pro footer
 *
 * @param string $emailTyp 'info' | 'reklamace'
 * @return string HTML
 */
function wgsFooterKontakt(string $emailTyp = 'info'): string {
    $email = wgsEmail($emailTyp);
    return '<strong>Email:</strong> <a href="mailto:' . $email . '" class="footer-link">' . $email . '</a>';
}
