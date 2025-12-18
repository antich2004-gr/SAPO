# Sistema de Permisos SAPO para AzuraCast

El plugin SAPO v2.0+ incluye un sistema integrado de permisos que permite controlar qué emisoras tienen acceso a SAPO.

## 🔐 Permiso: `sapo:access`

**Nombre completo**: Acceso a SAPO (Sistema de Automatización de Podcasts)
**Tipo**: Permiso por emisora (Station Permission)
**Descripción**: Controla qué emisoras pueden ver y acceder al enlace SAPO en el menú lateral de AzuraCast.

---

## 📋 Requisitos

- AzuraCast stable o rolling release
- Plugin SAPO Menu Integration v2.0+
- Modo de plugins activado (`AZURACAST_PLUGIN_MODE: true`)

---

## 🚀 Configuración Inicial

### 1. Instalar el Plugin

```bash
# Copiar archivos del plugin
sudo mkdir -p /var/azuracast/plugins-custom/sapo-menu-integration
sudo cp /ruta/SAPO/plugin-azuracast/* /var/azuracast/plugins-custom/sapo-menu-integration/

# Configurar docker-compose.override.yml
sudo nano /var/azuracast/docker-compose.override.yml
```

Añadir:

```yaml
version: '2.2'

services:
  web:
    environment:
      AZURACAST_PLUGIN_MODE: 'true'
    volumes:
      - /var/azuracast/plugins-custom:/var/azuracast/www/plugins
```

### 2. Reiniciar AzuraCast

```bash
cd /var/azuracast
docker-compose restart web
```

---

## 👥 Asignar Permisos a Emisoras

### Opción A: Desde la Interfaz Web (Recomendado)

1. **Acceder al panel de administración**
   - Iniciar sesión como administrador
   - Ir a: `Administración` → `Usuarios`

2. **Editar o crear un rol**
   - Hacer clic en `Roles` en el menú lateral
   - Crear un nuevo rol o editar uno existente

3. **Activar el permiso SAPO**
   - En la sección de permisos por emisora
   - Buscar: **"Acceso a SAPO (Sistema de Automatización de Podcasts)"**
   - Marcar la casilla para las emisoras que deben tener acceso

4. **Asignar el rol a usuarios**
   - Ir a `Usuarios`
   - Editar usuario
   - Asignar el rol con permiso SAPO activado

### Opción B: Desde la Base de Datos (Avanzado)

```sql
-- Ver todos los permisos disponibles
SELECT * FROM role_permissions WHERE action_name LIKE '%sapo%';

-- Asignar permiso SAPO a un rol específico para una emisora
INSERT INTO role_permissions (role_id, station_id, action_name)
VALUES (
    1,              -- ID del rol
    1,              -- ID de la emisora
    'sapo:access'   -- Permiso SAPO
);
```

---

## 🎯 Comportamiento del Sistema de Permisos

### Emisora CON Permiso `sapo:access`
✅ El enlace **🐸 SAPO** aparece en el menú lateral
✅ Puede acceder a https://sapo.radioslibres.info
✅ Mensaje en consola: `[SAPO Plugin] Añadiendo enlace al menú`

### Emisora SIN Permiso `sapo:access`
❌ El enlace **NO aparece** en el menú lateral
❌ No tiene acceso visual a SAPO desde AzuraCast
ℹ️ Mensaje en consola: `[SAPO Plugin] Usuario no tiene permiso sapo:access`

### Administradores Globales
👑 Los administradores con permiso global `administer all` **siempre** ven el enlace SAPO, independientemente de los permisos por emisora.

---

## 🔍 Verificación y Diagnóstico

### Verificar que el Permiso está Registrado

1. **Desde la interfaz web**:
   - Ir a `Administración` → `Roles`
   - Crear o editar un rol
   - En la sección de permisos por emisora, debe aparecer:
     > **Acceso a SAPO (Sistema de Automatización de Podcasts)**

2. **Desde la consola del navegador** (F12):
   - Ejecutar:
     ```javascript
     console.log(window.azuracast?.permissions);
     ```
   - Buscar `"sapo:access"` en el objeto retornado

### Logs de Depuración

El plugin registra mensajes en la consola del navegador:

```javascript
// Cuando el usuario TIENE permiso:
// (Ningún mensaje, el enlace se añade silenciosamente)

// Cuando el usuario NO TIENE permiso:
[SAPO Plugin] Usuario no tiene permiso sapo:access

// Para ver permisos actuales:
console.log(window.azuracast.permissions);
```

---

## 🛠️ Solución de Problemas

### El permiso no aparece en la lista de roles

**Causa**: El plugin no se cargó correctamente.

**Solución**:
```bash
# Verificar que el modo de plugins está activado
docker-compose exec web env | grep PLUGIN_MODE

# Debe mostrar: AZURACAST_PLUGIN_MODE=true

# Verificar logs del contenedor web
docker-compose logs web | grep -i sapo

# Reiniciar forzando recreación
docker-compose up -d --force-recreate web
```

### El enlace no aparece aunque el permiso está asignado

**Causa 1**: Caché del navegador.

**Solución**: Limpiar caché y cookies, o probar en ventana de incógnito.

**Causa 2**: JavaScript no está verificando permisos correctamente.

**Solución**:
1. Abrir consola del navegador (F12)
2. Buscar mensajes del plugin SAPO
3. Verificar permisos con:
   ```javascript
   // Verificar si window.azuracast existe
   console.log('AzuraCast objeto:', window.azuracast);

   // Verificar permisos del usuario
   console.log('Permisos:', window.azuracast?.permissions);

   // Buscar específicamente sapo:access
   const perms = window.azuracast?.permissions?.station || {};
   for (let stationId in perms) {
       console.log(`Emisora ${stationId}:`, perms[stationId]);
   }
   ```

**Causa 3**: El usuario no tiene el permiso para la emisora actual.

**Solución**: Verificar que el permiso `sapo:access` está asignado para la emisora correcta.

---

## 🔄 Migración desde v1.0

Si ya tenías instalado el plugin SAPO v1.0 (sin permisos):

### Cambios en v2.0:
- ✅ Se añadió permiso por emisora `sapo:access`
- ✅ Verificación de permisos en JavaScript
- ⚠️ **BREAKING**: El enlace ya NO aparece automáticamente para todos

### Pasos de Migración:

1. **Actualizar archivos del plugin**:
   ```bash
   sudo cp /ruta/SAPO/plugin-azuracast/events-working.php \
           /var/azuracast/plugins-custom/sapo-menu-integration/events.php

   sudo cp /ruta/SAPO/plugin-azuracast/plugin.json \
           /var/azuracast/plugins-custom/sapo-menu-integration/plugin.json
   ```

2. **Reiniciar el servicio web**:
   ```bash
   cd /var/azuracast
   docker-compose restart web
   ```

3. **Asignar permisos a emisoras**:
   - Ir a `Administración` → `Roles`
   - Asignar el permiso `sapo:access` a las emisoras que deben tener acceso
   - **Importante**: Hasta que no asignes el permiso, las emisoras NO verán el enlace

4. **Verificar funcionamiento**:
   - Iniciar sesión con un usuario de la emisora
   - Verificar que el enlace 🐸 SAPO aparece en el menú lateral

---

## 📊 Casos de Uso

### Caso 1: Una sola emisora con acceso a SAPO

```
Configuración:
- Emisora "Radio Libre" → Permiso sapo:access ✅
- Emisora "Radio Comunitaria" → Sin permiso ❌

Resultado:
- Usuarios de "Radio Libre" ven el enlace SAPO
- Usuarios de "Radio Comunitaria" NO ven el enlace
```

### Caso 2: Todas las emisoras con acceso

```
Configuración:
- Crear rol "Usuario SAPO"
- Asignar permiso sapo:access para TODAS las emisoras
- Asignar rol a todos los usuarios

Resultado:
- Todos los usuarios ven el enlace SAPO
```

### Caso 3: Solo administradores

```
Configuración:
- NO asignar permiso sapo:access a ninguna emisora
- Solo los admins globales verán el enlace

Resultado:
- Solo usuarios con permiso global "administer all" ven SAPO
```

---

## 🔗 Enlaces Útiles

- [Documentación oficial de Roles y Permisos de AzuraCast](https://docs.azuracast.com/en/administration/roles-and-permissions)
- [Desarrollo de Plugins para AzuraCast](https://docs.azuracast.com/en/developers/plugins)
- [SAPO - Sistema de Automatización de Podcasts](https://sapo.radioslibres.info)

---

## 📝 Notas Técnicas

### Métodos de Verificación de Permisos

El plugin utiliza tres métodos para verificar permisos (en orden de prioridad):

1. **Objeto global `window.azuracast.permissions`**
   - Método principal en AzuraCast moderno
   - Formato: `{ station: { "1": ["sapo:access", ...] } }`

2. **Meta tag HTML** `<meta name="user-permissions">`
   - Fallback si el objeto global no está disponible
   - Formato JSON: `{"station":{"1":["sapo:access"]}}`

3. **Verificación de admin global**
   - Clase CSS `admin` en `<body>`
   - Propiedad `window.azuracast.isAdmin`
   - Los admins globales bypasean la verificación de permisos

### Código de Verificación:

```javascript
function hasPermission(permission) {
    // Método 1: Objeto global
    if (window.azuracast?.permissions?.station) {
        for (let stationId in window.azuracast.permissions.station) {
            if (window.azuracast.permissions.station[stationId].includes(permission)) {
                return true;
            }
        }
    }

    // Método 2: Meta tag
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

    // Método 3: Admin global
    return document.body.classList.contains('admin') ||
           window.azuracast?.isAdmin;
}
```

---

## 🆘 Soporte

Si tienes problemas con el sistema de permisos:

1. Revisa los logs del contenedor web:
   ```bash
   docker-compose logs web | tail -100
   ```

2. Abre la consola del navegador (F12) y busca mensajes del plugin

3. Verifica la configuración del plugin:
   ```bash
   cat /var/azuracast/plugins-custom/sapo-menu-integration/plugin.json
   ```

4. Contacta con el equipo de Radios Libres

---

**Versión del documento**: 2.0
**Última actualización**: 2024-12-18
**Compatible con**: AzuraCast stable (commit 93832174+), SAPO Plugin v2.0+
