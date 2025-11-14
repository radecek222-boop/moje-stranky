-- ============================================
-- Přidání úkolu: Instalace PHPMailer
-- Datum: 2025-11-14
-- ============================================

-- Zkontrolovat, zda úkol už neexistuje (pending nebo in_progress)
SET @existing_task = (
    SELECT COUNT(*)
    FROM wgs_pending_actions
    WHERE action_type = 'install_phpmailer'
    AND status IN ('pending', 'in_progress')
);

-- Přidat úkol pouze pokud neexistuje
INSERT INTO wgs_pending_actions (
    action_type,
    action_title,
    action_description,
    action_url,
    priority,
    status
)
SELECT
    'install_phpmailer',
    '📧 Nainstalovat PHPMailer',
    'PHPMailer je potřeba pro odesílání emailů přes SMTP. Bez něj email queue používá pouze PHP mail() funkci, která často nefunguje na sdíleném hostingu.

Po instalaci:
✅ Emaily budou odcházet spolehlivě přes SMTP
✅ Email queue cron bude fungovat správně
✅ Budete vidět detailní chybové zprávy při problémech

Instalace automaticky:
• Stáhne PHPMailer z GitHubu
• Rozbalí do vendor/phpmailer/
• Vytvoří autoload.php
• Otestuje funkčnost',
    'scripts/install_phpmailer.php',
    'high',
    'pending'
WHERE @existing_task = 0;

-- Výsledek
SELECT
    CASE
        WHEN @existing_task > 0 THEN 'Úkol už existuje - přeskakuji'
        ELSE 'Úkol úspěšně přidán'
    END AS status;

-- ============================================
-- Konec migrace
-- ============================================
