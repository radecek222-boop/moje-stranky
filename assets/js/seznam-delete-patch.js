/**
 * PATCH pro seznam.js - Přidat mazací tlačítko pro adminy
 * Načíst PO seznam.js
 */

(function() {
    console.log('🔧 Mazací tlačítko patch se načítá...');
    
    // Přidat observer pro detailModal
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.id === 'detailModal' && mutation.target.classList.contains('active')) {
                addDeleteButton();
            }
        });
    });
    
    // Sledovat změny na detailModal
    const detailModal = document.getElementById('detailModal');
    if (detailModal) {
        observer.observe(detailModal, {
            attributes: true,
            attributeFilter: ['class']
        });
    }
    
    function addDeleteButton() {
        const currentUser = JSON.parse(localStorage.getItem('currentUser') || '{}');
        
        if (currentUser.role !== 'admin') return;
        
        const detailModal = document.getElementById('detailModal');
        if (!detailModal) return;
        
        // Hledat kontejner s tlačítky
        const buttonContainers = detailModal.querySelectorAll('[style*="gap"], .btns');
        
        for (let container of buttonContainers) {
            // Zkontrolovat jestli už tlačítko neexistuje
            if (container.querySelector('[data-action="deleteReklamace"]')) {
                continue;
            }
            
            // Vytvořit mazací tlačítko
            const deleteBtn = document.createElement('button');
            deleteBtn.innerHTML = '🗑️ Smazat reklamaci';
            deleteBtn.setAttribute('data-action', 'deleteReklamace');
            deleteBtn.style.cssText = `
                background: #ff4444;
                color: white;
                border: none;
                padding: 0.7rem 1.2rem;
                border-radius: 6px;
                font-size: 0.9rem;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.2s;
            `;
            
            deleteBtn.onmouseover = function() { this.style.background = '#cc0000'; };
            deleteBtn.onmouseout = function() { this.style.background = '#ff4444'; };
            
            deleteBtn.onclick = function(e) {
                e.stopPropagation();
                const reklamaceId = window.CURRENT_RECORD ? (window.CURRENT_RECORD.id || window.CURRENT_RECORD.reklamace_id) : null;
                if (reklamaceId && typeof deleteReklamace === 'function') {
                    deleteReklamace(reklamaceId);
                } else {
                    alert('Chyba: Nelze získat ID reklamace');
                }
            };
            
            container.appendChild(deleteBtn);
            console.log('✅ Mazací tlačítko přidáno');
            break;
        }
    }
    
    console.log('✅ Mazací tlačítko patch načten');
})();