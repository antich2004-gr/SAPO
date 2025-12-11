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

        // Selectores específicos para el sidebar principal de AzuraCast
        const selectors = [
            '#sidebar nav ul.nav',
            'aside.main-sidebar nav ul',
            '.main-sidebar .sidebar-menu',
            '#sidebar .sidebar-menu',
            'aside[role="complementary"] nav ul'
        ];
        let menu = null;

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element && element.querySelector('li a[href*="/admin"], li a[href*="/profile"], li a[href*="/dashboard"]')) {
                // Verificar que es realmente el sidebar principal buscando enlaces típicos
                menu = element;
                break;
            }
        }

        if (!menu) return;

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

        const icon = document.createElement('i');
        icon.className = 'fa fa-calendar';
        icon.style.marginRight = '8px';
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
