<?php
/**
 * Sidebar pro herní stránky - online hráči a chat
 * Include do všech herních stránek
 */

// Načíst online hráče a chat (pokud ještě nebylo)
if (!isset($onlineHraci)) {
    try {
        $pdo = getDbConnection();
        $stmtOnline = $pdo->query("SELECT user_id, username, aktualni_hra FROM wgs_hry_online ORDER BY posledni_aktivita DESC");
        $onlineHraci = $stmtOnline->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $onlineHraci = [];
    }
}

if (!isset($chatZpravy)) {
    try {
        $pdo = getDbConnection();
        $stmtChat = $pdo->query("
            SELECT id, username, zprava, cas
            FROM wgs_hry_chat
            WHERE mistnost_id IS NULL
            ORDER BY cas DESC
            LIMIT 10
        ");
        $chatZpravy = array_reverse($stmtChat->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        $chatZpravy = [];
    }
}

// Nacist cekajici mistnosti (hry cekajici na hrace)
if (!isset($cekajiciMistnosti)) {
    try {
        $pdo = getDbConnection();
        $stmtMistnosti = $pdo->query("
            SELECT m.id, m.nazev, m.hra, m.max_hracu,
                   (SELECT COUNT(*) FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = m.id) as pocet_hracu,
                   (SELECT username FROM wgs_users WHERE user_id = m.vytvoril_user_id) as vytvoril
            FROM wgs_hry_mistnosti m
            WHERE m.stav = 'ceka'
            ORDER BY m.vytvoreno DESC
            LIMIT 5
        ");
        $cekajiciMistnosti = $stmtMistnosti->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cekajiciMistnosti = [];
    }
}

$currentUserId = $_SESSION['user_id'] ?? '';
?>

<!-- Zvuky script -->
<script src="/assets/js/hry-zvuky.js"></script>

<!-- Herní sidebar - online hráči a chat -->
<aside class="hry-sidebar-panel" id="hrySidebar">
    <div class="sidebar-toggles">
        <button class="sidebar-toggle" id="sidebarToggle" title="Chat a online hráči">
            <span class="toggle-icon">&#9776;</span>
            <span class="toggle-badge" id="toggleBadge"><?php echo count($onlineHraci); ?></span>
        </button>
        <button class="sound-toggle" id="soundToggle" title="Zvuky">
            <span class="sound-icon" id="soundIcon">&#128266;</span>
        </button>
    </div>

    <div class="sidebar-content" id="sidebarContent">
        <!-- Online hráči -->
        <div class="sidebar-section">
            <div class="sidebar-header">
                ONLINE
                <span class="sidebar-pocet" id="sidebarPocet"><?php echo count($onlineHraci); ?></span>
            </div>
            <div class="sidebar-online-list" id="sidebarOnlineList">
                <?php if (empty($onlineHraci)): ?>
                <div class="sidebar-hrac">Nikdo</div>
                <?php else: ?>
                    <?php foreach ($onlineHraci as $hrac): ?>
                    <div class="sidebar-hrac <?php echo $hrac['user_id'] == $currentUserId ? 'ja' : ''; ?>">
                        <span class="sidebar-status"></span>
                        <?php echo htmlspecialchars($hrac['username']); ?>
                        <?php if ($hrac['aktualni_hra']): ?>
                        <span class="sidebar-hra"><?php echo htmlspecialchars($hrac['aktualni_hra']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cekajici hry -->
        <div class="sidebar-section sidebar-lobby" id="sidebarLobby">
            <div class="sidebar-header">
                POZVANKY
                <span class="sidebar-pocet" id="lobbyPocet"><?php echo count($cekajiciMistnosti); ?></span>
            </div>
            <div class="sidebar-lobby-list" id="sidebarLobbyList">
                <?php if (empty($cekajiciMistnosti)): ?>
                <div class="sidebar-lobby-empty">Zadne cekajici hry</div>
                <?php else: ?>
                    <?php foreach ($cekajiciMistnosti as $m): ?>
                    <div class="sidebar-lobby-item" data-id="<?php echo (int)$m['id']; ?>" data-hra="<?php echo htmlspecialchars($m['hra']); ?>">
                        <div class="lobby-info">
                            <span class="lobby-hra"><?php echo htmlspecialchars(ucfirst($m['hra'])); ?></span>
                            <span class="lobby-hrac"><?php echo htmlspecialchars($m['vytvoril'] ?? 'Hrac'); ?> ceka</span>
                        </div>
                        <button class="lobby-pripojit" onclick="pripojitSeKeHre(<?php echo (int)$m['id']; ?>, '<?php echo htmlspecialchars($m['hra']); ?>')">Hrat</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat -->
        <div class="sidebar-section sidebar-chat">
            <div class="sidebar-header">CHAT</div>
            <div class="sidebar-chat-messages" id="sidebarChatMessages">
                <?php foreach ($chatZpravy as $zprava): ?>
                <div class="sidebar-zprava" data-id="<?php echo (int)$zprava['id']; ?>">
                    <span class="sidebar-autor"><?php echo htmlspecialchars($zprava['username']); ?></span>
                    <span class="sidebar-cas"><?php echo date('H:i', strtotime($zprava['cas'])); ?></span>
                    <div class="sidebar-text"><?php echo htmlspecialchars($zprava['zprava']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sidebar-chat-input">
                <input type="hidden" name="csrf_token" id="sidebarCsrfToken" value="<?php echo generateCSRFToken(); ?>">
                <input type="text" id="sidebarChatInput" placeholder="Zpráva..." maxlength="200">
                <button id="sidebarChatSend">OK</button>
            </div>
        </div>
    </div>
</aside>

<style>
/* Herní sidebar */
.hry-sidebar-panel {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1000;
    display: flex;
    align-items: center;
}

.sidebar-toggles {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.sidebar-toggle,
.sound-toggle {
    background: rgba(0,0,0,0.9);
    border: 1px solid #333;
    border-right: none;
    border-radius: 8px 0 0 8px;
    padding: 0.75rem 0.5rem;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.sidebar-toggle:hover,
.sound-toggle:hover {
    background: rgba(0,153,255,0.2);
    border-color: #0099ff;
}

.toggle-icon {
    color: #fff;
    font-size: 1.2rem;
}

.toggle-badge {
    background: #0099ff;
    color: #000;
    font-size: 0.7rem;
    font-weight: bold;
    padding: 0.15rem 0.4rem;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}

.sound-icon {
    color: #0099ff;
    font-size: 1.2rem;
}

.sound-toggle.muted .sound-icon {
    color: #666;
    opacity: 0.5;
}

.sidebar-content {
    background: rgba(0,0,0,0.95);
    border: 1px solid #333;
    border-right: none;
    border-radius: 8px 0 0 8px;
    width: 250px;
    max-height: 80vh;
    overflow: hidden;
    display: none;
    flex-direction: column;
}

.sidebar-content.open {
    display: flex;
}

.sidebar-section {
    border-bottom: 1px solid #333;
}

.sidebar-section:last-child {
    border-bottom: none;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.sidebar-header {
    background: #1a1a1a;
    color: #fff;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sidebar-pocet {
    background: #0099ff;
    color: #000;
    padding: 0.1rem 0.4rem;
    border-radius: 8px;
    font-size: 0.7rem;
}

.sidebar-online-list {
    max-height: 120px;
    overflow-y: auto;
    padding: 0.5rem;
}

.sidebar-hrac {
    color: #aaa;
    font-size: 0.8rem;
    padding: 0.25rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sidebar-hrac.ja {
    color: #0099ff;
    font-weight: 600;
}

.sidebar-status {
    width: 6px;
    height: 6px;
    background: #0099ff;
    border-radius: 50%;
}

.sidebar-hra {
    color: #666;
    font-size: 0.65rem;
    margin-left: auto;
}

.sidebar-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 200px;
}

.sidebar-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
    max-height: 250px;
}

.sidebar-zprava {
    margin-bottom: 0.5rem;
}

.sidebar-autor {
    color: #0099ff;
    font-size: 0.75rem;
    font-weight: 600;
}

.sidebar-cas {
    color: #666;
    font-size: 0.6rem;
    margin-left: 0.25rem;
}

.sidebar-text {
    color: #ddd;
    font-size: 0.8rem;
    word-break: break-word;
}

.sidebar-chat-input {
    display: flex;
    gap: 0.25rem;
    padding: 0.5rem;
    border-top: 1px solid #333;
}

.sidebar-chat-input input {
    flex: 1;
    background: #111;
    border: 1px solid #333;
    border-radius: 4px;
    padding: 0.4rem;
    color: #fff;
    font-size: 0.8rem;
}

.sidebar-chat-input input:focus {
    outline: none;
    border-color: #0099ff;
}

.sidebar-chat-input button {
    background: #0099ff;
    color: #000;
    border: none;
    border-radius: 4px;
    padding: 0.4rem 0.75rem;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.8rem;
}

/* Lobby - pozvanky do her */
.sidebar-lobby-list {
    padding: 0.5rem;
    max-height: 150px;
    overflow-y: auto;
}

.sidebar-lobby-empty {
    color: #666;
    font-size: 0.75rem;
    text-align: center;
    padding: 0.5rem;
}

.sidebar-lobby-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 6px;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
}

.sidebar-lobby-item:last-child {
    margin-bottom: 0;
}

.lobby-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.lobby-hra {
    color: #0099ff;
    font-size: 0.8rem;
    font-weight: 600;
}

.lobby-hrac {
    color: #888;
    font-size: 0.7rem;
}

.lobby-pripojit {
    background: #39ff14;
    color: #000;
    border: none;
    border-radius: 4px;
    padding: 0.3rem 0.6rem;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.lobby-pripojit:hover {
    background: #fff;
}

@media (max-width: 600px) {
    .sidebar-content {
        width: 200px;
    }
}
</style>

<script>
(function() {
    'use strict';

    const toggle = document.getElementById('sidebarToggle');
    const content = document.getElementById('sidebarContent');
    const chatMessages = document.getElementById('sidebarChatMessages');
    const chatInput = document.getElementById('sidebarChatInput');
    const chatSend = document.getElementById('sidebarChatSend');
    const csrfToken = document.getElementById('sidebarCsrfToken').value;
    const pocetEl = document.getElementById('sidebarPocet');
    const badgeEl = document.getElementById('toggleBadge');
    const onlineList = document.getElementById('sidebarOnlineList');
    const soundToggle = document.getElementById('soundToggle');
    const soundIcon = document.getElementById('soundIcon');

    let posledniChatId = 0;
    let sidebarOpen = false;
    const zobrazeneZpravyIds = new Set(); // Sledování již zobrazených zpráv

    // Inicializovat z existujících zpráv v DOM (z PHP)
    document.querySelectorAll('#sidebarChatMessages .sidebar-zprava[data-id]').forEach(el => {
        const id = parseInt(el.getAttribute('data-id')) || 0;
        if (id > 0) {
            zobrazeneZpravyIds.add(id);
            posledniChatId = Math.max(posledniChatId, id);
        }
    });

    // Nastavit stav ikony zvuku podle uloženého nastavení
    function aktualizovatIkonuZvuku() {
        if (window.HryZvuky && !window.HryZvuky.jeZapnuto()) {
            soundToggle.classList.add('muted');
            soundIcon.innerHTML = '&#128263;'; // Ztlumený reproduktor
            soundToggle.title = 'Zvuky vypnuty';
        } else {
            soundToggle.classList.remove('muted');
            soundIcon.innerHTML = '&#128266;'; // Reproduktor se zvukem
            soundToggle.title = 'Zvuky zapnuty';
        }
    }

    // Zvuk toggle
    soundToggle.addEventListener('click', () => {
        if (window.HryZvuky) {
            window.HryZvuky.prepnout();
            aktualizovatIkonuZvuku();
        }
    });

    // Inicializovat stav ikony
    setTimeout(aktualizovatIkonuZvuku, 100);

    // Toggle sidebar
    toggle.addEventListener('click', () => {
        sidebarOpen = !sidebarOpen;
        content.classList.toggle('open', sidebarOpen);
        if (sidebarOpen) {
            scrollChatDolu();
            if (window.HryZvuky) window.HryZvuky.prehrat('klik');
        }
    });

    function scrollChatDolu() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

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
                pridatZpravu(result);
            }
        } catch (error) {
            console.error('Chat error:', error);
        }

        chatInput.disabled = false;
        chatSend.disabled = false;
        chatInput.focus();
    }

    function pridatZpravu(data, prehratZvuk = true) {
        if (!data || !data.username || !data.zprava) return;

        // Kontrola duplicity - pokud už zprávu máme, přeskočit
        const zpravaId = parseInt(data.id) || 0;
        if (zpravaId > 0 && zobrazeneZpravyIds.has(zpravaId)) {
            return; // Zpráva už existuje, nepřidávat
        }

        const div = document.createElement('div');
        div.className = 'sidebar-zprava';
        div.setAttribute('data-id', zpravaId);
        div.innerHTML = `
            <span class="sidebar-autor">${escapeHtml(data.username)}</span>
            <span class="sidebar-cas">${data.cas || ''}</span>
            <div class="sidebar-text">${escapeHtml(data.zprava)}</div>
        `;
        chatMessages.appendChild(div);

        // Zapamatovat si ID
        if (zpravaId > 0) {
            zobrazeneZpravyIds.add(zpravaId);
            posledniChatId = Math.max(posledniChatId, zpravaId);
        }

        // Odstranit staré zprávy (max 10)
        while (chatMessages.children.length > 10) {
            const stara = chatMessages.firstChild;
            const stareId = parseInt(stara.getAttribute('data-id')) || 0;
            if (stareId > 0) {
                zobrazeneZpravyIds.delete(stareId);
            }
            chatMessages.removeChild(stara);
        }

        scrollChatDolu();

        // Přehrát zvuk pro novou zprávu
        if (prehratZvuk && window.HryZvuky) {
            window.HryZvuky.prehrat('chat');
        }
    }

    function aktualizovatOnline(hraci) {
        if (pocetEl) pocetEl.textContent = hraci.length;
        if (badgeEl) badgeEl.textContent = hraci.length;

        if (hraci.length === 0) {
            onlineList.innerHTML = '<div class="sidebar-hrac">Nikdo</div>';
            return;
        }

        onlineList.innerHTML = hraci.map(h => `
            <div class="sidebar-hrac ${h.ja ? 'ja' : ''}">
                <span class="sidebar-status"></span>
                ${escapeHtml(h.username)}
                ${h.aktualni_hra ? `<span class="sidebar-hra">${escapeHtml(h.aktualni_hra)}</span>` : ''}
            </div>
        `).join('');
    }

    // Event listeners
    chatSend.addEventListener('click', odeslatZpravu);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') odeslatZpravu();
    });

    // Polling - online hráči (5s)
    setInterval(async () => {
        try {
            const response = await fetch('/api/hry_api.php?action=stav');
            const result = await response.json();
            if (result.status === 'success' && result.online) {
                aktualizovatOnline(result.online);
            }
        } catch (e) {}
    }, 5000);

    // Polling - chat (1s)
    setInterval(async () => {
        try {
            const response = await fetch('/api/hry_api.php?action=chat_poll&posledni_id=' + posledniChatId);
            const result = await response.json();
            if (result.status === 'success' && result.chat && result.chat.length > 0) {
                result.chat.forEach(z => {
                    pridatZpravu(z);
                    posledniChatId = Math.max(posledniChatId, z.id);
                });
            }
        } catch (e) {}
    }, 1000);

    // Polling - lobby/cekajici hry (3s)
    const lobbyList = document.getElementById('sidebarLobbyList');
    const lobbyPocet = document.getElementById('lobbyPocet');

    async function aktualizovatLobby() {
        try {
            const response = await fetch('/api/hry_api.php?action=mistnosti');
            const result = await response.json();
            if (result.status === 'success' && result.mistnosti) {
                const cekajici = result.mistnosti.filter(m => m.stav === 'ceka');
                if (lobbyPocet) lobbyPocet.textContent = cekajici.length;

                if (cekajici.length === 0) {
                    lobbyList.innerHTML = '<div class="sidebar-lobby-empty">Zadne cekajici hry</div>';
                } else {
                    lobbyList.innerHTML = cekajici.map(m => `
                        <div class="sidebar-lobby-item" data-id="${m.id}" data-hra="${escapeHtml(m.hra)}">
                            <div class="lobby-info">
                                <span class="lobby-hra">${escapeHtml(m.hra.charAt(0).toUpperCase() + m.hra.slice(1))}</span>
                                <span class="lobby-hrac">${m.pocet_hracu}/${m.max_hracu} hracu ceka</span>
                            </div>
                            <button class="lobby-pripojit" onclick="pripojitSeKeHre(${m.id}, '${escapeHtml(m.hra)}')">Hrat</button>
                        </div>
                    `).join('');
                }
            }
        } catch (e) {}
    }

    setInterval(aktualizovatLobby, 3000);

})();

// Globalni funkce pro pripojeni ke hre
async function pripojitSeKeHre(mistnostId, hra) {
    try {
        const csrfToken = document.getElementById('sidebarCsrfToken').value;
        const formData = new FormData();
        formData.append('action', 'pripojit');
        formData.append('csrf_token', csrfToken);
        formData.append('mistnost_id', mistnostId);

        const response = await fetch('/api/hry_api.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            // Presmerovat na hru
            window.location.href = '/hry/' + hra + '.php?mistnost=' + mistnostId;
        } else {
            alert(result.message || 'Nelze se pripojit');
        }
    } catch (e) {
        console.error('Pripojeni error:', e);
        alert('Chyba pri pripojovani');
    }
}
</script>
