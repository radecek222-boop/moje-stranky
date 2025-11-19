<?php
/**
 * Příklady použití EmailClient
 *
 * Tento soubor obsahuje ukázkové příklady jak používat nový
 * centralizovaný email systém přes emailClient.php
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/emailClient.php';

// Vytvoření instance
$emailClient = new EmailClient();

// =======================================================
// PŘÍKLAD 1: Jednoduchý plaintext email
// =======================================================
$result = $emailClient->odeslat([
    'to' => 'zakaznik@example.com',
    'subject' => 'Potvrzení termínu návštěvy',
    'body' => 'Dobrý den,

potvrzujeme termín návštěvy na 25.11.2025 v 14:00.

S pozdravem,
White Glove Service'
]);

if ($result['success']) {
    echo "✓ Email odeslán: {$result['message']}\n";
} else {
    echo "✗ Chyba: {$result['message']}\n";
}

// =======================================================
// PŘÍKLAD 2: HTML email s přílohou
// =======================================================
$result = $emailClient->odeslat([
    'to' => 'zakaznik@example.com',
    'to_name' => 'Jan Novák',
    'subject' => 'Servisní protokol',
    'body' => '<h1>Servisní protokol</h1>
<p>Dobrý den,</p>
<p>v příloze zasíláme servisní protokol.</p>',
    'html' => true,
    'attachments' => [
        '/path/to/protokol.pdf'
    ]
]);

// =======================================================
// PŘÍKLAD 3: Email s CC a BCC
// =======================================================
$result = $emailClient->odeslat([
    'to' => 'zakaznik@example.com',
    'subject' => 'Informace o reklamaci',
    'body' => 'Tělo emailu...',
    'cc' => ['admin@wgs-service.cz'],
    'bcc' => ['archiv@wgs-service.cz']
]);

// =======================================================
// PŘÍKLAD 4: Email s prioritou (vysoká)
// =======================================================
$result = $emailClient->odeslat([
    'to' => 'zakaznik@example.com',
    'subject' => 'URGENT: Změna termínu',
    'body' => 'Tělo emailu...',
    'priority' => 1 // 1=high, 3=normal, 5=low
]);

// =======================================================
// PŘÍKLAD 5: Asynchronní odeslání přes email queue
// =======================================================
$result = $emailClient->odeslat([
    'to' => 'zakaznik@example.com',
    'subject' => 'Newsletter',
    'body' => 'Tělo emailu...',
    'use_queue' => true,  // ← PŘIDÁ DO FRONTY místo přímého odeslání
    'priority' => 'low'
]);

if ($result['queued']) {
    echo "✓ Email přidán do fronty pro pozdější odeslání\n";
}

// =======================================================
// PŘÍKLAD 6: Email s vlastním odesílatelem
// =======================================================
$result = $emailClient->odeslat([
    'to' => 'zakaznik@example.com',
    'from' => 'podpora@wgs-service.cz',
    'from_name' => 'WGS Podpora',
    'subject' => 'Odpověď na dotaz',
    'body' => 'Tělo emailu...',
    'reply_to' => 'podpora@wgs-service.cz'
]);

// =======================================================
// PŘÍKLAD 7: Email s více příjemci
// =======================================================
$result = $emailClient->odeslat([
    'to' => [
        'zakaznik1@example.com' => 'Jan Novák',
        'zakaznik2@example.com' => 'Petr Svoboda'
    ],
    'subject' => 'Hromadná notifikace',
    'body' => 'Tělo emailu...'
]);

// =======================================================
// PŘÍKLAD 8: Získání informací o konfiguraci
// =======================================================
$info = $emailClient->ziskatInfo();

echo "=== Konfigurace EmailClient ===\n";
echo "PHPMailer dostupný: " . ($info['phpmailer_available'] ? 'ANO' : 'NE') . "\n";
echo "SMTP host: {$info['smtp_host']}\n";
echo "SMTP port: {$info['smtp_port']}\n";
echo "SMTP encryption: {$info['smtp_encryption']}\n";
echo "SMTP username: {$info['smtp_username']}\n";
echo "From email: {$info['smtp_from']}\n";

// =======================================================
// PŘÍKLAD 9: Použití v protokol_api.php (refaktorováno)
// =======================================================
function odeslatProtokolZakaznikovi($reklamaceData, $pdfPath) {
    $emailClient = new EmailClient();

    $result = $emailClient->odeslat([
        'to' => $reklamaceData['email'],
        'to_name' => $reklamaceData['jmeno'],
        'subject' => "Servisní protokol WGS - Reklamace č. {$reklamaceData['cislo']}",
        'body' => "Dobrý den {$reklamaceData['jmeno']},

zasíláme Vám kompletní servisní report k reklamaci č. {$reklamaceData['cislo']}.

V příloze naleznete servisní protokol s fotodokumentací.

S pozdravem,
White Glove Service
reklamace@wgs-service.cz
+420 725 965 826",
        'attachments' => [
            [
                'path' => $pdfPath,
                'name' => "WGS_Report_{$reklamaceData['cislo']}.pdf"
            ]
        ],
        'use_queue' => true,  // ← ASYNCHRONNÍ! UX je rychlé
        'priority' => 'high'
    ]);

    return $result;
}

// =======================================================
// PŘÍKLAD 10: Použití v notification_sender.php (už je)
// =======================================================
// notification_sender.php už používá EmailQueue->enqueue()
// což je v pořádku, ale může být refaktorováno na:

function odeslatNotifikaci($notificationId, $data) {
    $emailClient = new EmailClient();

    // Načíst šablonu z databáze (stejně jako dřív)
    // ... načtení šablony ...

    $result = $emailClient->odeslat([
        'to' => $data['customer_email'],
        'subject' => $parsedSubject,
        'body' => $parsedBody,
        'cc' => $ccEmails,
        'bcc' => $bccEmails,
        'use_queue' => true,
        'notification_id' => $notificationId
    ]);

    return $result;
}
