<?php
/**
 * Email Footer Helper
 * Přidává standardní patičku do emailů včetně unsubscribe odkazu
 *
 * Použití:
 *   require_once __DIR__ . '/includes/email_footer.php';
 *   $body = pridatEmailFooter($body, $email, $html);
 */

/**
 * Přidá standardní patičku do emailu
 *
 * @param string $body Tělo emailu
 * @param string $email Email příjemce (pro unsubscribe token)
 * @param bool $html Je tělo HTML formát?
 * @return string Tělo s patičkou
 */
function pridatEmailFooter($body, $email = '', $html = false) {
    // Generovat unsubscribe token
    $token = '';
    if ($email) {
        $token = base64_encode($email . '|' . time() . '|' . substr(md5($email . getenv('APP_KEY')), 0, 8));
    }

    $baseUrl = 'https://www.wgs-service.cz';

    if ($html) {
        // HTML formát
        $footer = '
<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; text-align: center;">
    <p style="margin: 0 0 10px 0;">
        <strong>White Glove Service, s.r.o.</strong><br>
        Do Dubče 364, 190 11 Praha 9 – Běchovice<br>
        Tel: +420 725 965 826 | Email: reklamace@wgs-service.cz
    </p>
    <p style="margin: 0;">
        <a href="' . $baseUrl . '/gdpr.php" style="color: #6b7280; text-decoration: underline;">GDPR</a>
        &nbsp;|&nbsp;
        <a href="' . $baseUrl . '/cookies.php" style="color: #6b7280; text-decoration: underline;">Cookies</a>
        &nbsp;|&nbsp;
        <a href="' . $baseUrl . '/podminky.php" style="color: #6b7280; text-decoration: underline;">Obchodní podmínky</a>
    </p>';

        if ($token) {
            $footer .= '
    <p style="margin: 10px 0 0 0;">
        <a href="' . $baseUrl . '/email-odhlaseni.php?token=' . urlencode($token) . '" style="color: #9ca3af; text-decoration: underline; font-size: 11px;">
            Odhlásit se z emailových notifikací
        </a>
    </p>';
        }

        $footer .= '
</div>';

        return $body . $footer;
    } else {
        // Plain text formát
        $footer = "\n\n";
        $footer .= "---\n";
        $footer .= "White Glove Service, s.r.o.\n";
        $footer .= "Do Dubče 364, 190 11 Praha 9 – Běchovice\n";
        $footer .= "Tel: +420 725 965 826 | Email: reklamace@wgs-service.cz\n\n";
        $footer .= "GDPR: {$baseUrl}/gdpr.php\n";
        $footer .= "Cookies: {$baseUrl}/cookies.php\n";
        $footer .= "Obchodní podmínky: {$baseUrl}/podminky.php\n";

        if ($token) {
            $footer .= "\nOdhlásit se z emailů: {$baseUrl}/email-odhlaseni.php?token=" . urlencode($token) . "\n";
        }

        return $body . $footer;
    }
}

/**
 * Verifikuje unsubscribe token
 *
 * @param string $token Token z URL
 * @return array|false ['email' => string, 'timestamp' => int] nebo false
 */
function verifikovatUnsubscribeToken($token) {
    try {
        $decoded = base64_decode($token);
        if (!$decoded) return false;

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) return false;

        list($email, $timestamp, $hash) = $parts;

        // Ověřit email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        // Ověřit hash
        $expectedHash = substr(md5($email . getenv('APP_KEY')), 0, 8);
        if ($hash !== $expectedHash) return false;

        // Token platí 30 dní
        if (time() - (int)$timestamp > 30 * 24 * 60 * 60) return false;

        return [
            'email' => $email,
            'timestamp' => (int)$timestamp
        ];
    } catch (Exception $e) {
        return false;
    }
}
