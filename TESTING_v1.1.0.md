# Plan de Testing - SAPO v1.1.0

**Período de pruebas:** 7-14 días
**Versión:** 1.1.0
**Fecha de inicio:** _________

---

## 🎯 Objetivo

Verificar que las correcciones de seguridad y nuevas funcionalidades de v1.1.0 funcionan correctamente en producción sin afectar la operativa normal.

---

## ✅ Checklist de Verificación Inicial

### Pre-deployment

- [ ] Backup completo de db.json realizado
- [ ] Backup de archivos de configuración
- [ ] Documentar versión anterior instalada
- [ ] Acceso a logs del servidor verificado
- [ ] Usuario de prueba creado (no usar admin en producción)

### Post-deployment

- [ ] Página carga correctamente (sin pantalla en blanco)
- [ ] Footer muestra "Versión 1.1.0"
- [ ] Login funciona correctamente
- [ ] Panel de usuario accesible
- [ ] Panel de admin accesible

---

## 🧪 Tests Funcionales (Día 1-2)

### 1. Funcionalidad Básica

**Login/Logout:**
- [ ] Login con credenciales correctas funciona
- [ ] Login con credenciales incorrectas muestra error
- [ ] Logout funciona correctamente
- [ ] Sesión expira después de 30 minutos de inactividad

**Gestión de Podcasts:**
- [ ] Agregar nuevo podcast funciona
- [ ] Editar podcast existente funciona
- [ ] Eliminar podcast funciona
- [ ] Búsqueda de podcasts funciona
- [ ] Paginación funciona correctamente

**Gestión de Categorías:**
- [ ] Crear nueva categoría funciona
- [ ] Renombrar categoría funciona
- [ ] Eliminar categoría vacía funciona
- [ ] Gestor de categorías muestra estadísticas correctas
- [ ] Ver archivos de categoría funciona

**Importar/Exportar:**
- [ ] Importar serverlist.txt funciona
- [ ] Exportar serverlist.txt funciona
- [ ] Archivo exportado tiene formato correcto

---

## 🔒 Tests de Seguridad (Día 3-4)

### 2. Protección XXE

**Ejecutar script de testing:**
```bash
cd /ruta/a/sapo
php test_feeds.php
```

**Verificar:**
- [ ] Feeds RSS legítimos funcionan correctamente
- [ ] Test XXE muestra "PROTECCIÓN XXE ACTIVA"
- [ ] No hay errores de parsing en feeds válidos

### 3. Headers de Seguridad

**Verificar con curl:**
```bash
# Para HTTP
curl -I http://tu-servidor/sapo/

# Para HTTPS
curl -I https://tu-servidor/sapo/
```

**Verificar que aparecen:**
- [ ] X-Content-Type-Options: nosniff
- [ ] X-Frame-Options: SAMEORIGIN
- [ ] X-XSS-Protection: 1; mode=block
- [ ] Referrer-Policy: strict-origin-when-cross-origin
- [ ] Content-Security-Policy: default-src 'self'...
- [ ] Strict-Transport-Security (solo en HTTPS)

### 4. Protección CSRF

- [ ] Formularios incluyen csrf_token
- [ ] Peticiones POST sin token son rechazadas
- [ ] Token incorrecto es rechazado

### 5. Rate Limiting

**Test manual:**
1. Realizar 25 acciones rápidas (ej: actualizar feeds 25 veces)
2. [ ] Después de 20, debería aparecer error de rate limit
3. [ ] Después de 60 segundos, debería funcionar de nuevo

---

## 📊 Monitoreo de Logs (Diario durante período de pruebas)

### Ubicación de logs
```bash
# Logs de PHP (ajustar según tu configuración)
tail -f /var/log/php8.1-fpm.log
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
```

### Qué monitorear

**Día 1-3 (Intensivo):**
```bash
# Ver logs de seguridad SAPO
grep "SAPO-Security" /ruta/al/log | tail -20

# Ver intentos XXE bloqueados
grep "XXE ATTEMPT" /ruta/al/log

# Ver intentos SSRF bloqueados
grep "SSRF attempt" /ruta/al/log

# Ver errores de feeds
grep "SAPO-Feed" /ruta/al/log | tail -20
```

**Registro de anomalías:**
- [ ] Día 1: Sin anomalías / Anomalías encontradas: _______
- [ ] Día 2: Sin anomalías / Anomalías encontradas: _______
- [ ] Día 3: Sin anomalías / Anomalías encontradas: _______
- [ ] Día 4: Sin anomalías / Anomalías encontradas: _______
- [ ] Día 5: Sin anomalías / Anomalías encontradas: _______
- [ ] Día 6: Sin anomalías / Anomalías encontradas: _______
- [ ] Día 7: Sin anomalías / Anomalías encontradas: _______

---

## 🎙️ Tests con Feeds Reales (Día 5-7)

### Verificar Feeds RSS de Producción

**Seleccionar 5-10 feeds reales de emisoras:**

1. Feed 1: ________________
   - [ ] Se actualiza correctamente
   - [ ] Última fecha se muestra bien
   - [ ] Estado (🟢🟠🔴) es correcto
   - [ ] No hay errores en logs

2. Feed 2: ________________
   - [ ] Se actualiza correctamente
   - [ ] Última fecha se muestra bien
   - [ ] Estado (🟢🟠🔴) es correcto
   - [ ] No hay errores en logs

3. Feed 3: ________________
   - [ ] Se actualiza correctamente
   - [ ] Última fecha se muestra bien
   - [ ] Estado (🟢🟠🔴) es correcto
   - [ ] No hay errores en logs

_(Repetir para todos los feeds de prueba)_

### Actualizar Todos los Feeds

- [ ] Botón "🔄 Actualizar Feeds" funciona
- [ ] Tiempo de respuesta es aceptable (< 10 segundos para ~20 feeds)
- [ ] No hay timeouts
- [ ] Caché funciona correctamente (segunda actualización es más rápida)

---

## 🔄 Tests de Integración con Podget (Día 8-10)

### Ejecutar Descargas

- [ ] Botón "Ejecutar Descargas" funciona
- [ ] Mensaje de éxito aparece
- [ ] Script se ejecuta en segundo plano
- [ ] No hay errores en logs de Podget
- [ ] Archivos MP3 se descargan correctamente
- [ ] Archivos se guardan en carpetas correctas

### Verificar Informes

- [ ] Informe diario se genera correctamente
- [ ] Estadísticas son precisas
- [ ] Historial de descargas muestra episodios recientes
- [ ] Selector de período (7/14/30/60/90 días) funciona

---

## 🚨 Detección de Problemas Críticos

**Si encuentras alguno de estos problemas, DETENER y reportar inmediatamente:**

- ❌ Pantalla en blanco al cargar la página
- ❌ No se puede hacer login
- ❌ Feeds RSS dejan de funcionar
- ❌ Errores 500 en servidor
- ❌ Pérdida de datos en db.json
- ❌ Sistema de descargas no funciona
- ❌ Headers de seguridad causan problemas de funcionalidad

**Procedimiento de rollback:**
1. Restaurar versión anterior desde backup
2. Documentar el problema encontrado
3. Reportar en GitHub: https://github.com/antich2004-gr/SAPO/issues

---

## 📋 Problemas No Críticos

**Documentar pero continuar testing:**

- ⚠️ Mensajes de error poco claros
- ⚠️ Lentitud en algunas operaciones
- ⚠️ Comportamiento inesperado pero no bloqueante
- ⚠️ Errores cosméticos en interfaz
- ⚠️ Logs demasiado verbosos

**Formato de reporte:**
```
Fecha: _______
Problema: _______________________________________
Severidad: Baja / Media / Alta
Pasos para reproducir:
1. _______
2. _______
3. _______
Comportamiento esperado: _______
Comportamiento actual: _______
```

---

## 📈 Métricas de Rendimiento

### Antes de v1.1.0
- Tiempo de carga página principal: _______ ms
- Tiempo actualizar feeds (20 podcasts): _______ seg
- Tiempo ejecutar descargas: _______ seg
- Tamaño de logs (1 semana): _______ MB

### Después de v1.1.0
- Tiempo de carga página principal: _______ ms
- Tiempo actualizar feeds (20 podcasts): _______ seg
- Tiempo ejecutar descargas: _______ seg
- Tamaño de logs (1 semana): _______ MB

**Notas sobre rendimiento:**
_____________________________________________
_____________________________________________

---

## ✅ Criterios de Aceptación (Fin del período)

**Para considerar v1.1.0 estable en producción:**

- [ ] Todos los tests funcionales pasan ✅
- [ ] No hay problemas críticos detectados
- [ ] Logs muestran protecciones de seguridad funcionando
- [ ] Feeds RSS funcionan correctamente
- [ ] Integración con Podget funciona
- [ ] Rendimiento es igual o mejor que versión anterior
- [ ] Usuarios reportan funcionamiento normal
- [ ] Al menos 7 días de operación sin incidentes

**Problemas no críticos encontrados:** _______ (aceptable si < 5)

---

## 📝 Notas Adicionales del Testing

### Día 1:
_____________________________________________
_____________________________________________

### Día 2:
_____________________________________________
_____________________________________________

### Día 3:
_____________________________________________
_____________________________________________

_(Continuar para cada día)_

---

## 🎉 Conclusión del Testing

**Fecha de finalización:** _________

**Resultado:**
- [ ] ✅ APROBADO - Proceder con mejoras adicionales
- [ ] ⚠️ APROBADO CON RESERVAS - Corregir issues menores primero
- [ ] ❌ RECHAZADO - Rollback necesario

**Firma:** _________________________
**Rol:** _________________________
**Emisora:** _________________________

---

## 📞 Contacto en Caso de Problemas

- **GitHub Issues:** https://github.com/antich2004-gr/SAPO/issues
- **Logs de seguridad:** Revisar con `grep "SAPO-Security" /var/log/...`
- **Documentación:** Ver SECURITY.md y README.md en el repositorio

---

**Próximos pasos después del testing exitoso:**
1. Implementar mejoras de la lista de recomendaciones
2. Considerar actualización a v1.2.0 con nuevas funcionalidades
3. Documentar lecciones aprendidas
