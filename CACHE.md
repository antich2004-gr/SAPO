# 🚀 Sistema de Caché de SAPO

SAPO incluye un sistema de caché centralizado que mejora significativamente el rendimiento de la parrilla pública.

---

## 📊 Beneficios

### Antes del caché:
- ⏱️ Tiempo de carga: ~300-500ms
- 🔄 Queries por request: 5-8 queries
- 💾 CPU: Alta (generación constante)

### Con caché:
- ⏱️ Primera carga (MISS): ~300-500ms
- ⏱️ Cargas desde caché (HIT): ~5-20ms (95-98% más rápido)
- 🔄 Queries por request (HIT): 0 queries
- 💾 CPU: Mínima (solo cada 3 minutos)

### Capacidad estimada:
- **Sin caché:** ~10-20 requests/segundo
- **Con caché:** ~500-1000 requests/segundo

---

## ⚙️ Cómo funciona

### 1. **Caché de HTML completo**
`parrilla_cards.php` cachea la salida HTML completa durante **3 minutos**.

- Al recibir una petición, primero intenta servir desde caché
- Si existe caché válido → responde inmediatamente (header `X-Cache: HIT`)
- Si no existe → genera HTML, lo cachea, y responde (header `X-Cache: MISS`)

### 2. **Invalidación automática**
El caché se invalida automáticamente cuando:
- Se crea un programa nuevo
- Se actualiza un programa existente
- Se elimina un programa

Esto garantiza que los cambios se reflejen inmediatamente sin servir datos obsoletos.

### 3. **Headers HTTP**
```
Cache-Control: public, max-age=120
Expires: [fecha]
X-Cache: HIT/MISS
```

Los navegadores cachean el contenido durante **2 minutos**, reduciendo peticiones al servidor.

---

## 🔍 Verificar que funciona

### Opción 1: Headers HTTP
```bash
curl -I "https://tudominio.com/sapo/parrilla_cards.php?station=tu_emisora"
```

Busca el header `X-Cache`:
- `X-Cache: HIT` → Servido desde caché ✅
- `X-Cache: MISS` → Generado en tiempo real

### Opción 2: Logs del servidor
```bash
tail -f /var/log/apache2/access.log | grep "X-Cache"
```

### Opción 3: DevTools del navegador
1. Abre la parrilla en el navegador
2. F12 → Network → Recarga la página
3. Busca `parrilla_cards.php`
4. En Response Headers verás `X-Cache: HIT` o `MISS`

---

## 🛠️ Archivos del sistema de caché

```
SAPO/
├── cache/              # Directorio de archivos de caché
│   ├── .gitkeep        # Mantiene el directorio en Git
│   └── *.cache         # Archivos de caché (ignorados por Git)
├── includes/
│   └── cache.php       # Sistema de caché centralizado
├── parrilla_cards.php  # Usa caché de HTML
└── includes/programs.php # Invalida caché al modificar programas
```

---

## 🧹 Mantenimiento

### Limpiar cachés antiguos

Los cachés antiguos (>24 horas) se pueden limpiar automáticamente con un cron job:

```bash
# Ejecutar a las 3 AM cada día
0 3 * * * cd /var/www/sapo && php -r "require 'includes/cache.php'; cachePurgeOld();"
```

O crear un script manual:

```bash
cd /var/www/sapo
php -r "require 'includes/cache.php'; \$deleted = cachePurgeOld(); echo \"Cachés eliminados: \$deleted\n\";"
```

### Invalidar caché manualmente

Si necesitas forzar regeneración de la parrilla:

```bash
cd /var/www/sapo
rm -f cache/*.cache
```

O para un usuario específico desde PHP:

```php
<?php
require_once 'includes/cache.php';
cacheInvalidateUser('nombre_usuario');
```

### Forzar refresh en el navegador

Añade `?refresh=1` a la URL (máximo 1 vez cada 5 minutos por IP):

```
https://tudominio.com/sapo/parrilla_cards.php?station=tu_emisora&refresh=1
```

---

## 🔧 Funciones disponibles

### `cacheGet($key, $ttl)`
Obtener un valor del caché.

```php
$data = cacheGet('mi_clave', 600); // 10 minutos TTL
if ($data === null) {
    // No existe o expiró
}
```

### `cacheSet($key, $data)`
Guardar un valor en el caché.

```php
cacheSet('mi_clave', $misDatos);
```

### `cacheInvalidate($key)`
Invalidar una entrada específica.

```php
cacheInvalidate('mi_clave');
```

### `cacheInvalidateUser($username)`
Invalidar todo el caché de un usuario (schedule, parrilla HTML, programs).

```php
cacheInvalidateUser('mi_emisora');
```

### `cacheRemember($key, $callback, $ttl)`
Wrapper para cachear el resultado de una función.

```php
$result = cacheRemember('heavy_computation', function() {
    return computeSomethingExpensive();
}, 600);
```

### `cachePurgeOld()`
Limpiar cachés antiguos (>24 horas). Retorna número de archivos eliminados.

```php
$deleted = cachePurgeOld();
```

---

## 🐛 Troubleshooting

### El caché no se invalida

Verificar permisos del directorio cache:

```bash
chmod 755 cache/
chmod 644 cache/*
```

### Caché crece demasiado

Ejecutar limpieza manual:

```bash
find cache/ -name "*.cache" -mtime +1 -delete
```

### Programas no se actualizan

El caché se invalida automáticamente al guardar programas. Si persiste, verifica que `includes/programs.php` tenga:

```php
require_once __DIR__ . '/cache.php';
```

Y que `saveProgramsDB()` incluya:

```php
cacheInvalidateUser($username);
```

### Error "CACHE_DIR not defined"

Asegúrate de que `includes/cache.php` esté incluido antes de usar funciones de caché:

```php
require_once INCLUDES_DIR . '/cache.php';
```

---

## 📈 Recomendaciones adicionales

### 1. PHP OpCache

Activar OpCache en `php.ini` para cachear código PHP compilado:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### 2. Compresión GZIP

Ya está activada en `parrilla_cards.php` mediante `ob_gzhandler()`.

### 3. CDN (opcional)

Para tráfico muy elevado, considera usar Cloudflare:
- Caché adicional en edge
- Protección DDoS
- Compresión Brotli

### 4. Monitoreo

Ver hit rate del caché:

```bash
tail -f /var/log/apache2/access.log | grep "X-Cache: HIT" | wc -l
tail -f /var/log/apache2/access.log | grep "X-Cache: MISS" | wc -l
```

Objetivo: >90% HIT rate después de la primera carga.

---

## 📞 Soporte

Si tienes problemas con el rendimiento:

1. Verifica logs: `tail -f error_log | grep PERFORMANCE`
2. Revisa headers X-Cache en respuestas HTTP
3. Comprueba tamaño de cache: `du -sh cache/`
4. Verifica permisos: `ls -la cache/`

---

## 🎯 Migrado desde GRILLO

Este sistema de caché fue portado desde [GRILLO](https://github.com/antich2004-gr/grillo) y adaptado a la arquitectura de SAPO.

**Diferencias:**
- GRILLO usa MySQL → SAPO usa JSON files
- GRILLO es standalone → SAPO integra con AzuraCast
- Mismo principio de caché HTML completo
