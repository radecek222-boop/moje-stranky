<?php
/**
 * Notifikace Helper - PHP-side odeslání emailových notifikací
 *
 * Použití: odeslat_notifikaci_zakazky($pdo, $zakazkaId, 'appointment_confirmed')
 *          odeslat_notifikaci_zakazky($pdo, $zakazkaId, 'order_completed')
 *
 * Šablona v wgs_notifications definuje kdo dostane email (zákazník, prodejce, admin...).
 * Tato funkce pouze načte data zakázky, předá je šabloně a přidá do EmailQueue.
 */

require_once __DIR__ . '/EmailQueue.php';

/**
 * Odešle emailovou notifikaci pro danou zakázku dle šablony.
 *
 * @param PDO    $pdo          Databázové připojení
 * @param int    $zakazkaId    ID záznamu v wgs_reklamace (sloupec id)
 * @param string $triggerEvent Název události (appointment_confirmed, order_completed...)
 * @return bool True pokud byl email přidán do fronty
 */
function odeslat_notifikaci_zakazky(PDO $pdo, int $zakazkaId, string $triggerEvent): bool
{
    try {
        // === 1. NAČTENÍ ŠABLONY ===
        $stmtSablona = $pdo->prepare("
            SELECT id, name, subject, template, template_data,
                   to_recipients, cc_recipients, bcc_recipients,
                   cc_emails, bcc_emails, recipient_type, type, active
            FROM wgs_notifications
            WHERE trigger_event = :trigger
              AND type = 'email'
              AND active = 1
            LIMIT 1
        ");
        $stmtSablona->execute(['trigger' => $triggerEvent]);
        $sablona = $stmtSablona->fetch(PDO::FETCH_ASSOC);

        if (!$sablona) {
            error_log("notifikace_helper: Šablona nenalezena nebo neaktivní pro trigger '{$triggerEvent}'");
            return false;
        }

        // === 2. NAČTENÍ DAT ZAKÁZKY + PRODEJCE + TECHNIK ===
        $stmtData = $pdo->prepare("
            SELECT
                r.id, r.reklamace_id, r.cislo, r.jmeno, r.email, r.telefon,
                r.model, r.provedeni, r.popis_problemu,
                r.termin, r.cas_navstevy, r.stav,
                r.adresa, r.ulice, r.mesto, r.psc,
                r.created_at, r.datum_dokonceni, r.updated_at,
                u.email  AS prodejce_email,
                u.name   AS prodejce_jmeno,
                t.email  AS technik_email,
                t.name   AS technik_jmeno,
                t.phone  AS technik_telefon
            FROM wgs_reklamace r
            LEFT JOIN wgs_users u ON r.created_by = u.user_id
            LEFT JOIN wgs_users t ON r.assigned_to = t.id
            WHERE r.id = :id
            LIMIT 1
        ");
        $stmtData->execute(['id' => $zakazkaId]);
        $d = $stmtData->fetch(PDO::FETCH_ASSOC);

        if (!$d) {
            error_log("notifikace_helper: Zakázka ID {$zakazkaId} nenalezena");
            return false;
        }

        // Sestavení adresy
        $adresaCasti = array_filter([
            $d['ulice'] ?? $d['adresa'] ?? '',
            $d['mesto'] ?? '',
            $d['psc'] ?? ''
        ]);
        $adresa = implode(', ', $adresaCasti);

        // ID zakázky pro email
        $cisloZakazky = $d['reklamace_id'] ?: ($d['cislo'] ?: (string)$zakazkaId);

        // Datum dokončení
        $datumDokonceni = '';
        if (!empty($d['datum_dokonceni'])) {
            $datumDokonceni = date('d.m.Y', strtotime($d['datum_dokonceni']));
        } elseif (!empty($d['updated_at'])) {
            $datumDokonceni = date('d.m.Y', strtotime($d['updated_at']));
        }

        // === 3. MAPOVÁNÍ PROMĚNNÝCH PRO ŠABLONU ===
        $promenne = [
            '{{customer_name}}'      => $d['jmeno'] ?? 'Zákazník',
            '{{customer_email}}'     => $d['email'] ?? '',
            '{{customer_phone}}'     => $d['telefon'] ?? '',
            '{{order_id}}'           => $cisloZakazky,
            '{{address}}'            => $adresa,
            '{{product}}'            => $d['model'] ?? '',
            '{{description}}'        => $d['popis_problemu'] ?? '',
            '{{date}}'               => $d['termin'] ? date('d.m.Y', strtotime($d['termin'])) : '',
            '{{time}}'               => $d['cas_navstevy'] ?? '',
            '{{seller_name}}'        => $d['prodejce_jmeno'] ?? '',
            '{{seller_email}}'       => $d['prodejce_email'] ?? '',
            '{{technician_name}}'    => $d['technik_jmeno'] ?? '',
            '{{technician_email}}'   => $d['technik_email'] ?? '',
            '{{technician_phone}}'   => $d['technik_telefon'] ?? '',
            '{{created_at}}'         => $d['created_at'] ? date('d.m.Y', strtotime($d['created_at'])) : '',
            '{{completed_at}}'       => $datumDokonceni,
            '{{company_email}}'      => 'reklamace@wgs-service.cz',
            '{{company_phone}}'      => '+420 725 965 826',
        ];

        // === 4. ZPRACOVÁNÍ PŘÍJEMCŮ (dle šablony) ===
        $vyresitRoli = function (string $role) use ($d, $pdo): ?string {
            switch ($role) {
                case 'customer':
                    return !empty($d['email']) ? $d['email'] : null;
                case 'seller':
                    return !empty($d['prodejce_email']) ? $d['prodejce_email'] : null;
                case 'technician':
                    return !empty($d['technik_email']) ? $d['technik_email'] : null;
                case 'admin':
                    $stmtAdmin = $pdo->prepare("SELECT config_value FROM wgs_system_config WHERE config_key = 'admin_email' LIMIT 1");
                    $stmtAdmin->execute();
                    $adminEmail = $stmtAdmin->fetchColumn();
                    return ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) ? $adminEmail : null;
                default:
                    return null;
            }
        };

        // TO příjemci
        $toRole = !empty($sablona['to_recipients'])
            ? json_decode($sablona['to_recipients'], true)
            : [$sablona['recipient_type']];

        $toEmaily = [];
        if (is_array($toRole)) {
            foreach ($toRole as $role) {
                $email = $vyresitRoli($role);
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $toEmaily[] = $email;
                }
            }
        }

        if (empty($toEmaily)) {
            error_log("notifikace_helper: Žádný validní TO příjemce pro '{$triggerEvent}' zakázka {$cisloZakazky}");
            return false;
        }

        // CC příjemci (role)
        $ccRole = !empty($sablona['cc_recipients']) ? json_decode($sablona['cc_recipients'], true) : [];
        $ccEmaily = [];
        if (is_array($ccRole)) {
            foreach ($ccRole as $role) {
                $email = $vyresitRoli($role);
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $ccEmaily[] = $email;
                }
            }
        }
        // CC explicitní emaily (s náhradou proměnných)
        $ccExplicitni = !empty($sablona['cc_emails']) ? json_decode($sablona['cc_emails'], true) : [];
        if (is_array($ccExplicitni)) {
            foreach ($ccExplicitni as $emailSablonou) {
                $email = trim(str_replace(array_keys($promenne), array_values($promenne), $emailSablonou));
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $ccEmaily[] = $email;
                }
            }
        }

        // BCC příjemci (role)
        $bccRole = !empty($sablona['bcc_recipients']) ? json_decode($sablona['bcc_recipients'], true) : [];
        $bccEmaily = [];
        if (is_array($bccRole)) {
            foreach ($bccRole as $role) {
                $email = $vyresitRoli($role);
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $bccEmaily[] = $email;
                }
            }
        }
        // BCC explicitní emaily
        $bccExplicitni = !empty($sablona['bcc_emails']) ? json_decode($sablona['bcc_emails'], true) : [];
        if (is_array($bccExplicitni)) {
            foreach ($bccExplicitni as $emailSablonou) {
                $email = trim(str_replace(array_keys($promenne), array_values($promenne), $emailSablonou));
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $bccEmaily[] = $email;
                }
            }
        }

        // Více TO příjemců → první je hlavní, ostatní jdou do CC
        $hlavniPrijemce = array_shift($toEmaily);
        $ccEmaily = array_merge($toEmaily, $ccEmaily);

        // Odstranit duplicity
        $ccEmaily  = array_values(array_unique($ccEmaily));
        $bccEmaily = array_values(array_unique($bccEmaily));

        // === 5. NÁHRADA PROMĚNNÝCH V ŠABLONĚ ===
        $predmet = str_replace(array_keys($promenne), array_values($promenne), $sablona['subject'] ?? '');
        $telo    = str_replace(array_keys($promenne), array_values($promenne), $sablona['template'] ?? '');

        // Pokud šablona nemá HTML, zabalit do základního HTML
        if ($telo && strip_tags($telo) === $telo) {
            $telo = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;">'
                  . nl2br(htmlspecialchars($telo))
                  . '</div>';
        }

        // === 6. PŘIDAT DO FRONTY ===
        $emailFronta = new EmailQueue($pdo);
        $vysledek = $emailFronta->enqueue([
            'notification_id' => $sablona['id'],
            'to'              => $hlavniPrijemce,
            'to_name'         => $d['jmeno'] ?? null,
            'subject'         => $predmet,
            'body'            => $telo,
            'cc'              => $ccEmaily,
            'bcc'             => $bccEmaily,
            'priority'        => 'normal',
        ]);

        if ($vysledek) {
            error_log("notifikace_helper: Email do fronty OK | trigger={$triggerEvent} zakázka={$cisloZakazky} TO={$hlavniPrijemce} CC=" . implode(',', $ccEmaily));
        } else {
            error_log("notifikace_helper: Nepodařilo se přidat do fronty | trigger={$triggerEvent} zakázka={$cisloZakazky}");
        }

        return (bool)$vysledek;

    } catch (Exception $vyjimka) {
        error_log("notifikace_helper: Chyba při odesílání '{$triggerEvent}' pro zakázku {$zakazkaId}: " . $vyjimka->getMessage());
        return false;
    }
}
