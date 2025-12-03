#!/bin/bash

# Script de implementación para integrar SAPO con AzuraCast
# Configura el contenedor nginx de AzuraCast para servir SAPO en un subdominio

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuración
SUBDOMAIN="sapo.radioslibres.info"
SAPO_HTML_DIR="/var/www/html"
SAPO_PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Configuración SAPO en AzuraCast${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Verificar que se ejecuta como root o con sudo
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Este script debe ejecutarse como root o con sudo${NC}"
   exit 1
fi

# 1. Buscar instalación de AzuraCast
echo -e "${YELLOW}[1/8] Buscando instalación de AzuraCast...${NC}"
AZURACAST_PATHS=(
    "/var/azuracast"
    "/opt/azuracast"
    "$HOME/azuracast"
)

AZURACAST_DIR=""
for path in "${AZURACAST_PATHS[@]}"; do
    if [ -d "$path" ] && [ -f "$path/docker-compose.yml" ]; then
        echo -e "${GREEN}✓ Encontrado en: $path${NC}"
        AZURACAST_DIR="$path"
        break
    fi
done

if [ -z "$AZURACAST_DIR" ]; then
    echo -e "${YELLOW}No se encontró automáticamente. Por favor, introduce la ruta:${NC}"
    read -p "Ruta de AzuraCast: " AZURACAST_DIR
    if [ ! -d "$AZURACAST_DIR" ] || [ ! -f "$AZURACAST_DIR/docker-compose.yml" ]; then
        echo -e "${RED}Ruta inválida. Saliendo.${NC}"
        exit 1
    fi
fi

# 2. Crear backup del docker-compose.yml
echo -e "\n${YELLOW}[2/8] Creando backup de docker-compose.yml...${NC}"
BACKUP_FILE="$AZURACAST_DIR/docker-compose.yml.backup-$(date +%Y%m%d-%H%M%S)"
cp "$AZURACAST_DIR/docker-compose.yml" "$BACKUP_FILE"
echo -e "${GREEN}✓ Backup creado: $BACKUP_FILE${NC}"

# 3. Crear directorio para configuración personalizada de nginx
echo -e "\n${YELLOW}[3/8] Creando directorio para configuración personalizada...${NC}"
NGINX_CUSTOM_DIR="$AZURACAST_DIR/nginx-custom"
mkdir -p "$NGINX_CUSTOM_DIR"
echo -e "${GREEN}✓ Directorio creado: $NGINX_CUSTOM_DIR${NC}"

# 4. Crear configuración de virtual host para SAPO
echo -e "\n${YELLOW}[4/8] Creando configuración de virtual host para SAPO...${NC}"
cat > "$NGINX_CUSTOM_DIR/sapo.conf" << 'EOF'
server {
    listen 80;
    listen [::]:80;

    server_name sapo.radioslibres.info;

    root /var/www/html;
    index index.html index.htm;

    # Logs específicos para SAPO
    access_log /var/log/nginx/sapo-access.log;
    error_log /var/log/nginx/sapo-error.log;

    # Configuración de seguridad básica
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Servir archivos estáticos
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Configuración para archivos estáticos con caché
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Denegar acceso a archivos ocultos
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
EOF

echo -e "${GREEN}✓ Configuración creada: $NGINX_CUSTOM_DIR/sapo.conf${NC}"

# 5. Crear o copiar contenido SAPO a /var/www/html
echo -e "\n${YELLOW}[5/8] Preparando contenido SAPO en $SAPO_HTML_DIR...${NC}"
if [ ! -d "$SAPO_HTML_DIR" ]; then
    mkdir -p "$SAPO_HTML_DIR"
    echo -e "${GREEN}✓ Directorio $SAPO_HTML_DIR creado${NC}"
fi

# Si existe un directorio www o public en el proyecto SAPO, copiarlo
if [ -d "$SAPO_PROJECT_DIR/www" ]; then
    echo -e "${BLUE}Copiando contenido desde $SAPO_PROJECT_DIR/www...${NC}"
    cp -r "$SAPO_PROJECT_DIR/www/"* "$SAPO_HTML_DIR/"
    echo -e "${GREEN}✓ Contenido copiado${NC}"
elif [ -d "$SAPO_PROJECT_DIR/public" ]; then
    echo -e "${BLUE}Copiando contenido desde $SAPO_PROJECT_DIR/public...${NC}"
    cp -r "$SAPO_PROJECT_DIR/public/"* "$SAPO_HTML_DIR/"
    echo -e "${GREEN}✓ Contenido copiado${NC}"
else
    echo -e "${YELLOW}⚠ No se encontró directorio www/ o public/ en el proyecto SAPO${NC}"
    echo -e "${YELLOW}  Deberás copiar manualmente los archivos HTML a $SAPO_HTML_DIR${NC}"
fi

# Establecer permisos adecuados
chmod -R 755 "$SAPO_HTML_DIR"

# 6. Modificar docker-compose.yml para añadir volúmenes
echo -e "\n${YELLOW}[6/8] Modificando docker-compose.yml...${NC}"

# Identificar el servicio web en docker-compose
WEB_SERVICE=$(grep -E "^\s+web:" "$AZURACAST_DIR/docker-compose.yml" > /dev/null 2>&1 && echo "web" || echo "")
if [ -z "$WEB_SERVICE" ]; then
    WEB_SERVICE=$(grep -E "^\s+nginx:" "$AZURACAST_DIR/docker-compose.yml" > /dev/null 2>&1 && echo "nginx" || echo "")
fi

if [ -z "$WEB_SERVICE" ]; then
    echo -e "${RED}✗ No se pudo identificar el servicio web en docker-compose.yml${NC}"
    echo -e "${YELLOW}Deberás añadir manualmente los volúmenes al servicio correspondiente:${NC}"
    echo -e "  - $SAPO_HTML_DIR:/var/www/html:ro"
    echo -e "  - $NGINX_CUSTOM_DIR/sapo.conf:/etc/nginx/conf.d/sapo.conf:ro"
else
    echo -e "${GREEN}✓ Servicio web identificado: $WEB_SERVICE${NC}"
    echo -e "${BLUE}Añadiendo volúmenes al docker-compose.yml...${NC}"

    # Crear script Python para modificar el docker-compose.yml
    cat > /tmp/modify_compose.py << 'PYSCRIPT'
#!/usr/bin/env python3
import sys
import re

compose_file = sys.argv[1]
service_name = sys.argv[2]
html_dir = sys.argv[3]
nginx_conf = sys.argv[4]

with open(compose_file, 'r') as f:
    content = f.read()

# Buscar la sección de volumes del servicio
service_pattern = rf'(\s+{service_name}:.*?)(volumes:)'
match = re.search(service_pattern, content, re.DOTALL)

if match:
    # Verificar si los volúmenes ya existen
    if '/var/www/html' not in content and 'sapo.conf' not in content:
        # Encontrar el final de la sección de volumes
        volumes_start = match.end()
        # Buscar la siguiente línea que no esté indentada con más espacios
        remaining = content[volumes_start:]

        # Encontrar donde insertar
        lines = remaining.split('\n')
        insert_pos = 0
        base_indent = None

        for i, line in enumerate(lines):
            if line.strip() and not line.startswith('      -'):
                insert_pos = i
                break
            if line.strip().startswith('-') and base_indent is None:
                base_indent = len(line) - len(line.lstrip())

        if base_indent is None:
            base_indent = 6

        # Crear las nuevas líneas de volumen
        new_volumes = f"\n{' ' * base_indent}- {html_dir}:/var/www/html:ro\n{' ' * base_indent}- {nginx_conf}:/etc/nginx/conf.d/sapo.conf:ro"

        # Insertar en la posición correcta
        insertion_point = volumes_start + sum(len(l) + 1 for l in lines[:insert_pos])
        content = content[:insertion_point] + new_volumes + content[insertion_point:]

        with open(compose_file, 'w') as f:
            f.write(content)
        print("Volúmenes añadidos correctamente")
    else:
        print("Los volúmenes ya parecen estar configurados")
else:
    print(f"No se encontró la sección volumes del servicio {service_name}")
    sys.exit(1)
PYSCRIPT

    chmod +x /tmp/modify_compose.py

    if command -v python3 &> /dev/null; then
        python3 /tmp/modify_compose.py "$AZURACAST_DIR/docker-compose.yml" "$WEB_SERVICE" "$SAPO_HTML_DIR" "$NGINX_CUSTOM_DIR/sapo.conf"
        echo -e "${GREEN}✓ docker-compose.yml modificado${NC}"
    else
        echo -e "${YELLOW}⚠ Python3 no disponible. Añade manualmente al servicio '$WEB_SERVICE':${NC}"
        echo -e "  volumes:"
        echo -e "    - $SAPO_HTML_DIR:/var/www/html:ro"
        echo -e "    - $NGINX_CUSTOM_DIR/sapo.conf:/etc/nginx/conf.d/sapo.conf:ro"
    fi

    rm -f /tmp/modify_compose.py
fi

# 7. Mostrar los cambios realizados
echo -e "\n${YELLOW}[7/8] Verificando cambios en docker-compose.yml...${NC}"
echo -e "${BLUE}Diferencias con el backup:${NC}"
diff -u "$BACKUP_FILE" "$AZURACAST_DIR/docker-compose.yml" || true

# 8. Instrucciones finales
echo -e "\n${YELLOW}[8/8] Configuración completada${NC}"

echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}PRÓXIMOS PASOS${NC}"
echo -e "${BLUE}========================================${NC}\n"

echo -e "${GREEN}1. Reiniciar el contenedor web de AzuraCast:${NC}"
echo -e "   cd $AZURACAST_DIR"
echo -e "   docker-compose down"
echo -e "   docker-compose up -d"

echo -e "\n${GREEN}2. Verificar que el contenedor está funcionando:${NC}"
echo -e "   docker-compose ps"
echo -e "   docker-compose logs web"

echo -e "\n${GREEN}3. Configurar DNS:${NC}"
echo -e "   Apuntar $SUBDOMAIN a la IP de este servidor"

echo -e "\n${GREEN}4. Probar acceso:${NC}"
echo -e "   curl -H 'Host: $SUBDOMAIN' http://localhost"
echo -e "   O desde un navegador: http://$SUBDOMAIN"

echo -e "\n${GREEN}5. (Opcional) Configurar SSL con Let's Encrypt:${NC}"
echo -e "   Después de que el DNS esté configurado, puedes añadir SSL"

echo -e "\n${BLUE}========================================${NC}"
echo -e "${GREEN}Archivos creados:${NC}"
echo -e "  - Configuración nginx: $NGINX_CUSTOM_DIR/sapo.conf"
echo -e "  - Backup: $BACKUP_FILE"
echo -e "  - Contenido web: $SAPO_HTML_DIR"
echo -e "${BLUE}========================================${NC}\n"
