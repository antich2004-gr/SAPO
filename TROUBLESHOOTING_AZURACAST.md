# Solución de Problemas - Integración SAPO en AzuraCast

## Problema: El JavaScript Personalizado No Se Ejecuta

### Verificaciones Básicas

#### 1. Verificar que Custom Branding está habilitado

```bash
# Conectarse al contenedor de AzuraCast
docker-compose exec web bash

# Verificar permisos de escritura
ls -la /var/azuracast/www/web/static/

# Verificar que la configuración existe en la BD
docker-compose exec mariadb mysql -u azuracast -pazuracast -e "SELECT * FROM azuracast.settings WHERE setting_key LIKE '%custom%';"
```

#### 2. Verificar la versión de AzuraCast

```bash
docker-compose exec web azuracast version
```

Si es una versión muy antigua, puede que no tenga la funcionalidad de Custom JS for Internal Pages.

#### 3. Limpiar caché de AzuraCast

```bash
docker-compose exec web azuracast cache:clear
docker-compose restart web
```

### Solución Alternativa: Modificar directamente el template

Si Custom Branding no funciona, puedes modificar directamente el template:

#### Opción A: Crear un volumen personalizado

1. Crear archivo local con el JavaScript:

```bash
mkdir -p /var/azuracast/custom
cat > /var/azuracast/custom/sapo-menu.js << 'EOF'
// Contenido del script aquí
EOF
```

2. Modificar `docker-compose.override.yml`:

```yaml
version: '2.2'

services:
  web:
    volumes:
      - /var/azuracast/custom/sapo-menu.js:/var/azuracast/www/web/static/dist/sapo-menu.js:ro
```

3. Reiniciar:

```bash
docker-compose down
docker-compose up -d
```

#### Opción B: Plugin de AzuraCast

Si Custom JS no funciona, la mejor opción es crear un plugin.

### Solución Alternativa 2: Bookmarklet

Si nada funciona, puedes crear un bookmarklet que el usuario ejecute manualmente:

```javascript
javascript:(function(){var s=document.createElement('div');s.style.cssText='position:fixed;bottom:20px;right:20px;background:#4CAF50;color:white;padding:15px 20px;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.3);z-index:9999;font-family:Arial;';s.innerHTML='<a href="https://sapo.radioslibres.info" target="_blank" style="color:white;text-decoration:none;font-weight:bold;">📅 Abrir SAPO</a>';document.body.appendChild(s);})();
```

El usuario lo guarda como marcador y lo ejecuta cuando esté en AzuraCast.

### Solución Alternativa 3: Extensión de Navegador (Tampermonkey)

Crear un script de Tampermonkey/Greasemonkey:

```javascript
// ==UserScript==
// @name         AzuraCast - SAPO Menu
// @namespace    http://tampermonkey.net/
// @version      1.0
// @description  Añade SAPO al menú de AzuraCast
// @match        https://tu-azuracast.com/*
// @grant        none
// ==/UserScript==

(function() {
    'use strict';
    // Aquí va el código del script
})();
```

### Verificar logs de errores

```bash
# Ver logs del contenedor web
docker-compose logs -f web

# Ver logs de errores de PHP
docker-compose exec web tail -f /var/azuracast/www_tmp/php_errors.log
```

## Información para el Soporte

Si necesitas pedir ayuda, proporciona:

1. Versión de AzuraCast: `docker-compose exec web azuracast version`
2. Tipo de instalación: Docker, Ansible, otro
3. Sistema operativo del host
4. Navegador y versión
5. Captura de pantalla de `/admin/branding`
6. Logs de la consola del navegador (F12)
