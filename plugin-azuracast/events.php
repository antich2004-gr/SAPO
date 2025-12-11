<?php

/**
 * Plugin SAPO Menu Integration
 * Añade un enlace a SAPO en el menú lateral de AzuraCast
 */

declare(strict_types=1);

return function (\App\EventDispatcher $dispatcher) {

    // Inyectar JavaScript en todas las vistas internas
    $dispatcher->addListener(
        \App\Event\BuildView::class,
        function (\App\Event\BuildView $event) {
            $view = $event->getView();

            // JavaScript para añadir el elemento SAPO al menú
            $sapoScript = <<<'JAVASCRIPT'
<script>
(function() {
    'use strict';

    function addSAPOToMenu() {
        // Verificar si ya existe
        if (document.getElementById('sapo-menu-link')) {
            return;
        }

        // Buscar el menú de navegación
        const selectors = [
            'nav.navbar-nav',
            '.sidebar-menu',
            'nav ul',
            '#sidebar ul',
            'aside nav ul',
            '.app-sidebar ul',
            'nav[role="navigation"] ul'
        ];

        let menu = null;
        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element && element.querySelector('li')) {
                menu = element;
                break;
            }
        }

        if (!menu) {
            return;
        }

        // Obtener un elemento de referencia para copiar el estilo
        const referenceItem = menu.querySelector('li');
        if (!referenceItem) {
            return;
        }

        // Crear el elemento del menú SAPO
        const sapoMenuItem = document.createElement('li');
        sapoMenuItem.className = referenceItem.className;
        sapoMenuItem.id = 'sapo-menu-link';

        // Crear el enlace
        const sapoLink = document.createElement('a');
        sapoLink.href = 'https://sapo.radioslibres.info';
        sapoLink.target = '_blank';
        sapoLink.rel = 'noopener noreferrer';

        // Copiar clases del enlace de referencia
        const refLink = referenceItem.querySelector('a');
        if (refLink) {
            sapoLink.className = refLink.className;
        }

        // Añadir ícono
        const icon = document.createElement('i');
        icon.className = 'fa fa-calendar';
        icon.style.marginRight = '8px';
        sapoLink.appendChild(icon);

        // Añadir texto
        const text = document.createTextNode('SAPO');
        sapoLink.appendChild(text);

        // Ensamblar
        sapoMenuItem.appendChild(sapoLink);
        menu.appendChild(sapoMenuItem);
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSAPOToMenu);
    } else {
        addSAPOToMenu();
    }

    // Reintentos para apps SPA
    setTimeout(addSAPOToMenu, 1000);
    setTimeout(addSAPOToMenu, 3000);
})();
</script>
JAVASCRIPT;

            $view->appendToBody($sapoScript);
        }
    );
};
