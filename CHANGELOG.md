# CHANGELOG

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
