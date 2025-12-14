<?php
/**
 * Admin Transport - Správa transportních eventů
 *
 * Include soubor pro admin.php?tab=transport
 */

if (!defined('ADMIN_PHP_LOADED')) {
    die('Primy pristup neni povolen');
}
?>

<style>
/* Transport Admin Styles */
.transport-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

.transport-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.transport-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1a1a1a;
}

.transport-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.transport-filtr {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.transport-filtr label {
    font-size: 0.85rem;
    color: #666;
    margin-right: 0.25rem;
}

.transport-filtr input,
.transport-filtr select {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.85rem;
}

.transport-statistiky {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.transport-stat {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1rem 1.5rem;
    text-align: center;
    min-width: 120px;
}

.transport-stat-hodnota {
    font-size: 2rem;
    font-weight: 700;
    color: #1a1a1a;
}

.transport-stat-popis {
    font-size: 0.75rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.transport-stat.wait .transport-stat-hodnota { color: #666; }
.transport-stat.onway .transport-stat-hodnota { color: #333; }
.transport-stat.drop .transport-stat-hodnota { color: #39ff14; }

.transport-tabulka-wrapper {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.transport-tabulka {
    width: 100%;
    border-collapse: collapse;
}

.transport-tabulka th,
.transport-tabulka td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
    font-size: 0.85rem;
}

.transport-tabulka th {
    background: #f5f5f5;
    font-weight: 600;
    color: #333;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.transport-tabulka tbody tr:hover {
    background: #f9f9f9;
}

.transport-tabulka .cas-bunka {
    font-weight: 700;
    font-size: 1rem;
    white-space: nowrap;
}

.transport-stav {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.transport-stav:hover {
    transform: scale(1.05);
}

.transport-stav.wait {
    background: #f0f0f0;
    color: #666;
    border-color: #ccc;
}

.transport-stav.onway {
    background: #333;
    color: #fff;
    border-color: #333;
}

.transport-stav.drop {
    background: #000;
    color: #39ff14;
    border-color: #39ff14;
    box-shadow: 0 0 10px rgba(57, 255, 20, 0.3);
}

.transport-akce {
    display: flex;
    gap: 0.5rem;
}

.transport-akce button {
    padding: 0.4rem 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    font-size: 0.75rem;
    transition: all 0.2s ease;
}

.transport-akce button:hover {
    background: #f5f5f5;
    border-color: #333;
}

.transport-akce button.btn-smazat {
    color: #dc3545;
    border-color: #dc3545;
}

.transport-akce button.btn-smazat:hover {
    background: #dc3545;
    color: #fff;
}

.btn-pridat-transport {
    background: #333;
    color: #fff;
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-pridat-transport:hover {
    background: #555;
}

.btn-obnovit {
    background: #fff;
    color: #333;
    border: 1px solid #ddd;
    padding: 0.6rem 1.25rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-obnovit:hover {
    background: #f5f5f5;
    border-color: #333;
}

/* Modal */
.transport-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}

.transport-modal-overlay.aktivni {
    display: flex;
}

.transport-modal {
    background: #1a1a1a;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    border: 1px solid #333;
}

.transport-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #333;
    background: #222;
}

.transport-modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #fff;
}

.transport-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #999;
    line-height: 1;
    padding: 0;
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
    color: #aaa;
    margin-bottom: 0.4rem;
}

.transport-form-group input,
.transport-form-group select,
.transport-form-group textarea {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid #444;
    border-radius: 6px;
    font-size: 0.9rem;
    background: #2a2a2a;
    color: #fff;
}

.transport-form-group input:focus,
.transport-form-group select:focus,
.transport-form-group textarea:focus {
    outline: none;
    border-color: #666;
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
    background: #222;
}

.transport-modal-footer button {
    padding: 0.6rem 1.25rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-zrusit {
    background: #333;
    color: #fff;
    border: 1px solid #444;
}

.btn-zrusit:hover {
    background: #444;
}

.btn-ulozit {
    background: #28a745;
    color: #fff;
    border: none;
}

.btn-ulozit:hover {
    background: #218838;
}

/* Prázdný stav */
.transport-prazdny {
    text-align: center;
    padding: 3rem;
    color: #999;
}

.transport-prazdny p {
    margin: 0 0 1rem 0;
    font-size: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .transport-header {
        flex-direction: column;
        align-items: stretch;
    }

    .transport-actions {
        justify-content: stretch;
    }

    .transport-actions button {
        flex: 1;
    }

    .transport-form-row {
        grid-template-columns: 1fr;
    }

    .transport-tabulka th,
    .transport-tabulka td {
        padding: 0.5rem;
        font-size: 0.75rem;
    }

    .transport-tabulka .skryt-mobil {
        display: none;
    }
}
</style>

<div class="transport-container">
    <!-- Header -->
    <div class="transport-header">
        <h2>Transport Events</h2>
        <div class="transport-actions">
            <button class="btn-pridat-transport" onclick="transportOtevritModal()">+ Pridat transport</button>
            <button class="btn-obnovit" onclick="transportNactiData()">Obnovit</button>
        </div>
    </div>

    <!-- Filtry -->
    <div class="transport-filtr">
        <label>Datum:</label>
        <input type="date" id="transport-filtr-datum" onchange="transportNactiData()">

        <label>Ridic:</label>
        <select id="transport-filtr-ridic" onchange="transportNactiData()">
            <option value="">Vsichni</option>
        </select>

        <label>Stav:</label>
        <select id="transport-filtr-stav" onchange="transportNactiData()">
            <option value="">Vsechny</option>
            <option value="wait">WAIT</option>
            <option value="onway">ON THE WAY</option>
            <option value="drop">DROP OFF</option>
        </select>
    </div>

    <!-- Statistiky -->
    <div class="transport-statistiky">
        <div class="transport-stat wait">
            <div class="transport-stat-hodnota" id="stat-wait">0</div>
            <div class="transport-stat-popis">Ceka</div>
        </div>
        <div class="transport-stat onway">
            <div class="transport-stat-hodnota" id="stat-onway">0</div>
            <div class="transport-stat-popis">Na ceste</div>
        </div>
        <div class="transport-stat drop">
            <div class="transport-stat-hodnota" id="stat-drop">0</div>
            <div class="transport-stat-popis">Doruceno</div>
        </div>
        <div class="transport-stat">
            <div class="transport-stat-hodnota" id="stat-celkem">0</div>
            <div class="transport-stat-popis">Celkem</div>
        </div>
    </div>

    <!-- Tabulka -->
    <div class="transport-tabulka-wrapper">
        <table class="transport-tabulka">
            <thead>
                <tr>
                    <th>Cas</th>
                    <th>Jmeno</th>
                    <th class="skryt-mobil">Let</th>
                    <th class="skryt-mobil">Destinace</th>
                    <th class="skryt-mobil">Prilet</th>
                    <th class="skryt-mobil">Telefon</th>
                    <th class="skryt-mobil">Ridic</th>
                    <th>Stav</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody id="transport-tabulka-body">
                <tr>
                    <td colspan="9" class="transport-prazdny">
                        <p>Nacitam data...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal pro přidání/editaci -->
<div class="transport-modal-overlay" id="transport-modal">
    <div class="transport-modal">
        <div class="transport-modal-header">
            <h3 id="transport-modal-titulek">Pridat transport</h3>
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
                <input type="text" id="transport-destinace" placeholder="Praha -> Brno">
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
                <input type="text" id="transport-ridic" placeholder="Milan" list="ridici-list">
                <datalist id="ridici-list"></datalist>
            </div>

            <div class="transport-form-group">
                <label>Poznamka</label>
                <textarea id="transport-poznamka" rows="2" placeholder="Dodatecne informace..."></textarea>
            </div>
        </div>
        <div class="transport-modal-footer">
            <button class="btn-zrusit" onclick="transportZavritModal()">Zrusit</button>
            <button class="btn-ulozit" onclick="transportUlozit()">Ulozit</button>
        </div>
    </div>
</div>

<!-- Modal pro smazání -->
<div class="transport-modal-overlay" id="transport-modal-smazat">
    <div class="transport-modal" style="max-width: 400px;">
        <div class="transport-modal-header">
            <h3>Smazat transport</h3>
            <button class="transport-modal-close" onclick="transportZavritModalSmazat()">&times;</button>
        </div>
        <div class="transport-modal-body">
            <input type="hidden" id="transport-smazat-id">
            <p style="color: #ccc; margin: 0;">Opravdu chcete smazat tento transport?</p>
            <p style="color: #999; font-size: 0.85rem; margin: 1rem 0 0 0;" id="transport-smazat-info"></p>
        </div>
        <div class="transport-modal-footer">
            <button class="btn-zrusit" onclick="transportZavritModalSmazat()">Zrusit</button>
            <button class="btn-ulozit" style="background: #dc3545;" onclick="transportPotvrdSmazat()">Smazat</button>
        </div>
    </div>
</div>

<script>
// Transport Events - JavaScript
(function() {
    'use strict';

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // Inicializace při načtení
    document.addEventListener('DOMContentLoaded', function() {
        // Nastavit dnešní datum
        const dnes = new Date().toISOString().split('T')[0];
        document.getElementById('transport-filtr-datum').value = dnes;
        document.getElementById('transport-datum').value = dnes;

        // Načíst řidiče pro dropdown
        transportNactiRidice();

        // Načíst data
        transportNactiData();
    });

    // Globální funkce
    window.transportNactiData = async function() {
        const datum = document.getElementById('transport-filtr-datum').value;
        const ridic = document.getElementById('transport-filtr-ridic').value;
        const stav = document.getElementById('transport-filtr-stav').value;

        let url = '/api/transport_events_api.php?action=list';
        if (datum) url += '&datum=' + encodeURIComponent(datum);
        if (ridic) url += '&ridic=' + encodeURIComponent(ridic);
        if (stav) url += '&stav=' + encodeURIComponent(stav);

        try {
            const odpoved = await fetch(url);
            const data = await odpoved.json();

            if (data.status === 'success') {
                transportVykresliTabulku(data.data.transporty);
                transportNactiStatistiky();
            } else {
                console.error('Chyba:', data.message);
            }
        } catch (error) {
            console.error('Chyba pri nacitani:', error);
        }
    };

    window.transportNactiStatistiky = async function() {
        const datum = document.getElementById('transport-filtr-datum').value || new Date().toISOString().split('T')[0];

        try {
            const odpoved = await fetch('/api/transport_events_api.php?action=statistiky&datum=' + encodeURIComponent(datum));
            const data = await odpoved.json();

            if (data.status === 'success') {
                document.getElementById('stat-wait').textContent = data.data.statistiky.wait;
                document.getElementById('stat-onway').textContent = data.data.statistiky.onway;
                document.getElementById('stat-drop').textContent = data.data.statistiky.drop;
                document.getElementById('stat-celkem').textContent = data.data.statistiky.celkem;
            }
        } catch (error) {
            console.error('Chyba pri nacitani statistik:', error);
        }
    };

    window.transportNactiRidice = async function() {
        try {
            const odpoved = await fetch('/api/transport_events_api.php?action=ridici');
            const data = await odpoved.json();

            if (data.status === 'success') {
                const select = document.getElementById('transport-filtr-ridic');
                const datalist = document.getElementById('ridici-list');

                // Vyčistit a naplnit
                select.innerHTML = '<option value="">Vsichni</option>';
                datalist.innerHTML = '';

                data.data.ridici.forEach(function(ridic) {
                    select.innerHTML += '<option value="' + transportEscapeHtml(ridic) + '">' + transportEscapeHtml(ridic) + '</option>';
                    datalist.innerHTML += '<option value="' + transportEscapeHtml(ridic) + '">';
                });
            }
        } catch (error) {
            console.error('Chyba pri nacitani ridicu:', error);
        }
    };

    function transportVykresliTabulku(transporty) {
        const tbody = document.getElementById('transport-tabulka-body');

        if (!transporty || transporty.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="transport-prazdny"><p>Zadne transporty pro vybrane filtry</p><button class="btn-pridat-transport" onclick="transportOtevritModal()">+ Pridat prvni transport</button></td></tr>';
            return;
        }

        let html = '';
        transporty.forEach(function(t) {
            html += '<tr data-id="' + t.event_id + '">';
            html += '<td class="cas-bunka">' + transportEscapeHtml(t.cas?.substring(0, 5) || '') + '</td>';
            html += '<td>' + transportEscapeHtml(t.jmeno_prijmeni || '') + '</td>';
            html += '<td class="skryt-mobil">' + transportEscapeHtml(t.cislo_letu || '-') + '</td>';
            html += '<td class="skryt-mobil">' + transportEscapeHtml(t.destinace || '-') + '</td>';
            html += '<td class="skryt-mobil">' + transportEscapeHtml(t.cas_priletu?.substring(0, 5) || '-') + '</td>';
            html += '<td class="skryt-mobil">' + transportEscapeHtml(t.telefon || '-') + '</td>';
            html += '<td class="skryt-mobil">' + transportEscapeHtml(t.ridic || '-') + '</td>';
            html += '<td><span class="transport-stav ' + t.stav + '" onclick="transportZmenStav(' + t.event_id + ')">' + transportEscapeHtml(t.stav_text || t.stav) + '</span></td>';
            html += '<td class="transport-akce">';
            html += '<button onclick="transportEditovat(' + t.event_id + ')">Upravit</button>';
            html += '<button class="btn-smazat" onclick="transportOtevritModalSmazat(' + t.event_id + ', \'' + transportEscapeHtml(t.jmeno_prijmeni || '') + '\')">Smazat</button>';
            html += '</td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;
    }

    window.transportOtevritModal = function(data) {
        const modal = document.getElementById('transport-modal');
        const titulek = document.getElementById('transport-modal-titulek');

        // Reset formuláře
        document.getElementById('transport-edit-id').value = '';
        document.getElementById('transport-jmeno').value = '';
        document.getElementById('transport-let').value = '';
        document.getElementById('transport-destinace').value = '';
        document.getElementById('transport-prilet').value = '';
        document.getElementById('transport-telefon').value = '';
        document.getElementById('transport-email').value = '';
        document.getElementById('transport-ridic').value = '';
        document.getElementById('transport-poznamka').value = '';

        // Nastavit datum na dnešek pokud není vyplněno
        if (!document.getElementById('transport-datum').value) {
            document.getElementById('transport-datum').value = new Date().toISOString().split('T')[0];
        }

        if (data) {
            titulek.textContent = 'Upravit transport';
            document.getElementById('transport-edit-id').value = data.event_id;
            document.getElementById('transport-datum').value = data.datum;
            document.getElementById('transport-cas').value = data.cas?.substring(0, 5) || '';
            document.getElementById('transport-jmeno').value = data.jmeno_prijmeni || '';
            document.getElementById('transport-let').value = data.cislo_letu || '';
            document.getElementById('transport-destinace').value = data.destinace || '';
            document.getElementById('transport-prilet').value = data.cas_priletu?.substring(0, 5) || '';
            document.getElementById('transport-telefon').value = data.telefon || '';
            document.getElementById('transport-email').value = data.email || '';
            document.getElementById('transport-ridic').value = data.ridic || '';
            document.getElementById('transport-poznamka').value = data.poznamka || '';
        } else {
            titulek.textContent = 'Pridat transport';
            document.getElementById('transport-cas').value = '';
        }

        modal.classList.add('aktivni');
    };

    window.transportZavritModal = function() {
        document.getElementById('transport-modal').classList.remove('aktivni');
    };

    window.transportEditovat = async function(eventId) {
        try {
            const odpoved = await fetch('/api/transport_events_api.php?action=list');
            const data = await odpoved.json();

            if (data.status === 'success') {
                const transport = data.data.transporty.find(function(t) {
                    return t.event_id == eventId;
                });
                if (transport) {
                    transportOtevritModal(transport);
                }
            }
        } catch (error) {
            console.error('Chyba pri nacitani transportu:', error);
        }
    };

    window.transportUlozit = async function() {
        const eventId = document.getElementById('transport-edit-id').value;
        const akce = eventId ? 'update' : 'create';

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', akce);
        if (eventId) formData.append('event_id', eventId);
        formData.append('datum', document.getElementById('transport-datum').value);
        formData.append('cas', document.getElementById('transport-cas').value);
        formData.append('jmeno_prijmeni', document.getElementById('transport-jmeno').value);
        formData.append('cislo_letu', document.getElementById('transport-let').value);
        formData.append('destinace', document.getElementById('transport-destinace').value);
        formData.append('cas_priletu', document.getElementById('transport-prilet').value);
        formData.append('telefon', document.getElementById('transport-telefon').value);
        formData.append('email', document.getElementById('transport-email').value);
        formData.append('ridic', document.getElementById('transport-ridic').value);
        formData.append('poznamka', document.getElementById('transport-poznamka').value);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', {
                method: 'POST',
                body: formData
            });
            const data = await odpoved.json();

            if (data.status === 'success') {
                transportZavritModal();
                transportNactiData();
                transportNactiRidice();

                // Toast notifikace
                if (typeof WGSToast !== 'undefined') {
                    WGSToast.zobrazit(eventId ? 'Transport aktualizovan' : 'Transport vytvoren');
                }
            } else {
                alert('Chyba: ' + data.message);
            }
        } catch (error) {
            console.error('Chyba pri ukladani:', error);
            alert('Chyba pri ukladani');
        }
    };

    window.transportZmenStav = async function(eventId) {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'zmena_stavu');
        formData.append('event_id', eventId);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', {
                method: 'POST',
                body: formData
            });
            const data = await odpoved.json();

            if (data.status === 'success') {
                transportNactiData();
            } else {
                alert('Chyba: ' + data.message);
            }
        } catch (error) {
            console.error('Chyba pri zmene stavu:', error);
        }
    };

    window.transportOtevritModalSmazat = function(eventId, jmeno) {
        document.getElementById('transport-smazat-id').value = eventId;
        document.getElementById('transport-smazat-info').textContent = jmeno;
        document.getElementById('transport-modal-smazat').classList.add('aktivni');
    };

    window.transportZavritModalSmazat = function() {
        document.getElementById('transport-modal-smazat').classList.remove('aktivni');
    };

    window.transportPotvrdSmazat = async function() {
        const eventId = document.getElementById('transport-smazat-id').value;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'delete');
        formData.append('event_id', eventId);

        try {
            const odpoved = await fetch('/api/transport_events_api.php', {
                method: 'POST',
                body: formData
            });
            const data = await odpoved.json();

            if (data.status === 'success') {
                transportZavritModalSmazat();
                transportNactiData();

                if (typeof WGSToast !== 'undefined') {
                    WGSToast.zobrazit('Transport smazan');
                }
            } else {
                alert('Chyba: ' + data.message);
            }
        } catch (error) {
            console.error('Chyba pri mazani:', error);
        }
    };

    function transportEscapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Zavřít modal při kliku mimo
    document.getElementById('transport-modal').addEventListener('click', function(e) {
        if (e.target === this) transportZavritModal();
    });

    document.getElementById('transport-modal-smazat').addEventListener('click', function(e) {
        if (e.target === this) transportZavritModalSmazat();
    });

    // ESC zavře modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            transportZavritModal();
            transportZavritModalSmazat();
        }
    });
})();
</script>
