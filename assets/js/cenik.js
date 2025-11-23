/**
 * Ceník služeb - JavaScript
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Globální proměnné
    let pricingData = [];
    let currentEditItem = null;

    // ========================================
    // HELPER: Markdown parsing
    // ========================================
    function parseMarkdown(text) {
        if (!text) return '';
        // **text** → <strong>text</strong>
        return text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    }

    // ========================================
    // INIT
    // ========================================
    window.addEventListener('DOMContentLoaded', () => {
        nactiCenik();
    });

    // ========================================
    // NAČTENÍ CENÍKU Z API
    // ========================================
    async function nactiCenik() {
        try {
            const response = await fetch('/api/pricing_api.php?action=list');
            const result = await response.json();

            if (result.status === 'success') {
                pricingData = result.by_category;
                zobrazitCenik(result.by_category);
            } else {
                console.error('[Ceník] Chyba:', result.message);
                document.getElementById('loading-indicator').innerHTML = '<p style="color: red;">Chyba při načítání ceníku</p>';
            }
        } catch (error) {
            console.error('[Ceník] Síťová chyba:', error);
            document.getElementById('loading-indicator').innerHTML = '<p style="color: red;">Síťová chyba při načítání</p>';
        }
    }

    // ========================================
    // ZOBRAZENÍ CENÍKU
    // ========================================
    function zobrazitCenik(byCategory) {
        const grid = document.getElementById('pricing-grid');
        const loading = document.getElementById('loading-indicator');

        loading.style.display = 'none';
        grid.style.display = 'grid';
        grid.innerHTML = '';

        // Seřadit kategorie
        const categories = Object.keys(byCategory).sort();

        categories.forEach(category => {
            const items = byCategory[category];

            const categoryEl = document.createElement('div');
            categoryEl.className = 'pricing-category';

            const headerEl = document.createElement('div');
            headerEl.className = 'category-header';
            headerEl.textContent = category;

            const itemsEl = document.createElement('div');
            itemsEl.className = 'category-items';

            items.forEach(item => {
                const itemEl = vytvo\u0159itPolozkuElement(item);
                itemsEl.appendChild(itemEl);
            });

            categoryEl.appendChild(headerEl);
            categoryEl.appendChild(itemsEl);
            grid.appendChild(categoryEl);
        });
    }

    // ========================================
    // VYTVOŘENÍ ELEMENTU POLOŽKY
    // ========================================
    function vytvo\u0159itPolozkuElement(item) {
        const itemEl = document.createElement('div');
        itemEl.className = 'pricing-item';
        itemEl.dataset.id = item.id;

        // Header (název + cena)
        const headerEl = document.createElement('div');
        headerEl.className = 'item-header';

        const nameEl = document.createElement('div');
        nameEl.className = 'item-name';
        nameEl.textContent = item.service_name;

        const priceEl = document.createElement('div');
        priceEl.className = 'item-price';

        if (item.price_from && item.price_to) {
            priceEl.innerHTML = `${item.price_from} - ${item.price_to} ${item.price_unit}`;
        } else if (item.price_from) {
            priceEl.className += ' range';
            priceEl.innerHTML = `${item.price_from} ${item.price_unit}`;
        } else if (item.price_to) {
            priceEl.innerHTML = `${item.price_to} ${item.price_unit}`;
        }

        headerEl.appendChild(nameEl);
        headerEl.appendChild(priceEl);
        itemEl.appendChild(headerEl);

        // Popis
        if (item.description) {
            const descEl = document.createElement('div');
            descEl.className = 'item-description';
            descEl.innerHTML = parseMarkdown(item.description);
            itemEl.appendChild(descEl);
        }

        // Admin edit tlačítko
        if (window.isAdmin) {
            const editBtn = document.createElement('button');
            editBtn.className = 'item-edit-btn';
            editBtn.textContent = 'Upravit';
            editBtn.onclick = () => upravitPolozku(item);
            itemEl.appendChild(editBtn);
        }

        return itemEl;
    }

    // ========================================
    // ADMIN: UPRAVIT POLOŽKU
    // ========================================
    window.upravitPolozku = function(item) {
        currentEditItem = item;

        // Naplnit formulář
        document.getElementById('modal-title').textContent = 'Upravit položku';
        document.getElementById('item-id').value = item.id;
        document.getElementById('service-name').value = item.service_name;
        document.getElementById('description').value = item.description || '';
        document.getElementById('price-from').value = item.price_from || '';
        document.getElementById('price-to').value = item.price_to || '';
        document.getElementById('price-unit').value = item.price_unit;
        document.getElementById('category').value = item.category || '';
        document.getElementById('is-active').checked = item.is_active == 1;

        // Zobrazit delete tlačítko
        document.getElementById('delete-btn').style.display = 'inline-block';

        // Otevřít modal
        document.getElementById('edit-modal').style.display = 'flex';
    };

    // ========================================
    // ADMIN: PŘIDAT NOVOU POLOŽKU
    // ========================================
    window.pridatPolozku = function() {
        currentEditItem = null;

        // Vyčistit formulář
        document.getElementById('modal-title').textContent = 'Přidat novou položku';
        document.getElementById('edit-form').reset();
        document.getElementById('item-id').value = '';
        document.getElementById('is-active').checked = true;

        // Skrýt delete tlačítko
        document.getElementById('delete-btn').style.display = 'none';

        // Otevřít modal
        document.getElementById('edit-modal').style.display = 'flex';
    };

    // ========================================
    // ADMIN: ULOŽIT POLOŽKU
    // ========================================
    window.ulozitPolozku = async function(event) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);

        // Přidat CSRF token
        formData.append('csrf_token', window.csrfToken);

        // Určit action (update nebo create)
        const itemId = formData.get('id');
        const action = itemId ? 'update' : 'create';
        formData.append('action', action);

        // Checkbox is_active
        if (!formData.get('is_active')) {
            formData.append('is_active', '0');
        }

        try {
            const response = await fetch('/api/pricing_api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                alert(itemId ? 'Položka aktualizována' : 'Položka vytvořena');
                zavritModal();
                nactiCenik(); // Reload ceníku
            } else {
                alert('Chyba: ' + result.message);
            }
        } catch (error) {
            console.error('[Ceník] Chyba při ukládání:', error);
            alert('Síťová chyba při ukládání');
        }
    };

    // ========================================
    // ADMIN: SMAZAT POLOŽKU
    // ========================================
    window.smazatPolozku = async function() {
        if (!currentEditItem) return;

        if (!confirm('Opravdu chcete smazat tuto položku?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', currentEditItem.id);
        formData.append('csrf_token', window.csrfToken);

        try {
            const response = await fetch('/api/pricing_api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                alert('Položka smazána');
                zavritModal();
                nactiCenik(); // Reload ceníku
            } else {
                alert('Chyba: ' + result.message);
            }
        } catch (error) {
            console.error('[Ceník] Chyba při mazání:', error);
            alert('Síťová chyba při mazání');
        }
    };

    // ========================================
    // ZAVŘÍT MODAL
    // ========================================
    window.zavritModal = function() {
        document.getElementById('edit-modal').style.display = 'none';
        currentEditItem = null;
    };

    // Zavřít modal při kliku mimo obsah
    window.addEventListener('click', (event) => {
        const modal = document.getElementById('edit-modal');
        if (event.target === modal) {
            zavritModal();
        }
    });

})();
