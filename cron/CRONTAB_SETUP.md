# Configuración del Cron para Limpieza Automática de Grabaciones

## 📋 Instrucciones

El script `cleanup_recordings.php` debe ejecutarse automáticamente cada 24 horas para eliminar grabaciones antiguas.

## 🔧 Configuración del Crontab

### **Opción 1: Ejecución diaria a las 3:00 AM**

```bash
# Editar crontab
crontab -e

# Añadir esta línea:
0 3 * * * /usr/bin/php /home/user/SAPO/cron/cleanup_recordings.php >> /home/user/SAPO/logs/cron.log 2>&1
```

### **Opción 2: Ejecución diaria a medianoche**

```bash
0 0 * * * /usr/bin/php /home/user/SAPO/cron/cleanup_recordings.php >> /home/user/SAPO/logs/cron.log 2>&1
```

### **Opción 3: Ejecución cada 6 horas** (más frecuente)

```bash
0 */6 * * * /usr/bin/php /home/user/SAPO/cron/cleanup_recordings.php >> /home/user/SAPO/logs/cron.log 2>&1
```

## 📝 Formato del Crontab

```
┌───────────── minuto (0 - 59)
│ ┌───────────── hora (0 - 23)
│ │ ┌───────────── día del mes (1 - 31)
│ │ │ ┌───────────── mes (1 - 12)
│ │ │ │ ┌───────────── día de la semana (0 - 6) (Domingo=0)
│ │ │ │ │
│ │ │ │ │
* * * * * comando a ejecutar
```

## ✅ Verificar configuración

### Ver crontab actual:
```bash
crontab -l
```

### Ver logs de ejecución:
```bash
tail -f /home/user/SAPO/logs/recordings_cleanup.log
```

### Probar manualmente:
```bash
php /home/user/SAPO/cron/cleanup_recordings.php
```

## 📊 Ubicación de logs

- **Log de limpieza**: `/home/user/SAPO/logs/recordings_cleanup.log`
- **Log de cron**: `/home/user/SAPO/logs/cron.log`

## ⚙️ Configuración por usuario

Cada usuario puede configurar:
- **Días de retención**: Desde el panel web (7, 15, 30, 60, 90, 180, 365 días)
- **Auto-delete**: Activar/desactivar eliminación automática

El script respeta la configuración de cada usuario automáticamente.

## 🔍 Troubleshooting

### El cron no se ejecuta:
1. Verificar que el script sea ejecutable: `chmod +x /home/user/SAPO/cron/cleanup_recordings.php`
2. Verificar ruta de PHP: `which php`
3. Revisar logs del sistema: `grep CRON /var/log/syslog`

### Permisos de archivos:
```bash
chmod +x /home/user/SAPO/cron/cleanup_recordings.php
chmod 755 /home/user/SAPO/logs
```

## 📧 Notificaciones por email (opcional)

Para recibir notificaciones por email:

```bash
# Añadir esta línea al inicio del crontab
MAILTO=tu@email.com

# Luego las líneas de cron...
0 3 * * * /usr/bin/php /home/user/SAPO/cron/cleanup_recordings.php
```
