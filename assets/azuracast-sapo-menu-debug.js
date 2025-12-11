/**
 * Versión DEBUG para diagnosticar problemas
 * Copia este código en Custom JS for Internal Pages
 */

(function() {
    'use strict';

    console.log('🟢 [SAPO] Script iniciado');
    console.log('🟢 [SAPO] URL actual:', window.location.href);
    console.log('🟢 [SAPO] DOM Ready State:', document.readyState);

    function addSAPOMenuItem() {
        console.log('🔵 [SAPO] Intentando añadir elemento...');

        // Verificar si ya existe
        if (document.getElementById('sapo-menu-item')) {
            console.log('⚠️ [SAPO] El elemento ya existe');
            return;
        }

        // Lista de selectores posibles para el sidebar
        const sidebarSelectors = [
            '#sidebar',
            'nav#sidebar',
            '[id*="sidebar"]',
            'aside',
            'nav.sidebar',
            '.sidebar',
            'nav[role="navigation"]',
            '.app-sidebar',
            '.main-sidebar',
            'nav.navbar-nav',
            '.sidebar-menu',
            '.nav-menu'
        ];

        console.log('🔍 [SAPO] Buscando sidebar con', sidebarSelectors.length, 'selectores...');

        let sidebar = null;
        for (const selector of sidebarSelectors) {
            const element = document.querySelector(selector);
            if (element) {
                sidebar = element;
                console.log('✅ [SAPO] Sidebar encontrado con:', selector);
                console.log('📋 [SAPO] Elemento sidebar:', element);
                break;
            }
        }

        if (!sidebar) {
            console.error('❌ [SAPO] No se encontró el sidebar');
            console.log('📋 [SAPO] Mostrando todos los elementos <nav>:');
            document.querySelectorAll('nav').forEach((nav, i) => {
                console.log(`  Nav ${i}:`, nav.className, nav.id);
            });
            console.log('📋 [SAPO] Mostrando todos los elementos <aside>:');
            document.querySelectorAll('aside').forEach((aside, i) => {
                console.log(`  Aside ${i}:`, aside.className, aside.id);
            });
            return;
        }

        // Buscar lista de menú dentro del sidebar
        let menuList = sidebar.querySelector('ul');
        if (!menuList && sidebar.tagName === 'UL') {
            menuList = sidebar;
        }

        console.log('📋 [SAPO] Lista de menú:', menuList);

        if (!menuList) {
            console.error('❌ [SAPO] No se encontró lista <ul> en el sidebar');
            return;
        }

        // Buscar elemento de referencia
        const referenceItem = menuList.querySelector('li') || menuList.querySelector('a');

        if (!referenceItem) {
            console.error('❌ [SAPO] No se encontró elemento de menú de referencia');
            return;
        }

        console.log('📋 [SAPO] Elemento de referencia:', referenceItem);
        console.log('📋 [SAPO] Clases del elemento:', referenceItem.className);

        // Crear el elemento SAPO
        const sapoItem = document.createElement('li');
        sapoItem.id = 'sapo-menu-item';
        sapoItem.className = referenceItem.className;
        sapoItem.style.backgroundColor = '#ff0000'; // Rojo para verlo fácilmente

        const sapoLink = document.createElement('a');
        sapoLink.href = 'https://sapo.radioslibres.info';
        sapoLink.target = '_blank';
        sapoLink.textContent = '🔴 SAPO (TEST)';

        // Copiar clases del enlace de referencia si existe
        const refLink = referenceItem.querySelector('a');
        if (refLink) {
            sapoLink.className = refLink.className;
        }

        sapoItem.appendChild(sapoLink);
        menuList.appendChild(sapoItem);

        console.log('✅ [SAPO] Elemento añadido correctamente');
        console.log('📋 [SAPO] Elemento creado:', sapoItem);
    }

    // Ejecutar en diferentes momentos
    console.log('🔵 [SAPO] Configurando listeners...');

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🔵 [SAPO] DOMContentLoaded disparado');
            addSAPOMenuItem();
        });
    } else {
        console.log('🔵 [SAPO] DOM ya está listo');
        addSAPOMenuItem();
    }

    // Reintentos
    [500, 1000, 2000, 3000, 5000].forEach(delay => {
        setTimeout(() => {
            console.log(`🔵 [SAPO] Reintento después de ${delay}ms`);
            addSAPOMenuItem();
        }, delay);
    });

    console.log('🟢 [SAPO] Script configurado completamente');
})();
