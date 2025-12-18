<?php

declare(strict_types=1);

/**
 * Plugin SAPO Menu Integration para AzuraCast
 * Sistema de permisos integrado - Solo emisoras autorizadas verán el enlace SAPO
 * Compatible con stable branch (agosto 2024)
 */

return function ($dispatcher) {

    // PASO 1: Registrar permiso personalizado para SAPO
    $dispatcher->addListener(
        \App\Event\BuildPermissions::class,
        function (\App\Event\BuildPermissions $event) {
            // Permiso por emisora para acceder a SAPO
            $event->addPermission(
                'station',
                'sapo:access',
                'Acceso a SAPO (Sistema de Automatización de Podcasts)'
            );
        },
        -5  // Prioridad: ejecutar antes que listeners por defecto
    );

    // PASO 2: Inyectar enlace SAPO solo si el usuario tiene permisos
    $dispatcher->addListener(
        \App\Event\BuildView::class,
        function (\App\Event\BuildView $event) {
            $view = $event->getView();
            $sections = $view->getSections();

            // JavaScript con verificación de permisos para SAPO
            $sapoScript = <<<'JAVASCRIPT'
<script>
(function() {
    'use strict';

    // VERIFICAR PERMISOS DEL USUARIO
    function hasPermission(permission) {
        // Intentar obtener permisos del usuario desde diferentes fuentes
        // que AzuraCast expone en el frontend

        // Método 1: Desde el objeto global AzuraCast
        if (window.azuracast && window.azuracast.permissions) {
            const perms = window.azuracast.permissions;
            if (perms.station && typeof perms.station === 'object') {
                for (let stationId in perms.station) {
                    if (perms.station[stationId].includes(permission)) {
                        return true;
                    }
                }
            }
        }

        // Método 2: Desde meta tags inyectados en la página
        const permMeta = document.querySelector('meta[name="user-permissions"]');
        if (permMeta) {
            const perms = JSON.parse(permMeta.getAttribute('content') || '{}');
            if (perms.station) {
                for (let stationId in perms.station) {
                    if (perms.station[stationId].includes(permission)) {
                        return true;
                    }
                }
            }
        }

        // Método 3: Si es admin global, tiene todos los permisos
        const isAdmin = document.body.classList.contains('admin') ||
                       (window.azuracast && window.azuracast.isAdmin);

        return isAdmin;
    }

    function addSAPOToMenu() {
        if (document.getElementById('sapo-menu-link')) return;

        // VERIFICAR PERMISO SAPO:ACCESS
        if (!hasPermission('sapo:access')) {
            console.log('[SAPO Plugin] Usuario no tiene permiso sapo:access');
            return;
        }

        // Buscar específicamente el sidebar principal
        let sidebar = document.querySelector('aside.main-sidebar') ||
                     document.querySelector('#sidebar') ||
                     document.querySelector('aside[role="complementary"]');

        if (!sidebar) return;

        // Buscar el menú de navegación DENTRO del sidebar
        let menu = sidebar.querySelector('nav ul') ||
                  sidebar.querySelector('.sidebar-menu') ||
                  sidebar.querySelector('ul.nav');

        if (!menu) return;

        // Verificación adicional: el menú debe tener elementos típicos del sidebar
        const hasAdminLinks = menu.querySelector('li a[href*="/admin"]') ||
                             menu.querySelector('li a[href*="/profile"]') ||
                             menu.querySelector('li a[href*="/stations"]');

        if (!hasAdminLinks) return;

        // Verificación adicional: NO debe ser una lista numerada
        if (menu.tagName === 'OL' || menu.closest('ol')) return;

        const referenceItem = menu.querySelector('li');
        if (!referenceItem) return;

        const sapoMenuItem = document.createElement('li');
        sapoMenuItem.className = referenceItem.className;
        sapoMenuItem.id = 'sapo-menu-link';

        const sapoLink = document.createElement('a');
        sapoLink.href = 'https://sapo.radioslibres.info';
        sapoLink.target = '_blank';
        sapoLink.rel = 'noopener noreferrer';

        const refLink = referenceItem.querySelector('a');
        if (refLink) sapoLink.className = refLink.className;

        // Añadir emoji del sapo
        const icon = document.createElement('span');
        icon.textContent = '🐸';
        icon.style.marginRight = '8px';
        icon.style.fontSize = '1.2em';
        sapoLink.appendChild(icon);
        sapoLink.appendChild(document.createTextNode('SAPO'));

        sapoMenuItem.appendChild(sapoLink);
        menu.appendChild(sapoMenuItem);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSAPOToMenu);
    } else {
        addSAPOToMenu();
    }

    setTimeout(addSAPOToMenu, 1000);
    setTimeout(addSAPOToMenu, 3000);
})();
</script>
JAVASCRIPT;

            // Intentar añadir a diferentes secciones posibles
            try {
                $sections->append('bodyjs', $sapoScript);
            } catch (\Exception $e) {
                try {
                    $sections->append('scripts', $sapoScript);
                } catch (\Exception $e2) {
                    // Última opción: crear una nueva sección
                    $sections->set('sapo_script', $sapoScript);
                }
            }
        }
    );
};
