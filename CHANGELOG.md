# CHANGELOG

## [1.26] - 2026-03-16

### Dashboard — Mi SAPO (nueva sección)
- **Nueva pestaña "Mi SAPO"**: rediseño completo de la interfaz de usuario con un dashboard centralizado como punto de entrada
- **Barra de estado con 4 estados**: Activos, Pausados, Inactivos y Sin categoría con contadores en tiempo real
- **Popup al pasar el ratón** sobre cada barra de estado para ver la lista de podcasts en ese estado
- **Estado "Pausados"**: nuevo cuarto estado de podcast, visible en el dashboard y excluido de alertas RSS
- **Acordeón de alertas centralizado**: las alertas de RSS, carpeta vacía, disco y grabaciones se consolidan en un único panel colapsable, accesible desde cualquier emisora
- **Acordeón de log de descargas**: historial de descargas con modal de informe integrado en la sección de información de Mi SAPO
- **Barra de días de retención**: control de retención configurable visible directamente en el dashboard
- **Alertas de carpeta vacía** (nivel crítico): aviso inmediato si algún podcast tiene la carpeta de contenido vacía
- **Correcciones de UI**: contadores de estado corregidos, acordeón colapsable reparado, paginación de Mis Podcasts preserva pestaña activa, filtro "Todas las actividades" resuelto

### Alertas
- **Alerta de espacio en disco** (<500 MB libres): nueva alerta proactiva que se comprueba en el momento del login usando la API de AzuraCast y `disk_free_space()` como fallback
- **Alerta de grabaciones** (nivel crítico): aviso si la carpeta de grabaciones presenta problemas
- **Alertas de disco en rojo** (crítico): diferenciación visual clara — disco y carpeta vacía en rojo (`#fee2e2`/`#dc2626`), RSS en ámbar/warning
- **Alertas consolidadas en Mi SAPO**: los paneles de alerta se han movido de la parrilla al dashboard central
- **RSS sin actualizar ordenado** de mayor a menor días de desactualización; logs de descarga resaltados en azul
- **Podcasts pausados excluidos** de la alerta de RSS sin actualizar

### Grabaciones
- **Detección automática de ruta**: resolución de la ruta de grabaciones consultando la API de AzuraCast (`recordings_storage_location.path`) con fallback a `short_name` y búsqueda por candidatos en el filesystem
- **Limpieza de campo 'Carpeta grabaciones'** del panel de admin (la detección es automática, sin necesidad de configuración manual)
- **Corrección de días de retención** mostrados en el dashboard de grabaciones

### Parrilla
- **Leyenda de solapamiento**: nueva leyenda visual en la parrilla para identificar segmentos solapados
- **Tooltip en solapamientos**: al pasar el ratón sobre un segmento solapado se muestran los programas implicados
- **Playlists sin contenido** marcadas en el desplegable de Rotación (integración con API AzuraCast)
- **Paneles de alerta eliminados** de la vista de parrilla (consolidados en Mi SAPO)
- **Paneles de carpeta vacía y RSS** colapsados por defecto

### Administración
- **Impersonación de emisoras**: el administrador puede actuar en nombre de cualquier emisora desde el panel de admin
- **Rediseño del panel de admin**: mejor jerarquía visual, configuración avanzada en sección plegable

### Podcasts
- **Estado Pausado** añadido como cuarto estado (junto a Activo, Inactivo y Sin categoría)
- **Ficha Importar/Exportar** compactada
- **Paginación de Mis Podcasts** corregida para preservar la pestaña activa en la URL

### UI/UX
- **Tipografía y espaciado compactos**: base de 14px, menos padding/margin para aprovechar mejor el espacio
- **Responsive mejorado**: correcciones en grids, flex-wrap y tablas en tablet y móvil
- **Cache-busting** en `app.js` para evitar servir versiones obsoletas
- **Navegación**: "Volver al Dashboard" renombrado a "Volver a Mi SAPO" en la parrilla

### Correcciones técnicas
- Fix SSL: `verify_peer` deshabilitado en llamadas internas a la API de AzuraCast
- Fix `TypeError` en detección de cuota de almacenamiento (array vs null)
- Fix ruta relativa de `DB_FILE` en `config.php`
- Detección de cuota robustecida: prueba camelCase/snake_case y endpoint dedicado

### Ayuda / documentación
- Nuevas FAQs sobre el dashboard: logs, alertas e información de Mi SAPO
- FAQ sobre descarga de grabaciones antiguas desde Radiobot
- `help.php` actualizado para la nueva UI y alertas de listas vacías de AzuraCast
- Corrección de intervalos de colores en la ayuda: ámbar 31-60 días, rojo >60 días

---

## [1.25] - 2026-03-08

### Widget JS
- **Shadow DOM**: aislamiento total del CSS del widget respecto al sitio padre, evitando conflictos de estilos
- **Estilos igualados al iframe**: el widget JS ahora usa el mismo diseño visual que la versión iframe (pestañas por día, tarjetas de programa, tipografía, colores, iconos SVG de redes sociales, badge de "AHORA EN DIRECTO")

### Grabaciones
- **Gestión de grabaciones con limpieza automática**: nueva sección para ver, gestionar y limpiar grabaciones por emisora
- **Carga automática** al entrar en la pestaña de grabaciones
- **Retención configurable** de 1 a 180 días (selector numérico)
- **Ruta de grabaciones desde carpeta montada** configurable por usuario
- Escaneo de subcarpetas de streamers dentro del directorio de grabaciones
- Mejoras en la resolución de rutas usando la API de AzuraCast (`recordings_storage_location.path`)
- Campo slug de AzuraCast por usuario para rutas de grabaciones
- Uso del endpoint público de AzuraCast para obtener `short_name` sin necesidad de API key

### Suscripciones y plataformas (yt-dlp)
- **Integración de yt-dlp** para suscripciones de plataformas (YouTube y otras), de forma transparente junto a Podget
- **Cookies de YouTube globales** compartidas entre emisoras para evitar el bloqueo de bot
- **Detección automática de cookies caducadas o rotadas** por YouTube, con aviso al usuario
- `max_episodios` por defecto reducido de 5 a 1 para nuevas suscripciones
- Exclusión de directorios de yt-dlp en el renombrado/reemplazo de episodios

### Parrilla
- **Edición de programa como modal**: la ficha de edición de programa en la parrilla se abre como modal en lugar de página separada
- **Redes sociales colapsables** en el modal de edición (siempre colapsadas por defecto)
- **Margen de tolerancia configurable** por programa: 5, 10 o 15 minutos
- **Referencia de horario de AzuraCast** visible en modo solo lectura al editar
- Pre-rellenado de horario desde AzuraCast agrupado por hora + duración
- Uso correcto de `schedule_slots` en fichas de programas live de la parrilla

### Señales horarias
- Valores por defecto cambiados a **0.2 segundos** de duración y **60% de volumen**

### Infraestructura y scripts
- API key de SAPO leída desde `global.json` (eliminada duplicación en `radiobot.conf`)
- `PODGET_LOG` movido de `/tmp` a `INFORMES_DIR`
- Múltiples correcciones y mejoras de robustez en `cliente_rrll.sh`
- Purga de locks de Podget activada en `cliente_rrll.sh`
- Eliminación de `emisoras.txt` y `obtener_slug_azuracast` (obsoletos)

### Correcciones
- Estabilidad del script con `grep -v` vacío y `pipefail` activo
- Modal de edición scrollable y visible completo en pantallas pequeñas
- `recordings_folder` preservado al guardar configuración de usuario
- Referencia a `MAPA_TIEMPO_SEG` eliminada en `cliente_rrll.sh`

### Documentación
- Nuevas secciones de ayuda: **Señales horarias**, **Grabaciones**, **Suscripciones de plataformas**
- Eliminación de referencias obsoletas a cookies y configuración manual de episodios

---

## [1.24] - 2025-11-17

Versión anterior.
