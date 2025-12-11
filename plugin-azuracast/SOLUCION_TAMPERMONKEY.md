# Solución Alternativa: Tampermonkey/Greasemonkey

Si no tienes acceso al servidor o prefieres no instalar un plugin, puedes usar **Tampermonkey** (extensión de navegador).

## 🎯 Ventajas

- ✅ No requiere acceso al servidor
- ✅ Instalación en 5 minutos
- ✅ Funciona solo en tu navegador
- ✅ Fácil de activar/desactivar
- ✅ Se mantiene con actualizaciones de AzuraCast

## 📦 Instalación

### Paso 1: Instalar Tampermonkey

Descarga e instala la extensión para tu navegador:

- **Chrome/Edge**: [Tampermonkey en Chrome Web Store](https://chrome.google.com/webstore/detail/tampermonkey/dhdgffkkebhmkfjojejmpbldmpobfkfo)
- **Firefox**: [Tampermonkey en Firefox Add-ons](https://addons.mozilla.org/es/firefox/addon/tampermonkey/)
- **Safari**: [Tampermonkey para Safari](https://www.tampermonkey.net/?browser=safari)
- **Opera**: [Tampermonkey en Opera Add-ons](https://addons.opera.com/extensions/details/tampermonkey-beta/)

### Paso 2: Crear el Script

1. **Haz clic en el ícono de Tampermonkey** en tu navegador
2. Selecciona **"Create a new script"** (Crear nuevo script)
3. **Borra todo** el contenido
4. **Copia y pega** el siguiente código:

```javascript
// ==UserScript==
// @name         AzuraCast - SAPO Menu
// @namespace    https://sapo.radioslibres.info/
// @version      1.0.0
// @description  Añade un enlace a SAPO en el menú lateral de AzuraCast
// @author       Radios Libres
// @match        https://tu-azuracast.com/*
// @match        http://tu-azuracast.com/*
// @icon         https://www.google.com/s2/favicons?sz=64&domain=radioslibres.info
// @grant        none
// @run-at       document-end
// ==/UserScript==

(function() {
    'use strict';

    console.log('[SAPO] Script iniciado');

    function addSAPOToMenu() {
        // Verificar si ya existe
        if (document.getElementById('sapo-menu-link')) {
            console.log('[SAPO] Elemento ya existe');
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
            'nav[role="navigation"] ul',
            '.main-sidebar ul'
        ];

        let menu = null;
        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element && element.querySelector('li')) {
                menu = element;
                console.log('[SAPO] Menú encontrado:', selector);
                break;
            }
        }

        if (!menu) {
            console.warn('[SAPO] No se encontró el menú');
            return;
        }

        // Obtener elemento de referencia
        const referenceItem = menu.querySelector('li');
        if (!referenceItem) {
            console.warn('[SAPO] No se encontró elemento de referencia');
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

        // Ensamblar y añadir al menú
        sapoMenuItem.appendChild(sapoLink);
        menu.appendChild(sapoMenuItem);

        console.log('[SAPO] Elemento añadido exitosamente');
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSAPOToMenu);
    } else {
        addSAPOToMenu();
    }

    // Reintentos para aplicaciones SPA (Vue.js)
    setTimeout(addSAPOToMenu, 500);
    setTimeout(addSAPOToMenu, 1000);
    setTimeout(addSAPOToMenu, 2000);
    setTimeout(addSAPOToMenu, 3000);

    // Observer para detectar cambios en el DOM (navegación SPA)
    const observer = new MutationObserver(function(mutations) {
        addSAPOToMenu();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Desconectar observer después de 10 segundos
    setTimeout(function() {
        observer.disconnect();
        console.log('[SAPO] Observer desconectado');
    }, 10000);

})();
```

### Paso 3: Configurar la URL de tu AzuraCast

**IMPORTANTE**: En el código anterior, **cambia** estas líneas:

```javascript
// @match        https://tu-azuracast.com/*
// @match        http://tu-azuracast.com/*
```

Por la URL real de tu AzuraCast, por ejemplo:

```javascript
// @match        https://radio.midominio.com/*
// @match        http://radio.midominio.com/*
```

O si usas una IP:

```javascript
// @match        http://192.168.1.100:8080/*
// @match        https://192.168.1.100:8443/*
```

### Paso 4: Guardar

1. Presiona **Ctrl+S** o haz clic en el ícono de **guardar** (💾)
2. Cierra la pestaña del editor

### Paso 5: Verificar

1. Ve a tu instalación de AzuraCast
2. Recarga la página (F5)
3. Deberías ver **"SAPO"** en el menú lateral

---

## 🔧 Solución de Problemas

### No aparece el elemento SAPO

1. **Verifica que Tampermonkey está activo**
   - El ícono de Tampermonkey debería mostrar "1" (1 script activo)

2. **Verifica que la URL coincide**
   - Haz clic en el ícono de Tampermonkey
   - Debería aparecer "AzuraCast - SAPO Menu" con un switch verde

3. **Abre la consola del navegador** (F12)
   - Busca mensajes que empiecen con `[SAPO]`
   - Si ves errores, cópialos

### El script no se ejecuta

- Verifica que la URL en `@match` coincide exactamente con tu AzuraCast
- Asegúrate de incluir tanto `http://` como `https://` si no sabes cuál usas

### Quiero que funcione en múltiples dominios

Añade más líneas `@match`:

```javascript
// @match        https://radio1.com/*
// @match        https://radio2.com/*
// @match        http://192.168.1.100/*
```

---

## 🎨 Personalización

### Cambiar el ícono

Busca esta línea:

```javascript
icon.className = 'fa fa-calendar';
```

Cámbiala por otro ícono de Font Awesome:

```javascript
icon.className = 'fa fa-broadcast-tower';  // Torre de radio
icon.className = 'fa fa-music';            // Nota musical
icon.className = 'fa fa-clock';            // Reloj
icon.className = 'fa fa-list';             // Lista
```

### Cambiar el texto

Busca:

```javascript
const text = document.createTextNode('SAPO');
```

Cámbialo por:

```javascript
const text = document.createTextNode('Programación');
```

### Cambiar la URL de destino

Busca:

```javascript
sapoLink.href = 'https://sapo.radioslibres.info';
```

Cámbiala por tu URL preferida.

---

## 🗑️ Desinstalación

1. Haz clic en el ícono de **Tampermonkey**
2. Selecciona **"Dashboard"**
3. Busca **"AzuraCast - SAPO Menu"**
4. Haz clic en el ícono de **papelera** 🗑️
5. Confirma

---

## 📱 Usar en Móvil

Tampermonkey está disponible para navegadores móviles:

### Android (Firefox):
1. Instala Firefox para Android
2. Instala Tampermonkey desde Firefox Add-ons
3. Sigue los mismos pasos

### iOS (Safari):
- Tampermonkey está disponible en la App Store
- Sigue los mismos pasos

---

## ✅ Ventajas vs Desventajas

| Aspecto | Plugin en Servidor | Tampermonkey |
|---------|-------------------|--------------|
| Requiere acceso SSH | ✅ Sí | ❌ No |
| Funciona para todos los usuarios | ✅ Sí | ❌ Solo para ti |
| Fácil de instalar | ❌ Requiere conocimientos | ✅ Muy fácil |
| Se mantiene con actualizaciones | ✅ Sí | ✅ Sí |
| Portátil entre dispositivos | ❌ No | ✅ Sí (sync) |

**Recomendación**:
- Si eres **el administrador** del servidor → Usa el **Plugin**
- Si eres **un usuario** normal → Usa **Tampermonkey**
