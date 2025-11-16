<?php
/**
 * Shared helpers for sanitizing reklamace IDs that may contain separators.
 */
if (!function_exists('sanitizeReklamaceId')) {
    function sanitizeReklamaceId($value, string $fieldLabel = 'ID reklamace'): string
    {
        if ($value === null) {
            throw new Exception('Chybí ' . $fieldLabel);
        }

        if (is_array($value)) {
            throw new Exception('Neplatné ' . $fieldLabel);
        }

        $value = trim((string)$value);
        if ($value === '') {
            throw new Exception('Chybí ' . $fieldLabel);
        }

        $value = mb_substr($value, 0, 120, 'UTF-8');

        if (!preg_match('/^[a-zA-Z0-9._\/-]+$/', $value)) {
            throw new Exception('Neplatné ID reklamace. Povolené znaky: písmena, čísla, tečka, lomítko, pomlčka a podtržítko.');
        }

        return $value;
    }
}

if (!function_exists('reklamaceStorageKey')) {
    function reklamaceStorageKey(string $reklamaceId): string
    {
        $safe = str_replace(['/', '\\'], '-', $reklamaceId);
        $safe = preg_replace('/-+/', '-', $safe);
        $safe = trim($safe, '-');

        return $safe !== '' ? $safe : 'reklamace';
    }
}
