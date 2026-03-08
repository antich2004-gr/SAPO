<?php
/**
 * cache.php - Sistema de caché centralizado para SAPO
 * Reduce la carga del servidor cacheando contenido generado
 * Portado desde GRILLO
 */

/**
 * Directorio base para archivos de caché
 */
define('CACHE_DIR', __DIR__ . '/../cache');

/**
 * Obtener un valor del caché
 *
 * @param string $key Clave del caché
 * @param int $ttl Tiempo de vida en segundos (0 = sin expiración)
 * @return mixed|null Contenido cacheado o null si no existe o expiró
 */
function cacheGet($key, $ttl = 0) {
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.cache';

    if (!file_exists($cacheFile)) {
        return null;
    }

    // Verificar TTL si está definido
    if ($ttl > 0) {
        $age = time() - filemtime($cacheFile);
        if ($age > $ttl) {
            // Cache expirado, eliminar
            @unlink($cacheFile);
            return null;
        }
    }

    $content = @file_get_contents($cacheFile);
    if ($content === false) {
        return null;
    }

    $decoded = json_decode($content, true);
    // Si json_decode falla (ej. archivo legacy con serialize), tratar como miss
    if ($decoded === null && $content !== 'null') {
        @unlink($cacheFile);
        return null;
    }

    return $decoded;
}

/**
 * Guardar un valor en el caché
 *
 * @param string $key Clave del caché
 * @param mixed $data Datos a cachear
 * @return bool true si se guardó correctamente
 */
function cacheSet($key, $data) {
    // Asegurar que existe el directorio de caché
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
    }

    $cacheFile = CACHE_DIR . '/' . md5($key) . '.cache';
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

    return @file_put_contents($cacheFile, $encoded, LOCK_EX) !== false;
}

/**
 * Invalidar un valor del caché
 *
 * @param string $key Clave del caché a invalidar
 * @return bool true si se eliminó correctamente
 */
function cacheInvalidate($key) {
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.cache';

    if (file_exists($cacheFile)) {
        return @unlink($cacheFile);
    }

    return true;
}

/**
 * Invalidar todos los cachés de un usuario
 * Útil cuando se modifican programas
 *
 * @param string $username Nombre de usuario
 * @return int Número de archivos eliminados
 */
function cacheInvalidateUser($username) {
    $count = 0;

    // Patrones a invalidar
    $patterns = [
        "schedule_{$username}",
        "parrilla_html_{$username}",
        "programs_{$username}"
    ];

    foreach ($patterns as $pattern) {
        $cacheFile = CACHE_DIR . '/' . md5($pattern) . '.cache';
        if (file_exists($cacheFile)) {
            if (@unlink($cacheFile)) {
                $count++;
            }
        }
    }

    return $count;
}

/**
 * Limpiar cachés antiguos (más de 24 horas)
 * Útil para ejecutar periódicamente via cron
 *
 * @return int Número de archivos eliminados
 */
function cachePurgeOld() {
    $count = 0;
    $maxAge = 86400; // 24 horas

    if (!is_dir(CACHE_DIR)) {
        return 0;
    }

    $files = glob(CACHE_DIR . '/*.cache');
    if ($files === false) {
        return 0;
    }

    foreach ($files as $file) {
        $age = time() - filemtime($file);
        if ($age > $maxAge) {
            if (@unlink($file)) {
                $count++;
            }
        }
    }

    return $count;
}

/**
 * Wrapper para cachear el resultado de una función
 *
 * @param string $key Clave del caché
 * @param callable $callback Función a ejecutar si no hay caché
 * @param int $ttl Tiempo de vida en segundos
 * @return mixed Resultado de la función o caché
 */
function cacheRemember($key, $callback, $ttl = 600) {
    $cached = cacheGet($key, $ttl);

    if ($cached !== null) {
        return $cached;
    }

    $result = $callback();
    cacheSet($key, $result);

    return $result;
}
