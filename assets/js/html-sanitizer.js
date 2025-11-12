/**
 * HTML Sanitizer - Bezpečná práce s innerHTML
 * Ochrana proti XSS útokům
 *
 * Použití:
 *   element.innerHTML = sanitizeHTML(unsafeHTML);
 */

(function(window) {
    'use strict';

    /**
     * Sanitizuje HTML string a odstraní potenciálně nebezpečný obsah
     * @param {string} html - HTML string k sanitizaci
     * @param {object} options - Volitelné nastavení
     * @returns {string} - Bezpečný HTML string
     */
    function sanitizeHTML(html, options = {}) {
        if (!html || typeof html !== 'string') {
            return '';
        }

        // Základní konfigurace
        const config = {
            allowedTags: options.allowedTags || [
                'div', 'span', 'p', 'br', 'strong', 'em', 'b', 'i', 'u',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li',
                'table', 'thead', 'tbody', 'tr', 'td', 'th',
                'a', 'img',
                'code', 'pre',
                'button', 'input', 'select', 'option', 'textarea', 'label', 'form'
            ],
            allowedAttributes: options.allowedAttributes || {
                '*': ['class', 'id', 'style', 'data-*'],
                'a': ['href', 'title', 'target'],
                'img': ['src', 'alt', 'title', 'width', 'height'],
                'button': ['type', 'onclick', 'disabled'],
                'input': ['type', 'name', 'value', 'placeholder', 'required', 'disabled'],
                'select': ['name', 'required', 'disabled'],
                'option': ['value', 'selected'],
                'textarea': ['name', 'rows', 'cols', 'placeholder', 'required', 'disabled'],
                'label': ['for'],
                'form': ['action', 'method']
            },
            allowedStyles: options.allowedStyles || [
                'color', 'background', 'background-color',
                'padding', 'margin',
                'width', 'height', 'max-width', 'max-height',
                'border', 'border-radius',
                'font-size', 'font-weight', 'font-family',
                'text-align', 'text-decoration',
                'display', 'flex', 'grid',
                'position', 'top', 'left', 'right', 'bottom'
            ]
        };

        // Vytvoř dočasný element pro parsování
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;

        // Projdi všechny elementy a vyčisti je
        cleanElement(tempDiv, config);

        return tempDiv.innerHTML;
    }

    /**
     * Rekurzivně vyčistí element a jeho děti
     */
    function cleanElement(element, config) {
        const nodesToRemove = [];

        // Projdi všechny child nodes
        Array.from(element.childNodes).forEach(node => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                const tagName = node.tagName.toLowerCase();

                // Pokud tag není povolen, odstraň ho (ale ponechej obsah)
                if (!config.allowedTags.includes(tagName)) {
                    nodesToRemove.push(node);
                    return;
                }

                // Vyčisti atributy
                cleanAttributes(node, config);

                // Rekurzivně vyčisti děti
                cleanElement(node, config);
            } else if (node.nodeType === Node.TEXT_NODE) {
                // Text nodes jsou OK
            } else {
                // Odstraň ostatní typy nodes (komentáře, atd.)
                nodesToRemove.push(node);
            }
        });

        // Odstraň nebezpečné nodes
        nodesToRemove.forEach(node => {
            // Přesuň děti před smazáním
            while (node.firstChild) {
                element.insertBefore(node.firstChild, node);
            }
            element.removeChild(node);
        });
    }

    /**
     * Vyčistí atributy elementu
     */
    function cleanAttributes(element, config) {
        const tagName = element.tagName.toLowerCase();
        const allowedForAll = config.allowedAttributes['*'] || [];
        const allowedForTag = config.allowedAttributes[tagName] || [];
        const allowedAttributes = [...allowedForAll, ...allowedForTag];

        const attributesToRemove = [];

        // Projdi všechny atributy
        Array.from(element.attributes).forEach(attr => {
            const attrName = attr.name.toLowerCase();
            let isAllowed = false;

            // Kontrola, jestli je atribut povolen
            for (const allowed of allowedAttributes) {
                if (allowed === attrName) {
                    isAllowed = true;
                    break;
                }
                // Podpor wildcard (např. data-*)
                if (allowed.endsWith('*')) {
                    const prefix = allowed.slice(0, -1);
                    if (attrName.startsWith(prefix)) {
                        isAllowed = true;
                        break;
                    }
                }
            }

            if (!isAllowed) {
                attributesToRemove.push(attrName);
                return;
            }

            // Speciální kontroly pro nebezpečné atributy
            if (attrName === 'href' || attrName === 'src') {
                const value = attr.value.toLowerCase().trim();
                // Blokuj javascript: a data: URL (kromě data:image)
                if (value.startsWith('javascript:') ||
                    (value.startsWith('data:') && !value.startsWith('data:image/'))) {
                    attributesToRemove.push(attrName);
                }
            }

            // Vyčisti style atributy
            if (attrName === 'style') {
                element.setAttribute('style', sanitizeStyle(attr.value, config));
            }

            // Kontrola onclick a dalších event handlers
            if (attrName.startsWith('on')) {
                // Ponech onclick pouze pokud je explicitně povolen
                if (!allowedAttributes.includes(attrName)) {
                    attributesToRemove.push(attrName);
                }
            }
        });

        // Odstraň nebezpečné atributy
        attributesToRemove.forEach(attrName => {
            element.removeAttribute(attrName);
        });
    }

    /**
     * Sanitizuje inline style
     */
    function sanitizeStyle(styleString, config) {
        if (!styleString) return '';

        const styles = styleString.split(';')
            .map(s => s.trim())
            .filter(s => s.length > 0);

        const cleanStyles = [];

        styles.forEach(style => {
            const [property, value] = style.split(':').map(s => s.trim());

            if (!property || !value) return;

            // Kontrola, jestli je property povolena
            const propLower = property.toLowerCase();
            if (config.allowedStyles.includes(propLower)) {
                // Blokuj nebezpečné hodnoty (javascript:, expression, atd.)
                const valueLower = value.toLowerCase();
                if (!valueLower.includes('javascript:') &&
                    !valueLower.includes('expression(') &&
                    !valueLower.includes('import') &&
                    !valueLower.includes('url(javascript:')) {
                    cleanStyles.push(`${property}: ${value}`);
                }
            }
        });

        return cleanStyles.join('; ');
    }

    /**
     * Bezpečně nastaví innerHTML elementu
     * @param {HTMLElement} element - Cílový element
     * @param {string} html - HTML k nastavení
     * @param {object} options - Volitelné nastavení
     */
    function setInnerHTML(element, html, options = {}) {
        if (!element || !(element instanceof HTMLElement)) {
            console.error('setInnerHTML: První parametr musí být HTMLElement');
            return;
        }

        element.innerHTML = sanitizeHTML(html, options);
    }

    /**
     * Pro případ, kdy potřebujete jen text bez HTML
     * @param {string} text - Text k escapování
     * @returns {string} - Escapovaný text
     */
    function escapeHTML(text) {
        if (!text || typeof text !== 'string') {
            return '';
        }

        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Export do global scope
    window.sanitizeHTML = sanitizeHTML;
    window.setInnerHTML = setInnerHTML;
    window.escapeHTML = escapeHTML;

})(window);
