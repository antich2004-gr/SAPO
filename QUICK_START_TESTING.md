# 🚀 Quick Start - Testing SAPO v1.1.0

**Inicio rápido para período de pruebas**

---

## ✅ Paso 1: Verificación Inicial (5 minutos)

```bash
# 1. Verificar que la página carga
curl -I http://tu-servidor/sapo/

# 2. Verificar versión en footer
# Abrir en navegador y buscar "Versión 1.1.0" en el pie de página

# 3. Verificar headers de seguridad
curl -I http://tu-servidor/sapo/ | grep -E "(X-Content|X-Frame|CSP|HSTS)"
```

**Checklist rápido:**
- [ ] Página carga sin errores
- [ ] Footer muestra "Versión 1.1.0"
- [ ] Headers de seguridad presentes

---

## 🧪 Paso 2: Test de Feeds RSS (10 minutos)

```bash
cd /var/www/sapo
php test_feeds.php
```

**Resultado esperado:**
```
=== SAPO - Test de Feeds RSS ===
Testing: BBC News
  ✓ Validación SSRF: OK
  ✓ Última fecha: 2024-11-13 10:30:00

=== Test de Protección XXE ===
✓ PROTECCIÓN XXE ACTIVA: XML malicioso fue rechazado correctamente
```

**Checklist:**
- [ ] Al menos 2 feeds pasan validación
- [ ] Test XXE muestra "PROTECCIÓN XXE ACTIVA"

---

## 🔍 Paso 3: Monitoreo de Logs (Primer día)

```bash
# Ver logs de seguridad SAPO
tail -f /var/log/php-errors.log | grep "SAPO"

# En otra terminal, usar la aplicación normalmente
# (login, agregar podcast, actualizar feeds, etc.)
```

**Qué buscar:**
- ✅ `[SAPO-Feed]` - Logs normales de feeds
- ✅ `[SAPO-Security]` - Solo si hay intentos de ataque (normal = vacío)
- ❌ Errores PHP fatales
- ❌ Warnings sobre archivos faltantes

---

## 📋 Paso 4: Uso Normal (Próximos 7 días)

### Usar SAPO normalmente:
1. ✅ Hacer login diariamente
2. ✅ Agregar/editar/eliminar podcasts
3. ✅ Actualizar feeds
4. ✅ Ejecutar descargas
5. ✅ Ver informes

### Reportar cualquier:
- ⚠️ Lentitud inusual
- ⚠️ Errores en pantalla
- ⚠️ Comportamiento extraño
- ⚠️ Funciones que no funcionan

---

## 📊 Paso 5: Revisión Semanal

**Al final de la semana:**

```bash
# 1. Revisar logs de seguridad
grep "SAPO-Security" /var/log/php-errors.log | wc -l

# 2. Verificar que feeds funcionan
# (login → actualizar feeds → verificar que se actualizan)

# 3. Verificar descargas
# (ejecutar descargas → ver que archivos MP3 se descargan)
```

**Completar:**
- [ ] No hay errores críticos en logs
- [ ] Feeds RSS funcionan
- [ ] Descargas funcionan
- [ ] Usuarios satisfechos con rendimiento

---

## ✅ Criterio de Éxito (Mínimo para aprobar)

Después de 7 días:
- [ ] **0** errores críticos (pantalla blanco, no login, etc.)
- [ ] **0** pérdidas de datos
- [ ] Feeds RSS funcionando normalmente
- [ ] Descargas funcionando
- [ ] Rendimiento aceptable

**Si todo ✅ → Proceder con ROADMAP_v1.2.md**
**Si hay ❌ → Revisar TESTING_v1.1.0.md para más detalles**

---

## 🚨 ¿Problema Crítico?

**Pantalla en blanco / Error 500:**
```bash
# Ver últimas líneas del log
tail -50 /var/log/php-errors.log
tail -50 /var/log/apache2/error.log
```

**No se puede hacer login:**
```bash
# Verificar permisos de db.json
ls -la db.json

# Debe mostrar: -rw-r----- o similar (legible por web server)
```

**Rollback de emergencia:**
```bash
cd /var/www/sapo
git log --oneline  # Ver commits recientes
git reset --hard <commit-anterior-v1.1.0>
systemctl restart apache2  # o php-fpm
```

---

## 📞 Ayuda

- **Documentación completa:** [TESTING_v1.1.0.md](TESTING_v1.1.0.md)
- **Mejoras futuras:** [ROADMAP_v1.2.md](ROADMAP_v1.2.md)
- **Seguridad:** [SECURITY.md](SECURITY.md)
- **GitHub Issues:** https://github.com/antich2004-gr/SAPO/issues

---

**¡Buen testing! 🐸**
