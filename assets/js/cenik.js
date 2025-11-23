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
    // HELPER: Překlad databázového obsahu
    // ========================================
    function prelozitText(text, typ) {
        if (!text) return text;

        // Typ může být: 'category', 'service', 'desc'
        const klic = `pricing.${typ}.${text}`;

        // Zkusit najít překlad
        const preklad = window.t ? window.t(klic) : null;

        // Pokud existuje překlad a není to stejný klíč (nebyl nalezen), použít ho
        if (preklad && preklad !== klic) {
            return preklad;
        }

        // Jinak vrátit původní text
        return text;
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
                document.getElementById('loading-indicator').innerHTML = `<p style="color: red;">${window.t('pricingGrid.loading.error')}</p>`;
            }
        } catch (error) {
            console.error('[Ceník] Síťová chyba:', error);
            document.getElementById('loading-indicator').innerHTML = `<p style="color: red;">${window.t('pricingGrid.loading.networkError')}</p>`;
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

            // Zjistit překlad kategorie z první položky
            const jazyk = window.ziskejAktualniJazyk ? window.ziskejAktualniJazyk() : 'cs';
            const firstItem = items[0];
            let categoryName = category;

            if (firstItem) {
                if (jazyk === 'en' && firstItem.category_en) {
                    categoryName = firstItem.category_en;
                } else if (jazyk === 'it' && firstItem.category_it) {
                    categoryName = firstItem.category_it;
                }
            }

            headerEl.textContent = categoryName;

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

        // Zjistit aktuální jazyk
        const jazyk = window.ziskejAktualniJazyk ? window.ziskejAktualniJazyk() : 'cs';

        // Header (název + cena)
        const headerEl = document.createElement('div');
        headerEl.className = 'item-header';

        const nameEl = document.createElement('div');
        nameEl.className = 'item-name';

        // Načíst název podle jazyka
        if (jazyk === 'cs') {
            nameEl.textContent = item.service_name || '';
        } else if (jazyk === 'en') {
            let dbPreklad = item.service_name_en;
            // Pokud DB překlad neexistuje nebo je stejný jako český text, zkusit slovník
            if (!dbPreklad || dbPreklad === item.service_name) {
                dbPreklad = prelozitText(item.service_name, 'service');
            }
            nameEl.textContent = dbPreklad || item.service_name || '';
        } else if (jazyk === 'it') {
            let dbPreklad = item.service_name_it;
            // Pokud DB překlad neexistuje nebo je stejný jako český text, zkusit slovník
            if (!dbPreklad || dbPreklad === item.service_name) {
                dbPreklad = prelozitText(item.service_name, 'service');
            }
            nameEl.textContent = dbPreklad || item.service_name || '';
        }

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

        // Popis podle jazyka
        let popis = '';
        if (jazyk === 'cs') {
            popis = item.description || '';
        } else if (jazyk === 'en') {
            let dbPreklad = item.description_en;
            // Pokud DB překlad neexistuje nebo je stejný jako český text, zkusit slovník
            if (!dbPreklad || dbPreklad === item.description) {
                dbPreklad = prelozitText(item.description, 'desc');
            }
            popis = dbPreklad || item.description || '';
        } else if (jazyk === 'it') {
            let dbPreklad = item.description_it;
            // Pokud DB překlad neexistuje nebo je stejný jako český text, zkusit slovník
            if (!dbPreklad || dbPreklad === item.description) {
                dbPreklad = prelozitText(item.description, 'desc');
            }
            popis = dbPreklad || item.description || '';
        }

        if (popis) {
            const descEl = document.createElement('div');
            descEl.className = 'item-description';
            descEl.innerHTML = parseMarkdown(popis);
            itemEl.appendChild(descEl);
        }

        // Admin edit tlačítko
        if (window.isAdmin) {
            const editBtn = document.createElement('button');
            editBtn.className = 'item-edit-btn';
            editBtn.textContent = window.t('pricingGrid.btn.edit');
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

        // Zjistit aktuální jazyk stránky
        const jazyk = window.ziskejAktualniJazyk ? window.ziskejAktualniJazyk() : 'cs';

        // Naplnit formulář podle aktivního jazyka
        document.getElementById('modal-title').textContent = window.t('pricingGrid.modal.titleEdit');
        document.getElementById('item-id').value = item.id;
        document.getElementById('edit-lang').value = jazyk;

        // Aktualizovat info text podle jazyka
        const infoElement = document.querySelector('.language-info span');
        if (infoElement) {
            infoElement.textContent = window.t('pricingGrid.info.editingLanguage');
        }

        // Načíst správnou jazykovou verzi
        if (jazyk === 'cs') {
            document.getElementById('service-name').value = item.service_name || '';
            document.getElementById('description').value = item.description || '';
            document.getElementById('category').value = item.category || '';
        } else if (jazyk === 'en') {
            document.getElementById('service-name').value = item.service_name_en || item.service_name || '';
            document.getElementById('description').value = item.description_en || item.description || '';
            document.getElementById('category').value = item.category_en || item.category || '';
        } else if (jazyk === 'it') {
            document.getElementById('service-name').value = item.service_name_it || item.service_name || '';
            document.getElementById('description').value = item.description_it || item.description || '';
            document.getElementById('category').value = item.category_it || item.category || '';
        }

        // Společné hodnoty (ceny, aktivní)
        document.getElementById('price-from').value = item.price_from || '';
        document.getElementById('price-to').value = item.price_to || '';
        document.getElementById('price-unit').value = item.price_unit;
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

        // Zjistit aktuální jazyk stránky
        const jazyk = window.ziskejAktualniJazyk ? window.ziskejAktualniJazyk() : 'cs';

        // Vyčistit formulář
        document.getElementById('modal-title').textContent = window.t('pricingGrid.modal.titleAdd');
        document.getElementById('edit-form').reset();
        document.getElementById('item-id').value = '';
        document.getElementById('edit-lang').value = jazyk;
        document.getElementById('is-active').checked = true;

        // Aktualizovat info text podle jazyka
        const infoElement = document.querySelector('.language-info span');
        if (infoElement) {
            infoElement.textContent = window.t('pricingGrid.info.editingLanguage');
        }

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
                alert(itemId ? window.t('pricingGrid.alert.updated') : window.t('pricingGrid.alert.created'));
                zavritModal();
                nactiCenik(); // Reload ceníku
            } else {
                alert(window.t('pricingGrid.alert.error') + ': ' + result.message);
            }
        } catch (error) {
            console.error('[Ceník] Chyba při ukládání:', error);
            alert(window.t('pricingGrid.alert.saveError'));
        }
    };

    // ========================================
    // ADMIN: SMAZAT POLOŽKU
    // ========================================
    window.smazatPolozku = async function() {
        if (!currentEditItem) return;

        if (!confirm(window.t('pricingGrid.alert.deleteConfirm'))) {
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
                alert(window.t('pricingGrid.alert.deleted'));
                zavritModal();
                nactiCenik(); // Reload ceníku
            } else {
                alert(window.t('pricingGrid.alert.error') + ': ' + result.message);
            }
        } catch (error) {
            console.error('[Ceník] Chyba při mazání:', error);
            alert(window.t('pricingGrid.alert.deleteError'));
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

    // ========================================
    // PODPORA PŘEPÍNÁNÍ JAZYKA
    // ========================================
    // Sledovat změny jazyka a aktualizovat cenící
    const originalPrepniJazyk = window.prepniJazyk;
    if (originalPrepniJazyk) {
        window.prepniJazyk = function(jazyk) {
            // Zavolat původní funkci
            originalPrepniJazyk(jazyk);

            // Aktualizovat ceník s novými překlady (pokud jsou data načtena)
            if (pricingData && Object.keys(pricingData).length > 0) {
                zobrazitCenik(pricingData);
            }
        };
    }

})();
