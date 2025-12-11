<?php

declare(strict_types=1);

/**
 * Plugin SAPO Menu Integration para AzuraCast
 * Compatible con versiones antiguas
 */

return function ($dispatcher) {
    // Registrar carpeta de plantillas del plugin
    $dispatcher->addListener(
        \App\Event\BuildView::class,
        function (\App\Event\BuildView $event) {
            $view = $event->getView();

            // Añadir carpeta de plantillas del plugin
            $view->addFolder('sapo', __DIR__ . '/templates');

            // Registrar una función helper para inyectar el script SAPO
            $view->registerFunction('sapoMenuItem', function() {
                return file_get_contents(__DIR__ . '/templates/sapo-menu-script.html');
            });
        }
    );
};
