<?php
/**
 * Email Domain Validator
 * Kontrola existence MX záznamů pro emailovou doménu
 */

if (!function_exists('validateEmailDomain')) {
    /**
     * Zkontroluje, zda emailová doména má platné MX záznamy
     *
     * @param string $email Emailová adresa k ověření
     * @return array ['valid' => bool, 'error' => string|null]
     */
    function validateEmailDomain(string $email): array
    {
        // Základní validace formátu emailu
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'error' => 'Neplatný formát emailové adresy'
            ];
        }

        // Získání domény z emailu
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return [
                'valid' => false,
                'error' => 'Neplatný formát emailové adresy'
            ];
        }

        $domain = strtolower(trim($parts[1]));

        // Whitelist: Vždy povolit známé testovací/demo domény
        $allowedTestDomains = [
            'example.com',
            'test.com',
            'demo.com',
            'localhost'
        ];

        if (in_array($domain, $allowedTestDomains, true)) {
            return ['valid' => true, 'error' => null];
        }

        // Kontrola MX záznamů
        $mxHosts = [];
        $hasMX = @getmxrr($domain, $mxHosts);

        if (!$hasMX || empty($mxHosts)) {
            // Pokud nemá MX záznamy, zkusit A záznam (některé domény používají)
            $hasARecord = @gethostbyname($domain) !== $domain;

            if (!$hasARecord) {
                return [
                    'valid' => false,
                    'error' => "Emailová doména '{$domain}' neexistuje nebo nemá platné mailové záznamy. Zkontrolujte prosím email."
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validateAndSanitizeEmail')) {
    /**
     * Kompletní validace a sanitizace emailu včetně kontroly domény
     *
     * @param string $email Emailová adresa
     * @param bool $checkDomain Kontrolovat existenci domény? (default: true)
     * @return array ['valid' => bool, 'email' => string|null, 'error' => string|null]
     */
    function validateAndSanitizeEmail(string $email, bool $checkDomain = true): array
    {
        $email = trim($email);

        // Prázdný email
        if ($email === '') {
            return [
                'valid' => true,
                'email' => null,
                'error' => null
            ];
        }

        // Validace formátu
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'email' => null,
                'error' => 'Neplatný formát emailové adresy'
            ];
        }

        // Sanitizace
        $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);

        // Kontrola domény (pokud je požadována)
        if ($checkDomain) {
            $domainCheck = validateEmailDomain($sanitized);
            if (!$domainCheck['valid']) {
                return [
                    'valid' => false,
                    'email' => null,
                    'error' => $domainCheck['error']
                ];
            }
        }

        return [
            'valid' => true,
            'email' => $sanitized,
            'error' => null
        ];
    }
}
