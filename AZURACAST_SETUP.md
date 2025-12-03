# Integración de SAPO con AzuraCast

Este documento describe cómo servir la web de SAPO desde el subdominio `sapo.radioslibres.info` utilizando el contenedor nginx de AzuraCast existente, sin necesidad de instalar nada en el host y sin modificar los puertos de AzuraCast.

## 🎯 Objetivo

Servir el sitio web de SAPO desde `/var/www/html` en el subdominio `sapo.radioslibres.info` utilizando el contenedor nginx de AzuraCast.

## 📋 Requisitos

- Servidor Debian Buster con AzuraCast instalado y funcionando
- Acceso root o sudo
- Docker y docker-compose instalados (ya incluidos con AzuraCast)
- Acceso a la configuración DNS del dominio

## 🚀 Proceso de Instalación

### Paso 1: Diagnóstico

Primero, ejecuta el script de diagnóstico para analizar tu configuración actual:

```bash
cd /home/user/SAPO
chmod +x azuracast-sapo-diagnostic.sh
sudo ./azuracast-sapo-diagnostic.sh
```

Este script analizará:
- Instalación de Docker y docker-compose
- Ubicación de AzuraCast
- Contenedores en ejecución
- Configuración actual de nginx
- Puertos en uso
- Volúmenes montados

### Paso 2: Configuración Automática

Una vez revisado el diagnóstico, ejecuta el script de configuración:

```bash
chmod +x azuracast-sapo-setup.sh
sudo ./azuracast-sapo-setup.sh
```

Este script realizará automáticamente:

1. **Backup del `docker-compose.yml`** actual
2. **Creación de directorio de configuración** personalizada en `$AZURACAST_DIR/nginx-custom/`
3. **Generación de virtual host** para nginx con la configuración de SAPO
4. **Preparación de `/var/www/html`** con el contenido web
5. **Modificación del `docker-compose.yml`** para añadir los volúmenes necesarios:
   - `/var/www/html:/var/www/html:ro` (contenido web)
   - `nginx-custom/sapo.conf:/etc/nginx/conf.d/sapo.conf:ro` (configuración)

### Paso 3: Reiniciar AzuraCast

Después de la configuración, reinicia los contenedores de AzuraCast:

```bash
cd /var/azuracast  # O la ruta donde esté instalado AzuraCast
sudo docker-compose down
sudo docker-compose up -d
```

Verifica que todo está funcionando:

```bash
sudo docker-compose ps
sudo docker-compose logs web
```

### Paso 4: Configurar DNS

Configura un registro A en tu proveedor DNS:

```
Tipo: A
Nombre: sapo
Dominio: radioslibres.info
Valor: [IP_DE_TU_SERVIDOR]
TTL: 3600 (o el valor por defecto)
```

### Paso 5: Verificación

Ejecuta el script de verificación para comprobar que todo funciona correctamente:

```bash
chmod +x azuracast-sapo-verify.sh
sudo ./azuracast-sapo-verify.sh
```

Este script verificará:
- Contenedores en ejecución
- Montaje de volúmenes
- Configuración de nginx
- Sintaxis de configuración
- Puertos abiertos
- Respuesta HTTP
- Logs de nginx
- Configuración DNS

## 🧪 Pruebas

### Prueba local (sin DNS configurado)

```bash
curl -H 'Host: sapo.radioslibres.info' http://localhost
```

O añade temporalmente a `/etc/hosts`:

```bash
echo "127.0.0.1 sapo.radioslibres.info" | sudo tee -a /etc/hosts
```

### Prueba con DNS configurado

```bash
curl http://sapo.radioslibres.info
```

O accede desde un navegador: `http://sapo.radioslibres.info`

## 📁 Estructura de Archivos

```
/var/azuracast/                          # Directorio de AzuraCast
├── docker-compose.yml                   # Modificado con nuevos volúmenes
├── docker-compose.yml.backup-YYYYMMDD   # Backup automático
└── nginx-custom/                        # Nueva carpeta
    └── sapo.conf                        # Configuración del virtual host

/var/www/html/                           # Contenido web de SAPO
├── index.html
├── css/
├── js/
└── ...
```

## 🔧 Configuración de nginx

El archivo `sapo.conf` creado contiene:

```nginx
server {
    listen 80;
    listen [::]:80;

    server_name sapo.radioslibres.info;

    root /var/www/html;
    index index.html index.htm;

    # Logs específicos para SAPO
    access_log /var/log/nginx/sapo-access.log;
    error_log /var/log/nginx/sapo-error.log;

    # Seguridad básica
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Caché para archivos estáticos
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

## 🔐 Configurar HTTPS/SSL (Opcional)

Una vez que el DNS esté propagado y funcionando con HTTP, puedes añadir SSL:

### Opción 1: Let's Encrypt dentro del contenedor

```bash
# Acceder al contenedor
sudo docker exec -it azuracast_web bash

# Instalar certbot (si no está instalado)
apt-get update && apt-get install -y certbot

# Obtener certificado
certbot certonly --webroot -w /var/www/html -d sapo.radioslibres.info
```

Luego modifica `nginx-custom/sapo.conf` para añadir la configuración SSL:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name sapo.radioslibres.info;

    ssl_certificate /etc/letsencrypt/live/sapo.radioslibres.info/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sapo.radioslibres.info/privkey.pem;

    root /var/www/html;
    # ... resto de la configuración
}

server {
    listen 80;
    listen [::]:80;
    server_name sapo.radioslibres.info;
    return 301 https://$server_name$request_uri;
}
```

### Opción 2: Certificado existente

Si ya tienes certificados SSL, móntálos como volúmenes en `docker-compose.yml`:

```yaml
volumes:
  - /path/to/certs:/etc/letsencrypt:ro
```

## 🛠️ Mantenimiento

### Ver logs de SAPO

```bash
# Logs de acceso
sudo docker exec azuracast_web tail -f /var/log/nginx/sapo-access.log

# Logs de errores
sudo docker exec azuracast_web tail -f /var/log/nginx/sapo-error.log
```

### Actualizar contenido

```bash
# Simplemente actualiza los archivos en /var/www/html
sudo cp -r /ruta/nuevo/contenido/* /var/www/html/
```

No es necesario reiniciar el contenedor para cambios en el contenido HTML.

### Modificar configuración de nginx

```bash
# Edita el archivo de configuración
sudo nano /var/azuracast/nginx-custom/sapo.conf

# Reinicia el contenedor
cd /var/azuracast
sudo docker-compose restart web
```

### Verificar configuración de nginx antes de reiniciar

```bash
sudo docker exec azuracast_web nginx -t
```

## 🔍 Solución de Problemas

### El sitio no carga

1. Verifica que los contenedores están corriendo:
   ```bash
   sudo docker-compose ps
   ```

2. Verifica los logs:
   ```bash
   sudo docker-compose logs web
   ```

3. Verifica la sintaxis de nginx:
   ```bash
   sudo docker exec azuracast_web nginx -t
   ```

### Error 404

1. Verifica que `/var/www/html` tiene contenido:
   ```bash
   ls -la /var/www/html
   ```

2. Verifica que el volumen está montado:
   ```bash
   sudo docker exec azuracast_web ls -la /var/www/html
   ```

### Error de conexión

1. Verifica que el puerto 80 está abierto:
   ```bash
   sudo netstat -tlnp | grep :80
   ```

2. Verifica el firewall:
   ```bash
   sudo ufw status
   # Si está activo, asegúrate de que permite el puerto 80
   sudo ufw allow 80/tcp
   ```

### DNS no resuelve

1. Verifica la configuración DNS:
   ```bash
   nslookup sapo.radioslibres.info
   ```

2. La propagación DNS puede tardar hasta 48 horas

3. Prueba con un servidor DNS público:
   ```bash
   nslookup sapo.radioslibres.info 8.8.8.8
   ```

## 🔄 Deshacer Cambios

Si necesitas revertir los cambios:

```bash
cd /var/azuracast

# Restaurar el docker-compose.yml original
sudo cp docker-compose.yml.backup-YYYYMMDD docker-compose.yml

# Reiniciar contenedores
sudo docker-compose down
sudo docker-compose up -d

# Opcionalmente, eliminar la configuración personalizada
sudo rm -rf nginx-custom/
```

## 📞 Soporte

Si encuentras problemas:

1. Ejecuta el script de diagnóstico: `./azuracast-sapo-diagnostic.sh`
2. Ejecuta el script de verificación: `./azuracast-sapo-verify.sh`
3. Revisa los logs: `sudo docker-compose logs web`

## ✅ Checklist de Instalación

- [ ] Ejecutar script de diagnóstico
- [ ] Ejecutar script de configuración
- [ ] Copiar contenido web a `/var/www/html`
- [ ] Reiniciar contenedores de AzuraCast
- [ ] Configurar registro DNS
- [ ] Verificar acceso HTTP local
- [ ] Esperar propagación DNS
- [ ] Verificar acceso desde internet
- [ ] (Opcional) Configurar SSL/HTTPS
- [ ] Ejecutar script de verificación

## 📝 Notas Importantes

- ✅ No se requiere instalar ningún software adicional en el host
- ✅ No se modifican los puertos de AzuraCast
- ✅ No interfiere con la funcionalidad de AzuraCast
- ✅ El contenido se sirve desde el mismo nginx de AzuraCast
- ✅ Se mantienen logs separados para SAPO
- ✅ Se pueden servir múltiples subdominios añadiendo más archivos `.conf`
