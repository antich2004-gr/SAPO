# SAPO v1.2 - Roadmap de Mejoras Post-Testing

**Versión actual:** 1.1.0
**Versión objetivo:** 1.2.0
**Inicio planificado:** Después de período de testing exitoso (7-14 días)

---

## 🎯 Objetivo General

Implementar mejoras de seguridad, hardening y operaciones después de validar que v1.1.0 funciona correctamente en producción.

---

## 📋 Mejoras Planificadas

### 🔴 FASE 1: Hardening Crítico (Semana 1)

**Prioridad:** ALTA
**Tiempo estimado:** 3-5 días
**Riesgo:** Bajo

#### 1.1 Mover db.json fuera de DocumentRoot
- **Descripción:** Relocar base de datos a `/var/sapo/data/`
- **Beneficio:** Prevenir acceso directo incluso si .htaccess falla
- **Archivos afectados:**
  - `config.php` (definir nueva ruta)
  - `includes/database.php` (verificar que funciona)
- **Testing:** Verificar que login/CRUD sigue funcionando
- **Rollback:** Sencillo (restaurar ruta antigua)

#### 1.2 Configurar Rotación de Logs
- **Descripción:** Configurar logrotate para logs de seguridad
- **Beneficio:** Prevenir logs que crecen indefinidamente
- **Archivos:** `/etc/logrotate.d/sapo`
- **Testing:** Verificar que logs rotan correctamente
- **Rollback:** Eliminar archivo de configuración

#### 1.3 Límites de Seguridad en .htaccess
- **Descripción:** Agregar LimitRequestBody y Timeout
- **Beneficio:** Protección adicional contra DoS
- **Archivos afectados:** `.htaccess`
- **Testing:** Subir archivo >1MB debe fallar
- **Rollback:** Eliminar líneas agregadas

**Criterio de éxito Fase 1:**
- [ ] db.json inaccesible vía web
- [ ] Logs rotan automáticamente
- [ ] Límites funcionan correctamente
- [ ] No hay regresiones funcionales

---

### 🟡 FASE 2: Operaciones y Monitoreo (Semana 2)

**Prioridad:** MEDIA
**Tiempo estimado:** 5-7 días
**Riesgo:** Bajo

#### 2.1 Script de Backup Automático
- **Descripción:** Backup diario de db.json y configuración
- **Beneficio:** Recuperación rápida ante fallos
- **Archivos:**
  - `/usr/local/bin/sapo-backup.sh`
  - Crontab entry
- **Testing:** Ejecutar manualmente y verificar archivos
- **Configuración:** Retención de 30 días

#### 2.2 Monitoreo de Intentos de Login
- **Descripción:** Alertas cuando cuenta es bloqueada
- **Beneficio:** Detección temprana de ataques de fuerza bruta
- **Archivos afectados:** `includes/auth.php`
- **Testing:** Hacer 5 intentos fallidos y verificar alerta
- **Configuración:** Definir email de admin

#### 2.3 Sanitización de Logs
- **Descripción:** Limpiar caracteres peligrosos en logs
- **Beneficio:** Prevenir log injection
- **Archivos afectados:** `includes/feed.php`, `includes/auth.php`
- **Testing:** Verificar logs con caracteres especiales

**Criterio de éxito Fase 2:**
- [ ] Backups se crean automáticamente
- [ ] Alertas de seguridad funcionan
- [ ] Logs son seguros de leer
- [ ] Sistema funciona sin interrupciones

---

### 🟢 FASE 3: Mejoras Opcionales (Semana 3-4)

**Prioridad:** BAJA
**Tiempo estimado:** 7-10 días
**Riesgo:** Medio

#### 3.1 Verificación de Integridad
- **Descripción:** Script que verifica hashes de archivos críticos
- **Beneficio:** Detectar modificaciones no autorizadas
- **Archivos:** `verify_integrity.php`
- **Testing:** Modificar archivo y verificar alerta
- **Nota:** Requiere generar hashes iniciales

#### 3.2 Sistema Honeypot
- **Descripción:** Campos trampa en formularios
- **Beneficio:** Detectar bots automáticos
- **Archivos afectados:** `views/login.php`, `index.php`
- **Testing:** Llenar campo honeypot debe fallar
- **Configuración:** Tiempo de delay para bots

#### 3.3 Caché de Validaciones SSRF
- **Descripción:** Cachear resoluciones DNS exitosas
- **Beneficio:** Mejora de rendimiento
- **Archivos afectados:** `includes/feed.php`
- **Testing:** Medir tiempo antes/después
- **Riesgo:** Puede cachear decisiones incorrectas

#### 3.4 Sistema de Alertas por Email
- **Descripción:** Enviar emails en eventos críticos
- **Beneficio:** Notificación inmediata de problemas
- **Archivos:** `includes/alerts.php`
- **Testing:** Generar evento crítico y verificar email
- **Configuración:** SMTP, destinatarios

#### 3.5 Headers Adicionales
- **Descripción:** X-Download-Options, Permissions-Policy
- **Beneficio:** Capa extra de seguridad
- **Archivos afectados:** `index.php`
- **Testing:** Verificar con curl
- **Riesgo:** Muy bajo

#### 3.6 Validación de Sesión Mejorada
- **Descripción:** Verificar IP y User-Agent en sesión
- **Beneficio:** Prevenir session hijacking
- **Archivos afectados:** `includes/session.php`
- **Testing:** Cambiar IP/UA debe invalidar sesión
- **Riesgo:** Puede causar problemas con IPs dinámicas

**Criterio de éxito Fase 3:**
- [ ] Al menos 3 de 6 mejoras implementadas
- [ ] Mejoras seleccionadas funcionan correctamente
- [ ] No hay impacto negativo en rendimiento
- [ ] Usuarios no reportan problemas

---

### 📚 FASE 4: Documentación (Paralelo a Fases 1-3)

**Prioridad:** MEDIA
**Tiempo estimado:** Continuo
**Riesgo:** Ninguno

#### 4.1 Runbook de Operaciones
- **Descripción:** Guía para respuesta a incidentes
- **Archivo:** `RUNBOOK.md`
- **Contenido:**
  - Detección de ataques XXE
  - Respuesta a lockout masivo
  - Procedimientos de mantenimiento
  - Comandos útiles de troubleshooting

#### 4.2 Actualizar SECURITY.md
- **Descripción:** Documentar nuevas medidas de seguridad
- **Contenido a agregar:**
  - Backup y recuperación
  - Monitoreo y alertas
  - Procedimientos de hardening implementados

#### 4.3 Guía de Deployment
- **Descripción:** Paso a paso para instalar/actualizar SAPO
- **Archivo:** `DEPLOYMENT.md`
- **Contenido:**
  - Requisitos del sistema
  - Instalación desde cero
  - Actualización entre versiones
  - Troubleshooting común

**Criterio de éxito Fase 4:**
- [ ] Runbook cubre escenarios principales
- [ ] SECURITY.md está actualizado
- [ ] Guía de deployment es clara y completa
- [ ] Documentación revisada por al menos 2 personas

---

## 📊 Criterios Generales de Aceptación v1.2

**Para liberar v1.2.0:**

### Funcional
- [ ] Todas las funciones de v1.1.0 siguen funcionando
- [ ] Al menos Fase 1 y 2 completadas
- [ ] No hay regresiones detectadas
- [ ] Testing exhaustivo realizado (mínimo 7 días)

### Seguridad
- [ ] No se introducen nuevas vulnerabilidades
- [ ] Mejoras de seguridad verificadas funcionando
- [ ] OWASP Top 10 revisado
- [ ] Logs de seguridad monitoreados sin anomalías

### Rendimiento
- [ ] Tiempo de respuesta igual o mejor que v1.1.0
- [ ] Uso de memoria no aumenta significativamente
- [ ] Tamaño de logs bajo control con rotación
- [ ] Backups no afectan rendimiento del sistema

### Operaciones
- [ ] Backups automáticos funcionando
- [ ] Logs rotando correctamente
- [ ] Alertas llegando cuando corresponde
- [ ] Documentación actualizada

---

## 🗓️ Timeline Propuesto

```
Semana 0: Testing v1.1.0 (7-14 días)
│
├─ Día 1-3: Testing intensivo funcional
├─ Día 4-7: Monitoreo de logs y seguridad
├─ Día 8-10: Testing con feeds reales
└─ Día 11-14: Validación final
    │
    └─ ✅ Aprobación para continuar
        │
        v
Semana 1: FASE 1 - Hardening Crítico
│
├─ Día 1-2: Mover db.json
├─ Día 3: Configurar logrotate
├─ Día 4: Límites en .htaccess
└─ Día 5: Testing Fase 1
    │
    v
Semana 2: FASE 2 - Operaciones
│
├─ Día 1-2: Script de backup
├─ Día 3-4: Monitoreo de logins
├─ Día 5: Sanitización de logs
├─ Día 6-7: Testing Fase 2
    │
    v
Semana 3-4: FASE 3 - Mejoras Opcionales
│
├─ Seleccionar 3-4 mejoras prioritarias
├─ Implementar secuencialmente
├─ Testing individual de cada mejora
└─ Testing integrado final
    │
    v
Semana 5: Release v1.2.0
│
├─ Code freeze
├─ Testing final
├─ Documentación final
├─ Preparar release notes
└─ Deploy a producción
```

---

## 🎯 Métricas de Éxito

### Seguridad
- **Target:** 0 vulnerabilidades críticas
- **Medir:** Scan con OWASP ZAP o similar
- **Meta:** Score A en Mozilla Observatory

### Disponibilidad
- **Target:** 99.9% uptime
- **Medir:** Tiempo sin incidentes
- **Meta:** Máximo 40 minutos de downtime en 30 días

### Rendimiento
- **Target:** < 2 segundos carga página
- **Medir:** Chrome DevTools / GTmetrix
- **Meta:** Igual o mejor que v1.1.0

### Operaciones
- **Target:** 100% backups exitosos
- **Medir:** Logs de backup script
- **Meta:** 30 días de backups sin fallos

---

## 🚨 Plan de Rollback

**Si algo sale mal durante implementación de v1.2:**

### Rollback Fase 1
1. Restaurar `config.php` con ruta antigua de db.json
2. Copiar db.json de vuelta a directorio web
3. Eliminar configuración de logrotate
4. Restaurar `.htaccess` desde backup
5. **Tiempo estimado:** 5-10 minutos

### Rollback Fase 2
1. Desactivar crontab de backups
2. Restaurar `includes/auth.php` desde git
3. Restaurar archivos de Fase 2 desde backup
4. **Tiempo estimado:** 10-15 minutos

### Rollback Fase 3
1. Restaurar archivos modificados desde git
2. Verificar funcionalidad básica
3. **Tiempo estimado:** 5 minutos por mejora

### Rollback Completo a v1.1.0
```bash
cd /var/www/sapo
git reset --hard <commit-hash-v1.1.0>
cp /var/backups/sapo/db_backup.json db.json
systemctl restart php-fpm
```
**Tiempo estimado:** 5 minutos

---

## 📞 Recursos y Contactos

### Documentación
- [TESTING_v1.1.0.md](TESTING_v1.1.0.md) - Plan de testing actual
- [SECURITY.md](SECURITY.md) - Documentación de seguridad
- [README.md](README.md) - Documentación general
- [ROADMAP_v2.0.md](ROADMAP_v2.0.md) - Visión a largo plazo

### Issues y Seguimiento
- **GitHub Issues:** https://github.com/antich2004-gr/SAPO/issues
- **Etiqueta:** `v1.2-improvements`
- **Milestone:** v1.2.0

### Testing y QA
- Script de testing: `test_feeds.php`
- Plan de testing: `TESTING_v1.1.0.md`
- Logs: `/var/log/php-errors.log`, `/var/log/apache2/error.log`

---

## 📝 Notas Finales

### Decisiones Pendientes
- [ ] Definir si implementar validación de sesión mejorada (3.6)
- [ ] Decidir configuración SMTP para alertas por email (3.4)
- [ ] Evaluar necesidad de honeypot basado en logs de producción (3.2)

### Dependencias Externas
- Ninguna (todas las mejoras son auto-contenidas)

### Riesgos Identificados
1. **Riesgo:** Mover db.json puede causar permisos incorrectos
   - **Mitigación:** Testing exhaustivo, documentación clara

2. **Riesgo:** Validación de sesión mejorada puede afectar usuarios con IP dinámica
   - **Mitigación:** Hacer opcional, añadir flag en config.php

3. **Riesgo:** Backups pueden llenar disco
   - **Mitigación:** Configurar retención de 30 días, monitoreo de espacio

---

**Última actualización:** Noviembre 2024
**Mantenedor:** Equipo SAPO
**Estado:** Draft - Pendiente de aprobación post-testing v1.1.0
