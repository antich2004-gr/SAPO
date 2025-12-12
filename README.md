# SAPO - Sistema de Administración de Podcasts y Organización

Sistema integral de gestión de podcasts y parrillas de programación para emisoras de radio que utilizan AzuraCast.

## 📋 Descripción

SAPO es una aplicación web PHP que permite a las emisoras de radio gestionar eficientemente sus podcasts, parrillas de programación y contenidos RSS. Diseñado específicamente para integrarse con AzuraCast, SAPO automatiza la descarga, organización y publicación de podcasts, además de proporcionar una interfaz visual para la programación de emisoras.

## ✨ Características Principales

### 🎙️ Gestión de Podcasts
- **Suscripción a feeds RSS** con actualización automática
- **Descarga automatizada** de episodios mediante podget
- **Categorización flexible** de podcasts por temáticas
- **Gestión de caducidad** personalizada por podcast
- **Control de duración** para evitar contenidos excesivamente largos
- **Renombrado automático** de archivos según estándares
- **Filtrado por actividad** (últimas 24h, 7 días, 30 días, inactivos)

### 📅 Parrilla de Programación
- **Vista de calendario interactiva** con FullCalendar
- **Gestión de programas** con horarios y descripción
- **Badge "EN DIRECTO"** clickeable al stream en vivo
- **Vista embebida** para integración en sitios web
- **Sincronización con AzuraCast** para obtener información de programación

### 🔐 Seguridad
- **Autenticación robusta** con BCrypt (cost factor 10)
- **Protección CSRF** en todos los formularios
- **Rate limiting** para prevenir ataques de fuerza bruta
- **Control de intentos de login** (máx. 5 intentos, bloqueo de 15 min)
- **Timeout de sesión** (30 minutos de inactividad)
- **Sanitización de entradas** para prevenir XSS e inyección SQL
- **Logging de seguridad** con registro de eventos críticos

### 👥 Multi-usuario
- **Roles de administrador y usuario estándar**
- **Gestión independiente** por emisora
- **Panel de administración** para configuración global
- **Configuración individual** por emisora (API AzuraCast, rutas, etc.)

### 📊 Reportes e Informes
- **Informes diarios automáticos** de descargas y eliminaciones
- **Estadísticas de actividad** por podcast
- **Detección de errores** en feeds RSS
- **Histórico de operaciones** (365 días)

## 🛠️ Requisitos

### Servidor
- **PHP**: >= 7.4
- **Extensiones PHP**: json, curl, mbstring, session
- **Servidor web**: Apache o Nginx
- **Base de datos**: Sistema de archivos JSON (no requiere MySQL)

### Sistema Operativo
- **GNU/Linux**: Debian/Ubuntu recomendado
- **Herramientas CLI**: bash, awk, find, ffprobe, podget

### Integración
- **AzuraCast**: Versión actual (API v1)
- **Estructura de directorios AzuraCast** en `/mnt/emisoras/`

## 📦 Instalación

### 1. Clonar el repositorio

```bash
cd /var/www/html
git clone https://github.com/antich2004-gr/SAPO.git
cd SAPO
```

### 2. Configurar permisos

```bash
# Ajustar propietario
sudo chown -R www-data:www-data .

# Permisos de archivos
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;

# Permisos de carpetas de datos
sudo chmod 775 data/
sudo chmod 664 db.json
```

### 3. Configurar carpetas de emisoras

```bash
cd /mnt/emisoras
sudo find . -path "*/media/Suscripciones" -type d -exec chown radioslibres:www-data {} \;
sudo find . -path "*/media/Suscripciones" -type d -exec chmod 2775 {} \;
sudo find . -path "*/media/Suscripciones/*" -type f -exec chmod 664 {} \;
```

### 4. Configurar Apache

Crear archivo `/etc/apache2/sites-available/sapo.conf`:

```apache
<VirtualHost *:80>
    ServerName sapo.example.com
    DocumentRoot /var/www/html/SAPO

    <Directory /var/www/html/SAPO>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/sapo_error.log
    CustomLog ${APACHE_LOG_DIR}/sapo_access.log combined
</VirtualHost>
```

Activar el sitio:

```bash
sudo a2ensite sapo
sudo systemctl reload apache2
```

### 5. Configuración inicial

Acceder a `http://sapo.example.com` y:

1. Crear usuario administrador
2. Configurar rutas base en panel de administración:
   - Ruta base: `/mnt/emisoras`
   - Carpeta suscripciones: `Suscripciones`
   - Carpeta podcasts: `Podcasts` (o `Podcast` según servidor)

3. Configurar cada emisora:
   - API URL de AzuraCast
   - API Key
   - Nombre de usuario local

## 🚀 Uso

### Panel de Usuario

1. **Agregar podcast**: Introducir URL del feed RSS
2. **Asignar categoría**: Organizar podcasts por temática
3. **Configurar caducidad**: Definir días de retención
4. **Actualizar feeds**: Botón para forzar actualización manual
5. **Gestionar podcasts**: Pausar, reanudar, editar o eliminar

### Panel de Administración

1. **Gestión de usuarios**: Crear, editar o eliminar cuentas
2. **Configuración global**: Rutas, carpetas, parámetros del sistema
3. **Configuración AzuraCast**: URLs y API keys por emisora
4. **Reportes**: Visualizar actividad del sistema

### Parrilla de Programación

1. **Vista calendario**: Visualización mensual de programación
2. **Añadir programa**: Click en día/hora para crear evento
3. **Editar programa**: Click en evento existente
4. **Configurar stream**: URL de página pública para badge "EN DIRECTO"

### Scripts de Cliente RRLL

Ejecutar manualmente o mediante cron:

```bash
cd cliente_rrll
./cliente_rrll.sh --emisora NOMBRE_EMISORA
```

O para todas las emisoras:

```bash
./cliente_rrll_todas.sh
```

## 📂 Estructura del Proyecto

```
SAPO/
├── index.php                  # Punto de entrada principal
├── config.php                 # Configuración global
├── db.json                    # Base de datos JSON
├── .htaccess                  # Configuración Apache
├── assets/                    # Recursos estáticos
│   ├── app.js                 # JavaScript principal
│   ├── style.css              # Estilos CSS
│   └── fullcalendar.min.js    # Librería calendario
├── includes/                  # Módulos PHP
│   ├── auth.php               # Autenticación
│   ├── database.php           # Gestión de datos
│   ├── podcasts.php           # Lógica de podcasts
│   ├── categories.php         # Gestión de categorías
│   ├── programs.php           # Gestión de programas
│   ├── azuracast.php          # Integración AzuraCast
│   ├── feed.php               # Procesamiento RSS
│   ├── reports.php            # Generación de informes
│   ├── security_logger.php    # Logging de seguridad
│   └── utils.php              # Utilidades generales
├── views/                     # Vistas de interfaz
│   ├── user.php               # Panel de usuario
│   ├── admin.php              # Panel administrador
│   ├── parrilla.php           # Editor de parrilla
│   ├── parrilla_programs.php  # Gestión de programas
│   ├── help.php               # Ayuda general
│   └── help_parrilla.php      # Ayuda de parrilla
├── cliente_rrll/              # Scripts de automatización
│   ├── cliente_rrll.sh        # Script principal
│   ├── cliente_rrll_todas.sh  # Ejecutar todas emisoras
│   └── verifica_rss.sh        # Verificación de feeds
├── data/                      # Datos del sistema
│   └── programs/              # Datos de programas
├── parrilla_cards.php         # Vista pública parrilla
├── parrilla_cards_embed.php   # Vista embebida
├── cron_rss_preload.php       # Precarga de feeds RSS
├── SECURITY.md                # Guía de seguridad
└── README.md                  # Este archivo
```

## 🔧 Configuración Avanzada

### Cron Jobs Recomendados

```cron
# Actualización de podcasts (cada hora)
0 * * * * cd /var/www/html/SAPO/cliente_rrll && ./cliente_rrll_todas.sh

# Precarga de feeds RSS (cada 6 horas)
0 */6 * * * php /var/www/html/SAPO/cron_rss_preload.php

# Informes diarios (9:00 AM)
0 9 * * * cd /var/www/html/SAPO/cliente_rrll && ./envio_informe_rrll.sh
```

### Variables de Configuración (config.php)

```php
define('MAX_LOGIN_ATTEMPTS', 5);      // Intentos de login permitidos
define('LOCKOUT_TIME', 900);          // Bloqueo en segundos (15 min)
define('SESSION_TIMEOUT', 1800);      // Timeout sesión (30 min)
define('SESSION_REGENERATE_TIME', 3600); // Regenerar sesión (1 hora)
```

## 🔒 Seguridad

Consulta [SECURITY.md](SECURITY.md) para una guía detallada de las medidas de seguridad implementadas y mejores prácticas de configuración.

### Aspectos Destacados:
- ✅ Contraseñas hasheadas con BCrypt
- ✅ Protección CSRF
- ✅ Rate limiting
- ✅ Validación de entradas
- ✅ Logging de eventos críticos
- ✅ Headers de seguridad configurables

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:

1. Fork el repositorio
2. Crea una branch para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la branch (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Licencia

Este proyecto está bajo desarrollo para el uso interno de emisoras comunitarias.

## 🙏 Créditos

Desarrollado para y en colaboración con las emisoras de Radio Libres.

## 📞 Soporte

Para reportar bugs o solicitar features, por favor abre un issue en el repositorio de GitHub.

---

**Versión actual**: 1.2.5+
**Última actualización**: Diciembre 2025
