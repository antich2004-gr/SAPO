# SAPO v2.0 - Roadmap de Desarrollo

## Objetivo General
Integrar completamente la funcionalidad del script `cliente_rrll.sh` dentro de SAPO, eliminando la dependencia de scripts bash externos y proporcionando una interfaz web completa para la gestión de podcasts.

---

## Estrategia de Desarrollo

### Entorno
- **Producción**: `sapo.radiobot.org` → SAPO v1.3 (estable)
- **Desarrollo**: `sapo2.radiobot.org` → SAPO v2.0 (testing)
- **Rama Git**: `v2.0-dev` (desde v1.3)
- **Emisora de prueba**: 1 emisora (galapagar o rvk)

### Ventajas
- ✅ Sin riesgo de afectar producción
- ✅ Testing exhaustivo antes de migración
- ✅ Rollback fácil si hay problemas
- ✅ Usuarios no ven desarrollo en progreso

---

## Análisis del Script Actual

### cliente_rrll.sh - Funcionalidades

#### 1. Gestión de Descargas
- Ejecución de `podget` con configuración personalizada
- Descarga de YouTube con `yt-dlp`
- Sistema de bloqueo (evita ejecuciones concurrentes)
- Parámetros: `--emisora`, `--sinpodget`

#### 2. Procesamiento de Archivos
- **Renombrado automático**: `carpetaDDMMAAAA.ext`
- **Corrección de extensiones**: elimina `.mp3.mp3`, `.ogg.ogg`, etc.
- **Limpieza de duplicados**: solo mantiene el archivo más reciente por carpeta
- **Soporte subcarpetas**: procesa jerarquías como `Podcasts/1H/SubPodcast/`

#### 3. Sistema de Caducidad
- Lee `caducidades.txt`: `nombre_podcast:dias`
- Default: 30 días
- Elimina archivos que excedan el límite por carpeta

#### 4. Verificación de Duración
- Lee `duraciones.txt`: `carpeta:30M|1H|1H30|2H|2H30|3H`
- Usa `ffprobe` para verificar duración real
- Elimina archivos que excedan límite (+5 min de tolerancia)
- Soporta carpetas especiales (ej: `1H:1H` = procesa subcarpetas)

#### 5. Sistema de Informes
- **Históricos** (365 días):
  - `historico_renombrados.txt`: fecha|archivo|RENOMBRADO
  - `historico_eliminados.txt`: fecha|archivo|CADUCIDAD|REEMPLAZO|EXCESO_DURACION
- **Informe diario**: `Informe_diario_DD_MM_AAAA.log`
  - Podcasts descargados (hoy + histórico)
  - Archivos eliminados (hoy + histórico)
  - Carpetas vacías (con días sin contenido)
  - Errores de Podget
  - Emisiones en directo (DJ/Icecast)
  - Playlists vacías (API AzuraCast)

#### 6. Integración AzuraCast
- Lee `emisoras.txt`: `numero:slug_azuracast:nombre_script`
- Consulta API: `/api/station/{slug}/playlists`
- Detecta playlists activas vacías (num_songs == 0)

### cliente_rrll_todas.sh - Wrapper
- Ejecuta `cliente_rrll.sh` para múltiples emisoras
- Logs individuales en `/tmp/logs_cliente_rrll/`
- Tracking de éxito/error por emisora
- Verificación de espacio en disco

---

## Arquitectura SAPO v2.0

### Nuevos Componentes PHP

```
includes/
├── downloader.php          # Ejecutar podget y yt-dlp
├── processor.php           # Renombrado, limpieza, corrección extensiones
├── duration.php            # Verificación de duración con ffprobe
├── reports.php             # Generación de informes y estadísticas
├── azuracast_api.php       # Cliente API AzuraCast
└── job_queue.php           # Cola de trabajos para múltiples emisoras

views/
├── downloads_v2.php        # Monitor en tiempo real con progreso
├── duration_manager.php    # Gestión visual de duraciones.txt
├── reports_dashboard.php   # Dashboard con gráficos
└── azuracast_status.php    # Estado de playlists y emisiones

workers/
└── download_worker.php     # Proceso background con SSE/WebSocket
```

### Base de Datos - Nuevas Tablas

```sql
-- Cola de trabajos de descarga
CREATE TABLE download_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    log_output TEXT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_username (username)
);

-- Histórico de descargas
CREATE TABLE download_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    podcast_name VARCHAR(100) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    action ENUM('RENOMBRADO', 'ELIMINADO') NOT NULL,
    reason ENUM('CADUCIDAD', 'REEMPLAZO', 'EXCESO_DURACION') NULL,
    file_size BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_date (username, created_at),
    INDEX idx_action (action)
);

-- Configuración de duraciones
CREATE TABLE duration_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    folder_name VARCHAR(100) NOT NULL,
    max_duration_minutes INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_folder (username, folder_name)
);

-- Errores de Podget
CREATE TABLE podget_errors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    podcast_name VARCHAR(100) NOT NULL,
    rss_url VARCHAR(500) NOT NULL,
    error_message TEXT NOT NULL,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_date (username, occurred_at)
);

-- Estado de playlists AzuraCast
CREATE TABLE azuracast_playlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    playlist_name VARCHAR(100) NOT NULL,
    is_empty BOOLEAN DEFAULT FALSE,
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_empty (username, is_empty)
);
```

---

## Desarrollo por Fases

### FASE 1: Fundamentos (v2.0-alpha)
**Duración estimada: 2-3 semanas**

#### Objetivos
- Reemplazar ejecución del script bash por funcionalidad PHP
- Monitor en tiempo real de descargas
- Sistema de logs estructurado

#### Tareas
1. **includes/downloader.php**
   - Función `executePodget($username)` mejorada
   - Ejecutar con `proc_open()` para capturar output en tiempo real
   - Parser del output de podget
   - Función `executeYoutubeDl($username)` para yt-dlp

2. **includes/processor.php**
   - `renameDownloadedFiles($username)`: renombrado por fecha
   - `fixMalformedExtensions($username)`: corregir extensiones
   - `cleanDuplicates($username)`: mantener solo el más reciente
   - `cleanByCaducidad($username)`: limpieza por días

3. **workers/download_worker.php**
   - Proceso background con `download_jobs` table
   - Actualización de estado en tiempo real
   - Manejo de errores y logging

4. **views/downloads_v2.php**
   - Monitor con Server-Sent Events (SSE) o WebSocket
   - Barra de progreso por podcast
   - Log en vivo con colores
   - Botón "Cancelar descarga"

#### Base de datos
- Crear tablas: `download_jobs`, `download_history`

#### Testing
- Ejecutar descarga en emisora de prueba
- Verificar renombrado correcto
- Verificar limpieza de duplicados
- Comprobar logs en BD

---

### FASE 2: Gestión de Duraciones (v2.0-beta)
**Duración estimada: 2 semanas**

#### Objetivos
- Interfaz visual para gestionar duraciones
- Verificación automática post-descarga
- Alertas de archivos eliminados

#### Tareas
1. **includes/duration.php**
   - `checkDuration($filePath)`: usar ffprobe
   - `verifyAndClean($username)`: verificar todos los archivos
   - `saveDurationConfig($username, $folder, $minutes)`: guardar config

2. **views/duration_manager.php**
   - Lista de carpetas con duración configurada
   - Selector de presets: 30M, 1H, 1H30, 2H, 2H30, 3H
   - Input custom para minutos exactos
   - Botón "Verificar ahora" manual

3. **Migración de duraciones.txt**
   - Script PHP para importar `duraciones.txt` → tabla `duration_config`
   - Mantener compatibilidad con archivo durante transición

#### Testing
- Configurar duraciones desde interfaz
- Subir archivo de prueba que exceda límite
- Verificar eliminación automática
- Comprobar alerta al usuario

---

### FASE 3: Sistema de Informes (v2.0-rc)
**Duración estimada: 1-2 semanas**

#### Objetivos
- Dashboard visual con estadísticas
- Gráficos de descargas y eliminaciones
- Histórico completo con filtros

#### Tareas
1. **includes/reports.php**
   - `getDailyStats($username, $date)`: estadísticas del día
   - `getWeeklyChart($username)`: datos para gráfico 7 días
   - `getMonthlyChart($username)`: datos para gráfico 30 días
   - `getEmptyFolders($username)`: carpetas sin archivos
   - `getPodgetErrors($username, $days)`: errores recientes

2. **views/reports_dashboard.php**
   - Resumen del día: descargas / eliminaciones
   - Gráfico de líneas: descargas por día (Chart.js)
   - Gráfico de barras: archivos por carpeta
   - Tabla de errores con filtros (fecha, podcast)
   - Lista de carpetas vacías con días sin contenido
   - Botón "Exportar a PDF"

3. **Migración de históricos**
   - Script PHP para importar:
     - `historico_renombrados.txt` → tabla `download_history`
     - `historico_eliminados.txt` → tabla `download_history`
   - Conservar archivos originales durante transición

#### Testing
- Verificar cálculos de estadísticas
- Comprobar gráficos con datos reales
- Exportar informe PDF
- Filtrar errores por fecha

---

### FASE 4: Integración AzuraCast (v2.0-rc2)
**Duración estimada: 1 semana**

#### Objetivos
- Detectar playlists vacías
- Mostrar emisiones en directo
- Alertas configurables

#### Tareas
1. **includes/azuracast_api.php**
   - `getApiCredentials($username)`: leer de config
   - `getEmptyPlaylists($username)`: consultar API
   - `getLiveShows($username, $date)`: parsear liquidsoap.log
   - `syncPlaylists($username)`: actualizar BD

2. **views/azuracast_status.php**
   - Panel "Playlists vacías" con alerta
   - Timeline de emisiones en directo del día
   - Estado de conexión API
   - Botón "Sincronizar ahora"

3. **Configuración**
   - Tabla `azuracast_config`: API URL, API Key por usuario
   - Cron job para sincronización automática cada hora

#### Testing
- Verificar conexión API
- Detectar playlists vacías correctamente
- Mostrar emisiones en directo
- Probar alertas

---

### FASE 5: Multi-emisora (v2.0 final)
**Duración estimada: 1 semana**

#### Objetivos
- Panel admin para gestionar múltiples emisoras
- Ejecución paralela o secuencial
- Dashboard global

#### Tareas
1. **includes/job_queue.php**
   - `queueDownload($username, $priority)`: añadir a cola
   - `processQueue()`: ejecutar trabajos pendientes
   - `getQueueStatus()`: estado de todos los trabajos

2. **views/admin_dashboard.php** (solo admin)
   - Lista de todas las emisoras
   - Estado actual de cada una (descargando, idle, error)
   - Botón "Ejecutar todas las emisoras"
   - Selector: paralelo (máx 3 simultáneas) / secuencial
   - Monitor global con progreso de cada emisora

3. **Cron automático**
   - Script para ejecutar descargas diarias
   - Configurable por emisora (hora, días de la semana)
   - Tabla `cron_config`

#### Testing
- Ejecutar 2 emisoras en paralelo
- Verificar límite de procesos simultáneos
- Comprobar ejecución automática
- Dashboard admin con ambas emisoras

---

## Migración y Compatibilidad

### Durante el Desarrollo (Híbrido)
- SAPO v1.3 sigue usando `cliente_rrll.sh` (producción)
- SAPO v2.0 usa funcionalidad PHP nativa (desarrollo)
- Scripts bash permanecen funcionales como backup

### Transición
1. **v2.0 en sapo2.radiobot.org con 1 emisora** (2-3 meses testing)
2. **Migración gradual**:
   - Semana 1: Emisora 1 a v2.0
   - Semana 2: Monitoreo y ajustes
   - Semana 3: Emisora 2 a v2.0
3. **Deprecación de scripts bash** (después de 1 mes estable)

### Rollback
- Si v2.0 falla → volver a v1.3 + scripts bash
- Base de datos v2.0 no afecta v1.3 (tablas separadas)

---

## Requisitos Técnicos

### Servidor
- PHP 8.0+ con extensiones:
  - `proc_open` habilitado
  - `pcntl` para manejo de procesos
  - `pdo_mysql` para BD
- Permisos sudo para ejecutar `podget` como usuario específico
- `ffprobe` instalado (verificación duración)
- `yt-dlp` instalado (descargas YouTube)

### Frontend
- Chart.js para gráficos
- EventSource API para SSE (monitor tiempo real)
- Soporte WebSocket (opcional, mejor performance)

### Configuración Apache/Nginx
- `sapo2.radiobot.org` → `/var/www/sapo2/`
- Tiempo de ejecución PHP: `max_execution_time = 3600` (1 hora)
- Memoria: `memory_limit = 512M`

---

## Estimación Total

| Fase | Duración | Acumulado |
|------|----------|-----------|
| FASE 1: Fundamentos | 2-3 semanas | 3 semanas |
| FASE 2: Duraciones | 2 semanas | 5 semanas |
| FASE 3: Informes | 1-2 semanas | 7 semanas |
| FASE 4: AzuraCast | 1 semana | 8 semanas |
| FASE 5: Multi-emisora | 1 semana | 9 semanas |
| **Testing en producción** | 4 semanas | **13 semanas** |

**Total estimado: ~3 meses de desarrollo + 1 mes de testing**

---

## Beneficios de SAPO v2.0

| Característica | Script bash | SAPO v2.0 |
|----------------|-------------|-----------|
| Interfaz | ❌ Terminal | ✅ Web moderna |
| Feedback | ❌ Solo log | ✅ Tiempo real con progreso |
| Configuración | ⚠️ Archivos .txt | ✅ Interfaz gráfica |
| Multi-emisora | ⚠️ Script separado | ✅ Dashboard unificado |
| Informes | ⚠️ Texto plano | ✅ Gráficos interactivos |
| Errores | ⚠️ Log manual | ✅ Alertas automáticas |
| Acceso | ❌ Solo SSH | ✅ Desde navegador |
| Histórico | ⚠️ Archivos .txt | ✅ Base de datos + búsqueda |
| Mantenimiento | ⚠️ Editar scripts | ✅ Todo desde interfaz |

---

## Riesgos y Mitigaciones

### Riesgos

1. **Pérdida de funcionalidad**: Algo del script no se implementa correctamente
   - **Mitigación**: Mantener scripts bash como backup durante 6 meses

2. **Performance**: Descargas PHP más lentas que bash
   - **Mitigación**: Procesos background + optimización

3. **Errores no detectados**: Bugs en producción
   - **Mitigación**: Testing exhaustivo en sapo2 con 1 emisora primero

4. **Complejidad**: Código más difícil de mantener
   - **Mitigación**: Documentación detallada + código limpio

### Plan B
- Si v2.0 no funciona bien después de 6 meses:
  - Mantener v1.3 + scripts bash indefinidamente
  - v2.0 solo como dashboard de monitoreo (sin ejecutar descargas)

---

## Próximos Pasos

### Ahora (Planificación)
- [x] Analizar scripts actuales
- [x] Diseñar arquitectura v2.0
- [x] Crear roadmap detallado
- [ ] Revisar y aprobar roadmap
- [ ] Decidir emisora de prueba

### Cuando se inicie desarrollo
1. Crear rama `v2.0-dev` desde v1.3
2. Configurar `sapo2.radiobot.org`
3. Empezar FASE 1 (Fundamentos)

---

**Documento creado:** 2025-11-07
**Versión:** 1.0
**Autor:** Claude Code + Usuario
