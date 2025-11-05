# Instrucciones para Migración a Base de Datos Separada

## ⚠️ IMPORTANTE - LEER ANTES DE EJECUTAR

Esta migración debe ejecutarse **UNA SOLA VEZ** y convierte la base de datos única (db.json) en una estructura de archivos separados para evitar problemas de concurrencia cuando múltiples usuarios trabajan simultáneamente.

## Estructura nueva que se creará

```
db/
├── global.json              # Usuarios, configuración, intentos de login
├── feed_cache.json          # Cache compartido de feeds RSS
└── users/
    ├── admin.json           # Categorías del admin (si tiene)
    ├── salto.json           # Categorías de la emisora Salto
    ├── sonora.json          # Categorías de la emisora Sonora
    └── radiobot.json        # Categorías de la emisora Radiobot
```

## Pasos para ejecutar la migración

### 1. Conectar al servidor

```bash
ssh usuario@tu-servidor
cd /ruta/a/SAPO
```

### 2. Verificar que existe db.json

```bash
ls -lh db.json
```

Deberías ver algo como:
```
-rw-rw-rw- 1 usuario grupo 12345 fecha db.json
```

### 3. Ejecutar el script de migración

```bash
php migrate_to_split_db.php
```

### 4. Verificar la salida

El script mostrará:
- Cantidad de usuarios encontrados
- Cantidad de categorías por usuario
- Cantidad de entradas en cache
- Creación de directorios
- Creación de archivos
- Resumen final

Ejemplo de salida esperada:
```
=== MIGRACIÓN DE BASE DE DATOS ===

1. Leyendo db.json actual...
   - Usuarios encontrados: 4
   - Categorías de usuarios: 3
   - Entradas en cache: X

2. Creando estructura de directorios...
   - Creado: /ruta/a/SAPO/db
   - Creado: /ruta/a/SAPO/db/users

3. Creando db/global.json...
   - Creado: /ruta/a/SAPO/db/global.json
   - Usuarios migrados: 4

4. Creando archivos de usuario individuales...
   - Creado: db/users/salto.json (X categorías)
   - Creado: db/users/sonora.json (X categorías)
   - Creado: db/users/radiobot.json (X categorías)

5. Creando db/feed_cache.json...
   - Creado: /ruta/a/SAPO/db/feed_cache.json
   - Entradas migradas: X

6. Creando backup del db.json original...
   - Backup creado: /ruta/a/SAPO/db.json.backup-2025-XX-XX-XXXXXX

=== MIGRACIÓN COMPLETADA ===
```

### 5. Verificar que se crearon los archivos

```bash
ls -lh db/
ls -lh db/users/
```

Deberías ver:
```
db/global.json
db/feed_cache.json
db/users/salto.json
db/users/sonora.json
db/users/radiobot.json
```

### 6. Verificar permisos

```bash
chmod 666 db/global.json
chmod 666 db/feed_cache.json
chmod 666 db/users/*.json
```

### 7. Probar el sistema

1. Acceder a la aplicación desde el navegador
2. Hacer login con cada usuario
3. Verificar que se ven las categorías correctamente
4. Probar añadir/eliminar una categoría
5. **IMPORTANTE**: Hacer login con DOS usuarios diferentes simultáneamente desde dos navegadores distintos
6. Hacer cambios con ambos usuarios a la vez
7. Verificar que no se pierden datos

### 8. Si todo funciona correctamente

El archivo db.json original quedó respaldado como:
```
db.json.backup-YYYY-MM-DD-HHMMSS
```

Puedes eliminar el db.json original:
```bash
rm db.json
```

O guardarlo como respaldo adicional:
```bash
mv db.json db.json.backup-manual
```

## 🔧 Solución de problemas

### Error: "No se encontró db.json"
- Verificar que estás en el directorio correcto
- Verificar que el archivo existe con `ls -la`

### Error: "Permission denied" al crear directorios
- Verificar permisos del directorio padre
- Ejecutar: `chmod 755 .`

### Error: "Failed to write file"
- Verificar permisos de escritura
- Verificar espacio en disco: `df -h`

### Los cambios no se reflejan
- Verificar permisos de los archivos (deben ser 666)
- Verificar que el servidor web puede escribir en db/

### Datos incorrectos después de la migración
- NO eliminar el backup
- Restaurar: `cp db.json.backup-YYYY-MM-DD-HHMMSS db.json`
- Eliminar: `rm -rf db/`
- Reportar el problema

## ⚠️ Rollback (volver atrás)

Si algo sale mal y necesitas volver al sistema anterior:

```bash
# 1. Detener el servidor web temporalmente (opcional)
sudo systemctl stop apache2  # o nginx/lighttpd según tu servidor

# 2. Eliminar la nueva estructura
rm -rf db/

# 3. Restaurar el backup
cp db.json.backup-YYYY-MM-DD-HHMMSS db.json

# 4. Reiniciar servidor web
sudo systemctl start apache2

# 5. Verificar que funciona
```

## Beneficios de la nueva estructura

✅ **Evita conflictos de concurrencia**: Cada emisora tiene su propio archivo
✅ **Mejor rendimiento**: Lecturas/escrituras más rápidas
✅ **Más seguro**: Los cambios de un usuario no afectan a otros
✅ **Más escalable**: Fácil añadir más usuarios sin impacto
✅ **Mejor organización**: Estructura clara y mantenible

## Contacto

Si tienes problemas durante la migración, guarda el mensaje de error completo y la salida del script.
