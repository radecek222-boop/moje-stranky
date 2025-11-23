<?php
/**
 * Posílená Email Validace
 *
 * Poskytuje bezpečnou validaci emailových adres s ochranou proti:
 * - XSS útokům ("><script>alert(1)</script>@example.com)
 * - Příliš dlouhým emailům (DoS)
 * - Neexistujícím doménám
 * - Neplatným znakům
 *
 * @version 1.0.0
 * @date 2025-11-23
 */

/**
 * Validuje email adresu s posílenými bezpečnostními kontrolami
 *
 * @param string $email Email k validaci
 * @param bool $checkDNS Zkontrolovat existenci domény (volitelné, pomalejší)
 * @return array ['valid' => bool, 'email' => string|null, 'error' => string|null]
 */
function validateEmailStrong($email, $checkDNS = false) {
    $result = [
        'valid' => false,
        'email' => null,
        'error' => null
    ];

    // 1. Základní validace
    if (empty($email) || !is_string($email)) {
        $result['error'] = 'Email nesmí být prázdný';
        return $result;
    }

    // 2. Trim whitespace
    $email = trim($email);

    // 3. RFC 5321 max délka kontrola
    if (strlen($email) > 254) {
        $result['error'] = 'Email je příliš dlouhý (maximum 254 znaků)';
        return $result;
    }

    // 4. PHP filter validace
    $filtered = filter_var($email, FILTER_VALIDATE_EMAIL);
    if ($filtered === false) {
        $result['error'] = 'Neplatný formát emailu';
        return $result;
    }

    // 5. Sanitizace (odstranění nebezpečných znaků)
    $sanitized = filter_var($filtered, FILTER_SANITIZE_EMAIL);
    if ($sanitized !== $filtered) {
        $result['error'] = 'Email obsahuje nepovolené znaky';
        return $result;
    }

    // 6. Kontrola local part a domain
    $parts = explode('@', $sanitized);
    if (count($parts) !== 2) {
        $result['error'] = 'Neplatný formát emailu';
        return $result;
    }

    list($localPart, $domain) = $parts;

    // 7. Kontrola délky local part (max 64 znaků)
    if (strlen($localPart) > 64) {
        $result['error'] = 'Místní část emailu je příliš dlouhá (maximum 64 znaků)';
        return $result;
    }

    // 8. Kontrola délky domény (max 253 znaků)
    if (strlen($domain) > 253) {
        $result['error'] = 'Doména je příliš dlouhá (maximum 253 znaků)';
        return $result;
    }

    // 9. Blokovat IP-based emaily (user@[192.168.1.1])
    if (preg_match('/^\[.*\]$/', $domain)) {
        $result['error'] = 'IP-based emaily nejsou povoleny';
        return $result;
    }

    // 10. Kontrola domény - musí obsahovat tečku
    if (strpos($domain, '.') === false) {
        $result['error'] = 'Doména musí obsahovat tečku (např. example.com)';
        return $result;
    }

    // 11. DNS kontrola (volitelné - může být pomalé)
    if ($checkDNS) {
        // Kontrola MX nebo A záznamu
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            $result['error'] = 'Emailová doména neexistuje nebo nemá mail server';
            return $result;
        }
    }

    // ✅ Email je validní
    $result['valid'] = true;
    $result['email'] = strtolower($sanitized); // Normalizovat na lowercase
    return $result;
}

/**
 * Rychlá validace emailu (bez DNS kontroly)
 * Použití: if (!validateEmail($email)) { ... }
 *
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    $result = validateEmailStrong($email, false);
    return $result['valid'];
}

/**
 * Validace emailu s DNS kontrolou (pomalejší, ale bezpečnější)
 *
 * @param string $email
 * @return bool
 */
function validateEmailWithDNS($email) {
    $result = validateEmailStrong($email, true);
    return $result['valid'];
}
