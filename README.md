# SAPO

**Sistema de Automatización de Podcasts para Radiobot**

Sistema web para la gestión automatizada de suscripciones de podcasts en múltiples emisoras de radio utilizando Radiobot como plataforma de reproducción.

## 📋 Descripción

SAPO es una aplicación web desarrollada en PHP que permite a múltiples emisoras de radio gestionar sus suscripciones a podcasts de forma independiente. El sistema descarga automáticamente nuevos episodios mediante Podget, los organiza por categorías personalizadas y los integra con Radiobot/AzuraCast para su reproducción automática.

### Características principales

- ✅ **Multi-usuario**: Cada emisora tiene su propio espacio independiente
- ✅ **Gestor de categorías avanzado**: Renombrado masivo, movimiento de archivos, estadísticas
- ✅ **Gestión de podcasts**: Organización personalizada por categorías
- ✅ **Descarga automatizada**: Integración con Podget para descarga de episodios RSS
- ✅ **Sistema de caducidad**: Control personalizado del tiempo de retención por podcast
- ✅ **Control de duración**: Límites de duración por podcast con verificación automática
- ✅ **Informes diarios**: Reportes automáticos de descargas, eliminaciones y errores
- ✅ **Historial de descargas**: Visualización de episodios descargados en múltiples períodos
- ✅ **Cache de feeds**: Optimización de consultas a feeds RSS compartido entre emisoras
- ✅ **Importar/Exportar**: Soporte para serverlist.txt de Podget
- ✅ **Panel de administración**: Gestión centralizada de usuarios y configuración
- ✅ **Seguridad robusta**: CSRF protection, rate limiting, BCrypt, validación de uploads, sesiones seguras
- ✅ **Pausar/Reanudar podcasts**: Control de suscripciones sin eliminarlas
- ✅ **Búsqueda global**: Buscar podcasts en toda la base de datos, no solo en la página actual
- ✅ **Filtrado avanzado**: Filtrar por categoría mostrando todos los podcasts, independientemente de la paginación
- ✅ **Renombrado inteligente**: Al cambiar el nombre de un podcast, la carpeta se renombra automáticamente conservando archivos
- ✅ **Notificaciones auto-ocultables**: Los mensajes de alerta desaparecen automáticamente después de 5 segundos

## 🏗️ Arquitectura

### Estructura de archivos

```
SAPO/
├── index.php                    # Controlador principal y router
├── config.php                   # Configuración global y constantes de seguridad
├── db.json                      # Base de datos JSON (usuarios, config, categorías)
├── .htaccess                    # Configuración Apache y headers de seguridad
├── .gitignore                   # Archivos excluidos de git
├── includes/
│   ├── auth.php                 # Sistema de autenticación y seguridad (141 líneas)
│   ├── categories.php           # Gestión avanzada de categorías (590 líneas)
│   ├── database.php             # Capa de acceso a datos JSON (355 líneas)
│   ├── feed.php                 # Funciones para feeds RSS (195 líneas)
│   ├── podcasts.php             # Lógica de gestión de podcasts (512 líneas)
│   ├── reports.php              # Gestión de informes diarios (319 líneas)
│   ├── session.php              # Gestión de sesiones seguras (80 líneas)
│   └── utils.php                # Funciones de utilidad y sanitización (56 líneas)
├── views/
│   ├── layout.php               # Layout principal HTML
│   ├── login.php                # Vista de login
│   ├── admin.php                # Panel de administración
│   ├── user.php                 # Panel de emisora con pestañas
│   ├── help.php                 # Página de ayuda y documentación
│   ├── edit_podcast_form.php    # Formulario de edición de podcasts
│   ├── report_view.php          # Vista de informes consolidados
│   └── podget_status.php        # Estado de ejecución de Podget
├── assets/
│   ├── style.css                # Estilos de la aplicación
│   ├── app.js                   # JavaScript del frontend
│   └── favicon.svg              # Icono de la aplicación
├── README.md                    # Este archivo
├── SECURITY.md                  # Documentación de seguridad
└── ROADMAP_v2.0.md              # Hoja de ruta para la versión 2.0

```

### Base de datos

SAPO utiliza un único archivo JSON (`db.json`) que contiene:

#### Estructura de db.json

```json
{
  "users": [
    {
      "id": 1,
      "username": "admin",
      "password_hash": "$2y$10$...",
      "role": "admin",
      "station_name": "Administrador"
    },
    {
      "id": 2,
      "username": "emisora1",
      "password_hash": "$2y$10$...",
      "role": "user",
      "station_name": "Radio Ejemplo"
    }
  ],
  "config": {
    "base_path": "/ruta/al/directorio/emisoras",
    "subscriptions_folder": "Suscripciones",
    "radiobot_url": "https://radiobot.radioslibres.info"
  },
  "login_attempts": {},
  "feed_cache": {},
  "users_data": {
    "emisora1": {
      "categories": ["Noticias", "Deportes", "Cultura"]
    }
  }
}
```

### Archivos de emisora

Cada emisora tiene su propio directorio en `{base_path}/{username}/` con:

```
{username}/
├── media/
│   ├── Suscripciones/
│   │   ├── serverlist.txt       # Lista de podcasts en formato Podget
│   │   ├── caducidades.txt      # Días de retención por podcast
│   │   └── duraciones.txt       # Límites de duración por podcast
│   ├── Podcast/                 # Archivos MP3 descargados
│   │   ├── Noticias/
│   │   ├── Deportes/
│   │   └── ...
│   └── Informes/                # Informes diarios generados
│       └── Informe_diario_DD_MM_YYYY.log
└── playlists/                   # Listas M3U para Radiobot
    ├── Noticias.m3u
    ├── Deportes.m3u
    └── ...
```

## 🚀 Instalación

### Requisitos previos

- PHP 7.4 o superior
- Servidor web (Apache o Nginx)
- Extensiones PHP: json, curl, simplexml, mbstring, fileinfo
- Podget instalado en el servidor (para descargas automáticas)
- Radiobot/AzuraCast (opcional, para integración)

### Pasos de instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/antich2004-gr/SAPO.git
cd SAPO
```

2. **Configurar permisos**
```bash
chmod 755 .
chmod 640 config.php db.json
chmod 755 includes/ views/ assets/
```

3. **Crear base de datos inicial**

El archivo `db.json` se incluye con el usuario admin por defecto. Si no existe, créalo manualmente o el sistema lo creará automáticamente en el primer acceso.

4. **Configurar el servidor web**

**Apache** (el .htaccess ya está incluido):
```apache
<Directory /ruta/a/SAPO>
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx**:
```nginx
server {
    listen 80;
    server_name sapo.tudominio.com;
    root /ruta/a/SAPO;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

5. **Acceder a la aplicación**

Abrir en el navegador: `http://tu-servidor/SAPO`

Credenciales por defecto:
- Usuario: `admin`
- Contraseña: `admin123`

**⚠️ IMPORTANTE: Cambiar la contraseña del admin inmediatamente después del primer acceso**

6. **Configurar rutas**

Desde el panel de administración:
- **Ruta base**: Directorio raíz donde están los directorios de las emisoras
- **Carpeta de suscripciones**: Nombre de la carpeta (por defecto: `Suscripciones`)

## 📖 Uso

### Panel de Administración

El usuario admin puede:
- ✅ Crear nuevas emisoras (usuarios)
- ✅ Asignar nombre de emisora y credenciales
- ✅ Eliminar emisoras (excepto el admin principal)
- ✅ Configurar rutas globales del sistema
- ✅ Ver lista de todas las emisoras registradas

### Panel de Emisora

Cada emisora accede a un panel con 3 pestañas principales:

#### 1. Mis Podcasts

- **Agregar podcasts**: URL RSS, categoría, nombre personalizado, caducidad (1-365 días), límite de duración
- **Buscar podcasts**: Filtro en tiempo real por nombre
- **Ordenamiento alfabético**: Lista automáticamente ordenada
- **Editar podcasts**: Modificar categoría, nombre, caducidad, duración
- **Eliminar podcasts**: Borrado individual con confirmación
- **Estado de feeds**: Indicadores visuales de actividad
  - 🟢 Verde: Activo (≤30 días desde último episodio)
  - 🟠 Naranja: Poco activo (31-90 días)
  - 🔴 Rojo: Inactivo (>90 días)
- **Actualizar feeds**: Botón para refrescar estado de todos los feeds
- **Gestor de categorías**: Acceso al gestor avanzado (ver más abajo)

#### 2. Importar/Exportar

- **Importar serverlist.txt**: Carga masiva de podcasts desde archivo Podget
  - Validación: Solo archivos .txt, máximo 1 MB
  - Detección automática de podcasts nuevos
  - Asignación a categorías existentes
- **Exportar serverlist.txt**: Descarga del archivo actual
  - Formato: `categoria|url|nombre`
  - Nombre de archivo: `serverlist_{username}_{fecha}.txt`

#### 3. Descargas e Informes

- **Ejecutar descargas**: Botón para lanzar Podget en segundo plano
- **Estado de ejecución**: Verificación del log de Podget
- **Historial de descargas**: Visualización de episodios descargados
  - Selector de período: 7, 14, 30, 60, 90 días
  - Información: Fecha, hora, podcast, archivo
  - Carga dinámica vía AJAX
- **Informes diarios**: Acceso a informes consolidados generados automáticamente

### Gestor de Categorías (Avanzado)

Funcionalidad destacada de SAPO para gestión masiva de categorías:

**Características:**
- 📊 **Estadísticas por categoría**: Número de podcasts y archivos
- 📝 **Renombrar categorías**: Cambio de nombre con actualización automática
  - Renombra la carpeta física en el servidor
  - Actualiza serverlist.txt
  - Actualiza categorías en base de datos
  - Recordatorio para actualizar playlists en Radiobot
- 🔍 **Ver archivos**: Listado de archivos MP3 por categoría
  - Nombre, tamaño, fecha de modificación
  - Ordenado por fecha (más reciente primero)
- 🗑️ **Eliminar categorías vacías**: Solo si no tienen podcasts ni archivos
- ⚠️ **Alertas y confirmaciones**: Prevención de errores

**Nota**: Los administradores no pueden usar el gestor de categorías. Solo usuarios de emisora.

## 🔧 Configuración avanzada

### Formato serverlist.txt

```
Noticias|https://ejemplo.com/feed.rss|Podcast de Noticias
Deportes|https://ejemplo.com/deportes.rss|Resumen Deportivo
Cultura|https://ejemplo.com/cultura.rss|Programa Cultural
```

Formato: `categoria|url_rss|nombre_podcast`

### Formato caducidades.txt

```
Podcast de Noticias:7
Resumen Deportivo:14
Programa Cultural:30
```

Formato: `nombre_podcast:dias`
- Días: 1-365
- Por defecto: 30 días si no está especificado

### Formato duraciones.txt

```
Podcast de Noticias:30M
Resumen Deportivo:1H
Programa Cultural:2H
```

Formato: `nombre_podcast:limite`
- Límites disponibles: 30M, 1H, 1H30, 2H, 2H30, 3H
- Sin límite si no está especificado
- Verificación automática con ffprobe (tolerancia +5 minutos)

### Variables de configuración (config.php)

```php
// Seguridad
define('DB_FILE', 'db.json');                  // Archivo de base de datos
define('MAX_LOGIN_ATTEMPTS', 5);               // Intentos de login permitidos
define('LOCKOUT_TIME', 900);                   // Tiempo de bloqueo (15 min)
define('SESSION_TIMEOUT', 1800);               // Timeout de sesión (30 min)

// Directorio del proyecto
define('PROJECT_DIR', dirname(__FILE__));
define('INCLUDES_DIR', PROJECT_DIR . '/includes');

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Mensajes de error
define('ERROR_INVALID_TOKEN', 'Token de seguridad inválido...');
define('ERROR_RATE_LIMIT', 'Demasiadas peticiones...');
define('ERROR_AUTH_FAILED', 'Usuario o contraseña incorrectos.');
define('ERROR_LOCKED_ACCOUNT', 'Cuenta bloqueada temporalmente...');
```

## 🔒 Seguridad

SAPO implementa múltiples capas de seguridad. Ver [SECURITY.md](SECURITY.md) para detalles completos:

- ✅ **BCrypt** para contraseñas (cost factor 10)
- ✅ **Control de intentos de login** (5 intentos, bloqueo 15 min)
- ✅ **CSRF protection** con tokens únicos por sesión
- ✅ **Rate limiting** (20 peticiones/minuto por acción)
- ✅ **Validación estricta de entrada** (usernames, URLs, paths)
- ✅ **Sanitización de nombres** (prevención de directory traversal)
- ✅ **Validación de uploads** (1 MB máx, solo .txt)
- ✅ **Headers de seguridad HTTP**:
  - X-Content-Type-Options: nosniff
  - X-Frame-Options: SAMEORIGIN
  - X-XSS-Protection: 1; mode=block
  - Referrer-Policy: strict-origin-when-cross-origin
  - Content-Security-Policy (configurado)
- ✅ **Sesiones seguras** (HTTPOnly, SameSite Strict, timeout 30 min)
- ✅ **Regeneración de session ID** cada hora
- ✅ **Path traversal protection** en todas las operaciones de archivos
- ✅ **Escape HTML** en todas las salidas (XSS prevention)

## 📊 Versiones

## 📊 Versiones

### v1.2.0 (Noviembre 2024) - Actual
- ⏸️ **Pausar/Reanudar podcasts**: Nueva funcionalidad para pausar descargas sin eliminar la suscripción
- 🔄 **Renombrado automático de carpetas**: Al cambiar el nombre de un podcast, la carpeta física se renombra conservando todos los archivos
- 🔍 **Búsqueda mejorada**: Buscar podcasts en toda la base de datos, no solo en los 25 de la página actual
- 🎯 **Filtrado por categoría mejorado**: Muestra todos los podcasts de una categoría, ignorando la paginación
- ⏱️ **Auto-ocultación de mensajes**: Los mensajes de alerta desaparecen automáticamente después de 5 segundos
- 🎨 **Mejoras visuales**: Badge de "PAUSADO" en rojo para mayor visibilidad
- 📝 **Ayuda actualizada**: Documentación completa de todas las funcionalidades
- 🐛 **Correcciones**: Múltiples fixes en paginación, filtrado y sintaxis PHP

### v1.1.0 (Noviembre 2024)
- 🔒 **[CRÍTICO] Corrección de vulnerabilidad XXE** en feed.php (LIBXML_NOENT → LIBXML_NONET)
- 🔒 Agregado header Content-Security-Policy faltante
- 🔒 Agregado header Strict-Transport-Security (HSTS) condicional para HTTPS
- 📝 Logging mejorado de intentos SSRF y XXE con detalles de seguridad
- 🧪 Script de testing para verificar feeds RSS (test_feeds.php)
- ✅ Corrección de vista `podget_status.php` faltante
- ✅ README actualizado con información precisa del código
- ✅ Footer con nombre de proyecto y versión
- 📚 SECURITY.md actualizado con detalles técnicos de protección XXE

### v1.0 beta (Noviembre 2024)
- ✅ Gestor avanzado de categorías (renombrado, mover archivos, estadísticas)
- ✅ Sistema de informes diarios automáticos
- ✅ Historial de descargas con múltiples períodos
- ✅ Control de duración de podcasts
- ✅ Mejoras de seguridad: validación de uploads, permisos 0640
- ✅ Interfaz con pestañas en panel de usuario
- ✅ Búsqueda en tiempo real de podcasts
- ✅ Headers de seguridad unificados
- ✅ Favicon con icono de SAPO
- ✅ Sistema de base de datos JSON unificada
- ✅ Funcionalidades básicas de gestión de podcasts
- ✅ Autenticación con BCrypt y multi-usuario
- ✅ Integración básica con Podget

## 🗺️ Roadmap

Ver [ROADMAP_v2.0.md](ROADMAP_v2.0.md) para la hoja de ruta completa de la versión 2.0.

**Próximas funcionalidades planificadas:**
- 🔄 Integración completa de cliente_rrll.sh en PHP
- 🎵 Procesamiento de archivos (renombrado automático, corrección de extensiones)
- 🧹 Limpieza automática de duplicados
- 📁 Soporte de subcarpetas jerárquicas
- 📺 Descarga de YouTube con yt-dlp
- 🔌 Integración API AzuraCast (detección de playlists vacías)
- 📊 Informes mejorados con emisiones en directo

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios con mensajes descriptivos
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Licencia

Este proyecto es de uso interno para emisoras de radio. Contactar con el autor para más información sobre licenciamiento.

## 👨‍💻 Autor

Desarrollado para automatizar la gestión de podcasts en emisoras de radio que utilizan Radiobot/AzuraCast.

## 🐛 Reporte de problemas

Si encuentras algún problema o tienes sugerencias, por favor abre un issue en el repositorio de GitHub con:
- Descripción del problema
- Pasos para reproducirlo
- Comportamiento esperado vs comportamiento actual
- Logs relevantes (si aplica)

## 📞 Soporte

Para soporte técnico o consultas, contactar a través del repositorio de GitHub.

---

**SAPO** 🐸 - Sistema de Automatización de Podcasts para Radiobot
