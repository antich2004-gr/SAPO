# SAPO v1.2.5 - Changelog

**Fecha de lanzamiento:** 2025-11-18

## 🎯 Resumen

Esta versión corrige múltiples bugs relacionados con la creación de nuevos usuarios y la gestión de podcasts, añade nuevas funcionalidades a la parrilla de programación, y mejora la experiencia de usuario.

---

## ✨ Nuevas Funcionalidades

### Parrilla de Programación
- **FEAT:** Badge "🔴 AHORA EN DIRECTO" convertido en enlace clickeable al stream
  - Nueva opción en configuración: "URL de la Página Pública del Stream"
  - Cuando se configura, el badge lleva directamente a la página de escucha de AzuraCast
  - Efecto hover mejorado en el enlace
  - Retrocompatible: sin URL configurada funciona como antes

### Gestión de Podcasts
- **FEAT:** Auto-detección de categorías desde podcasts existentes
  - Si un usuario no tiene categorías creadas pero sus podcasts tienen categorías asignadas, SAPO las detecta automáticamente
  - Especialmente útil para usuarios nuevos o migrados desde serverlist.txt

---

## 🐛 Correcciones de Bugs

### Problemas Críticos Resueltos

#### 1. Modal de Edición no Abría (Usuarios Nuevos)
- **Problema:** Al crear un nuevo usuario, el modal de editar podcast no se abría
- **Causa Raíz:**
  - Desalineación de índices entre PHP (sin ordenar) y JavaScript (ordenado)
  - Campos del formulario que no existían (categorías) causaban errores de JavaScript
- **Solución:**
  - Ordenamiento consistente alfabético en backend y frontend
  - Defensive programming: verificar existencia de elementos antes de manipularlos
  - Archivos modificados: `views/user.php`, `index.php`

#### 2. Funciones de Gestión con Índices Incorrectos
- **Problema:** Pausar/Reanudar/Eliminar/Editar afectaban al podcast incorrecto
- **Causa:** Mismo problema de desalineación de índices
- **Solución:**
  - Uso de URL como identificador único inmutable
  - Búsqueda por URL en lugar de índice
  - Archivos modificados: `includes/podcasts.php`

#### 3. Edición de Caducidad no Guardaba Correctamente
- **Problema:** Los días de caducidad se guardaban como "podcasts:60" en lugar del nombre correcto
- **Causa:** Permisos insuficientes en carpeta `/mnt/emisoras/*/media/Suscripciones/`
- **Solución:**
  - Documentación de permisos correctos (775 con grupo www-data)
  - Validación mejorada en `writeCaducidades()`

#### 4. Barra de Progreso de Actualización de Feeds
- **Problema:** La barra se detenía antes del 100% (ej: 58%) cuando algunos feeds fallaban
- **Causa:** Errores en feeds no actualizaban el contador de progreso
- **Solución:**
  - Actualizar progreso incluso si falla un feed individual
  - Ordenamiento consistente de podcasts en backend
  - Archivos modificados: `assets/app.js`, `index.php`

---

## 🧹 Limpieza y Mantenimiento

### Archivos Eliminados
- `test_simple.php` - Exponía phpinfo() (riesgo de seguridad)
- `TESTING_v1.1.0.md` - Plan de testing temporal
- `QUICK_START_TESTING.md` - Guía temporal
- `PARRILLA_DEVELOPMENT.md` - Notas de desarrollo
- `REVISION_PARRILLA.md` - Revisión temporal

### Código de Debug
- Eliminado logging de debug de `writeCaducidades()`
- Eliminado logging de debug de `editPodcast()`
- Código más limpio y mantenible

---

## 📚 Documentación

### Ayuda Actualizada (`views/help.php`)
- Documentada nueva funcionalidad de URL del stream
- Actualizada sección "Parrilla de Programación"
- Añadidos ejemplos de configuración
- Mejores instrucciones paso a paso

---

## 🔧 Archivos Modificados

### Backend PHP
- `includes/podcasts.php` - Funciones de gestión con índices corregidos
- `includes/azuracast.php` - Soporte para stream_url
- `index.php` - Handler para stream_url y ordenamiento de feeds

### Frontend
- `assets/app.js` - Barra de progreso corregida
- `parrilla_cards.php` - Badge como enlace
- `views/user.php` - Modal de edición corregido
- `views/parrilla.php` - Campo de configuración stream_url
- `views/help.php` - Documentación actualizada

---

## 🎯 Pruebas Realizadas

✅ Creación de nuevo usuario
✅ Edición de podcasts con y sin categorías
✅ Pausar/Reanudar/Eliminar podcasts
✅ Actualización de feeds (progreso al 100%)
✅ Configuración de URL del stream
✅ Badge clickeable en parrilla
✅ Auto-detección de categorías

---

## 📝 Notas de Actualización

### Para actualizar desde v1.2.4:

```bash
cd /var/www/html
git pull origin claude/debug-user-creation-01FriWjvcNtA6ri3F8GEfLpS
sudo systemctl restart apache2
```

### Permisos recomendados:

```bash
# Ajustar permisos de carpetas de podcasts
cd /mnt/emisoras
sudo find . -path "*/media/Suscripciones" -type d -exec chown radioslibres:www-data {} \;
sudo find . -path "*/media/Suscripciones" -type d -exec chmod 2775 {} \;
sudo find . -path "*/media/Suscripciones/*" -type f -exec chmod 664 {} \;
```

---

## 🙏 Créditos

Todos los bugs reportados y solucionados en colaboración con el equipo de Radio Galápagar.

---

## 🔗 Enlaces

- **Repositorio:** https://github.com/antich2004-gr/SAPO
- **Branch:** claude/debug-user-creation-01FriWjvcNtA6ri3F8GEfLpS
- **Tag:** v1.2.5
