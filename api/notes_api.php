<?php
/**
 * Notes API
 * API pro práci s poznámkami k reklamacím
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/reklamace_id_validator.php';
require_once __DIR__ . '/../includes/WebPush.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Kontrola přihlášení
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'error' => 'Neautorizovaný přístup. Přihlaste se prosím.'
        ]);
        exit;
    }

    // PERFORMANCE FIX: Načíst session data a uvolnit zámek
    // Audit 2025-11-24: Session locking blokuje paralelní requesty
    $currentUserEmail = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    // KRITICKÉ: Uvolnit session lock pro paralelní zpracování
    session_write_close();

    // Zjištění akce
    $action = '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        // SECURITY FIX: GET metoda může provádět pouze read-only operace
        // Prevence CSRF útoku přes GET requesty (např. <img src="...?action=delete&...">)
        $readOnlyActions = ['get', 'list', 'count', 'get_unread_counts'];
        if (!in_array($action, $readOnlyActions, true)) {
            // DEBUG: Vice info o requestu
            $debugInfo = [
                'php_sees_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNDEFINED',
                'action_from_GET' => $action,
                'POST_data' => $_POST,
                'php_input' => substr(file_get_contents('php://input'), 0, 500)
            ];
            throw new Exception('GET request pro non-read akci. DEBUG: ' . json_encode($debugInfo, JSON_UNESCAPED_UNICODE));
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // BEZPEČNOST: CSRF ochrana pro POST operace
        requireCSRF();
    } else {
        throw new Exception('Povolena pouze GET nebo POST metoda');
    }

    $pdo = getDbConnection();

    switch ($action) {
        case 'get':
            // Načtení poznámek
            $reklamaceId = sanitizeReklamaceId($_GET['reklamace_id'] ?? null, 'reklamace_id');

            // Převést reklamace_id na claim_id (číselné ID)
            $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
            $stmt->execute([':reklamace_id' => $reklamaceId, ':cislo' => $reklamaceId]);
            $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reklamace) {
                throw new Exception('Reklamace nebyla nalezena');
            }

            $claimId = $reklamace['id'];

            // Načtení poznámek z databáze s read status pro aktuálního uživatele
            // $currentUserEmail již načteno výše (řádek 27)

            $stmt = $pdo->prepare("
                SELECT
                    n.id,
                    n.claim_id,
                    n.note_text,
                    n.audio_path,
                    n.created_by,
                    n.created_at,
                    CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END as is_read,
                    u.name as user_name
                FROM wgs_notes n
                LEFT JOIN wgs_notes_read nr ON n.id = nr.note_id AND nr.user_email = :user_email
                LEFT JOIN wgs_users u ON n.created_by = u.email
                WHERE n.claim_id = :claim_id
                ORDER BY n.created_at DESC
            ");
            $stmt->execute([
                ':claim_id' => $claimId,
                ':user_email' => $currentUserEmail
            ]);
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Převést is_read na boolean pro frontend
            foreach ($notes as &$note) {
                $note['read'] = (bool)$note['is_read'];
                $note['author'] = $note['created_by'];

                // Zobrazit jméno místo emailu
                if ($note['created_by'] === 'admin@wgs-service.cz') {
                    $note['author_name'] = 'Radek';
                } else {
                    $note['author_name'] = $note['user_name'] ?: $note['created_by'];
                }

                $note['timestamp'] = $note['created_at'];
                $note['text'] = $note['note_text'];  // KRITICKÉ: Mapování textu poznámky

                // Audio podpora
                $note['has_audio'] = !empty($note['audio_path']);
                // Pokud existuje audio, přidat URL pro přehrávání
                if ($note['has_audio']) {
                    $note['audio_url'] = $note['audio_path'];
                }

                unset($note['is_read']);
                unset($note['user_name']); // Vyčistit pomocný sloupec
            }

            echo json_encode([
                'status' => 'success',
                'notes' => $notes
            ]);
            break;

        case 'add':
            // Přidání poznámky (textové nebo hlasové)
            $reklamaceId = sanitizeReklamaceId($_POST['reklamace_id'] ?? null, 'reklamace_id');
            $text = $_POST['text'] ?? null;
            $audioPath = null;

            // Zpracování audio souboru pokud byl nahrán
            if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
                $audioFile = $_FILES['audio'];

                // BEZPEČNOST: Validace typu souboru
                // video/webm a video/mp4 - Safari/WebKit nahrává audio jako video MIME typ
                $allowedMimes = ['audio/webm', 'audio/mp3', 'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/x-m4a', 'video/webm', 'video/mp4'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($audioFile['tmp_name']);

                if (!in_array($mimeType, $allowedMimes, true)) {
                    throw new Exception('Nepovolený typ audio souboru: ' . $mimeType);
                }

                // BEZPEČNOST: Maximální velikost 10MB
                if ($audioFile['size'] > 10 * 1024 * 1024) {
                    throw new Exception('Audio soubor je příliš velký (max 10MB)');
                }

                // Vytvořit adresář pokud neexistuje
                $uploadDir = __DIR__ . '/../uploads/audio/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Vygenerovat unikátní název souboru
                $extension = 'webm'; // Výchozí pro MediaRecorder
                if ($mimeType === 'audio/mp3' || $mimeType === 'audio/mpeg') {
                    $extension = 'mp3';
                } elseif ($mimeType === 'audio/ogg') {
                    $extension = 'ogg';
                } elseif ($mimeType === 'audio/wav') {
                    $extension = 'wav';
                } elseif ($mimeType === 'audio/mp4' || $mimeType === 'audio/x-m4a') {
                    $extension = 'm4a';
                }

                $filename = 'audio_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $targetPath = $uploadDir . $filename;

                if (!move_uploaded_file($audioFile['tmp_name'], $targetPath)) {
                    throw new Exception('Nepodařilo se uložit audio soubor');
                }

                $audioPath = 'uploads/audio/' . $filename;

                // Pro hlasovou poznámku bez textu nastavit výchozí text
                if (empty($text)) {
                    $text = '[Hlasová poznámka]';
                }
            }

            // Validace - musí být alespoň text nebo audio
            if (empty($text) && empty($audioPath)) {
                throw new Exception('Chybí text poznámky nebo audio');
            }

            // BEZPEČNOST: Validace textu
            $text = trim($text);
            if (strlen($text) > 5000) {
                throw new Exception('Text poznámky je příliš dlouhý (max 5000 znaků)');
            }

            // BEZPEČNOST: XSS ochrana - sanitizace HTML
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            // Převést reklamace_id na claim_id (číselné ID)
            $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
            $stmt->execute([':reklamace_id' => $reklamaceId, ':cislo' => $reklamaceId]);
            $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reklamace) {
                throw new Exception('Reklamace nebyla nalezena');
            }

            $claimId = $reklamace['id'];

            // Zjištění autora
            $createdBy = $currentUserEmail ?? 'system';

            // Vložení do databáze (s audio_path pokud existuje)
            $stmt = $pdo->prepare("
                INSERT INTO wgs_notes (
                    claim_id, note_text, audio_path, created_by, created_at
                ) VALUES (
                    :claim_id, :note_text, :audio_path, :created_by, NOW()
                )
            ");
            $stmt->execute([
                ':claim_id' => $claimId,
                ':note_text' => $text,
                ':audio_path' => $audioPath,
                ':created_by' => $createdBy
            ]);

            $noteId = $pdo->lastInsertId();

            // ========================================
            // PUSH NOTIFIKACE - Odeslat relevantním uživatelům
            // ========================================
            try {
                $webPush = new WGSWebPush($pdo);

                if ($webPush->jeInicializovano()) {
                    // Načíst info o reklamaci včetně vlastníka (created_by + email vlastníka)
                    $stmtInfo = $pdo->prepare("
                        SELECT r.reklamace_id, r.jmeno, r.cislo, r.created_by,
                               u.email as vlastnik_email
                        FROM wgs_reklamace r
                        LEFT JOIN wgs_users u ON (
                            u.user_id = r.created_by OR u.id = r.created_by
                        )
                        WHERE r.id = :id
                    ");
                    $stmtInfo->execute([':id' => $claimId]);
                    $infoReklamace = $stmtInfo->fetch(PDO::FETCH_ASSOC);

                    // Vlastník reklamace (prodejce který ji vytvořil)
                    $vlastnikReklamace = $infoReklamace['created_by'] ?? null;
                    $vlastnikEmail = $infoReklamace['vlastnik_email'] ?? null;

                    // Zjistit jmeno autora poznamky
                    $jmenoAutora = $createdBy; // fallback na email
                    $stmtAutor = $pdo->prepare("SELECT name FROM wgs_users WHERE email = :email LIMIT 1");
                    $stmtAutor->execute([':email' => $createdBy]);
                    $autorRow = $stmtAutor->fetch(PDO::FETCH_ASSOC);
                    if ($autorRow && !empty($autorRow['name'])) {
                        $jmenoAutora = $autorRow['name'];
                    }

                    // Cislo reklamace
                    $cisloReklamace = $infoReklamace['cislo'] ?? $infoReklamace['reklamace_id'] ?? '';

                    // Sestavit payload
                    $payload = [
                        'title' => 'WGS',
                        'body' => $jmenoAutora . ' prave pridal poznamku k ' . $cisloReklamace,
                        'icon' => '/icon192.png',
                        'tag' => 'wgs-note-' . $noteId,
                        'typ' => 'nova_poznamka',
                        'data' => [
                            'claim_id' => $claimId,
                            'reklamace_id' => $infoReklamace['reklamace_id'] ?? null,
                            'note_id' => $noteId,
                            'url' => '/seznam.php?highlight=' . $claimId
                        ]
                    ];

                    // ========================================
                    // PRAVIDLA PRO NOTIFIKACE:
                    // - Admin/Technik: vidí vše → dostane notifikaci (pokud není autor)
                    // - Prodejce: vidí jen své → dostane notifikaci jen pokud je vlastník reklamace
                    // - Autor poznámky NIKDY nedostane notifikaci
                    // ========================================
                    // OPRAVA: Zjednoduseny dotaz - nacist VSE a filtrovat v PHP
                    // (reseni problemu s datovymi typy user_id mezi tabulkami)
                    $stmtSubs = $pdo->prepare("
                        SELECT ps.id, ps.endpoint, ps.p256dh, ps.auth, ps.user_id, ps.email
                        FROM wgs_push_subscriptions ps
                        WHERE ps.aktivni = 1
                    ");
                    $stmtSubs->execute();
                    $vsechnySubscriptions = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

                    // Filtrovat podle pravidel viditelnosti
                    $subscriptions = [];
                    foreach ($vsechnySubscriptions as $sub) {
                        $subUserId = $sub['user_id'] ?? null;
                        $subEmail = $sub['email'] ?? null;

                        // Preskocit autora poznamky - kontrolovat OBOJE user_id i email
                        // FIX: Nekteri uzivatele nemaji user_id v session, tak porovnavame i email
                        $jeAutor = false;
                        if ($subUserId !== null && $userId !== null && (string)$subUserId === (string)$userId) {
                            $jeAutor = true;
                        }
                        if ($subEmail !== null && $createdBy !== null && strtolower($subEmail) === strtolower($createdBy)) {
                            $jeAutor = true;
                        }
                        if ($jeAutor) {
                            continue;
                        }

                        // Nacist roli uzivatele z wgs_users
                        // Zkusit nejdrive podle user_id, pak podle id (zpetna kompatibilita)
                        $subRole = 'guest';
                        if ($subUserId !== null) {
                            // Zkusit podle user_id (spravny zpusob)
                            $stmtRole = $pdo->prepare("SELECT role FROM wgs_users WHERE user_id = :uid LIMIT 1");
                            $stmtRole->execute([':uid' => $subUserId]);
                            $roleRow = $stmtRole->fetch(PDO::FETCH_ASSOC);

                            // Fallback: zkusit podle id (pro stare subscriptions s auto-increment id)
                            if (!$roleRow && is_numeric($subUserId)) {
                                $stmtRole = $pdo->prepare("SELECT role FROM wgs_users WHERE id = :uid LIMIT 1");
                                $stmtRole->execute([':uid' => (int)$subUserId]);
                                $roleRow = $stmtRole->fetch(PDO::FETCH_ASSOC);
                            }

                            $subRole = strtolower(trim($roleRow['role'] ?? 'guest'));
                        }

                        // Admin a Technik vidí vše - dostanou notifikaci
                        if (in_array($subRole, ['admin', 'technik', 'technician'])) {
                            $subscriptions[] = $sub;
                        }
                        // Prodejce vidí jen své reklamace - kontrola podle user_id NEBO email
                        elseif (in_array($subRole, ['prodejce', 'user'])) {
                            $jeVlastnik = false;

                            // Kontrola 1: Podle user_id
                            if ($vlastnikReklamace !== null && $subUserId !== null && (string)$subUserId === (string)$vlastnikReklamace) {
                                $jeVlastnik = true;
                            }

                            // Kontrola 2: Podle emailu (fallback)
                            if (!$jeVlastnik && $vlastnikEmail !== null && $subEmail !== null && strtolower($subEmail) === strtolower($vlastnikEmail)) {
                                $jeVlastnik = true;
                            }

                            if ($jeVlastnik) {
                                $subscriptions[] = $sub;
                            }
                        }
                        // Ostatní (guest) - povoleno pokud odpovídá vlastníkovi (user_id NEBO email)
                        else {
                            $jeVlastnik = false;

                            // Kontrola 1: Podle user_id
                            if ($vlastnikReklamace !== null && $subUserId !== null && (string)$subUserId === (string)$vlastnikReklamace) {
                                $jeVlastnik = true;
                            }

                            // Kontrola 2: Podle emailu (fallback)
                            if (!$jeVlastnik && $vlastnikEmail !== null && $subEmail !== null && strtolower($subEmail) === strtolower($vlastnikEmail)) {
                                $jeVlastnik = true;
                            }

                            if ($jeVlastnik) {
                                $subscriptions[] = $sub;
                            }
                        }
                    }

                    if (!empty($subscriptions)) {
                        $webPush->odeslatVice($subscriptions, $payload);
                    }
                }
            } catch (Exception $pushError) {
                // Push chyby neblokuji přidání poznámky - logovat pouze do souboru bez citlivých dat
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Poznámka přidána',
                'note_id' => $noteId
            ]);
            break;

        case 'delete':
            // Smazání poznámky
            $noteId = $_POST['note_id'] ?? null;

            if (!$noteId) {
                throw new Exception('Chybí note_id');
            }

            // BEZPEČNOST: Validace ID (pouze čísla)
            if (!is_numeric($noteId)) {
                throw new Exception('Neplatné ID poznámky');
            }

            // SECURITY FIX: Kontrola vlastnictví poznámky
            // Poznámka: created_by je EMAIL, ne user_id!
            // $currentUserEmail a $isAdmin již načteno výše (řádky 27, 30)

            if (!$currentUserEmail && !$isAdmin) {
                throw new Exception('Přístup odepřen');
            }

            // Nejdřív načíst audio_path pro případné smazání souboru
            $stmtAudio = $pdo->prepare("SELECT audio_path, created_by FROM wgs_notes WHERE id = :id");
            $stmtAudio->execute([':id' => $noteId]);
            $noteData = $stmtAudio->fetch(PDO::FETCH_ASSOC);

            // Kontrola oprávnění
            if (!$noteData) {
                throw new Exception('Poznámka nebyla nalezena');
            }

            // Autor muze smazat svou poznamku, admin muze smazat cokoliv
            $jeAutor = $currentUserEmail && $noteData['created_by'] === $currentUserEmail;
            if (!$isAdmin && !$jeAutor) {
                throw new Exception('Nemáte oprávnění smazat tuto poznámku');
            }

            // Smazat audio soubor pokud existuje
            if (!empty($noteData['audio_path'])) {
                $audioFile = __DIR__ . '/../' . $noteData['audio_path'];
                if (file_exists($audioFile)) {
                    unlink($audioFile);
                }
            }

            // Smazání z databáze
            $stmt = $pdo->prepare("DELETE FROM wgs_notes WHERE id = :id");
            $stmt->execute([':id' => $noteId]);

            // Kontrola zda byla poznámka smazána
            if ($stmt->rowCount() === 0) {
                throw new Exception('Poznámku nelze smazat');
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Poznámka smazána'
            ]);
            break;

        case 'get_unread_counts':
            // Načtení počtu nepřečtených poznámek pro reklamace podle oprávnění
            // $currentUserEmail, $userId, $userRole, $isAdmin již načteno výše (řádky 27-30)

            if (!$currentUserEmail) {
                throw new Exception('Uživatel není přihlášen');
            }

            // Sestavit WHERE podmínky podle role (stejná logika jako v load.php)
            $whereParts = [];
            $params = [
                ':user_email' => $currentUserEmail,
                ':user_email_author' => $currentUserEmail
            ];

            if (!$isAdmin) {
                $isProdejce = in_array($userRole, ['prodejce', 'user'], true);
                $isTechnik = in_array($userRole, ['technik', 'technician'], true);

                if ($isProdejce) {
                    // PRODEJCE: Vidí pouze poznámky u SVÝCH reklamací
                    if ($userId !== null) {
                        $whereParts[] = 'r.created_by = :created_by';
                        $params[':created_by'] = $userId;
                    } else {
                        $whereParts[] = '1 = 0'; // Bez user_id nevidí nic
                    }
                } elseif ($isTechnik) {
                    // TECHNIK: Vidí poznámky u VŠECH reklamací (žádný filtr)
                    // Necháme prázdné whereParts pro technika
                } else {
                    // GUEST: Vidí poznámky pouze u reklamací se svým emailem
                    $guestConditions = [];
                    $guestConditions[] = 'LOWER(TRIM(r.email)) = LOWER(TRIM(:guest_email))';
                    $params[':guest_email'] = $currentUserEmail;
                    $whereParts[] = '(' . implode(' OR ', $guestConditions) . ')';
                }
            }
            // Admin vidí VŠE (žádný filtr)

            // Sestavit SQL dotaz
            $whereClause = '';
            if (!empty($whereParts)) {
                $whereClause = ' AND ' . implode(' AND ', $whereParts);
            }

            // Načíst nepřečtené poznámky s filtrováním podle oprávnění
            $sql = "
                SELECT
                    n.claim_id,
                    COUNT(*) as unread_count
                FROM wgs_notes n
                INNER JOIN wgs_reklamace r ON n.claim_id = r.id
                LEFT JOIN wgs_notes_read nr ON n.id = nr.note_id AND nr.user_email = :user_email
                WHERE nr.id IS NULL
                  AND n.created_by != :user_email_author
                  $whereClause
                GROUP BY n.claim_id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $unreadCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            echo json_encode([
                'status' => 'success',
                'unread_counts' => $unreadCounts
            ]);
            break;

        case 'mark_read':
            // Označení všech poznámek reklamace jako přečtené
            $reklamaceId = sanitizeReklamaceId($_POST['reklamace_id'] ?? null, 'reklamace_id');

            // Převést reklamace_id na claim_id (číselné ID)
            $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
            $stmt->execute([':reklamace_id' => $reklamaceId, ':cislo' => $reklamaceId]);
            $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reklamace) {
                throw new Exception('Reklamace nebyla nalezena');
            }

            $claimId = $reklamace['id'];
            $currentUserEmail = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? null;

            if (!$currentUserEmail) {
                throw new Exception('Uživatel není přihlášen');
            }

            // Načíst všechny poznámky pro tuto reklamaci
            $stmt = $pdo->prepare("SELECT id FROM wgs_notes WHERE claim_id = :claim_id");
            $stmt->execute([':claim_id' => $claimId]);
            $noteIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Označit všechny jako přečtené (INSERT IGNORE pro zabránění duplicit)
            $markedCount = 0;
            foreach ($noteIds as $noteId) {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO wgs_notes_read (note_id, user_email, read_at)
                    VALUES (:note_id, :user_email, NOW())
                ");
                $stmt->execute([
                    ':note_id' => $noteId,
                    ':user_email' => $currentUserEmail
                ]);
                $markedCount += $stmt->rowCount();
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Poznámky označeny jako přečtené',
                'marked_count' => $markedCount
            ]);
            break;

        default:
            throw new Exception('Neplatná akce: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
