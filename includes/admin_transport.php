<?php
/**
 * Admin Transport - Kartový systém eventů
 *
 * Include soubor pro admin.php?tab=transport
 * Po kliknutí na kartu eventu se zobrazí transporty pro daný event
 */

if (!defined('ADMIN_PHP_LOADED')) {
    die('Primy pristup neni povolen');
}

// Získat ID eventu pokud je v URL
$eventId = isset($_GET['event']) ? (int)$_GET['event'] : null;
?>

<style>
/* Transport Events - Kartový systém */
.transport-eventy-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.transport-eventy-header {
    text-align: center;
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #d1d1d6;
}

.transport-eventy-title {
    font-family: 'Poppins', sans-serif;
    font-size: 2rem;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.01em;
}

.transport-eventy-subtitle {
    font-family: 'Poppins', sans-serif;
    font-size: 0.95rem;
    font-weight: 400;
    color: #666;
    margin: 0;
}

.transport-eventy-actions {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.btn-pridat-event {
    background: #333;
    color: #fff;
    border: none;
    padding: 0.6rem 1.5rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-pridat-event:hover {
    background: #555;
}

/* Grid eventů - stejný styl jako cc-grid */
.transport-eventy-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
}

.transport-event-card {
    background: #fff;
    border: 1px solid #d1d1d6;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.transport-event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: #333;
}

.transport-event-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 0.5rem;
    letter-spacing: 0.5px;
}

.transport-event-card-datum {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.75rem;
}

.transport-event-card-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: #888;
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid #eee;
}

.transport-event-card-stat {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.transport-event-card-stat strong {
    color: #333;
}

.transport-event-card-akce {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: flex;
    gap: 0.5rem;
}

.transport-event-card-akce button {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0.4rem 0.6rem;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.transport-event-card-akce button:hover {
    background: #eee;
    border-color: #333;
}

.transport-event-card-akce button.btn-smazat-event {
    color: #dc3545;
    border-color: #dc3545;
}

.transport-event-card-akce button.btn-smazat-event:hover {
    background: #dc3545;
    color: #fff;
}

/* Prazdny stav */
.transport-eventy-prazdne {
    text-align: center;
    padding: 4rem 2rem;
    color: #999;
}

.transport-eventy-prazdne p {
    font-size: 1rem;
    margin-bottom: 1.5rem;
}

/* ================================
   DETAIL EVENTU - Techmission styl
   ================================ */
.transport-detail-wrapper {
    background: #000;
    color: #fff;
    min-height: calc(100vh - 60px);
    padding: 15px;
    margin: -1rem;
}

.transport-detail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 0 25px;
    border-bottom: 1px solid #333;
    margin-bottom: 20px;
}

.transport-detail-back {
    background: #222;
    border: 1px solid #444;
    color: #fff;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.transport-detail-back:hover {
    background: #333;
}

.transport-detail-title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.transport-detail-actions {
    display: flex;
    gap: 0.5rem;
}

/* Sekce dne */
.transport-den {
    margin-bottom: 25px;
}

.transport-den-header {
    font-size: 14px;
    font-weight: 600;
    color: #888;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-pridat-transport {
    background: #222;
    border: 1px solid #444;
    color: #888;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-pridat-transport:hover {
    background: #333;
    color: #fff;
}

/* Transport radek */
.transport-radek {
    background: #111;
    border: 1px solid #222;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
}

.transport-radek:hover {
    background: #1a1a1a;
}

.transport-radek-cas {
    font-size: 24px;
    font-weight: 700;
    min-width: 70px;
    font-variant-numeric: tabular-nums;
}

.transport-radek-info {
    flex: 1;
}

.transport-radek-jmeno {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 3px;
}

.transport-radek-let {
    font-size: 12px;
    color: #888;
    margin-bottom: 2px;
}

.transport-radek-trasa {
    font-size: 12px;
    color: #666;
}

.transport-radek-kontakt {
    font-size: 11px;
    color: #555;
    margin-top: 4px;
}

/* Stav tlacitko */
.transport-radek-stav {
    min-width: 110px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.stav-btn {
    padding: 10px 18px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.2s;
    min-height: 38px;
}

.stav-wait {
    background: #333;
    color: #fff;
}

.stav-wait:hover {
    background: #444;
}

.stav-onway {
    background: #fff;
    color: #000;
}

.stav-drop {
    background: #111;
    color: #39ff14;
    border: 1px solid #39ff14;
    box-shadow: 0 0 10px rgba(57, 255, 20, 0.4);
    animation: drop-pulse 2s ease-in-out infinite;
}

@keyframes drop-pulse {
    0%, 100% { box-shadow: 0 0 10px rgba(57, 255, 20, 0.4); }
    50% { box-shadow: 0 0 20px rgba(57, 255, 20, 0.6); }
}

.stav-cas-info {
    font-size: 10px;
    color: #666;
}

/* Akce na radku */
.transport-radek-akce {
    position: absolute;
    bottom: 8px;
    right: 8px;
    display: flex;
    gap: 8px;
}

.transport-radek-akce button {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s;
    padding: 4px;
}

.btn-upravit-transport {
    color: #888;
}

.btn-upravit-transport:hover {
    color: #fff;
}

.btn-smazat-transport {
    color: #ff4444;
}

.btn-smazat-transport:hover {
    color: #ff6666;
}

/* Modal - tmavý styl */
.transport-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}

.transport-modal-overlay.aktivni {
    display: flex;
}

.transport-modal {
    background: #111;
    border: 1px solid #333;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.transport-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #333;
}

.transport-modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #fff;
    font-weight: 700;
}

.transport-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #888;
}

.transport-modal-close:hover {
    color: #fff;
}

.transport-modal-body {
    padding: 1.5rem;
}

.transport-form-group {
    margin-bottom: 1rem;
}

.transport-form-group label {
    display: block;
    font-size: 0.85rem;
    color: #888;
    margin-bottom: 0.4rem;
}

.transport-form-group input,
.transport-form-group select,
.transport-form-group textarea {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid #333;
    border-radius: 6px;
    font-size: 0.9rem;
    background: #222;
    color: #fff;
}

.transport-form-group input:focus,
.transport-form-group select:focus {
    outline: none;
    border-color: #555;
}

.transport-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.transport-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #333;
}

.transport-modal-footer button {
    padding: 0.6rem 1.25rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
}

.btn-modal-zrusit {
    background: #222;
    color: #888;
    border: none;
}

.btn-modal-zrusit:hover {
    background: #333;
}

.btn-modal-ulozit {
    background: #fff;
    color: #000;
    border: none;
}

.btn-modal-ulozit:hover {
    background: #ddd;
}

.btn-modal-smazat {
    background: #ff4444;
    color: #fff;
    border: none;
}

.btn-modal-smazat:hover {
    background: #cc3333;
}

/* ================================
   RIDICI - Techmission styl
   ================================ */
.ridici-sekce {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #333;
}

.ridici-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.ridici-header h3 {
    font-size: 12px;
    font-weight: 600;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin: 0;
}

.btn-pridat-ridice {
    background: #222;
    border: 1px solid #444;
    color: #888;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-pridat-ridice:hover {
    background: #333;
    color: #fff;
}

.ridici-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
}

.ridic-karta {
    background: #111;
    border: 1px solid #333;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    position: relative;
}

.ridic-karta:hover {
    background: #1a1a1a;
}

.ridic-karta-akce {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    gap: 6px;
}

.ridic-karta-akce button {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    padding: 2px 4px;
}

.ridic-karta-akce button.btn-upravit-ridice {
    color: #888;
}

.ridic-karta-akce button.btn-upravit-ridice:hover {
    color: #fff;
}

.ridic-karta-akce button.btn-smazat-ridice {
    color: #ff4444;
}

.ridic-karta-akce button.btn-smazat-ridice:hover {
    color: #ff6666;
}

.ridic-auto-svg {
    width: 32px;
    height: 32px;
    fill: #888;
    margin-bottom: 8px;
}

.ridic-jmeno {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 4px;
}

.ridic-auto {
    font-size: 11px;
    color: #888;
    text-transform: uppercase;
}

.ridic-spz {
    font-size: 10px;
    color: #666;
    margin-top: 2px;
}

.ridic-poznamka {
    font-size: 9px;
    color: #555;
    margin-top: 4px;
    font-style: italic;
}

.ridic-tel-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #222;
    border-radius: 50%;
    margin-top: 10px;
    transition: all 0.2s;
}

.ridic-tel-link:hover {
    background: #444;
}

.ridici-prazdne {
    text-align: center;
    padding: 2rem;
    color: #666;
}

/* Responsive */
@media (max-width: 768px) {
    .transport-eventy-grid {
        grid-template-columns: 1fr;
    }

    .transport-form-row {
        grid-template-columns: 1fr;
    }

    .transport-radek {
        flex-wrap: wrap;
    }

    .transport-radek-stav {
        width: 100%;
        margin-top: 10px;
    }

    .transport-detail-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .ridici-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<?php if ($eventId): ?>
<!-- ================================
     DETAIL EVENTU - Transporty
     ================================ -->
<div class="transport-detail-wrapper" id="transport-detail" data-event-id="<?= $eventId ?>">
    <div class="transport-detail-header">
        <a href="admin.php?tab=transport" class="transport-detail-back">Zpet na eventy</a>
        <div class="transport-detail-title" id="detail-event-nazev">Nacitam...</div>
        <div class="transport-detail-actions">
            <button class="btn-pridat-transport" onclick="transportOtevritModal()">+ Pridat transport</button>
        </div>
    </div>

    <!-- Sekce ridicu -->
    <div class="ridici-sekce">
        <div class="ridici-header">
            <h3>Ridici</h3>
            <button class="btn-pridat-ridice" onclick="ridicOtevritModal()">+ Pridat ridice</button>
        </div>
        <div class="ridici-grid" id="ridici-kontejner">
            <!-- Ridici se vykresli JavaScriptem -->
            <div class="ridici-prazdne">Nacitam ridice...</div>
        </div>
    </div>

    <div id="transport-dny-kontejner">
        <!-- Dny a transporty se vykreslí JavaScriptem -->
        <div style="text-align: center; padding: 3rem; color: #666;">Nacitam transporty...</div>
    </div>
</div>

<?php else: ?>
<!-- ================================
     SEZNAM EVENTU - Karty
     ================================ -->
<div class="transport-eventy-wrapper">
    <div class="transport-eventy-header">
        <h1 class="transport-eventy-title">Transport Events</h1>
        <p class="transport-eventy-subtitle">Vyberte event pro zobrazeni transportu</p>
        <div class="transport-eventy-actions">
            <button class="btn-pridat-event" onclick="eventOtevritModal()">+ Pridat event</button>
        </div>
    </div>

    <div class="transport-eventy-grid" id="eventy-grid">
        <!-- Karty eventů se vykreslí JavaScriptem -->
        <div class="transport-eventy-prazdne">
            <p>Nacitam eventy...</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal pro přidání/editaci eventu -->
<div class="transport-modal-overlay" id="modal-event">
    <div class="transport-modal">
        <div class="transport-modal-header">
            <h3 id="modal-event-titulek">Pridat event</h3>
            <button class="transport-modal-close" onclick="eventZavritModal()">&times;</button>
        </div>
        <div class="transport-modal-body">
            <input type="hidden" id="event-edit-id">
            <div class="transport-form-group">
                <label>Nazev eventu *</label>
                <input type="text" id="event-nazev" placeholder="STVANICE 26" required>
            </div>
            <div class="transport-form-row">
                <div class="transport-form-group">
                    <label>Datum od</label>
                    <input type="date" id="event-datum-od">
                </div>
                <div class="transport-form-group">
                    <label>Datum do</label>
                    <input type="date" id="event-datum-do">
                </div>
            </div>
            <div class="transport-form-group">
                <label>Popis</label>
                <textarea id="event-popis" rows="2" placeholder="Kratky popis eventu..."></textarea>
            </div>
        </div>
        <div class="transport-modal-footer">
            <button class="btn-modal-zrusit" onclick="eventZavritModal()">Zrusit</button>
            <button class="btn-modal-ulozit" onclick="eventUlozit()">Ulozit</button>
        </div>
    </div>
</div>

<!-- Modal pro smazání eventu -->
<div class="transport-modal-overlay" id="modal-event-smazat">
    <div class="transport-modal" style="max-width: 400px;">
        <div class="transport-modal-header">
            <h3>Smazat event</h3>
            <button class="transport-modal-close" onclick="eventZavritModalSmazat()">&times;</button>
        </div>
        <div class="transport-modal-body">
            <input type="hidden" id="event-smazat-id">
            <p style="color: #ccc;">Opravdu chcete smazat tento event?</p>
            <p style="color: #888; font-size: 0.85rem; margin-top: 0.5rem;" id="event-smazat-info"></p>
            <p style="color: #ff4444; font-size: 0.8rem; margin-top: 1rem;">Budou smazany vsechny transporty v tomto eventu!</p>
        </div>
        <div class="transport-modal-footer">
            <button class="btn-modal-zrusit" onclick="eventZavritModalSmazat()">Zrusit</button>
            <button class="btn-modal-smazat" onclick="eventPotvrdSmazat()">Smazat</button>
        </div>
    </div>
</div>

<!-- Modal pro přidání/editaci transportu -->
<div class="transport-modal-overlay" id="modal-transport">
    <div class="transport-modal">
        <div class="transport-modal-header">
            <h3 id="modal-transport-titulek">Pridat transport</h3>
            <button class="transport-modal-close" onclick="transportZavritModal()">&times;</button>
        </div>
        <div class="transport-modal-body">
            <input type="hidden" id="transport-edit-id">
            <div class="transport-form-row">
                <div class="transport-form-group">
                    <label>Datum *</label>
                    <input type="date" id="transport-datum" required>
                </div>
                <div class="transport-form-group">
                    <label>Cas *</label>
                    <input type="time" id="transport-cas" required>
                </div>
            </div>
            <div class="transport-form-group">
                <label>Jmeno a prijmeni *</label>
                <input type="text" id="transport-jmeno" placeholder="Jan Novak" required>
            </div>
            <div class="transport-form-row">
                <div class="transport-form-group">
                    <label>Cislo letu</label>
                    <input type="text" id="transport-let" placeholder="OK123">
                </div>
                <div class="transport-form-group">
                    <label>Cas priletu</label>
                    <input type="time" id="transport-prilet">
                </div>
            </div>
            <div class="transport-form-group">
                <label>Destinace</label>
                <input type="text" id="transport-destinace" placeholder="Letiste -> Hotel">
            </div>
            <div class="transport-form-row">
                <div class="transport-form-group">
                    <label>Telefon</label>
                    <input type="tel" id="transport-telefon" placeholder="+420 123 456 789">
                </div>
                <div class="transport-form-group">
                    <label>Email</label>
                    <input type="email" id="transport-email" placeholder="jan@example.com">
                </div>
            </div>
            <div class="transport-form-group">
                <label>Ridic</label>
                <select id="transport-ridic-id">
                    <option value="">-- Vyberte ridice --</option>
                    <!-- Ridici se naplni JavaScriptem -->
                </select>
            </div>
            <div class="transport-form-group">
                <label>Poznamka</label>
                <textarea id="transport-poznamka" rows="2" placeholder="Dalsi poznamky..."></textarea>
            </div>
        </div>
        <div class="transport-modal-footer">
            <button class="btn-modal-zrusit" onclick="transportZavritModal()">Zrusit</button>
            <button class="btn-modal-ulozit" onclick="transportUlozit()">Ulozit</button>
        </div>
    </div>
</div>

<!-- Modal pro smazání transportu -->
<div class="transport-modal-overlay" id="modal-transport-smazat">
    <div class="transport-modal" style="max-width: 400px;">
        <div class="transport-modal-header">
            <h3>Smazat transport</h3>
            <button class="transport-modal-close" onclick="transportZavritModalSmazat()">&times;</button>
        </div>
        <div class="transport-modal-body">
            <input type="hidden" id="transport-smazat-id">
            <p style="color: #ccc;">Opravdu chcete smazat tento transport?</p>
            <p style="color: #888; font-size: 0.85rem; margin-top: 0.5rem;" id="transport-smazat-info"></p>
        </div>
        <div class="transport-modal-footer">
            <button class="btn-modal-zrusit" onclick="transportZavritModalSmazat()">Zrusit</button>
            <button class="btn-modal-smazat" onclick="transportPotvrdSmazat()">Smazat</button>
        </div>
    </div>
</div>

<!-- Modal pro přidání/editaci řidiče -->
<div class="transport-modal-overlay" id="modal-ridic">
    <div class="transport-modal">
        <div class="transport-modal-header">
            <h3 id="modal-ridic-titulek">Pridat ridice</h3>
            <button class="transport-modal-close" onclick="ridicZavritModal()">&times;</button>
        </div>
        <div class="transport-modal-body">
            <input type="hidden" id="ridic-edit-id">
            <div class="transport-form-group">
                <label>Jmeno ridice *</label>
                <input type="text" id="ridic-jmeno" placeholder="Milan" required>
            </div>
            <div class="transport-form-group">
                <label>Telefon</label>
                <input type="tel" id="ridic-telefon" placeholder="+420 735 084 519">
            </div>
            <div class="transport-form-row">
                <div class="transport-form-group">
                    <label>Auto</label>
                    <input type="text" id="ridic-auto" placeholder="MB V CLASS">
                </div>
                <div class="transport-form-group">
                    <label>SPZ</label>
                    <input type="text" id="ridic-spz" placeholder="1AB 2345">
                </div>
            </div>
            <div class="transport-form-group">
                <label>Poznamka</label>
                <input type="text" id="ridic-poznamka" placeholder="STAND BY 21:00 - 06:00">
            </div>
        </div>
        <div class="transport-modal-footer">
            <button class="btn-modal-zrusit" onclick="ridicZavritModal()">Zrusit</button>
            <button class="btn-modal-ulozit" onclick="ridicUlozit()">Ulozit</button>
        </div>
    </div>
</div>

<!-- Modal pro smazání řidiče -->
<div class="transport-modal-overlay" id="modal-ridic-smazat">
    <div class="transport-modal" style="max-width: 400px;">
        <div class="transport-modal-header">
            <h3>Smazat ridice</h3>
            <button class="transport-modal-close" onclick="ridicZavritModalSmazat()">&times;</button>
        </div>
        <div class="transport-modal-body">
            <input type="hidden" id="ridic-smazat-id">
            <p style="color: #ccc;">Opravdu chcete smazat tohoto ridice?</p>
            <p style="color: #888; font-size: 0.85rem; margin-top: 0.5rem;" id="ridic-smazat-info"></p>
            <p style="color: #ff4444; font-size: 0.8rem; margin-top: 1rem;">Ridic bude odpojen od vsech transportu!</p>
        </div>
        <div class="transport-modal-footer">
            <button class="btn-modal-zrusit" onclick="ridicZavritModalSmazat()">Zrusit</button>
            <button class="btn-modal-smazat" onclick="ridicPotvrdSmazat()">Smazat</button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const eventId = document.getElementById('transport-detail')?.dataset?.eventId || null;

    // ==========================================
    // EVENTY - Seznam
    // ==========================================

    window.nactiEventy = async function() {
        const grid = document.getElementById('eventy-grid');
        if (!grid) return;

        try {
            const odpoved = await fetch('/api/transport_events_api.php?action=eventy_list');
            const data = await odpoved.json();

            if (data.status === 'success') {
                const eventy = data.data?.eventy || [];
                if (eventy.length > 0) {
                    vykresliEventy(eventy);
                } else {
                    grid.innerHTML = `
                        <div class="transport-eventy-prazdne">
                            <p>Zatim zadne eventy</p>
                            <button class="btn-pridat-event" onclick="eventOtevritModal()">+ Pridat prvni event</button>
                        </div>
                    `;
                }
            } else {
                console.error('API chyba:', data.message);
                grid.innerHTML = `<div class="transport-eventy-prazdne"><p>Chyba: ${escapeHtml(data.message || 'Neznama chyba')}</p></div>`;
            }
        } catch (error) {
            console.error('Chyba pri nacitani eventu:', error);
            grid.innerHTML = '<div class="transport-eventy-prazdne"><p>Chyba pri nacitani</p></div>';
        }
    };

    function vykresliEventy(eventy) {
        const grid = document.getElementById('eventy-grid');
        let html = '';

        eventy.forEach(function(e) {
            const datumText = e.datum_od ? formatujDatum(e.datum_od) + (e.datum_do ? ' - ' + formatujDatum(e.datum_do) : '') : 'Bez datumu';

            html += `
                <div class="transport-event-card" onclick="window.location.href='admin.php?tab=transport&event=${e.event_id}'">
                    <div class="transport-event-card-akce" onclick="event.stopPropagation()">
                        <button onclick="eventEditovat(${e.event_id})">Upravit</button>
                        <button class="btn-smazat-event" onclick="eventOtevritModalSmazat(${e.event_id}, '${escapeHtml(e.nazev)}')">Smazat</button>
                    </div>
                    <div class="transport-event-card-title">${escapeHtml(e.nazev)}</div>
                    <div class="transport-event-card-datum">${datumText}</div>
                    ${e.popis ? `<div style="font-size: 0.85rem; color: #666;">${escapeHtml(e.popis)}</div>` : ''}
                    <div class="transport-event-card-stats">
                        <div class="transport-event-card-stat">Transportu: <strong>${e.pocet_transportu || 0}</strong></div>
                    </div>
                </div>
            `;
        });

        grid.innerHTML = html;
    }

    window.eventOtevritModal = function(data) {
        document.getElementById('event-edit-id').value = '';
        document.getElementById('event-nazev').value = '';
        document.getElementById('event-datum-od').value = '';
        document.getElementById('event-datum-do').value = '';
        document.getElementById('event-popis').value = '';
        document.getElementById('modal-event-titulek').textContent = 'Pridat event';

        if (data) {
            document.getElementById('event-edit-id').value = data.event_id;
            document.getElementById('event-nazev').value = data.nazev || '';
            document.getElementById('event-datum-od').value = data.datum_od || '';
            document.getElementById('event-datum-do').value = data.datum_do || '';
            document.getElementById('event-popis').value = data.popis || '';
            document.getElementById('modal-event-titulek').textContent = 'Upravit event';
        }

        document.getElementById('modal-event').classList.add('aktivni');
    };

    window.eventZavritModal = function() {
        document.getElementById('modal-event').classList.remove('aktivni');
    };

    window.eventEditovat = async function(id) {
        try {
            const odpoved = await fetch('/api/transport_events_api.php?action=eventy_list');
            const data = await odpoved.json();
            if (data.status === 'success') {
                const event = data.data.eventy.find(e => e.event_id == id);
                if (event) eventOtevritModal(event);
            }
        } catch (error) {
            console.error('Chyba:', error);
        }
    };

    window.eventUlozit = async function() {
        const id = document.getElementById('event-edit-id').value;
        const akce = id ? 'event_update' : 'event_create';

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', akce);
        if (id) formData.append('event_id', id);
        formData.append('nazev', document.getElementById('event-nazev').value);
        formData.append('datum_od', document.getElementById('event-datum-od').value);
        formData.append('datum_do', document.getElementById('event-datum-do').value);
        formData.append('popis', document.getElementById('event-popis').value);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', { method: 'POST', body: formData });
            const data = await odpoved.json();

            if (data.status === 'success') {
                eventZavritModal();
                nactiEventy();
            } else {
                alert('Chyba: ' + data.message);
            }
        } catch (error) {
            console.error('Chyba:', error);
            alert('Chyba pri ukladani');
        }
    };

    window.eventOtevritModalSmazat = function(id, nazev) {
        document.getElementById('event-smazat-id').value = id;
        document.getElementById('event-smazat-info').textContent = nazev;
        document.getElementById('modal-event-smazat').classList.add('aktivni');
    };

    window.eventZavritModalSmazat = function() {
        document.getElementById('modal-event-smazat').classList.remove('aktivni');
    };

    window.eventPotvrdSmazat = async function() {
        const id = document.getElementById('event-smazat-id').value;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'event_delete');
        formData.append('event_id', id);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', { method: 'POST', body: formData });
            const data = await odpoved.json();

            if (data.status === 'success') {
                eventZavritModalSmazat();
                nactiEventy();
            } else {
                alert('Chyba: ' + data.message);
            }
        } catch (error) {
            console.error('Chyba:', error);
        }
    };

    // ==========================================
    // RIDICI - Sprava ridicu pro event
    // ==========================================

    // Globalni seznam ridicu pro aktualni event
    let aktualniRidici = [];

    window.nactiRidice = async function() {
        if (!eventId) return;

        const kontejner = document.getElementById('ridici-kontejner');
        if (!kontejner) return;

        try {
            const odpoved = await fetch('/api/transport_events_api.php?action=ridici_list&event_id=' + eventId);
            const data = await odpoved.json();

            if (data.status === 'success') {
                aktualniRidici = data.data.ridici || [];
                vykresliRidice(aktualniRidici);
                aktualizujSelectRidicu();
            }
        } catch (error) {
            console.error('Chyba pri nacitani ridicu:', error);
            kontejner.innerHTML = '<div class="ridici-prazdne">Chyba pri nacitani</div>';
        }
    };

    function vykresliRidice(ridici) {
        const kontejner = document.getElementById('ridici-kontejner');

        if (!ridici || ridici.length === 0) {
            kontejner.innerHTML = `
                <div class="ridici-prazdne">
                    <p>Zatim zadni ridici</p>
                    <button class="btn-pridat-ridice" onclick="ridicOtevritModal()">+ Pridat prvniho ridice</button>
                </div>
            `;
            return;
        }

        const autoSvg = '<svg class="ridic-auto-svg" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>';
        const telSvg = '<svg viewBox="0 0 24 24" fill="#fff" width="16" height="16"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>';

        let html = '';
        ridici.forEach(function(r) {
            html += `
                <div class="ridic-karta" data-ridic-id="${r.ridic_id}">
                    <div class="ridic-karta-akce">
                        <button class="btn-upravit-ridice" onclick="ridicEditovat(${r.ridic_id})">✎</button>
                        <button class="btn-smazat-ridice" onclick="ridicOtevritModalSmazat(${r.ridic_id}, '${escapeHtml(r.jmeno)}')">&times;</button>
                    </div>
                    ${autoSvg}
                    <div class="ridic-jmeno">${escapeHtml(r.jmeno)}</div>
                    ${r.auto ? `<div class="ridic-auto">${escapeHtml(r.auto)}</div>` : ''}
                    ${r.spz ? `<div class="ridic-spz">${escapeHtml(r.spz)}</div>` : ''}
                    ${r.poznamka ? `<div class="ridic-poznamka">${escapeHtml(r.poznamka)}</div>` : ''}
                    ${r.telefon ? `<a href="tel:${escapeHtml(r.telefon)}" class="ridic-tel-link">${telSvg}</a>` : ''}
                </div>
            `;
        });

        kontejner.innerHTML = html;
    }

    function aktualizujSelectRidicu() {
        const select = document.getElementById('transport-ridic-id');
        if (!select) return;

        let html = '<option value="">-- Vyberte ridice --</option>';
        aktualniRidici.forEach(function(r) {
            html += `<option value="${r.ridic_id}">${escapeHtml(r.jmeno)}${r.auto ? ' (' + escapeHtml(r.auto) + ')' : ''}</option>`;
        });
        select.innerHTML = html;
    }

    window.ridicOtevritModal = function(data) {
        document.getElementById('ridic-edit-id').value = '';
        document.getElementById('ridic-jmeno').value = '';
        document.getElementById('ridic-telefon').value = '';
        document.getElementById('ridic-auto').value = '';
        document.getElementById('ridic-spz').value = '';
        document.getElementById('ridic-poznamka').value = '';
        document.getElementById('modal-ridic-titulek').textContent = 'Pridat ridice';

        if (data) {
            document.getElementById('ridic-edit-id').value = data.ridic_id;
            document.getElementById('ridic-jmeno').value = data.jmeno || '';
            document.getElementById('ridic-telefon').value = data.telefon || '';
            document.getElementById('ridic-auto').value = data.auto || '';
            document.getElementById('ridic-spz').value = data.spz || '';
            document.getElementById('ridic-poznamka').value = data.poznamka || '';
            document.getElementById('modal-ridic-titulek').textContent = 'Upravit ridice';
        }

        document.getElementById('modal-ridic').classList.add('aktivni');
    };

    window.ridicZavritModal = function() {
        document.getElementById('modal-ridic').classList.remove('aktivni');
    };

    window.ridicEditovat = function(id) {
        const ridic = aktualniRidici.find(r => r.ridic_id == id);
        if (ridic) ridicOtevritModal(ridic);
    };

    window.ridicUlozit = async function() {
        const id = document.getElementById('ridic-edit-id').value;
        const akce = id ? 'ridic_update' : 'ridic_create';

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', akce);
        formData.append('event_id', eventId);
        if (id) formData.append('ridic_id', id);
        formData.append('jmeno', document.getElementById('ridic-jmeno').value);
        formData.append('telefon', document.getElementById('ridic-telefon').value);
        formData.append('auto', document.getElementById('ridic-auto').value);
        formData.append('spz', document.getElementById('ridic-spz').value);
        formData.append('poznamka', document.getElementById('ridic-poznamka').value);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', { method: 'POST', body: formData });
            const data = await odpoved.json();

            if (data.status === 'success') {
                ridicZavritModal();
                nactiRidice();
            } else {
                alert('Chyba: ' + data.message);
            }
        } catch (error) {
            console.error('Chyba:', error);
            alert('Chyba pri ukladani');
        }
    };

    window.ridicOtevritModalSmazat = function(id, jmeno) {
        document.getElementById('ridic-smazat-id').value = id;
        document.getElementById('ridic-smazat-info').textContent = jmeno;
        document.getElementById('modal-ridic-smazat').classList.add('aktivni');
    };

    window.ridicZavritModalSmazat = function() {
        document.getElementById('modal-ridic-smazat').classList.remove('aktivni');
    };

    window.ridicPotvrdSmazat = async function() {
        const id = document.getElementById('ridic-smazat-id').value;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'ridic_delete');
        formData.append('ridic_id', id);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', { method: 'POST', body: formData });
            const data = await odpoved.json();
            if (data.status === 'success') {
                ridicZavritModalSmazat();
                nactiRidice();
                nactiTransporty(); // Obnovit i transporty protoze mohli mit prirazenych ridicu
            } else {
                alert('Chyba: ' + data.message);
            }
        } catch (error) {
            console.error('Chyba:', error);
        }
    };

    // ==========================================
    // TRANSPORTY - Detail eventu
    // ==========================================

    window.nactiTransporty = async function() {
        if (!eventId) return;

        try {
            // Načíst info o eventu
            const eventOdpoved = await fetch('/api/transport_events_api.php?action=event_detail&event_id=' + eventId);
            const eventData = await eventOdpoved.json();

            if (eventData.status === 'success' && eventData.data.event) {
                document.getElementById('detail-event-nazev').textContent = eventData.data.event.nazev;
            }

            // Načíst transporty
            const odpoved = await fetch('/api/transport_events_api.php?action=list&event_id=' + eventId);
            const data = await odpoved.json();

            if (data.status === 'success') {
                vykresliTransporty(data.data.transporty);
            }
        } catch (error) {
            console.error('Chyba:', error);
        }
    };

    function vykresliTransporty(transporty) {
        const kontejner = document.getElementById('transport-dny-kontejner');

        if (!transporty || transporty.length === 0) {
            kontejner.innerHTML = `
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <p style="margin-bottom: 1rem;">Zatim zadne transporty</p>
                    <button class="btn-pridat-transport" onclick="transportOtevritModal()">+ Pridat prvni transport</button>
                </div>
            `;
            return;
        }

        // Seskupit podle datumu
        const dny = {};
        transporty.forEach(function(t) {
            const datum = t.datum || 'bez-datumu';
            if (!dny[datum]) dny[datum] = [];
            dny[datum].push(t);
        });

        // Seřadit dny
        const serazeneDny = Object.keys(dny).sort();

        let html = '';
        serazeneDny.forEach(function(datum) {
            const denText = datum === 'bez-datumu' ? 'Bez datumu' : formatujDatumDen(datum);

            html += `
                <div class="transport-den">
                    <div class="transport-den-header">
                        <span>${denText}</span>
                        <button class="btn-pridat-transport" onclick="transportOtevritModal(null, '${datum}')">+ Pridat</button>
                    </div>
                    <div class="transport-den-transporty">
            `;

            // Seřadit transporty podle času
            dny[datum].sort((a, b) => (a.cas || '').localeCompare(b.cas || ''));

            dny[datum].forEach(function(t) {
                const stavClass = t.stav === 'drop' ? 'stav-drop' : (t.stav === 'onway' ? 'stav-onway' : 'stav-wait');
                const stavText = t.stav === 'drop' ? 'DROP OFF' : (t.stav === 'onway' ? 'ON THE WAY' : 'WAIT');

                html += `
                    <div class="transport-radek" data-id="${t.event_id}">
                        <div class="transport-radek-akce">
                            <button class="btn-upravit-transport" onclick="transportEditovat(${t.event_id})">✎</button>
                            <button class="btn-smazat-transport" onclick="transportOtevritModalSmazat(${t.event_id}, '${escapeHtml(t.jmeno_prijmeni)}')">&times;</button>
                        </div>
                        <div class="transport-radek-cas">${(t.cas || '').substring(0, 5)}</div>
                        <div class="transport-radek-info">
                            <div class="transport-radek-jmeno">${escapeHtml(t.jmeno_prijmeni)}</div>
                            ${t.cislo_letu ? `<div class="transport-radek-let">Let: ${escapeHtml(t.cislo_letu)}${t.cas_priletu ? ' (prilet ' + t.cas_priletu.substring(0, 5) + ')' : ''}</div>` : ''}
                            ${t.destinace ? `<div class="transport-radek-trasa">${escapeHtml(t.destinace)}</div>` : ''}
                            ${t.telefon || t.email ? `<div class="transport-radek-kontakt">${t.telefon ? escapeHtml(t.telefon) : ''} ${t.email ? '| ' + escapeHtml(t.email) : ''}</div>` : ''}
                        </div>
                        <div class="transport-radek-stav">
                            <button class="stav-btn ${stavClass}" onclick="transportZmenStav(${t.event_id})">${stavText}</button>
                            ${t.ridic_jmeno ? `<div class="stav-cas-info">${escapeHtml(t.ridic_jmeno)}${t.ridic_auto ? ' (' + escapeHtml(t.ridic_auto) + ')' : ''}</div>` : ''}
                        </div>
                    </div>
                `;
            });

            html += '</div></div>';
        });

        kontejner.innerHTML = html;
    }

    window.transportOtevritModal = function(data, datum) {
        // Aktualizovat select ridicu pred otevrenim modalu
        aktualizujSelectRidicu();

        document.getElementById('transport-edit-id').value = '';
        document.getElementById('transport-datum').value = datum || new Date().toISOString().split('T')[0];
        document.getElementById('transport-cas').value = '';
        document.getElementById('transport-jmeno').value = '';
        document.getElementById('transport-let').value = '';
        document.getElementById('transport-prilet').value = '';
        document.getElementById('transport-destinace').value = '';
        document.getElementById('transport-telefon').value = '';
        document.getElementById('transport-email').value = '';
        document.getElementById('transport-ridic-id').value = '';
        document.getElementById('transport-poznamka').value = '';
        document.getElementById('modal-transport-titulek').textContent = 'Pridat transport';

        if (data) {
            document.getElementById('transport-edit-id').value = data.event_id;
            document.getElementById('transport-datum').value = data.datum || '';
            document.getElementById('transport-cas').value = (data.cas || '').substring(0, 5);
            document.getElementById('transport-jmeno').value = data.jmeno_prijmeni || '';
            document.getElementById('transport-let').value = data.cislo_letu || '';
            document.getElementById('transport-prilet').value = (data.cas_priletu || '').substring(0, 5);
            document.getElementById('transport-destinace').value = data.destinace || '';
            document.getElementById('transport-telefon').value = data.telefon || '';
            document.getElementById('transport-email').value = data.email || '';
            document.getElementById('transport-ridic-id').value = data.ridic_id || '';
            document.getElementById('transport-poznamka').value = data.poznamka || '';
            document.getElementById('modal-transport-titulek').textContent = 'Upravit transport';
        }

        document.getElementById('modal-transport').classList.add('aktivni');
    };

    window.transportZavritModal = function() {
        document.getElementById('modal-transport').classList.remove('aktivni');
    };

    window.transportEditovat = async function(id) {
        try {
            const odpoved = await fetch('/api/transport_events_api.php?action=list&event_id=' + eventId);
            const data = await odpoved.json();
            if (data.status === 'success') {
                const transport = data.data.transporty.find(t => t.event_id == id);
                if (transport) transportOtevritModal(transport);
            }
        } catch (error) {
            console.error('Chyba:', error);
        }
    };

    window.transportUlozit = async function() {
        const id = document.getElementById('transport-edit-id').value;
        const akce = id ? 'update' : 'create';

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', akce);
        formData.append('parent_event_id', eventId);
        if (id) formData.append('event_id', id);
        formData.append('datum', document.getElementById('transport-datum').value);
        formData.append('cas', document.getElementById('transport-cas').value);
        formData.append('jmeno_prijmeni', document.getElementById('transport-jmeno').value);
        formData.append('cislo_letu', document.getElementById('transport-let').value);
        formData.append('cas_priletu', document.getElementById('transport-prilet').value);
        formData.append('destinace', document.getElementById('transport-destinace').value);
        formData.append('telefon', document.getElementById('transport-telefon').value);
        formData.append('email', document.getElementById('transport-email').value);
        formData.append('ridic_id', document.getElementById('transport-ridic-id').value);
        formData.append('poznamka', document.getElementById('transport-poznamka').value);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', { method: 'POST', body: formData });
            const data = await odpoved.json();

            if (data.status === 'success') {
                transportZavritModal();
                nactiTransporty();
            } else {
                alert('Chyba: ' + data.message);
            }
        } catch (error) {
            console.error('Chyba:', error);
            alert('Chyba pri ukladani');
        }
    };

    window.transportZmenStav = async function(id) {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'zmena_stavu');
        formData.append('event_id', id);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', { method: 'POST', body: formData });
            const data = await odpoved.json();
            if (data.status === 'success') {
                nactiTransporty();
            }
        } catch (error) {
            console.error('Chyba:', error);
        }
    };

    window.transportOtevritModalSmazat = function(id, jmeno) {
        document.getElementById('transport-smazat-id').value = id;
        document.getElementById('transport-smazat-info').textContent = jmeno;
        document.getElementById('modal-transport-smazat').classList.add('aktivni');
    };

    window.transportZavritModalSmazat = function() {
        document.getElementById('modal-transport-smazat').classList.remove('aktivni');
    };

    window.transportPotvrdSmazat = async function() {
        const id = document.getElementById('transport-smazat-id').value;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'delete');
        formData.append('event_id', id);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', { method: 'POST', body: formData });
            const data = await odpoved.json();
            if (data.status === 'success') {
                transportZavritModalSmazat();
                nactiTransporty();
            }
        } catch (error) {
            console.error('Chyba:', error);
        }
    };

    // ==========================================
    // POMOCNE FUNKCE
    // ==========================================

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatujDatum(datum) {
        if (!datum) return '';
        const d = new Date(datum);
        return d.toLocaleDateString('cs-CZ', { day: 'numeric', month: 'numeric', year: 'numeric' });
    }

    function formatujDatumDen(datum) {
        if (!datum) return '';
        const d = new Date(datum);
        const dny = ['Nedele', 'Pondeli', 'Utery', 'Streda', 'Ctvrtek', 'Patek', 'Sobota'];
        return dny[d.getDay()] + ' ' + d.toLocaleDateString('cs-CZ', { day: 'numeric', month: 'numeric' });
    }

    // ESC zavře modaly
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            eventZavritModal();
            eventZavritModalSmazat();
            transportZavritModal();
            transportZavritModalSmazat();
            ridicZavritModal();
            ridicZavritModalSmazat();
        }
    });

    // Klik mimo modal zavře
    ['modal-event', 'modal-event-smazat', 'modal-transport', 'modal-transport-smazat', 'modal-ridic', 'modal-ridic-smazat'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('aktivni');
                }
            });
        }
    });

    // Inicializace
    document.addEventListener('DOMContentLoaded', function() {
        if (eventId) {
            nactiRidice();
            nactiTransporty();
        } else {
            nactiEventy();
        }
    });
})();
</script>
