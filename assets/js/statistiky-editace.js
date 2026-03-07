/**
 * statistiky-editace.js
 * Editace zakázky z pohledu statistik
 * Závisí na: statistiky.js (nactiZakazky, wgsToast, logger)
 */

// ========================================
// EDITACE ZAKÁZKY
// ========================================

/**
 * Otevřít modal pro editaci zakázky
 * Umožňuje změnit: technika, prodejce, zemi
 */
async function otevritEditaciZakazky(zakazkaId, reklamaceId) {
    try {
        // Načíst detaily zakázky
        const response = await fetch(`/api/statistiky_api.php?action=detail_zakazky&id=${zakazkaId}`);
        const result = await response.json();

        if (result.status !== 'success') {
            wgsToast.error('Chyba při načítání zakázky');
            return;
        }

        const zakazka = result.zakazka;

        // Načíst seznamy techniků a prodejců
        const [techniciResp, prodejciResp] = await Promise.all([
            fetch('/api/statistiky_api.php?action=seznam_techniku'),
            fetch('/api/statistiky_api.php?action=seznam_prodejcu')
        ]);

        const techniciData = await techniciResp.json();
        const prodejciData = await prodejciResp.json();

        const technici = techniciData.technici || [];
        const prodejci = prodejciData.prodejci || [];

        // Vytvořit modal
        const modal = document.createElement('div');
        modal.id = 'edit-zakazka-modal';
        modal.className = 'wgs-modal-overlay';
        modal.innerHTML = `
            <div class="wgs-modal-content" style="max-width: 600px;">
                <div class="wgs-modal-header">
                    <h2>Upravit zakázku</h2>
                    <button class="wgs-modal-close" id="close-edit-modal">&times;</button>
                </div>
                <div class="wgs-modal-body">
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <strong style="font-size: 1.1rem;">${reklamaceId}</strong><br>
                        <span style="color: #666; font-size: 0.9rem;">${zakazka.jmeno_zakaznika || ''}</span>
                    </div>

                    <form id="form-edit-zakazka">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Technik:</label>
                            <select id="edit-technik" name="technik" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                                <option value="">-- Nepřiřazeno --</option>
                                ${technici.map(t => `
                                    <option value="${t.id}" ${zakazka.assigned_to == t.id ? 'selected' : ''}>
                                        ${t.name} (${t.user_id})
                                    </option>
                                `).join('')}
                            </select>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Prodejce:</label>
                            <select id="edit-prodejce" name="prodejce" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                                <option value="">-- Nepřiřazeno --</option>
                                ${prodejci.map(p => `
                                    <option value="${p.user_id}" ${zakazka.created_by == p.user_id ? 'selected' : ''}>
                                        ${p.name}
                                    </option>
                                `).join('')}
                            </select>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Země:</label>
                            <select id="edit-zeme" name="zeme" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                                <option value="CZ" ${zakazka.faktura_zeme === 'CZ' ? 'selected' : ''}>Česká republika (CZ)</option>
                                <option value="SK" ${zakazka.faktura_zeme === 'SK' ? 'selected' : ''}>Slovensko (SK)</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Cena celkem (€):</label>
                            <input type="number" id="edit-cena-celkem" name="cena_celkem" min="0" step="0.01"
                                value="${parseFloat(zakazka.cena_celkem || 0).toFixed(2)}"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; box-sizing: border-box;">
                        </div>

                        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                            <button type="button" id="cancel-edit-zakazka" style="padding: 10px 20px; background: #999; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem;">
                                Zrušit
                            </button>
                            <button type="submit" style="padding: 10px 20px; background: #2D5016; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: 600;">
                                Uložit změny
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Event listenery
        document.getElementById('close-edit-modal').onclick = () => modal.remove();
        document.getElementById('cancel-edit-zakazka').onclick = () => modal.remove();

        // Zavření modalu kliknutím mimo
        modal.onclick = (e) => {
            if (e.target === modal) modal.remove();
        };

        // Submit formuláře
        document.getElementById('form-edit-zakazka').onsubmit = async (e) => {
            e.preventDefault();

            const technikId = document.getElementById('edit-technik').value;
            const prodejceId = document.getElementById('edit-prodejce').value;
            const zeme = document.getElementById('edit-zeme').value;
            const cenaCelkem = document.getElementById('edit-cena-celkem').value;

            try {
                const csrfToken = await fetchCsrfToken();

                const formData = new FormData();
                formData.append('action', 'upravit_zakazku');
                formData.append('id', zakazkaId);
                formData.append('assigned_to', technikId);
                formData.append('created_by', prodejceId);
                formData.append('faktura_zeme', zeme);
                formData.append('cena_celkem', cenaCelkem);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('/api/statistiky_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    wgsToast.success('Zakázka úspěšně upravena');
                    modal.remove();
                    // Reload dat
                    nactiZakazky();
                } else {
                    wgsToast.error(result.message || 'Chyba při ukládání');
                }

            } catch (error) {
                console.error('Chyba při ukládání:', error);
                wgsToast.error('Chyba při ukládání změn');
            }
        };

    } catch (error) {
        console.error('Chyba při otevření editace:', error);
        wgsToast.error('Chyba při otevření formuláře');
    }
}

// Export na window pro data-action
window.otevritEditaciZakazky = otevritEditaciZakazky;
