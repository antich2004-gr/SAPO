# ✅ Plugin SAPO Funcionando - Solución Final

## 🎉 Estado: FUNCIONANDO

El plugin de integración SAPO está funcionando correctamente en AzuraCast stable branch.

---

## 📋 Configuración Exitosa

### Versión de AzuraCast
- **Branch**: `stable`
- **Commit**: `93832174ac9cc1fbab85020f1954f6c09155c41a`
- **Fecha**: 2024-08-29

### Solución Utilizada
- **Método**: Plugin con `GlobalSections->append()`
- **Evento**: `\App\Event\BuildView`
- **Variable de entorno**: `AZURACAST_PLUGIN_MODE=true`

---

## 🚀 Instalación Completa

### Paso 1: Configurar docker-compose.override.yml

```yaml
version: '2.2'

services:
  web:
    environment:
      AZURACAST_PLUGIN_MODE: 'true'
    volumes:
      - /var/azuracast/plugins-custom:/var/azuracast/www/plugins
```

### Paso 2: Crear el plugin

```bash
mkdir -p /var/azuracast/plugins-custom/sapo-menu-integration
```

### Paso 3: Crear plugin.json

```json
{
    "name": "SAPO Menu Integration",
    "description": "Añade un enlace a SAPO en el menú lateral de AzuraCast",
    "author": "Radios Libres",
    "version": "1.0.0",
    "url": "https://sapo.radioslibres.info"
}
```

### Paso 4: Crear events.php

```php
<?php

declare(strict_types=1);

return function ($dispatcher) {
    $dispatcher->addListener(
        \App\Event\BuildView::class,
        function (\App\Event\BuildView $event) {
            $view = $event->getView();
            $sections = $view->getSections();

            $sapoScript = <<<'JAVASCRIPT'
<script>
(function() {
    'use strict';
    function addSAPOToMenu() {
        if (document.getElementById('sapo-menu-link')) return;

        const selectors = ['nav.navbar-nav', '.sidebar-menu', 'nav ul', '#sidebar ul', 'aside nav ul'];
        let menu = null;

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element && element.querySelector('li')) {
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

        // Icono verde
        const icon = document.createElement('i');
        icon.className = 'fa fa-calendar-check';
        icon.style.marginRight = '8px';
        icon.style.color = '#4CAF50';
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

            try {
                $sections->append('bodyjs', $sapoScript);
            } catch (\Exception $e) {
                try {
                    $sections->append('scripts', $sapoScript);
                } catch (\Exception $e2) {
                    $sections->set('sapo_script', $sapoScript);
                }
            }
        }
    );
};
```

### Paso 5: Ajustar permisos

```bash
cd /var/azuracast/plugins-custom/sapo-menu-integration
chown -R root:root .
chmod -R 755 .
```

### Paso 6: Reiniciar Docker

```bash
cd /var/azuracast
docker-compose down
docker-compose up -d
```

---

## ✅ Verificación

### Ver que el plugin está activo
```bash
docker-compose exec web env | grep PLUGIN
# Debe mostrar: AZURACAST_PLUGIN_MODE=true
```

### Ver que el plugin está montado
```bash
docker-compose exec web ls -la /var/azuracast/www/plugins/
# Debe mostrar: sapo-menu-integration
```

### Ver logs
```bash
docker-compose logs web | tail -100 | grep -i "fatal\|error"
# No debe haber errores relacionados con el plugin
```

---

## 🎨 Personalización

### Cambiar el icono

Edita el archivo `events.php` y cambia esta línea:
```javascript
icon.className = 'fa fa-calendar-check';
```

Opciones disponibles:
- `fa-calendar-check` - Calendario con check ✓ (actual)
- `fa-clipboard-list` - Lista 📋
- `fa-leaf` - Hoja 🍃
- `fa-tasks` - Tareas
- `fa-calendar` - Calendario simple
- `fa-bullseye` - Objetivo 🎯

### Cambiar el color del icono

```javascript
icon.style.color = '#4CAF50'; // Verde actual
```

Otros colores:
- `#2196F3` - Azul
- `#FF9800` - Naranja
- `#9C27B0` - Morado
- `#F44336` - Rojo

---

## 🔧 Troubleshooting

### El plugin no aparece
1. Verificar que `AZURACAST_PLUGIN_MODE=true`
2. Verificar que el volumen está montado correctamente
3. Ver logs: `docker-compose logs web`

### Errores al reiniciar
1. Verificar sintaxis de `events.php`
2. Verificar permisos de archivos
3. Ver logs detallados

---

## 📚 Referencias

- [AzuraCast Example Plugin](https://github.com/AzuraCast/example-plugin)
- [Plugin Documentation](https://www.azuracast.com/docs/developers/plugins/)
- Commit de AzuraCast: `93832174ac9cc1fbab85020f1954f6c09155c41a`

---

## ✅ Resultado Final

El plugin añade exitosamente un enlace "SAPO" con un icono verde de calendario en el menú lateral de AzuraCast, funcionando correctamente en la rama `stable` de agosto 2024.

**Estado**: ✅ FUNCIONANDO
**Fecha de implementación**: 11 de diciembre de 2024
