# Integración de SAPO en el Menú de AzuraCast

Este documento explica cómo añadir un enlace a SAPO en el menú lateral de AzuraCast.

## Opción 1: JavaScript Personalizado (Recomendado)

Esta es la solución más sencilla y no requiere modificar el código fuente de AzuraCast.

### Pasos:

1. **Acceder al panel de administración** de AzuraCast
2. **Navegar a** `/admin/branding` (Custom Branding)
3. **Buscar el editor** "Custom JS for Internal Pages"
4. **Añadir el siguiente código JavaScript**:

```javascript
// Añadir enlace a SAPO en el menú de AzuraCast
(function() {
    'use strict';

    // Función para añadir el elemento del menú
    function addSAPOMenuItem() {
        // Buscar el menú lateral (nav#sidebar o el contenedor del menú)
        const sidebar = document.querySelector('#sidebar nav, nav[role="navigation"], .sidebar-menu, nav.navbar-nav');

        if (!sidebar) {
            console.warn('No se encontró el menú lateral de AzuraCast');
            return;
        }

        // Verificar si ya existe el elemento SAPO
        if (document.getElementById('sapo-menu-item')) {
            return; // Ya existe, no añadir duplicado
        }

        // Buscar un elemento de menú existente para copiar su estructura
        const existingMenuItem = sidebar.querySelector('li, a.nav-link, .nav-item');

        if (!existingMenuItem) {
            console.warn('No se encontraron elementos de menú para copiar la estructura');
            return;
        }

        // Crear el nuevo elemento de menú SAPO
        const sapoMenuItem = document.createElement('li');
        sapoMenuItem.id = 'sapo-menu-item';
        sapoMenuItem.className = existingMenuItem.className; // Copiar clases del elemento existente

        // Crear el enlace
        const sapoLink = document.createElement('a');
        sapoLink.href = 'https://sapo.radioslibres.info';
        sapoLink.target = '_blank';
        sapoLink.rel = 'noopener noreferrer';
        sapoLink.className = existingMenuItem.querySelector('a') ?
            existingMenuItem.querySelector('a').className : 'nav-link';

        // Añadir ícono (usando Font Awesome o similar)
        const icon = document.createElement('i');
        icon.className = 'fas fa-calendar-alt'; // Ícono de calendario para SAPO
        sapoLink.appendChild(icon);

        // Añadir texto
        const text = document.createTextNode(' SAPO');
        sapoLink.appendChild(text);

        // Ensamblar el elemento
        sapoMenuItem.appendChild(sapoLink);

        // Insertar el elemento en el menú
        // Opción 1: Al final del menú
        sidebar.appendChild(sapoMenuItem);

        console.log('Elemento SAPO añadido al menú de AzuraCast');
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSAPOMenuItem);
    } else {
        addSAPOMenuItem();
    }

    // También ejecutar después de un pequeño retraso para apps SPA
    setTimeout(addSAPOMenuItem, 1000);
    setTimeout(addSAPOMenuItem, 3000);
})();
```

### Personalización del CSS (Opcional)

Si necesitas ajustar el estilo del elemento SAPO, añade en **"Custom CSS for Internal Pages"**:

```css
/* Estilo personalizado para el elemento SAPO en el menú */
#sapo-menu-item {
    /* Personaliza aquí según sea necesario */
}

#sapo-menu-item a {
    color: #fff;
    text-decoration: none;
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
}

#sapo-menu-item a:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

#sapo-menu-item i {
    margin-right: 0.5rem;
    width: 1.5rem;
    text-align: center;
}
```

## Opción 2: Plugin de AzuraCast

Para una integración más profunda y mantenible a largo plazo, puedes crear un plugin de AzuraCast.

### Estructura del plugin:

```
/plugins/sapo-integration/
├── plugin.json
├── events.php
├── src/
│   └── EventHandler/
│       └── AddSAPOMenuItem.php
```

### Archivo `plugin.json`:

```json
{
    "name": "SAPO Integration",
    "description": "Añade un enlace a SAPO en el menú de AzuraCast",
    "author": "Tu Nombre",
    "version": "1.0.0"
}
```

### Archivo `events.php`:

```php
<?php
/**
 * Plugin para añadir SAPO al menú de AzuraCast
 */

declare(strict_types=1);

use App\Event\BuildView;

return function (\App\EventDispatcher $dispatcher) {
    // Añadir JavaScript personalizado a la vista
    $dispatcher->addListener(BuildView::class, function (BuildView $event) {
        $view = $event->getView();

        // Añadir JavaScript inline para inyectar el elemento del menú
        $sapoScript = <<<'JS'
<script>
(function() {
    'use strict';
    function addSAPOMenuItem() {
        const sidebar = document.querySelector('#sidebar nav, nav[role="navigation"]');
        if (!sidebar || document.getElementById('sapo-menu-item')) return;

        const sapoMenuItem = document.createElement('li');
        sapoMenuItem.id = 'sapo-menu-item';
        sapoMenuItem.className = 'nav-item';

        const sapoLink = document.createElement('a');
        sapoLink.href = 'https://sapo.radioslibres.info';
        sapoLink.target = '_blank';
        sapoLink.className = 'nav-link';
        sapoLink.innerHTML = '<i class="fas fa-calendar-alt"></i> SAPO';

        sapoMenuItem.appendChild(sapoLink);
        sidebar.appendChild(sapoMenuItem);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSAPOMenuItem);
    } else {
        addSAPOMenuItem();
    }
})();
</script>
JS;

        $view->appendToBody($sapoScript);
    });
};
```

### Instalación del plugin:

1. Crea la carpeta `/plugins/sapo-integration/` en tu instalación de AzuraCast
2. Copia los archivos del plugin
3. Reinicia AzuraCast para que detecte el plugin:
   ```bash
   docker-compose restart
   ```

## Opción 3: Modificación Directa del Código (No Recomendado)

Si tienes acceso al código fuente y deseas una integración permanente, puedes modificar directamente los componentes Vue de AzuraCast. Sin embargo, **esto no se recomienda** porque:

- Perderás los cambios al actualizar AzuraCast
- Requiere recompilar los assets frontend
- Es más complejo de mantener

## Recomendación

**Para la mayoría de usuarios**: Utiliza la **Opción 1 (JavaScript Personalizado)** ya que:
- ✅ Es fácil de implementar
- ✅ No requiere modificar código fuente
- ✅ Se mantiene después de actualizaciones
- ✅ Se puede activar/desactivar fácilmente
- ✅ No requiere reiniciar servicios

## Recursos

- [Documentación de Custom Branding en AzuraCast](https://www.azuracast.com/docs/administration/customization/)
- [Sistema de Plugins de AzuraCast](https://www.azuracast.com/docs/developers/plugins/)
- [Repositorio de ejemplo de plugin](https://github.com/AzuraCast/example-plugin)

## Soporte

Si encuentras problemas con la integración, consulta:
- La comunidad de Discord de AzuraCast
- La documentación oficial de AzuraCast
- Este repositorio de SAPO

---

**Nota**: Este código JavaScript busca automáticamente el menú lateral y copia el estilo de los elementos existentes para mantener consistencia visual con el tema de AzuraCast.
