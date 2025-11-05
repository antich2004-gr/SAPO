# SAPO

**Sistema de Automatización de Podcasts para Radiobot**

Sistema web para la gestión automatizada de suscripciones de podcasts en múltiples emisoras de radio utilizando Radiobot como plataforma de reproducción.

## 📋 Descripción

SAPO es una aplicación web desarrollada en PHP que permite a múltiples emisoras de radio gestionar sus suscripciones a podcasts de forma independiente. El sistema descarga automáticamente nuevos episodios, los organiza por categorías y los integra con Radiobot para su reproducción automática.

### Características principales

- ✅ **Multi-usuario**: Cada emisora tiene su propio espacio independiente
- ✅ **Gestión de categorías**: Organización personalizada de podcasts por temas
- ✅ **Descarga automatizada**: Obtención automática de nuevos episodios vía RSS
- ✅ **Integración con Radiobot**: Generación de listas M3U compatibles
- ✅ **Sistema de caducidad**: Control de cuánto tiempo mantener episodios antiguos
- ✅ **Cache de feeds**: Optimización de consultas a feeds RSS
- ✅ **Control de concurrencia**: Múltiples emisoras pueden trabajar simultáneamente sin conflictos
- ✅ **Panel de administración**: Gestión centralizada de usuarios y configuración
- ✅ **Seguridad robusta**: CSRF protection, rate limiting, bloqueo por intentos fallidos

## 🏗️ Arquitectura

### Estructura de archivos

```
SAPO/
├── index.php                    # Controlador principal y router
├── config.php                   # Configuración global
├── includes/
│   ├── auth.php                 # Sistema de autenticación y seguridad
│   ├── categories.php           # Gestión de categorías
│   ├── database.php             # Capa de acceso a datos
│   ├── file_operations.php      # Operaciones con archivos
│   ├── podcast_functions.php    # Lógica de podcasts
│   └── rss_functions.php        # Parsing de feeds RSS
├── views/
│   ├── login.php                # Vista de login
│   ├── admin.php                # Panel de administración
│   └── user.php                 # Panel de emisora
├── assets/
│   ├── css/
│   │   └── style.css            # Estilos de la aplicación
│   └── js/
│       └── script.js            # JavaScript del frontend
└── db/
    ├── global.json              # Usuarios, configuración, login_attempts
    ├── feed_cache.json          # Cache compartido de feeds RSS
    └── users/
        ├── emisora1.json        # Categorías de emisora1
        ├── emisora2.json        # Categorías de emisora2
        └── ...

```

### Base de datos

SAPO utiliza un sistema de archivos JSON separados para evitar conflictos de concurrencia:

#### `db/global.json`
Contiene datos globales del sistema:
- **users**: Lista de usuarios (emisoras y admin)
- **config**: Configuración global (rutas, carpeta de suscripciones, duración de cache)
- **login_attempts**: Control de intentos fallidos de login

#### `db/feed_cache.json`
Cache compartido de feeds RSS para optimizar consultas y reducir peticiones a servidores externos.

#### `db/users/{username}.json`
Archivo individual por emisora conteniendo:
- **categories**: Categorías personalizadas de la emisora

### Archivos de emisora

Cada emisora tiene su propio directorio en el servidor con:
- `podcasts.txt`: Lista de podcasts suscritos con sus categorías
- `caducidades.txt`: Configuración de tiempo de retención por categoría
- `{categoria}.m3u`: Listas de reproducción M3U para Radiobot

## 🚀 Instalación

### Requisitos previos

- PHP 7.4 o superior
- Servidor web (Apache, Nginx, etc.)
- Extensiones PHP: json, curl, simplexml, mbstring
- Radiobot instalado en el servidor

### Pasos de instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/antich2004-gr/SAPO.git
cd SAPO
```

2. **Configurar permisos**
```bash
chmod 755 .
chmod 666 config.php
mkdir db
chmod 755 db
```

3. **Configurar el servidor web**

Ejemplo para Apache (`.htaccess`):
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

4. **Acceder a la aplicación**

Abrir en el navegador: `http://tu-servidor/SAPO`

Credenciales por defecto:
- Usuario: `admin`
- Contraseña: `admin123`

**⚠️ IMPORTANTE: Cambiar la contraseña del admin inmediatamente después del primer acceso**

5. **Configurar rutas**

Desde el panel de administración:
- **Ruta base**: Directorio raíz donde están los archivos de las emisoras
- **Carpeta de suscripciones**: Nombre de la carpeta donde se guardan los podcasts

## 📖 Uso

### Panel de Administración

El usuario admin puede:
- Crear nuevas emisoras (usuarios)
- Editar datos de emisoras existentes
- Eliminar emisoras
- Configurar rutas globales del sistema

### Panel de Emisora

Cada emisora puede:

1. **Gestionar categorías**
   - Crear categorías personalizadas
   - Eliminar categorías no utilizadas
   - Importar categorías desde podcasts.txt

2. **Añadir podcasts**
   - Pegar URL del feed RSS
   - Asignar a una categoría
   - El sistema descarga automáticamente los episodios

3. **Gestionar suscripciones**
   - Ver todas las suscripciones agrupadas por categoría
   - Editar categoría de un podcast
   - Eliminar suscripciones
   - Ver información del último episodio

4. **Actualizar feeds**
   - Botón "Actualizar estado" para refrescar todos los feeds
   - Indicadores visuales de actividad:
     - 🟢 Verde: Episodio reciente (< 15 días)
     - 🟠 Naranja: Episodio antiguo (15-30 días)
     - 🔴 Rojo: Podcast inactivo (> 30 días)

5. **Configurar caducidades**
   - Establecer cuántos días mantener episodios por categoría
   - Por defecto: 30 días

## 🔧 Configuración avanzada

### Estructura de podcasts.txt

```
Categoria1|https://feed1.rss|titulo1
Categoria2|https://feed2.rss|titulo2
```

### Estructura de caducidades.txt

```
Categoria1|30
Categoria2|45
```

### Variables de configuración (config.php)

```php
define('DB_FILE', __DIR__ . '/db.json');           // Archivo de base de datos (legacy)
define('MAX_LOGIN_ATTEMPTS', 5);                   // Intentos de login permitidos
define('LOCKOUT_TIME', 900);                       // Tiempo de bloqueo (segundos)
define('SESSION_TIMEOUT', 3600);                   // Timeout de sesión (segundos)
```

## 🔒 Seguridad

SAPO implementa múltiples capas de seguridad:

- **Autenticación con BCrypt**: Las contraseñas se almacenan hasheadas
- **Control de intentos de login**: Bloqueo temporal tras 5 intentos fallidos
- **Protección CSRF**: Tokens únicos por sesión
- **Rate limiting**: Control de frecuencia de acciones
- **Validación de entrada**: Sanitización de URLs y nombres de archivos
- **Sesiones seguras**: Timeout configurable y regeneración de ID
- **Separación de datos**: Cada emisora solo accede a sus propios datos

## 📊 Versiones

### v2.0-separated-db (Actual)
- Implementación de sistema de base de datos separada
- Resolución de problemas de concurrencia
- Mejora de rendimiento en operaciones concurrentes
- Estructura escalable con archivos individuales por usuario

### v1.0-stable
- Versión inicial estable
- Sistema de base de datos unificado (db.json)
- Funcionalidades básicas de gestión de podcasts

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Licencia

Este proyecto es de uso interno para emisoras de radio. Contactar con el autor para más información sobre licenciamiento.

## 👨‍💻 Autor

Desarrollado para automatizar la gestión de podcasts en emisoras de radio que utilizan Radiobot.

## 🐛 Reporte de problemas

Si encuentras algún problema o tienes sugerencias, por favor abre un issue en el repositorio de GitHub.

## 📞 Soporte

Para soporte técnico o consultas, contactar a través del repositorio de GitHub.

---

**SAPO** - Sistema de Automatización de Podcasts para Radiobot
