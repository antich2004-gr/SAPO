/**
 * Script para añadir enlace a SAPO en el menú lateral de AzuraCast
 *
 * Instrucciones:
 * 1. Acceder a /admin/branding en AzuraCast
 * 2. Copiar este código en "Custom JS for Internal Pages"
 * 3. Guardar cambios
 *
 * @version 1.0.0
 * @author SAPO - Sistema de Automatización de Parrilla de Oprogramación
 */

(function() {
    'use strict';

    // Configuración
    const CONFIG = {
        url: 'https://sapo.radioslibres.info',
        label: 'SAPO',
        icon: 'fas fa-calendar-alt', // Ícono de Font Awesome
        menuItemId: 'sapo-menu-item',
        retryDelays: [1000, 3000, 5000], // Reintentos para apps SPA
        debug: false // Cambiar a true para ver mensajes de depuración
    };

    /**
     * Función de logging condicional
     */
    function log(message, type = 'log') {
        if (CONFIG.debug) {
            console[type]('[SAPO Menu]', message);
        }
    }

    /**
     * Busca el contenedor del menú lateral
     * @returns {HTMLElement|null}
     */
    function findSidebarContainer() {
        const selectors = [
            '#sidebar nav',
            '#sidebar ul',
            'nav#sidebar',
            'nav[role="navigation"]',
            '.sidebar-menu',
            'nav.navbar-nav',
            'aside nav',
            '.app-sidebar nav',
            '.main-sidebar nav'
        ];

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element) {
                log(`Menú lateral encontrado con selector: ${selector}`);
                return element;
            }
        }

        log('No se encontró el contenedor del menú lateral', 'warn');
        return null;
    }

    /**
     * Busca un elemento de menú existente para copiar su estructura
     * @param {HTMLElement} sidebar
     * @returns {HTMLElement|null}
     */
    function findExistingMenuItem(sidebar) {
        const selectors = [
            'li.nav-item',
            'li',
            '.nav-item',
            'a.nav-link'
        ];

        for (const selector of selectors) {
            const element = sidebar.querySelector(selector);
            if (element) {
                log(`Elemento de menú de referencia encontrado: ${selector}`);
                return element;
            }
        }

        return null;
    }

    /**
     * Crea el elemento HTML del menú SAPO
     * @param {HTMLElement} referenceElement
     * @returns {HTMLElement}
     */
    function createSAPOMenuItem(referenceElement) {
        // Determinar si el elemento de referencia es un <li> o un <a>
        const isListItem = referenceElement.tagName === 'LI';
        const referenceLink = isListItem ?
            referenceElement.querySelector('a') :
            referenceElement;

        // Crear el contenedor <li>
        const menuItem = document.createElement('li');
        menuItem.id = CONFIG.menuItemId;

        // Copiar clases del elemento de referencia
        if (isListItem) {
            menuItem.className = referenceElement.className;
        } else {
            menuItem.className = 'nav-item';
        }

        // Crear el enlace
        const link = document.createElement('a');
        link.href = CONFIG.url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';

        // Copiar clases del enlace de referencia
        if (referenceLink) {
            link.className = referenceLink.className;
        } else {
            link.className = 'nav-link';
        }

        // Añadir ícono
        if (CONFIG.icon) {
            const icon = document.createElement('i');
            icon.className = CONFIG.icon;
            link.appendChild(icon);

            // Añadir espacio entre ícono y texto
            link.appendChild(document.createTextNode(' '));
        }

        // Añadir texto
        const textSpan = document.createElement('span');
        textSpan.textContent = CONFIG.label;
        link.appendChild(textSpan);

        // Ensamblar
        menuItem.appendChild(link);

        log('Elemento SAPO creado correctamente');
        return menuItem;
    }

    /**
     * Añade el elemento SAPO al menú
     */
    function addSAPOMenuItem() {
        try {
            // Verificar si ya existe
            if (document.getElementById(CONFIG.menuItemId)) {
                log('El elemento SAPO ya existe en el menú');
                return;
            }

            // Buscar el contenedor del menú
            const sidebar = findSidebarContainer();
            if (!sidebar) {
                return;
            }

            // Buscar un elemento de referencia
            const referenceElement = findExistingMenuItem(sidebar);
            if (!referenceElement) {
                log('No se encontró un elemento de menú de referencia', 'warn');
                return;
            }

            // Crear el elemento SAPO
            const sapoMenuItem = createSAPOMenuItem(referenceElement);

            // Insertar en el menú
            // Intentar añadir al final de la lista
            const menuList = sidebar.tagName === 'UL' ? sidebar : sidebar.querySelector('ul');
            if (menuList) {
                menuList.appendChild(sapoMenuItem);
            } else {
                sidebar.appendChild(sapoMenuItem);
            }

            log('✓ Elemento SAPO añadido exitosamente al menú', 'info');

        } catch (error) {
            log(`Error al añadir elemento SAPO: ${error.message}`, 'error');
        }
    }

    /**
     * Inicializar el script
     */
    function init() {
        log('Inicializando script de integración SAPO...');

        // Ejecutar inmediatamente si el DOM ya está listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addSAPOMenuItem);
        } else {
            addSAPOMenuItem();
        }

        // Reintentos para aplicaciones SPA (como Vue.js en AzuraCast)
        CONFIG.retryDelays.forEach(delay => {
            setTimeout(addSAPOMenuItem, delay);
        });

        // Observar cambios en el DOM para aplicaciones SPA dinámicas
        if (window.MutationObserver) {
            let retryCount = 0;
            const maxRetries = 10;

            const observer = new MutationObserver(() => {
                if (retryCount < maxRetries && !document.getElementById(CONFIG.menuItemId)) {
                    addSAPOMenuItem();
                    retryCount++;
                } else if (retryCount >= maxRetries) {
                    observer.disconnect();
                    log('Límite de reintentos alcanzado, deteniendo observador');
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            // Desconectar después de 30 segundos
            setTimeout(() => {
                observer.disconnect();
                log('Observador de mutaciones desconectado después de 30s');
            }, 30000);
        }
    }

    // Iniciar
    init();

})();
