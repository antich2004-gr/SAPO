# Guía de Seguridad - SAPO

## 🔒 Resumen de Seguridad

SAPO implementa múltiples capas de seguridad para proteger los datos de las emisoras y prevenir ataques comunes. Este documento describe las medidas de seguridad implementadas y las mejores prácticas de configuración.

---

## 🛡️ Medidas de Seguridad Implementadas

### 1. Autenticación y Gestión de Sesiones

#### Características:
- ✅ **Contraseñas hasheadas con BCrypt** (cost factor 10)
- ✅ **Control de intentos de login**: Máximo 5 intentos fallidos
- ✅ **Bloqueo temporal**: 15 minutos tras exceder intentos
- ✅ **Timeout de sesión**: 30 minutos de inactividad
- ✅ **Regeneración periódica de session ID**: Cada hora
- ✅ **Cookies HTTPOnly**: Previene acceso desde JavaScript
- ✅ **SameSite Strict**: Previene CSRF mediante cookies
- ✅ **Session destroy** tras timeout de inactividad

#### Configuración (config.php):
```php
define('MAX_LOGIN_ATTEMPTS', 5);      // Intentos permitidos
define('LOCKOUT_TIME', 900);          // 15 minutos de bloqueo
define('SESSION_TIMEOUT', 1800);      // 30 minutos de timeout
```

---

### 2. Protección CSRF (Cross-Site Request Forgery)

#### Implementación:
- ✅ Token CSRF único por sesión
- ✅ Validación obligatoria en todas las acciones POST (excepto login/logout)
- ✅ Tokens regenerados tras login exitoso
- ✅ Validación mediante `hash_equals()` (previene timing attacks)

#### Uso:
Todos los formularios incluyen:
```html
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
```

---

### 3. Rate Limiting

#### Características:
- ✅ Límite de 20 peticiones por minuto por acción
- ✅ Control basado en sesión (no IP, para evitar problemas con NAT)
- ✅ Ventana deslizante de 60 segundos
- ✅ Aplica a todas las acciones excepto login/logout

#### Configuración:
```php
checkRateLimit($action, 20, 60); // 20 peticiones en 60 segundos
```

---

### 4. Validación y Sanitización de Entrada

#### Funciones implementadas:

**validateInput($input, $type)**
- `username`: Solo alfanuméricos y guión bajo (3-50 caracteres)
- `url`: Validación estricta con `FILTER_VALIDATE_URL`
- `path`: Caracteres permitidos para rutas de sistema
- `text`: Validación de longitud (max 255 por defecto)

**sanitizePodcastName($text)**
- Transliteración de caracteres especiales
- Eliminación de caracteres peligrosos
- Prevención de directory traversal

**htmlEsc($text)**
- Escape HTML completo con `ENT_QUOTES`
- Prevención de XSS en salidas

---

### 5. Headers de Seguridad HTTP

SAPO establece los siguientes headers de seguridad:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';
```

#### Explicación:
- **X-Content-Type-Options**: Previene MIME sniffing
- **X-Frame-Options**: Previene clickjacking
- **X-XSS-Protection**: Protección XSS del navegador
- **Referrer-Policy**: Control de información de referencia
- **Content-Security-Policy**: Previene carga de recursos externos maliciosos

---

### 6. Protección de Archivos y Directorios

#### .htaccess (Apache):
- ✅ Deshabilitado listado de directorios
- ✅ Bloqueo de acceso directo a archivos .json y config.php
- ✅ Protección de directorios `/includes/` y `/db/`
- ✅ Prevención de inyección NULL byte
- ✅ Ocultación de información del servidor

#### Permisos recomendados:
```bash
# Directorios
chmod 755 /sapo
chmod 755 /sapo/includes
chmod 755 /sapo/views
chmod 755 /sapo/assets
chmod 755 /sapo/db
chmod 755 /sapo/db/users

# Archivos PHP
chmod 644 *.php
chmod 644 includes/*.php
chmod 644 views/*.php

# Archivos de configuración
chmod 600 config.php

# Base de datos
chmod 666 db/global.json          # Necesita escritura por web server
chmod 666 db/feed_cache.json
chmod 666 db/users/*.json

# Archivos de sistema
chmod 644 .htaccess
chmod 644 README.md
```

---

### 7. Separación de Datos por Usuario

#### Arquitectura:
- Cada emisora tiene su propio archivo JSON (`db/users/{username}.json`)
- Usuarios solo pueden acceder a sus propios datos
- Verificación de propiedad en cada operación
- Prevención de escalada de privilegios

#### Prevención de Path Traversal:
```php
// Validación estricta de username
if (!validateInput($username, 'username')) {
    die('Invalid username');
}
```

---

### 8. Protección Contra Inyecciones

#### SQL Injection:
✅ **No aplica** - SAPO no usa base de datos SQL

#### NoSQL/JSON Injection:
✅ **Prevención mediante**:
- Uso de `json_encode()` y `json_decode()` nativos de PHP
- Sin interpolación directa de strings en JSON
- Validación de tipos antes de guardar

#### Command Injection:
✅ **Prevención mediante**:
- Sin uso de `exec()`, `system()`, `shell_exec()`
- Llamadas a podget mediante scripts controlados
- Sanitización de nombres de archivo y rutas

#### XML External Entity (XXE):
✅ **Prevención mediante**:
- Uso de `libxml_use_internal_errors(true)`
- Sin carga de entidades externas
- Timeout en lectura de feeds RSS (5 segundos)

---

### 9. Gestión de Feeds RSS Externos

#### Medidas de seguridad:
- ✅ Timeout de 5 segundos en peticiones HTTP
- ✅ User-Agent identificativo: `SAPO-Radiobot/1.0`
- ✅ Validación estricta de URLs
- ✅ Manejo de errores con `@` y verificación posterior
- ✅ Limpieza de errores XML con `libxml_clear_errors()`
- ✅ Cache para reducir peticiones externas (12 horas por defecto)

---

### 10. Logging y Auditoría

#### Configuración actual:
```php
ini_set('display_errors', 0);      // Ocultar errores al usuario
ini_set('log_errors', 1);          // Registrar errores en log
error_reporting(E_ALL);            // Reportar todos los errores
```

#### Eventos registrados:
- Errores de PHP (error_log)
- Intentos fallidos de login (db/global.json)
- Acciones de administración

---

## 🔧 Configuración de Producción

### Lista de verificación pre-producción:

#### 1. SSL/TLS (HTTPS)
```php
// En config.php, cambiar:
ini_set('session.cookie_secure', 1);  // Cambiar de 0 a 1
```

```apache
# En .htaccess, descomentar:
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

#### 2. Permisos de archivos
```bash
# Ejecutar en el servidor:
find /ruta/sapo -type d -exec chmod 755 {} \;
find /ruta/sapo -type f -exec chmod 644 {} \;
chmod 600 /ruta/sapo/config.php
chmod 666 /ruta/sapo/db/*.json
chmod 666 /ruta/sapo/db/users/*.json
```

#### 3. Cambiar credenciales por defecto
- ✅ Cambiar contraseña de admin inmediatamente
- ✅ Usar contraseñas fuertes (mínimo 12 caracteres)
- ✅ Incluir mayúsculas, minúsculas, números y símbolos

#### 4. Configurar base_path correctamente
- ✅ Usar rutas absolutas
- ✅ Verificar permisos de escritura
- ✅ Asegurar que el directorio no sea accesible vía web

#### 5. Deshabilitar funciones peligrosas de PHP
```ini
# En php.ini:
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

#### 6. Actualizar regularmente
```bash
git pull origin main  # Mantener SAPO actualizado
```

---

## 🚨 Reporte de Vulnerabilidades

Si encuentras una vulnerabilidad de seguridad:

1. **NO** la publiques públicamente
2. Contacta mediante el repositorio de GitHub (issue privado)
3. Incluye:
   - Descripción detallada de la vulnerabilidad
   - Pasos para reproducirla
   - Impacto potencial
   - Sugerencia de solución (si la tienes)

---

## 📋 Checklist de Seguridad

### Al instalar:
- [ ] Cambiar contraseña de admin
- [ ] Configurar SSL/TLS (HTTPS)
- [ ] Activar session.cookie_secure
- [ ] Configurar permisos correctos
- [ ] Verificar que .htaccess está activo
- [ ] Comprobar que directorios protegidos no son accesibles

### Mantenimiento periódico:
- [ ] Revisar logs de errores semanalmente
- [ ] Actualizar SAPO cuando haya nuevas versiones
- [ ] Verificar permisos de archivos mensualmente
- [ ] Cambiar contraseñas cada 90 días (recomendado)
- [ ] Revisar usuarios activos trimestralmente

### Monitoreo:
- [ ] Verificar intentos de login fallidos
- [ ] Comprobar tamaño de archivos JSON (posible abuso)
- [ ] Revisar logs de Apache/PHP por actividad sospechosa

---

## 🛠️ Herramientas de Auditoría Recomendadas

- **OWASP ZAP**: Escáner de vulnerabilidades web
- **Nikto**: Escáner de servidores web
- **PHP Security Checker**: Verificar vulnerabilidades en código PHP
- **Mozilla Observatory**: Verificar headers de seguridad

---

## 📚 Referencias

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [Session Management Best Practices](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [CSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)

---

**Última actualización**: 2025-01-05 (v2.0-separated-db)
