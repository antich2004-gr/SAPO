# Configuración global de carpeta de Podcasts

## Resumen

Se ha implementado una nueva funcionalidad que permite configurar el nombre de la carpeta de podcasts a nivel global del servidor. Esto soluciona el problema de tener diferentes nombres de carpeta en diferentes servidores (ej: "Podcasts" vs "Podcast").

## Cambios realizados

### 1. Base de datos (`includes/database.php`)

**Campo añadido a configuración global:**
- `config['podcasts_folder']`: Nombre de la carpeta de podcasts (valor por defecto: "Podcasts")

**Función actualizada:**
- `getPodcastsFolder($username = null)`: Obtiene el nombre de la carpeta de podcasts desde la configuración global
  - El parámetro `$username` se mantiene por compatibilidad pero no se usa
  - Lee el valor de `config['podcasts_folder']`

**Función de configuración actualizada:**
- `saveConfig($basePath, $subscriptionsFolder, $podcastsFolder, ...)`: Añadido parámetro `$podcastsFolder`

**Migración automática:**
- La configuración global obtiene automáticamente el valor "Podcasts" por defecto
- Compatible con sistemas nuevos y legacy

### 2. Gestión de categorías (`includes/categories.php`)

**Funciones actualizadas:**
- `syncCategoriesFromDisk()`: Usa `getPodcastsFolder()` en lugar de hardcodear "Podcasts"
- `movePodcastFiles()`: Usa configuración por usuario
- `getCategoryStats()`: Usa configuración por usuario
- `renameCategory()`: Usa configuración por usuario
- `renamePodcastDirectory()`: Usa configuración por usuario

### 3. Panel de administración (`views/admin.php`)

**Interfaz añadida:**
- Campo "Carpeta de Podcasts" en la sección de **Configuración de Rutas** (global)
- Mostrado junto a "Ruta Base" y "Carpeta Suscripciones"
- Validación: solo permite letras, números, guiones y guiones bajos
- Tooltip explicativo: "Nombre de la carpeta de podcasts para todas las emisoras"
- Muestra el valor actual en el resumen de configuración

**Interfaz removida de:**
- Ya NO está en la configuración individual de cada emisora (se movió a global)

### 4. Procesamiento de formularios (`index.php`)

**Acción actualizada:**
- `save_config`: Ahora también procesa y guarda el campo `podcasts_folder` en la configuración global
- `update_azuracast_config`: Se simplificó, ya no maneja `podcasts_folder` (ahora es global)

## Cómo usar

### Para administradores

1. **Acceder al panel de administración**
   - Iniciar sesión como administrador
   - Ir a la página de administración

2. **Configurar la carpeta (una sola vez para todo el servidor)**
   - En la sección "Configuración de Rutas"
   - Buscar el campo "Carpeta de Podcasts"
   - Introducir el nombre correcto: "Podcasts" o "Podcast" (según el servidor)
   - Hacer clic en "Guardar Configuración"
   - **Esta configuración aplica a TODAS las emisoras del servidor**

### Ejemplos de configuración

**Servidor 1:**
- Ruta: `/mnt/emisoras/media/NOMBRE_EMISORA/Podcasts`
- Configuración: `Podcasts` (plural)

**Servidor 2:**
- Ruta: `/mnt/emisoras/media/NOMBRE_EMISORA/Podcast`
- Configuración: `Podcast` (singular)

## Archivos modificados

```
includes/database.php       - Añadido campo y funciones para podcasts_folder
includes/categories.php     - Actualizado para usar getPodcastsFolder()
views/admin.php            - Añadido campo en interfaz de administración
index.php                  - Procesamiento del campo podcasts_folder
```

## Compatibilidad

- **Retrocompatible**: La configuración global obtiene automáticamente el valor "Podcasts"
- **Migración automática**: No requiere intervención manual en la base de datos
- **Sin impacto**: El sistema funciona igual si no se modifica la configuración
- **Nivel de configuración**: Global (un solo valor para todo el servidor, no por emisora)

## Notas técnicas

- El campo se almacena en la configuración global: `db/global.json` (nuevo sistema) o `db.json` (legacy)
- La validación permite solo caracteres seguros para nombres de carpeta
- Todas las funciones de categorías usan dinámicamente la configuración global
- `getPodcastsFolder()` acepta `$username` por compatibilidad pero lee siempre de config global

## Scripts de cliente RRLL

**IMPORTANTE**: El script bash `cliente_rrll/cliente_rrll.sh` todavía tiene hardcodeado:
```bash
PODCASTS_DIR="$BASE_DIR/Podcasts"
```

**Solución temporal**:
Editar manualmente el script para cada emisora que use un nombre diferente.

**Solución futura**:
Modificar el script para leer la configuración desde la base de datos PHP.

## Fecha

11 de diciembre de 2025
