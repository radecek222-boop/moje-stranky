<?php
/**
 * Herní zóna - Hub pro všechny hry
 * Vyžaduje přihlášení
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Hráč';

// Aktualizovat online status
try {
    $pdo = getDbConnection();

    // Vložit/aktualizovat online status
    $stmt = $pdo->prepare("
        INSERT INTO wgs_hry_online (user_id, username, posledni_aktivita, aktualni_hra)
        VALUES (:user_id, :username, NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            posledni_aktivita = NOW(),
            aktualni_hra = NULL
    ");
    $stmt->execute([
        'user_id' => $userId,
        'username' => $username
    ]);

    // Smazat neaktivní hráče (5+ minut)
    $pdo->exec("DELETE FROM wgs_hry_online WHERE posledni_aktivita < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

    // Načíst online hráče
    $stmtOnline = $pdo->query("SELECT user_id, username, aktualni_hra FROM wgs_hry_online ORDER BY posledni_aktivita DESC");
    $onlineHraci = $stmtOnline->fetchAll(PDO::FETCH_ASSOC);

    // Načíst posledních 20 chat zpráv (globální chat)
    $stmtChat = $pdo->query("
        SELECT username, zprava, cas
        FROM wgs_hry_chat
        WHERE mistnost_id IS NULL
        ORDER BY cas DESC
        LIMIT 20
    ");
    $chatZpravy = array_reverse($stmtChat->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    $onlineHraci = [];
    $chatZpravy = [];
    error_log("Hry error: " . $e->getMessage());
}

// Dostupné hry
$dostupneHry = [
    [
        'id' => 'prsi',
        'nazev' => 'Prší',
        'popis' => 'Klasická česká karetní hra. Zbav se všech karet jako první!',
        'hracu' => '1-4',
        'ikona' => '♠♥♦♣',
        'hotovo' => true
    ],
    [
        'id' => 'lode',
        'nazev' => 'Lodě',
        'popis' => 'Námořní bitva! Najdi a potop všechny soupeřovy lodě.',
        'hracu' => '1',
        'ikona' => '~~~',
        'hotovo' => true
    ],
    [
        'id' => 'pong',
        'nazev' => 'Pong',
        'popis' => 'Klasická arkádová hra. Odraž míček a poraz soupeře!',
        'hracu' => '1',
        'ikona' => '| o |',
        'hotovo' => true
    ],
    [
        'id' => 'piskvorky',
        'nazev' => 'Piškvorky',
        'popis' => 'Jednoduchá strategická hra pro 2 hráče.',
        'hracu' => '1',
        'ikona' => '✕○',
        'hotovo' => true
    ]
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herní zóna | White Glove Service</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/wgs-base.min.css">
    <style>
        :root {
            --hry-bg: #0a0a0a;
            --hry-card: #1a1a1a;
            --hry-border: #333;
            --hry-text: #fff;
            --hry-muted: #888;
            --hry-accent: #0099ff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--hry-bg);
            color: var(--hry-text);
            min-height: 100vh;
        }

        .hry-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .hry-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .hry-header h1 {
            font-size: 2.5rem;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }

        .hry-header p {
            color: var(--hry-muted);
        }

        .hry-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }

        /* Karty her */
        .hry-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .hra-karta {
            background: var(--hry-card);
            border: 1px solid var(--hry-border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .hra-karta:hover {
            border-color: var(--hry-accent);
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 153, 255, 0.15);
        }

        .hra-karta.neaktivni {
            opacity: 0.5;
            pointer-events: none;
        }

        .hra-karta.neaktivni::after {
            content: 'BRZY';
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--hry-border);
            color: var(--hry-muted);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .hra-ikona {
            font-size: 3rem;
            margin-bottom: 1rem;
            letter-spacing: 0.2em;
        }

        .hra-nazev {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .hra-popis {
            color: var(--hry-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .hra-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--hry-border);
        }

        .hra-hracu {
            color: var(--hry-muted);
            font-size: 0.85rem;
        }

        .hra-btn {
            background: var(--hry-accent);
            color: #000;
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .hra-btn:hover {
            background: #33bbff;
            transform: scale(1.05);
        }

        /* Sidebar */
        .hry-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-panel {
            background: var(--hry-card);
            border: 1px solid var(--hry-border);
            border-radius: 12px;
            overflow: hidden;
        }

        .panel-header {
            background: #222;
            padding: 1rem;
            font-weight: 600;
            border-bottom: 1px solid var(--hry-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header .pocet {
            background: var(--hry-accent);
            color: #000;
            padding: 0.2rem 0.6rem;
            border-radius: 10px;
            font-size: 0.8rem;
        }

        /* Online hráči */
        .online-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .online-hrac {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--hry-border);
        }

        .online-hrac:last-child {
            border-bottom: none;
        }

        .online-status {
            width: 8px;
            height: 8px;
            background: var(--hry-accent);
            border-radius: 50%;
            box-shadow: 0 0 6px rgba(0, 153, 255, 0.6);
        }

        .online-jmeno {
            flex: 1;
        }

        .online-jmeno.ja {
            color: var(--hry-accent);
            font-weight: 600;
        }

        .online-hra {
            font-size: 0.75rem;
            color: var(--hry-muted);
        }

        /* Chat */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 350px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .chat-zprava {
            margin-bottom: 0.75rem;
        }

        .chat-autor {
            font-weight: 600;
            color: var(--hry-accent);
            font-size: 0.85rem;
        }

        .chat-text {
            color: var(--hry-text);
            font-size: 0.9rem;
            word-break: break-word;
        }

        .chat-cas {
            color: var(--hry-muted);
            font-size: 0.7rem;
        }

        .chat-input-wrapper {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem;
            border-top: 1px solid var(--hry-border);
        }

        .chat-input {
            flex: 1;
            background: #111;
            border: 1px solid var(--hry-border);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            color: var(--hry-text);
            font-size: 0.9rem;
        }

        .chat-input:focus {
            outline: none;
            border-color: var(--hry-accent);
        }

        .chat-send {
            background: var(--hry-accent);
            color: #000;
            border: none;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .hry-layout {
                grid-template-columns: 1fr;
            }

            .hry-sidebar {
                order: -1;
            }
        }

        @media (max-width: 600px) {
            .hry-container {
                padding: 1rem;
            }

            .hry-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/hamburger-menu.php'; ?>

    <main id="main-content" class="hry-container">
        <div class="hry-header">
            <h1>HERNÍ ZÓNA</h1>
            <p>
                Proč se chvilku neodreagovat, ne?
                <svg class="smajlik" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-left: 8px;">
                    <circle cx="12" cy="12" r="10" stroke="#0099ff" stroke-width="2" fill="none"/>
                    <circle cx="8" cy="10" r="1.5" fill="#0099ff"/>
                    <circle cx="16" cy="10" r="1.5" fill="#0099ff"/>
                    <path d="M8 15c1.5 2 6.5 2 8 0" stroke="#0099ff" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </p>
        </div>

        <div class="hry-layout">
            <!-- Hlavní obsah - karty her -->
            <section class="hry-main">
                <div class="hry-grid">
                    <?php foreach ($dostupneHry as $hra): ?>
                    <article class="hra-karta <?php echo $hra['hotovo'] ? '' : 'neaktivni'; ?>">
                        <div class="hra-ikona"><?php echo $hra['ikona']; ?></div>
                        <h2 class="hra-nazev"><?php echo htmlspecialchars($hra['nazev']); ?></h2>
                        <p class="hra-popis"><?php echo htmlspecialchars($hra['popis']); ?></p>
                        <div class="hra-info">
                            <span class="hra-hracu"><?php echo $hra['hracu']; ?> hráčů</span>
                            <?php if ($hra['hotovo']): ?>
                            <a href="hry/<?php echo $hra['id']; ?>.php" class="hra-btn">HRÁT</a>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Sidebar -->
            <aside class="hry-sidebar">
                <!-- Online hráči -->
                <div class="sidebar-panel">
                    <div class="panel-header">
                        ONLINE HRÁČI
                        <span class="pocet"><?php echo count($onlineHraci); ?></span>
                    </div>
                    <div class="online-list" id="onlineList">
                        <?php if (empty($onlineHraci)): ?>
                        <div class="online-hrac">
                            <span class="online-jmeno" style="color: var(--hry-muted);">Nikdo není online</span>
                        </div>
                        <?php else: ?>
                            <?php foreach ($onlineHraci as $hrac): ?>
                            <div class="online-hrac">
                                <span class="online-status"></span>
                                <span class="online-jmeno <?php echo $hrac['user_id'] == $userId ? 'ja' : ''; ?>">
                                    <?php echo htmlspecialchars($hrac['username']); ?>
                                    <?php echo $hrac['user_id'] == $userId ? '(ty)' : ''; ?>
                                </span>
                                <?php if ($hrac['aktualni_hra']): ?>
                                <span class="online-hra"><?php echo htmlspecialchars($hrac['aktualni_hra']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat -->
                <div class="sidebar-panel">
                    <div class="panel-header">CHAT</div>
                    <div class="chat-container">
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($chatZpravy)): ?>
                            <div class="chat-zprava">
                                <span class="chat-text" style="color: var(--hry-muted);">Zatím žádné zprávy. Napiš první!</span>
                            </div>
                            <?php else: ?>
                                <?php foreach ($chatZpravy as $zprava): ?>
                                <div class="chat-zprava">
                                    <span class="chat-autor"><?php echo htmlspecialchars($zprava['username']); ?></span>
                                    <span class="chat-cas"><?php echo date('H:i', strtotime($zprava['cas'])); ?></span>
                                    <div class="chat-text"><?php echo htmlspecialchars($zprava['zprava']); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="chat-input-wrapper">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="text" class="chat-input" id="chatInput" placeholder="Napište zprávu..." maxlength="200">
                            <button class="chat-send" id="chatSend">Odeslat</button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <script>
    (function() {
        'use strict';

        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const chatSend = document.getElementById('chatSend');
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

        // Sledovat poslední ID zprávy pro polling
        let posledniChatId = 0;

        // Scroll chat dolů
        function scrollChatDolu() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        scrollChatDolu();

        // Odeslat zprávu
        async function odeslatZpravu() {
            const zprava = chatInput.value.trim();
            if (!zprava) return;

            chatInput.disabled = true;
            chatSend.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'chat');
                formData.append('zprava', zprava);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('/api/hry_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    chatInput.value = '';
                    // Přidat zprávu do chatu
                    pridatZpravu(result.data);
                } else {
                    console.error('Chat error:', result.message);
                }
            } catch (error) {
                console.error('Chat error:', error);
            }

            chatInput.disabled = false;
            chatSend.disabled = false;
            chatInput.focus();
        }

        // Přidat zprávu do UI
        function pridatZpravu(data) {
            const div = document.createElement('div');
            div.className = 'chat-zprava';
            div.innerHTML = `
                <span class="chat-autor">${escapeHtml(data.username)}</span>
                <span class="chat-cas">${data.cas}</span>
                <div class="chat-text">${escapeHtml(data.zprava)}</div>
            `;
            chatMessages.appendChild(div);
            scrollChatDolu();

            // Aktualizovat poslední ID pro polling
            if (data.id) {
                posledniChatId = Math.max(posledniChatId, data.id);
            }
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Event listenery
        chatSend.addEventListener('click', odeslatZpravu);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                odeslatZpravu();
            }
        });

        // Periodicky aktualizovat online hráče (každých 5s)
        async function obnovitOnline() {
            try {
                const response = await fetch('/api/hry_api.php?action=stav');
                const result = await response.json();

                if (result.status === 'success' && result.online) {
                    aktualizovatOnline(result.online);
                }
            } catch (error) {
                console.error('Online polling error:', error);
            }
        }
        setInterval(obnovitOnline, 5000);

        // Periodicky aktualizovat chat (každou 1s)
        async function obnovitChat() {
            try {
                const response = await fetch('/api/hry_api.php?action=chat_poll&posledni_id=' + posledniChatId);
                const result = await response.json();

                if (result.status === 'success' && result.chat && result.chat.length > 0) {
                    result.chat.forEach(z => {
                        pridatZpravu(z);
                        posledniChatId = Math.max(posledniChatId, z.id);
                    });
                }
            } catch (error) {
                console.error('Chat polling error:', error);
            }
        }
        setInterval(obnovitChat, 1000);

        // Aktualizovat seznam online hráčů
        function aktualizovatOnline(hraci) {
            const onlineList = document.getElementById('onlineList');
            const pocetEl = document.querySelector('.panel-header .pocet');

            if (pocetEl) {
                pocetEl.textContent = hraci.length;
            }

            if (hraci.length === 0) {
                onlineList.innerHTML = '<div class="online-hrac"><span class="online-jmeno" style="color: var(--hry-muted);">Nikdo není online</span></div>';
                return;
            }

            onlineList.innerHTML = hraci.map(h => `
                <div class="online-hrac">
                    <span class="online-status"></span>
                    <span class="online-jmeno ${h.ja ? 'ja' : ''}">
                        ${escapeHtml(h.username)} ${h.ja ? '(ty)' : ''}
                    </span>
                    ${h.aktualni_hra ? `<span class="online-hra">${escapeHtml(h.aktualni_hra)}</span>` : ''}
                </div>
            `).join('');
        }

        // Heartbeat - udržovat online status
        setInterval(async () => {
            try {
                await fetch('/api/hry_api.php?action=heartbeat');
            } catch (e) {}
        }, 30000);

    })();
    </script>
</body>
</html>
