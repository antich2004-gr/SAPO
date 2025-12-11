# Configuración de carpeta de Podcasts por emisora

## Resumen

Se ha implementado una nueva funcionalidad que permite configurar el nombre de la carpeta de podcasts de forma independiente para cada emisora. Esto soluciona el problema de tener diferentes nombres de carpeta en diferentes servidores (ej: "Podcasts" vs "Podcast").

## Cambios realizados

### 1. Base de datos (`includes/database.php`)

**Campo añadido a usuarios:**
- `podcasts_folder`: Nombre de la carpeta de podcasts (valor por defecto: "Podcasts")

**Nuevas funciones:**
- `getPodcastsFolder($username)`: Obtiene el nombre de la carpeta de podcasts para un usuario
- `setPodcastsFolder($username, $folderName)`: Actualiza el nombre de la carpeta de podcasts

**Migración automática:**
- Los usuarios existentes se migran automáticamente con el valor por defecto "Podcasts"
- Nuevos usuarios se crean con "Podcasts" como valor por defecto

### 2. Gestión de categorías (`includes/categories.php`)

**Funciones actualizadas:**
- `syncCategoriesFromDisk()`: Usa `getPodcastsFolder()` en lugar de hardcodear "Podcasts"
- `movePodcastFiles()`: Usa configuración por usuario
- `getCategoryStats()`: Usa configuración por usuario
- `renameCategory()`: Usa configuración por usuario
- `renamePodcastDirectory()`: Usa configuración por usuario

### 3. Panel de administración (`views/admin.php`)

**Interfaz añadida:**
- Campo "Carpeta de Podcasts" en la configuración de cada emisora
- Validación: solo permite letras, números, guiones y guiones bajos
- Tooltip explicativo del uso del campo

### 4. Procesamiento de formularios (`index.php`)

**Acción actualizada:**
- `update_azuracast_config`: Ahora también procesa y guarda el campo `podcasts_folder`

## Cómo usar

### Para administradores

1. **Acceder al panel de administración**
   - Iniciar sesión como administrador
   - Ir a la página de administración

2. **Configurar la carpeta para cada emisora**
   - Buscar el usuario/emisora en la lista de "Usuarios Registrados"
   - En el formulario de configuración, encontrar el campo "Carpeta de Podcasts"
   - Introducir el nombre correcto: "Podcasts" o "Podcast" (según el servidor)
   - Hacer clic en "Guardar"

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

- **Retrocompatible**: Los usuarios existentes obtienen automáticamente el valor "Podcasts"
- **Migración automática**: No requiere intervención manual en la base de datos
- **Sin impacto**: El sistema funciona igual si no se modifica la configuración

## Notas técnicas

- El campo se almacena en el archivo JSON de cada usuario: `db/users/{username}.json`
- La validación permite solo caracteres seguros para nombres de carpeta
- Todas las funciones de categorías usan dinámicamente la configuración del usuario

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
