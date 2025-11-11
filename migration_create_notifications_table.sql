-- ============================================
-- Migration: Create wgs_notifications table
-- Datum: 2025-11-11
-- Účel: Přesun hardcoded email šablon do databáze pro správu v admin UI
-- ============================================

CREATE TABLE IF NOT EXISTS wgs_notifications (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trigger_event VARCHAR(100) NOT NULL,
    recipient_type ENUM('customer', 'admin', 'technician', 'seller') NOT NULL,
    type ENUM('email', 'sms', 'both') NOT NULL DEFAULT 'email',
    subject VARCHAR(255) DEFAULT NULL,
    template TEXT NOT NULL,
    variables JSON DEFAULT NULL,
    cc_emails JSON DEFAULT NULL,
    bcc_emails JSON DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Vložení existujících šablon z notification_sender.php
-- ============================================

INSERT INTO wgs_notifications (
    id,
    name,
    description,
    trigger_event,
    recipient_type,
    type,
    subject,
    template,
    variables,
    active
) VALUES
(
    'appointment_confirmed',
    'Potvrzení termínu návštěvy',
    'Email odesílaný zákazníkovi po potvrzení termínu návštěvy technika',
    'appointment_confirmed',
    'customer',
    'email',
    'Potvrzení termínu návštěvy - WGS Servis',
    'Dobrý den {{customer_name}},

potvrzujeme termín návštěvy technika:

Datum: {{date}}
Čas: {{time}}
Číslo zakázky: {{order_id}}

V případě jakýchkoli dotazů nás prosím kontaktujte.

S pozdravem,
White Glove Service
Tel: +420 725 965 826
Email: reklamace@wgs-service.cz',
    JSON_ARRAY('{{customer_name}}', '{{date}}', '{{time}}', '{{order_id}}'),
    1
),
(
    'order_reopened',
    'Zakázka znovu otevřena',
    'Notifikace pro admin/techniky při znovuotevření zakázky',
    'order_reopened',
    'admin',
    'email',
    'Zakázka #{{order_id}} byla znovu otevřena',
    'Zákazník: {{customer_name}}
Zakázka č.: {{order_id}}

Zakázka byla znovu otevřena uživatelem {{reopened_by}} dne {{reopened_at}}.

Stav byl změněn na NOVÁ. Termín byl vymazán.',
    JSON_ARRAY('{{customer_name}}', '{{order_id}}', '{{reopened_by}}', '{{reopened_at}}'),
    1
),
(
    'order_created',
    'Nová reklamace vytvořena',
    'Notifikace pro admin při vytvoření nové reklamace',
    'order_created',
    'admin',
    'email',
    'Nová reklamace #{{order_id}} - {{customer_name}}',
    'Byla vytvořena nová reklamace:

Zákazník: {{customer_name}}
Telefon: {{customer_phone}}
Email: {{customer_email}}
Adresa: {{address}}

Produkt: {{product}}
Popis problému: {{description}}

Vytvořeno: {{created_at}}',
    JSON_ARRAY('{{order_id}}', '{{customer_name}}', '{{customer_phone}}', '{{customer_email}}', '{{address}}', '{{product}}', '{{description}}', '{{created_at}}'),
    1
),
(
    'appointment_reminder_customer',
    'Připomenutí termínu zákazníkovi',
    'Email připomenutí den před návštěvou technika',
    'appointment_reminder',
    'customer',
    'email',
    'Připomenutí termínu návštěvy - zítra - WGS Servis',
    'Dobrý den {{customer_name}},

připomínáme termín návštěvy našeho technika:

Datum: {{date}}
Čas: {{time}}
Adresa: {{address}}
Číslo zakázky: {{order_id}}

Pokud potřebujete termín změnit, kontaktujte nás prosím co nejdříve.

S pozdravem,
White Glove Service
Tel: +420 725 965 826
Email: reklamace@wgs-service.cz',
    JSON_ARRAY('{{customer_name}}', '{{date}}', '{{time}}', '{{address}}', '{{order_id}}'),
    1
),
(
    'appointment_assigned_technician',
    'Přiřazení termínu technikovi',
    'Notifikace pro technika při přiřazení nového termínu',
    'appointment_assigned',
    'technician',
    'email',
    'Nový termín přiřazen - {{date}} {{time}}',
    'Dobrý den {{technician_name}},

byl vám přiřazen nový servisní termín:

Datum: {{date}}
Čas: {{time}}
Zákazník: {{customer_name}}
Telefon: {{customer_phone}}
Adresa: {{address}}

Produkt: {{product}}
Popis problému: {{description}}

Číslo zakázky: {{order_id}}

Prosím potvrďte přijetí termínu v admin systému.',
    JSON_ARRAY('{{technician_name}}', '{{date}}', '{{time}}', '{{customer_name}}', '{{customer_phone}}', '{{address}}', '{{product}}', '{{description}}', '{{order_id}}'),
    1
),
(
    'order_completed',
    'Zakázka dokončena',
    'Poděkování zákazníkovi po dokončení zakázky',
    'order_completed',
    'customer',
    'email',
    'Děkujeme za využití našich služeb - WGS Servis',
    'Dobrý den {{customer_name}},

děkujeme, že jste využili služeb White Glove Service.

Zakázka č. {{order_id}} byla úspěšně dokončena dne {{completed_at}}.

Pokud byste měli jakékoli dotazy nebo připomínky k provedené opravě, neváhejte nás kontaktovat.

Budeme rádi, když nás doporučíte svým známým.

S pozdravem,
White Glove Service
Tel: +420 725 965 826
Email: reklamace@wgs-service.cz
Web: www.wgs-service.cz',
    JSON_ARRAY('{{customer_name}}', '{{order_id}}', '{{completed_at}}'),
    1
);

-- ============================================
-- Kontrola výsledku
-- ============================================
SELECT 'Migration completed successfully!' AS status;
SELECT COUNT(*) AS notification_count FROM wgs_notifications;
