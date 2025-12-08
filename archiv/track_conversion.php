<?php
/**
 * Track Conversion API - Zaznamenání konverze
 *
 * Write API pro zaznamenání conversion events.
 *
 * POST params:
 * - session_id (required): Session ID z cookie
 * - conversion_type (required): typ konverze (form_submit, login, contact, purchase, etc.)
 * - conversion_label (optional): custom label
 * - conversion_value (optional): hodnota konverze v Kč (default: 0)
 * - metadata (optional): custom JSON data
 * - csrf_token (required): CSRF token
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #9 - Conversion Funnels
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/ConversionFunnel.php';
require_once __DIR__ . '/../includes/CampaignAttribution.php';

header('Content-Type: application/json; charset=utf-8');

// Pouze POST metoda
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Pouze POST metoda je povolena', 405);
}

try {
    // ========================================
    // CSRF VALIDACE
    // ========================================
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        sendJsonError('Neplatný CSRF token', 403);
    }

    $pdo = getDbConnection();

    // ========================================
    // VALIDACE PARAMETRŮ
    // ========================================
    $sessionId = $_POST['session_id'] ?? null;
    $conversionType = $_POST['conversion_type'] ?? null;
    $conversionLabel = $_POST['conversion_label'] ?? null;
    $conversionValue = isset($_POST['conversion_value']) ? (float)$_POST['conversion_value'] : 0;
    $metadata = $_POST['metadata'] ?? null;

    // Required fields
    if (!$sessionId) {
        sendJsonError('Chybí povinný parametr: session_id', 400);
    }

    if (!$conversionType) {
        sendJsonError('Chybí povinný parametr: conversion_type', 400);
    }

    // Validace conversion type
    $allowedTypes = [
        'form_submit',
        'login',
        'contact',
        'purchase',
        'registration',
        'download',
        'newsletter',
        'quote_request',
        'custom'
    ];

    if (!in_array($conversionType, $allowedTypes)) {
        sendJsonError('Neplatný conversion_type. Povolené: ' . implode(', ', $allowedTypes), 400);
    }

    // Pokud je metadata string (JSON), ověřit že je validní JSON
    if ($metadata && is_string($metadata)) {
        $decoded = json_decode($metadata, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonError('Metadata nejsou validní JSON', 400);
        }
        $metadata = $decoded; // Použít pole
    }

    // ========================================
    // ZAZNAMENAT KONVERZI
    // ========================================
    $conversionFunnel = new ConversionFunnel($pdo);

    $conversionId = $conversionFunnel->zaznamenatKonverzi(
        $sessionId,
        $conversionType,
        $conversionLabel,
        $conversionValue,
        $metadata
    );

    // ========================================
    // UPDATE CAMPAIGN ATTRIBUTION (Modul #8)
    // ========================================
    if ($conversionValue > 0) {
        try {
            $campaignAttribution = new CampaignAttribution($pdo);

            // Načíst session data pro UTM parametry
            $stmt = $pdo->prepare("
                SELECT
                    utm_source,
                    utm_medium,
                    utm_campaign,
                    utm_content,
                    utm_term,
                    device_type,
                    fingerprint_id
                FROM wgs_analytics_sessions
                WHERE session_id = :session_id
                LIMIT 1
            ");
            $stmt->execute(['session_id' => $sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session && ($session['utm_source'] || $session['utm_medium'] || $session['utm_campaign'])) {
                $kampan = [
                    'utm_source' => $session['utm_source'],
                    'utm_medium' => $session['utm_medium'],
                    'utm_campaign' => $session['utm_campaign'],
                    'utm_content' => $session['utm_content'],
                    'utm_term' => $session['utm_term']
                ];

                $date = date('Y-m-d');
                $deviceType = $session['device_type'];

                // Update last-click attribution
                $campaignAttribution->aktualizujConversionMetriky(
                    $kampan,
                    $date,
                    $deviceType,
                    $conversionValue,
                    'last_click'
                );

                // Update first-click attribution
                $prvniKampan = $campaignAttribution->zjistiPrvniKampan($session['fingerprint_id']);
                if ($prvniKampan) {
                    $campaignAttribution->aktualizujConversionMetriky(
                        $prvniKampan,
                        $date,
                        $deviceType,
                        $conversionValue,
                        'first_click'
                    );
                }

                // Update linear attribution
                $linearAttr = $campaignAttribution->vypocitejLinearniAttributi(
                    $session['fingerprint_id'],
                    $conversionValue
                );

                foreach ($linearAttr as $attr) {
                    $linearKampan = [
                        'utm_source' => $attr['utm_source'],
                        'utm_medium' => $attr['utm_medium'],
                        'utm_campaign' => $attr['utm_campaign'],
                        'utm_content' => $attr['utm_content'],
                        'utm_term' => $attr['utm_term']
                    ];

                    $campaignAttribution->aktualizujConversionMetriky(
                        $linearKampan,
                        $date,
                        $deviceType,
                        $attr['credit'],
                        'linear'
                    );
                }
            }
        } catch (Exception $e) {
            // Log error ale nepřerušuj hlavní flow
            error_log('Track Conversion: Campaign Attribution Error: ' . $e->getMessage());
        }
    }

    // ========================================
    // RESPONSE
    // ========================================
    sendJsonSuccess('Konverze zaznamenána', [
        'conversion_id' => $conversionId,
        'conversion_type' => $conversionType,
        'conversion_value' => $conversionValue
    ]);

} catch (Exception $e) {
    error_log('Track Conversion API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError($e->getMessage(), 500);
}
?>
