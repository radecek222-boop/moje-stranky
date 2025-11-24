<?php
/**
 * Notifikace uživatelů o nepřečtených poznámkách
 *
 * Tento skript kontroluje všechny aktivní uživatele a odesílá jim
 * emailovou notifikaci, pokud mají nepřečtené poznámky starší než 24 hodin.
 *
 * Spouští se: Denně v rámci ultra_master_cron.php
 *
 * @version 1.0.0
 * @date 2025-11-24
 */

// Absolutní cesta k root složce
$rootDir = dirname(__DIR__);
require_once $rootDir . '/init.php';

// Logování
function logujZpravu($zprava) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$zprava}\n";
}

logujZpravu("========================================");
logujZpravu("START: Notifikace nepřečtených poznámek");
logujZpravu("========================================");

try {
    $pdo = getDbConnection();

    // Načíst všechny aktivní uživatele s jejich emaily
    $stmtUsers = $pdo->query("
        SELECT DISTINCT
            user_id,
            email,
            name,
            role
        FROM wgs_users
        WHERE is_active = 1
          AND email IS NOT NULL
          AND email != ''
        ORDER BY email
    ");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    logujZpravu("Nalezeno aktivních uživatelů: " . count($users));

    $odeslanychEmailu = 0;
    $preskocenychUzivatelu = 0;

    foreach ($users as $user) {
        $userEmail = $user['email'];
        $userName = $user['name'];
        $userId = $user['user_id'];
        $userRole = strtolower(trim($user['role']));

        // Sestavit WHERE podmínky podle role (stejná logika jako v get_user_stats.php)
        $whereParts = [];
        $params = [
            ':user_email' => $userEmail,
            ':user_email_author' => $userEmail
        ];

        $isProdejce = in_array($userRole, ['prodejce', 'user'], true);
        $isTechnik = in_array($userRole, ['technik', 'technician'], true);

        if ($isProdejce) {
            // PRODEJCE: Vidí pouze poznámky u SVÝCH reklamací
            $whereParts[] = 'r.created_by = :created_by';
            $params[':created_by'] = $userId;
        } elseif ($isTechnik) {
            // TECHNIK: Vidí poznámky u VŠECH reklamací
            // Žádný filtr
        } else {
            // GUEST: Vidí poznámky pouze u reklamací se svým emailem
            $whereParts[] = 'LOWER(TRIM(r.email)) = LOWER(TRIM(:guest_email))';
            $params[':guest_email'] = $userEmail;
        }

        $whereClause = '';
        if (!empty($whereParts)) {
            $whereClause = ' AND ' . implode(' AND ', $whereParts);
        }

        // Načíst nepřečtené poznámky starší než 24 hodin
        $sqlUnread = "
            SELECT
                n.id,
                n.note_text,
                n.created_at,
                n.created_by,
                r.reklamace_id,
                r.cislo,
                DATEDIFF(NOW(), n.created_at) as days_old
            FROM wgs_notes n
            INNER JOIN wgs_reklamace r ON n.claim_id = r.id
            LEFT JOIN wgs_notes_read nr ON n.id = nr.note_id AND nr.user_email = :user_email
            WHERE nr.id IS NULL
              AND n.created_by != :user_email_author
              AND n.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
              $whereClause
            ORDER BY n.created_at DESC
            LIMIT 10
        ";

        $stmtUnread = $pdo->prepare($sqlUnread);
        $stmtUnread->execute($params);
        $unreadNotes = $stmtUnread->fetchAll(PDO::FETCH_ASSOC);

        $unreadCount = count($unreadNotes);

        if ($unreadCount === 0) {
            $preskocenychUzivatelu++;
            continue; // Žádné nepřečtené poznámky, přeskočit
        }

        logujZpravu("Uživatel {$userName} ({$userEmail}): {$unreadCount} nepřečtených poznámek");

        // Sestavit email s přehledem nepřečtených poznámek
        $emailSubject = "WGS: Máte {$unreadCount} nepřečtených poznámek";

        $emailBody = "Dobrý den {$userName},\n\n";
        $emailBody .= "máte celkem {$unreadCount} nepřečtených poznámek v systému WGS.\n\n";
        $emailBody .= "Přehled nejnovějších nepřečtených poznámek:\n\n";
        $emailBody .= "========================================\n\n";

        foreach ($unreadNotes as $note) {
            $reklamaceOznaceni = $note['reklamace_id'] ?: $note['cislo'];
            $poznamkaText = mb_substr($note['note_text'], 0, 100); // Max 100 znaků
            $autor = $note['created_by'];
            $datum = date('d.m.Y H:i', strtotime($note['created_at']));
            $daysOld = $note['days_old'];

            $emailBody .= "Reklamace: {$reklamaceOznaceni}\n";
            $emailBody .= "Od: {$autor}\n";
            $emailBody .= "Datum: {$datum} (před {$daysOld} dny)\n";
            $emailBody .= "Text: {$poznamkaText}" . (strlen($note['note_text']) > 100 ? '...' : '') . "\n";
            $emailBody .= "----------------------------------------\n\n";
        }

        $emailBody .= "\n";
        $emailBody .= "Pro zobrazení všech poznámek se prosím přihlaste do systému:\n";
        $emailBody .= "https://www.wgs-service.cz/login.php\n\n";
        $emailBody .= "S pozdravem,\n";
        $emailBody .= "Tým White Glove Service\n";

        // Odeslat email pomocí PHPMailer (přes email queue)
        require_once $rootDir . '/includes/EmailQueue.php';
        $emailQueue = new EmailQueue($pdo);

        $emailQueue->queueEmail(
            $userEmail,
            $emailSubject,
            $emailBody,
            null, // $from (použije se výchozí)
            null, // $replyTo
            [], // $attachments
            'high' // Priorita - high pro upozornění
        );

        $odeslanychEmailu++;
        logujZpravu("  → Email zařazen do fronty");
    }

    logujZpravu("========================================");
    logujZpravu("SUMMARY:");
    logujZpravu("Celkem uživatelů: " . count($users));
    logujZpravu("Odesláno emailů: {$odeslanychEmailu}");
    logujZpravu("Přeskočeno (bez nepřečtených): {$preskocenychUzivatelu}");
    logujZpravu("========================================");
    logujZpravu("HOTOVO");

} catch (Exception $e) {
    logujZpravu("CHYBA: " . $e->getMessage());
    error_log("Error in notifikovat_neprecte_poznamky.php: " . $e->getMessage());
    exit(1);
}
?>
