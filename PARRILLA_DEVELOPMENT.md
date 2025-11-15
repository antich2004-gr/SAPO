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

**Formato de Descripción en AzuraCast:**
```
Descripción del programa;Temática;https://url-del-programa.com
```

**Ejemplo:**
```
Un programa dedicado a la música alternativa;Musical;https://radio.com/alternativa
```

**Campos parseados:**
- `description`: Descripción del programa
- `programType`: Temática/tipo de programa
- `programUrl`: URL para más información

**Interacción:**
- Click en evento → Muestra información completa en alert
- Programa actual → Destacado con color de la estación
- Tooltip al pasar el ratón

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
- `includes/azuracast.php` - Funciones de integración con AzuraCast
- `test_azuracast.php` - Script de testing
- `assets/fullcalendar.min.js` - Librería FullCalendar local

**Modificados:**
- `includes/database.php` - Añadido soporte para configuración AzuraCast
- `index.php` - Añadida acción `update_azuracast_config`
- `views/admin.php` - UI para configurar Station ID y color del widget

### Branch y Commits

**Branch:** `feature/parrilla-azuracast`

**Commits recientes:**
1. `186ae2a` - Parsear información adicional de programas
2. `25c705e` - Simplificar destacado del programa actual
3. `10a40af` - Destacar programa EN VIVO
4. Anteriores: Implementación base del widget y diseño

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
