<?php

declare(strict_types=1);

/**
 * Plugin SAPO Menu Integration para AzuraCast
 * Compatible con stable branch (agosto 2024)
 */

return function ($dispatcher) {
    $dispatcher->addListener(
        \App\Event\BuildView::class,
        function (\App\Event\BuildView $event) {
            $view = $event->getView();
            $sections = $view->getSections();

            // JavaScript para inyectar el elemento SAPO en el menú lateral principal
            $sapoScript = <<<'JAVASCRIPT'
<script>
(function() {
    'use strict';
    function addSAPOToMenu() {
        if (document.getElementById('sapo-menu-link')) return;

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
