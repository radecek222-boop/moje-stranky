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

        // Přeložené předpony pro ceny
        const odPrefix = {
            cs: 'Od',
            en: 'From',
            it: 'Da'
        };

        if (item.price_from && item.price_to) {
            priceEl.innerHTML = `${item.price_from} - ${item.price_to} ${item.price_unit}`;
        } else if (item.price_from) {
            priceEl.className += ' range';
            priceEl.innerHTML = `${odPrefix[jazyk] || 'Od'} ${item.price_from} ${item.price_unit}`;
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

    // ========================================
    // EXPORT CENÍKU DO PDF (pouze pro adminy)
    // ========================================
    window.exportovatCenikDoPDF = async function() {
        try {
            // Kontrola jestli jsou knihovny načteny
            if (typeof window.jspdf === 'undefined' || typeof html2canvas === 'undefined') {
                alert('Knihovny pro PDF export se ještě načítají. Zkuste to prosím za chvíli.');
                return;
            }

            console.log('[Ceník] Generuji PDF pomocí html2canvas...');

            // Vytvoření dočasného wrapper pro PDF (stejně jako v protokolu)
            const pdfWrapper = document.createElement('div');
            pdfWrapper.id = 'pdf-cenik-wrapper-temp';
            pdfWrapper.style.cssText = `
                position: fixed;
                left: -9999px;
                top: 0;
                width: 800px;
                background: white;
                padding: 20px;
                font-family: 'Poppins', sans-serif;
            `;

            // Hlavička (stejná jako v protokolu)
            const header = document.createElement('div');
            header.style.cssText = `
                text-align: center;
                border-bottom: 3px solid #000;
                padding-bottom: 15px;
                margin-bottom: 30px;
            `;
            header.innerHTML = `
                <div style="font-size: 24px; font-weight: 700; color: #000; margin-bottom: 10px;">
                    WHITE GLOVE SERVICE
                </div>
                <div style="font-size: 12px; color: #333; line-height: 1.6;">
                    Do Dubče 364, Běchovice 190 11 · +420 725 965 826 · reklamace@wgs-service.cz · IČO 09769684
                </div>
            `;
            pdfWrapper.appendChild(header);

            // Nadpis ceníku
            const title = document.createElement('h1');
            title.style.cssText = `
                font-size: 22px;
                font-weight: 700;
                color: #000;
                margin: 20px 0;
                text-align: center;
            `;
            title.textContent = 'Ceník služeb - White Glove Service';
            pdfWrapper.appendChild(title);

            // Poznámka
            const note = document.createElement('div');
            note.style.cssText = `
                background: #fff9f0;
                border-left: 4px solid #ff9900;
                padding: 12px 15px;
                margin: 15px 0 25px 0;
                font-size: 11px;
                color: #333;
                line-height: 1.5;
            `;
            note.innerHTML = `
                <strong>Poznámka:</strong> Všechny ceny jsou uvedeny za práci BEZ materiálu.
                Materiál se účtuje zvlášť. Konečná cena může být ovlivněna složitostí opravy,
                dostupností materiálu a vzdáleností od naší dílny.
            `;
            pdfWrapper.appendChild(note);

            // Kontrola jestli máme data
            if (!pricingData || Object.keys(pricingData).length === 0) {
                alert('Ceník není načten. Zkuste to prosím znovu.');
                return;
            }

            // pricingData je už objekt seskupený podle kategorií
            // { "Čalounění": [...items...], "Mechanika": [...items...] }

            // Vygenerovat kategorie a položky
            Object.keys(pricingData).sort().forEach(kategorie => {
                // Hlavička kategorie
                const categoryHeader = document.createElement('div');
                categoryHeader.style.cssText = `
                    background: #4a4a4a;
                    color: white;
                    padding: 10px 15px;
                    font-size: 14px;
                    font-weight: 600;
                    margin-top: 25px;
                    margin-bottom: 10px;
                    border-radius: 5px 5px 0 0;
                `;
                categoryHeader.textContent = kategorie;
                pdfWrapper.appendChild(categoryHeader);

                // Container pro položky
                const itemsContainer = document.createElement('div');
                itemsContainer.style.cssText = `
                    background: white;
                    border: 1px solid #ddd;
                    border-top: none;
                    border-radius: 0 0 5px 5px;
                    margin-bottom: 5px;
                `;

                pricingData[kategorie].forEach((item, index) => {
                    const itemDiv = document.createElement('div');
                    itemDiv.style.cssText = `
                        padding: 12px 15px;
                        ${index < pricingData[kategorie].length - 1 ? 'border-bottom: 1px solid #eee;' : ''}
                    `;

                    const itemHeader = document.createElement('div');
                    itemHeader.style.cssText = `
                        display: flex;
                        justify-content: space-between;
                        align-items: baseline;
                        margin-bottom: 5px;
                    `;

                    const itemName = document.createElement('div');
                    itemName.style.cssText = `
                        font-size: 14px;
                        font-weight: 600;
                        color: #2a2a2a;
                        flex: 1;
                    `;
                    itemName.textContent = item.service_name;

                    const itemPrice = document.createElement('div');
                    itemPrice.style.cssText = `
                        font-size: 16px;
                        font-weight: 700;
                        color: #4a4a4a;
                        white-space: nowrap;
                        margin-left: 20px;
                    `;

                    // Zobrazit cenu
                    if (item.price_from && item.price_to) {
                        itemPrice.textContent = `${item.price_from} - ${item.price_to} ${item.price_unit}`;
                    } else if (item.price_from) {
                        itemPrice.textContent = `Od ${item.price_from} ${item.price_unit}`;
                    } else if (item.price_to) {
                        itemPrice.textContent = `${item.price_to} ${item.price_unit}`;
                    } else {
                        itemPrice.textContent = 'Dle dohody';
                    }

                    itemHeader.appendChild(itemName);
                    itemHeader.appendChild(itemPrice);
                    itemDiv.appendChild(itemHeader);

                    // Popis
                    if (item.description) {
                        const itemDesc = document.createElement('div');
                        itemDesc.style.cssText = `
                            font-size: 11px;
                            color: #666;
                            line-height: 1.5;
                            margin-top: 5px;
                        `;
                        itemDesc.textContent = item.description;
                        itemDiv.appendChild(itemDesc);
                    }

                    itemsContainer.appendChild(itemDiv);
                });

                pdfWrapper.appendChild(itemsContainer);
            });

            // Kontaktní informace na konci
            const footer = document.createElement('div');
            footer.style.cssText = `
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #ddd;
                text-align: center;
                font-size: 11px;
                color: #333;
            `;
            footer.innerHTML = `
                <div style="font-weight: 600; margin-bottom: 10px;">Máte dotazy k cenám?</div>
                <div>Neváhejte nás kontaktovat pro nezávaznou cenovou nabídku.</div>
                <div style="margin-top: 10px;">
                    <strong>Tel:</strong> +420 725 965 826 ·
                    <strong>Email:</strong> reklamace@wgs-service.cz
                </div>
                <div style="margin-top: 15px; font-size: 10px; color: #999;">
                    www.wgs-service.cz · Generováno ${new Date().toLocaleDateString('cs-CZ')}
                </div>
            `;
            pdfWrapper.appendChild(footer);

            // Přidat do DOM
            document.body.appendChild(pdfWrapper);

            // Počkat na reflow
            await new Promise(resolve => setTimeout(resolve, 200));

            console.log('[Ceník] Renderuji pomocí html2canvas...');

            // Vyrenderovat pomocí html2canvas
            const canvas = await html2canvas(pdfWrapper, {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false,
                imageTimeout: 0,
                allowTaint: true,
                letterRendering: true
            });

            const imgData = canvas.toDataURL('image/jpeg', 0.95);

            // Vytvořit PDF
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');

            const pageWidth = 210;
            const pageHeight = 297;
            const margin = 10;

            const availableWidth = pageWidth - (margin * 2);
            const availableHeight = pageHeight - (margin * 2);

            const canvasRatio = canvas.height / canvas.width;

            let imgWidth = availableWidth;
            let imgHeight = imgWidth * canvasRatio;

            // Pokud je výška větší než stránka, rozdělíme na více stránek
            if (imgHeight > availableHeight) {
                const pages = Math.ceil(imgHeight / availableHeight);

                for (let i = 0; i < pages; i++) {
                    if (i > 0) {
                        pdf.addPage();
                    }

                    const sourceY = i * (canvas.height / pages);
                    const sourceHeight = canvas.height / pages;

                    // Vytvořit dočasný canvas pro daný slice
                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = canvas.width;
                    tempCanvas.height = sourceHeight;
                    const tempCtx = tempCanvas.getContext('2d');
                    tempCtx.drawImage(canvas, 0, sourceY, canvas.width, sourceHeight, 0, 0, canvas.width, sourceHeight);

                    const pageImgData = tempCanvas.toDataURL('image/jpeg', 0.95);
                    const pageHeight = availableHeight;

                    pdf.addImage(pageImgData, 'JPEG', margin, margin, imgWidth, pageHeight);
                }
            } else {
                // Vejde se na jednu stránku
                pdf.addImage(imgData, 'JPEG', margin, margin, imgWidth, imgHeight);
            }

            // Odstranit dočasný wrapper
            document.body.removeChild(pdfWrapper);

            // Stáhnout PDF
            const datum = new Date().toLocaleDateString('cs-CZ').replace(/\./g, '-');
            pdf.save(`WGS-Cenik-${datum}.pdf`);

            console.log('[Ceník] PDF úspěšně vygenerováno a staženo');

        } catch (error) {
            console.error('[Ceník] Chyba při generování PDF:', error);
            alert('Chyba při generování PDF: ' + error.message);
        }
    };

})();
