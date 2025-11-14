<?php
/**
 * Safe File Operations Helper
 *
 * Náhrada za @ operator - proper error handling pro file operace
 *
 * Použití:
 *   Místo: $content = @file_get_contents($path);
 *   Použít: $content = safeFileGetContents($path);
 */

/**
 * Bezpečné čtení souboru s error handlingem
 *
 * @param string $path Cesta k souboru
 * @param mixed $default Výchozí hodnota při chybě (default: false)
 * @return string|false Obsah souboru nebo default hodnota
 */
function safeFileGetContents($path, $default = false) {
    if (!file_exists($path)) {
        error_log("safeFileGetContents: File not found: {$path}");
        return $default;
    }

    if (!is_readable($path)) {
        error_log("safeFileGetContents: File not readable: {$path}");
        return $default;
    }

    $content = file_get_contents($path);

    if ($content === false) {
        error_log("safeFileGetContents: Failed to read file: {$path}");
        return $default;
    }

    return $content;
}

/**
 * Bezpečný zápis do souboru s error handlingem
 *
 * @param string $path Cesta k souboru
 * @param string $data Data k zápisu
 * @param int $flags Flags pro file_put_contents (default: 0)
 * @return bool True při úspěchu, false při chybě
 */
function safeFilePutContents($path, $data, $flags = 0) {
    $dir = dirname($path);

    if (!is_dir($dir)) {
        error_log("safeFilePutContents: Directory does not exist: {$dir}");
        return false;
    }

    if (!is_writable($dir)) {
        error_log("safeFilePutContents: Directory not writable: {$dir}");
        return false;
    }

    $result = file_put_contents($path, $data, $flags);

    if ($result === false) {
        error_log("safeFilePutContents: Failed to write file: {$path}");
        return false;
    }

    return true;
}

/**
 * Bezpečná kontrola existence souboru
 *
 * @param string $path Cesta k souboru
 * @return bool True pokud soubor existuje
 */
function safeFileExists($path) {
    try {
        return file_exists($path);
    } catch (Exception $e) {
        error_log("safeFileExists: Exception for {$path}: " . $e->getMessage());
        return false;
    }
}

/**
 * Bezpečné čtení souboru po řádcích
 *
 * @param string $path Cesta k souboru
 * @param int $flags Flags pro file() (default: FILE_IGNORE_NEW_LINES)
 * @param mixed $default Výchozí hodnota při chybě (default: [])
 * @return array|mixed Pole řádků nebo default hodnota
 */
function safeFileToArray($path, $flags = FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, $default = []) {
    if (!file_exists($path)) {
        error_log("safeFileToArray: File not found: {$path}");
        return $default;
    }

    if (!is_readable($path)) {
        error_log("safeFileToArray: File not readable: {$path}");
        return $default;
    }

    $lines = file($path, $flags);

    if ($lines === false) {
        error_log("safeFileToArray: Failed to read file: {$path}");
        return $default;
    }

    return $lines;
}

/**
 * Bezpečné zjištění velikosti souboru
 *
 * @param string $path Cesta k souboru
 * @param mixed $default Výchozí hodnota při chybě (default: 0)
 * @return int|mixed Velikost souboru v bytech nebo default hodnota
 */
function safeFileSize($path, $default = 0) {
    if (!file_exists($path)) {
        error_log("safeFileSize: File not found: {$path}");
        return $default;
    }

    $size = filesize($path);

    if ($size === false) {
        error_log("safeFileSize: Failed to get file size: {$path}");
        return $default;
    }

    return $size;
}

/**
 * Bezpečné smazání souboru
 *
 * @param string $path Cesta k souboru
 * @return bool True při úspěchu, false při chybě
 */
function safeFileDelete($path) {
    if (!file_exists($path)) {
        return true; // Už neexistuje = úspěch
    }

    if (!is_writable(dirname($path))) {
        error_log("safeFileDelete: Directory not writable: " . dirname($path));
        return false;
    }

    $result = unlink($path);

    if (!$result) {
        error_log("safeFileDelete: Failed to delete file: {$path}");
        return false;
    }

    return true;
}

/**
 * Bezpečné vytvoření adresáře (rekurzivně)
 *
 * @param string $path Cesta k adresáři
 * @param int $permissions Oprávnění (default: 0755)
 * @return bool True při úspěchu, false při chybě
 */
function safeMkdir($path, $permissions = 0755) {
    if (is_dir($path)) {
        return true; // Už existuje = úspěch
    }

    $result = mkdir($path, $permissions, true);

    if (!$result) {
        error_log("safeMkdir: Failed to create directory: {$path}");
        return false;
    }

    return true;
}

/**
 * Bezpečné čtení JSON souboru
 *
 * @param string $path Cesta k JSON souboru
 * @param bool $assoc Vrátit jako asociativní pole (default: true)
 * @param mixed $default Výchozí hodnota při chybě (default: null)
 * @return mixed Parsovaná JSON data nebo default hodnota
 */
function safeJsonDecode($path, $assoc = true, $default = null) {
    $content = safeFileGetContents($path);

    if ($content === false) {
        return $default;
    }

    $data = json_decode($content, $assoc);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("safeJsonDecode: JSON parse error in {$path}: " . json_last_error_msg());
        return $default;
    }

    return $data;
}

/**
 * Bezpečný zápis JSON do souboru
 *
 * @param string $path Cesta k souboru
 * @param mixed $data Data k zapsání
 * @param int $flags JSON encode flags (default: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
 * @return bool True při úspěchu, false při chybě
 */
function safeJsonEncode($path, $data, $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) {
    $json = json_encode($data, $flags);

    if ($json === false) {
        error_log("safeJsonEncode: JSON encode error: " . json_last_error_msg());
        return false;
    }

    return safeFilePutContents($path, $json);
}
