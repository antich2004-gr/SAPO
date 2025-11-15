# Desarrollo de la Parrilla de Programación - AzuraCast

## Estado Actual (2025-11-15)

### ✅ Completado

#### FASE 1: Backend
- ✅ Integración con API de AzuraCast (`includes/azuracast.php`)
- ✅ Configuración por usuario (Station ID, color del widget)
- ✅ Panel de administración con campos de configuración
- ✅ Script de testing (`test_azuracast.php`)

#### FASE 2: Widget Público
- ✅ Widget embebible (`parrilla_widget.php`)
- ✅ FullCalendar 6.1.15 (descargado localmente en `assets/`)
- ✅ Eventos recurrentes semanales (sin fechas específicas)
- ✅ Rango horario 8:00 AM - 8:00 AM (24h + madrugada)
- ✅ Diseño minimalista estilo "El Salto Diario"
- ✅ Destacado del programa actual con color
- ✅ Parseo de información adicional desde campo Description

### Características Implementadas

#### Pestaña "Parrilla" - Gestión Unificada (NUEVO)

Toda la funcionalidad de parrilla está organizada en una única pestaña con 4 subsecciones:

**1. 👁️ Vista Previa**
- Preview en iframe del widget real
- Botón para abrir en nueva pestaña
- Muestra la parrilla tal como se verá en la web

**2. 📝 Gestión de Programas**
- Auto-descubrimiento desde AzuraCast
- Lista de programas con estados (✅ Completo, ⚠️ Parcial, ❌ Vacío)
- Edición de información adicional por programa
- Barra de progreso de completitud

**3. ⚙️ Configuración**
- Station ID de AzuraCast (requerido)
- Color del widget personalizable
- Enlace a test de conexión
- Interfaz auto-contenida (no requiere admin)

**4. 🔗 Código de Embebido**
- Snippet HTML listo para copiar
- Botón "Copiar al portapapeles"
- Instrucciones de personalización
- Consejos de uso

**Auto-descubrimiento desde AzuraCast:**
- Botón "🔄 Sincronizar con AzuraCast" detecta automáticamente todos los programas
- No sobrescribe información ya existente
- Detecta programas nuevos sin perder datos anteriores

**Navegación:**
- Acceso desde dashboard: botón "📺 Parrilla"
- Tabs visuales con color personalizado
- Estado persistente al editar programas
- URLs: `?page=parrilla&section=preview|programs|config|embed`

**Campos editables por programa:**
- Descripción corta (para previews)
- Descripción larga (para página de detalle)
- Temática (desplegable: Musical, Informativo, Cultural, etc.)
- URL del programa
- Imagen/portada del programa
- Presentadores (separados por comas)
- Twitter (sin @)
- Instagram (sin @)

**Integración automática:**
- `formatEventsForCalendar()` usa información de SAPO si está disponible
- Fallback: Si no hay info en SAPO, parsea campo Description de AzuraCast
- Formato fallback: `Descripción;Temática;URL` (separado por punto y coma)

**Base de datos:**
- Archivos JSON en `data/programs/{username}.json`
- Estructura con timestamp de sincronización
- Matching por nombre de programa/playlist

**Interacción en la parrilla:**
- Click en evento → Muestra información completa (descripción, temática, presentadores, RRSS, URL)
- Programa actual → Destacado con color de la estación
- Tooltip al pasar el ratón con nombre del programa

### 🔄 Pendiente / Mejoras Futuras

#### Estética
- [ ] Mejorar el diseño visual del widget
- [ ] Personalizar el modal de información (reemplazar alert por modal bonito)
- [ ] Añadir animaciones suaves
- [ ] Mejorar responsive para móviles

#### Funcionalidad
- [ ] Opción de compartir programación
- [ ] Exportar a PDF/imagen
- [ ] Modo oscuro
- [ ] Personalización de colores por programa (no solo por estación)

#### Integración
- [ ] Documentación para embedear en sitios web
- [ ] Shortcode para WordPress
- [ ] Preview del widget en panel de admin

### Archivos Modificados/Creados

**Nuevos:**
- `parrilla_widget.php` - Widget público embebible
- `views/parrilla.php` - Vista principal con tabs y subsecciones
- `views/parrilla_programs.php` - Subsección de gestión de programas
- `includes/azuracast.php` - Funciones de integración con AzuraCast
- `includes/programs.php` - Funciones CRUD para gestión de programas
- `test_azuracast.php` - Script de testing
- `assets/fullcalendar.min.js` - Librería FullCalendar local
- `data/programs/` - Directorio para datos de programas

**Modificados:**
- `includes/database.php` - Añadido soporte para configuración AzuraCast
- `includes/azuracast.php` - formatEventsForCalendar acepta username, integra info de SAPO
- `index.php` - Acciones: `update_azuracast_config`, `update_azuracast_config_user`, `sync_programs`, `save_program`
- `views/admin.php` - UI para configurar Station ID y color del widget
- `views/user.php` - Botón "Parrilla" en dashboard
- `views/layout.php` - Routing para page=parrilla
- `parrilla_widget.php` - Pasa username a formatEventsForCalendar

### Branch y Commits

**Branch:** `feature/parrilla-azuracast`

**Commits recientes:**
1. `4a30911` - Reorganizar gestión de parrilla en pestaña unificada con subsecciones
2. `6608ea3` - Fix: Actualizar test_azuracast.php para nueva firma
3. `a20e83a` - Actualizar documentación con sistema de gestión de programas
4. `b6616b5` - Sistema de gestión de programas con auto-descubrimiento
5. `7ff89ca` - Documentación del desarrollo de la parrilla
6. `186ae2a` - Parsear información adicional de programas
7. Anteriores: Diseño, NOW indicator, etc.

### Próximos Pasos Sugeridos

1. **Mejorar estética del widget**
   - Reemplazar `alert()` por modal bonito
   - Añadir iconos para temáticas
   - Mejorar tipografía

2. **Documentación**
   - Guía de uso para administradores
   - Ejemplos de embed en iframe

3. **Testing en producción**
   - Probar con datos reales de AzuraCast
   - Validar diferentes casos de uso

4. **Merge a main**
   - Una vez testeado, mergear feature branch
   - Crear release v1.3.0

### Notas Técnicas

**Parseo de duración:**
- Si el nombre del programa termina en "- XX" (ej: "PROGRAMA - 30"), se interpreta como XX minutos de duración
- Si no hay duración, se asume 1 hora por defecto

**Detección de programa actual:**
- Se compara timestamp actual con start/end de cada evento
- Se añade clase `.fc-event-now` al evento activo
- Funciona en tiempo real (se actualiza al cargar la página)

**Configuración por usuario:**
- Cada emisora puede tener su propio Station ID
- Color personalizable por estación
- Widget accesible vía: `parrilla_widget.php?station=nombre_usuario`

### URLs de Referencia

- Repositorio: https://github.com/antich2004-gr/SAPO
- AzuraCast API: `{base_url}/api/station/{station_id}/schedule`
- FullCalendar Docs: https://fullcalendar.io/docs
