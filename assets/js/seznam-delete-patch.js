/**
 * PATCH pro seznam.js - Přidat mazací tlačítko pro adminy
 * Načíst PO seznam.js
 */

(function() {
    console.log('🔧 Mazací tlačítko patch se načítá...');

    const overlay = document.getElementById('detailOverlay');
    if (!overlay) {
        console.warn('detailOverlay nenalezen, patch se nepoužije.');
        return;
    }

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'attributes' && overlay.classList.contains('active')) {
                addDeleteButton();
            }
        }
    });

    observer.observe(overlay, { attributes: true, attributeFilter: ['class'] });

    function addDeleteButton() {
        const currentUser = window.CURRENT_USER || {};
        const isAdmin = Boolean(currentUser.is_admin || currentUser.role === 'admin');
        if (!isAdmin) {
            return;
        }

        if (!window.CURRENT_RECORD) {
            return;
        }

        const modalBody = overlay.querySelector('.modal-body');
        if (!modalBody) {
            return;
        }

        const primaryActions = modalBody.querySelector('.modal-actions') || modalBody.querySelector('div');
        if (!primaryActions) {
            return;
        }

        if (primaryActions.querySelector('[data-action="deleteReklamace"]')) {
            return;
        }

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.setAttribute('data-action', 'deleteReklamace');
        deleteBtn.textContent = '🗑️ Smazat reklamaci';
        deleteBtn.style.background = '#ff4444';
        deleteBtn.style.color = '#fff';
        deleteBtn.style.border = 'none';
        deleteBtn.style.padding = '0.9rem 1.2rem';
        deleteBtn.style.borderRadius = '6px';
        deleteBtn.style.fontSize = '0.95rem';
        deleteBtn.style.fontWeight = '600';
        deleteBtn.style.cursor = 'pointer';

        deleteBtn.addEventListener('mouseenter', () => {
            deleteBtn.style.background = '#cc0000';
        });
        deleteBtn.addEventListener('mouseleave', () => {
            deleteBtn.style.background = '#ff4444';
        });

        deleteBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            const reklamaceId = window.CURRENT_RECORD.id || window.CURRENT_RECORD.reklamace_id;
            if (!reklamaceId) {
                alert('Chyba: Nelze získat ID reklamace');
                return;
            }
            if (typeof window.deleteReklamace === 'function') {
                window.deleteReklamace(reklamaceId);
            } else {
                alert('Mazání není dostupné v této verzi aplikace.');
            }
        });

        if (primaryActions.classList.contains('modal-actions')) {
            primaryActions.appendChild(deleteBtn);
        } else {
            primaryActions.insertAdjacentElement('beforeend', deleteBtn);
        }

        console.log('✅ Mazací tlačítko přidáno');
    }

    console.log('✅ Mazací tlačítko patch načten');
})();

