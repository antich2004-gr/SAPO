# Guía de Instalación del Plugin SAPO para AzuraCast

## 📦 Opción 1: Instalación mediante Plugin (Recomendado)

### Requisitos previos:
- Acceso SSH al servidor donde está instalado AzuraCast
- Permisos de root o sudo

### Pasos de instalación:

#### 1. Conectarse al servidor

```bash
ssh usuario@tu-servidor-azuracast.com
```

#### 2. Navegar al directorio de AzuraCast

```bash
cd /var/azuracast
```

> **Nota**: Si AzuraCast está en otra ubicación, ajusta la ruta. Puedes encontrarla con:
> ```bash
> find /opt /var /home -name "docker-compose.yml" -path "*/azuracast/*" 2>/dev/null
> ```

#### 3. Crear directorio de plugins

```bash
sudo mkdir -p plugins
```

#### 4. Crear el plugin SAPO

```bash
sudo mkdir -p plugins/sapo-menu-integration
```

#### 5. Crear los archivos del plugin

##### Archivo 1: plugin.json

```bash
sudo tee plugins/sapo-menu-integration/plugin.json > /dev/null << 'EOF'
{
    "name": "SAPO Menu Integration",
    "description": "Añade un enlace a SAPO en el menú lateral de AzuraCast",
    "author": "Radios Libres",
    "version": "1.0.0",
    "url": "https://sapo.radioslibres.info"
}
EOF
```

##### Archivo 2: events.php

```bash
sudo tee plugins/sapo-menu-integration/events.php > /dev/null << 'PHPEOF'
<?php

declare(strict_types=1);

return function (\App\EventDispatcher $dispatcher) {
    $dispatcher->addListener(
        \App\Event\BuildView::class,
        function (\App\Event\BuildView $event) {
            $view = $event->getView();

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

            $view->appendToBody($sapoScript);
        }
    );
};
PHPEOF
```

#### 6. Ajustar permisos

```bash
sudo chown -R azuracast:azuracast plugins/sapo-menu-integration
sudo chmod -R 755 plugins/sapo-menu-integration
```

> **Nota**: Si el usuario `azuracast` no existe, usa `www-data` o el usuario que ejecute Docker:
> ```bash
> sudo chown -R www-data:www-data plugins/sapo-menu-integration
> ```

#### 7. Verificar que los archivos están correctos

```bash
ls -la plugins/sapo-menu-integration/
cat plugins/sapo-menu-integration/plugin.json
```

Deberías ver:
```
total 16
drwxr-xr-x 2 azuracast azuracast 4096 ... .
drwxr-xr-x 3 azuracast azuracast 4096 ... ..
-rwxr-xr-x 1 azuracast azuracast  XXX ... events.php
-rwxr-xr-x 1 azuracast azuracast  XXX ... plugin.json
```

#### 8. Reiniciar AzuraCast

```bash
sudo docker-compose restart
```

O:

```bash
sudo ./docker.sh restart
```

#### 9. Limpiar caché (opcional pero recomendado)

```bash
sudo docker-compose exec web azuracast cache:clear
```

#### 10. Verificar

1. Accede a tu panel de AzuraCast en el navegador
2. Recarga la página (Ctrl+F5)
3. Deberías ver **"SAPO"** en el menú lateral con un ícono 📅

---

## 🔧 Solución de Problemas

### El plugin no aparece en el menú

#### Verificar que el plugin existe:
```bash
ls -la /var/azuracast/plugins/sapo-menu-integration/
```

#### Verificar permisos:
```bash
ls -la /var/azuracast/plugins/
```

#### Ver logs de AzuraCast:
```bash
docker-compose logs -f web | grep -i "plugin\|sapo"
```

#### Verificar que el directorio de plugins es leído:
```bash
docker-compose exec web ls -la /var/azuracast/www/plugins/
```

> **Nota**: Dentro del contenedor, el path puede ser diferente. AzuraCast monta los volúmenes en rutas específicas.

### El contenedor no arranca después de instalar

```bash
# Ver errores
docker-compose logs web

# Verificar sintaxis del PHP
docker-compose exec web php -l /var/azuracast/www/plugins/sapo-menu-integration/events.php
```

### Desinstalar el plugin

```bash
cd /var/azuracast
sudo rm -rf plugins/sapo-menu-integration
sudo docker-compose restart
```

---

## 📋 Verificación de la instalación

### Comprobar que AzuraCast detecta el plugin:

```bash
# Ver si el plugin está en el sistema de archivos del contenedor
docker-compose exec web ls -la /var/azuracast/www/plugins/

# Ver logs en tiempo real
docker-compose logs -f web
```

### Desde el navegador:

1. Abre la consola (F12)
2. Ejecuta:
```javascript
console.log(document.getElementById('sapo-menu-link'));
```

Si devuelve un elemento HTML, el plugin funciona. Si devuelve `null`, hay un problema.

---

## 🆘 Si nada funciona

### Opción alternativa: Tampermonkey/Greasemonkey

Si el sistema de plugins no funciona, usa la extensión del navegador.

Ver: `SOLUCION_TAMPERMONKEY.md`
