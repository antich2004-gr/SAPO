#!/bin/bash

# Script de diagnóstico para integrar SAPO con AzuraCast
# Analiza la configuración actual y determina los cambios necesarios

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Diagnóstico de Integración SAPO-AzuraCast${NC}"
echo -e "${BLUE}========================================${NC}\n"

# 1. Verificar si Docker está instalado
echo -e "${YELLOW}[1/10] Verificando Docker...${NC}"
if command -v docker &> /dev/null; then
    echo -e "${GREEN}✓ Docker está instalado: $(docker --version)${NC}"
else
    echo -e "${RED}✗ Docker no está instalado${NC}"
    exit 1
fi

# 2. Verificar docker-compose
echo -e "\n${YELLOW}[2/10] Verificando docker-compose...${NC}"
if command -v docker-compose &> /dev/null; then
    echo -e "${GREEN}✓ docker-compose está instalado: $(docker-compose --version)${NC}"
else
    echo -e "${RED}✗ docker-compose no está instalado${NC}"
fi

# 3. Buscar instalación de AzuraCast
echo -e "\n${YELLOW}[3/10] Buscando instalación de AzuraCast...${NC}"
AZURACAST_PATHS=(
    "/var/azuracast"
    "/opt/azuracast"
    "$HOME/azuracast"
)

AZURACAST_DIR=""
for path in "${AZURACAST_PATHS[@]}"; do
    if [ -d "$path" ]; then
        echo -e "${GREEN}✓ Encontrado en: $path${NC}"
        AZURACAST_DIR="$path"
        break
    fi
done

if [ -z "$AZURACAST_DIR" ]; then
    echo -e "${RED}✗ No se encontró la instalación de AzuraCast en las ubicaciones comunes${NC}"
    echo -e "${YELLOW}Por favor, introduce la ruta manualmente:${NC}"
    read -p "Ruta de AzuraCast: " AZURACAST_DIR
    if [ ! -d "$AZURACAST_DIR" ]; then
        echo -e "${RED}La ruta no existe. Saliendo.${NC}"
        exit 1
    fi
fi

# 4. Verificar docker-compose.yml
echo -e "\n${YELLOW}[4/10] Verificando docker-compose.yml...${NC}"
if [ -f "$AZURACAST_DIR/docker-compose.yml" ]; then
    echo -e "${GREEN}✓ Archivo docker-compose.yml encontrado${NC}"
else
    echo -e "${RED}✗ No se encontró docker-compose.yml en $AZURACAST_DIR${NC}"
fi

# 5. Listar contenedores de AzuraCast
echo -e "\n${YELLOW}[5/10] Listando contenedores de AzuraCast...${NC}"
CONTAINERS=$(docker ps --filter "name=azuracast" --format "{{.Names}}")
if [ -z "$CONTAINERS" ]; then
    echo -e "${RED}✗ No hay contenedores de AzuraCast en ejecución${NC}"
    echo -e "${YELLOW}Intentando con 'docker ps -a' para ver todos los contenedores...${NC}"
    docker ps -a --filter "name=azuracast" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
else
    echo -e "${GREEN}✓ Contenedores en ejecución:${NC}"
    echo "$CONTAINERS"
fi

# 6. Identificar contenedor nginx/web
echo -e "\n${YELLOW}[6/10] Identificando contenedor web/nginx...${NC}"
WEB_CONTAINER=""
for container in $CONTAINERS; do
    if [[ $container == *"web"* ]] || [[ $container == *"nginx"* ]]; then
        WEB_CONTAINER=$container
        echo -e "${GREEN}✓ Contenedor web encontrado: $WEB_CONTAINER${NC}"
        break
    fi
done

if [ -z "$WEB_CONTAINER" ]; then
    echo -e "${YELLOW}⚠ No se encontró contenedor web específico, intentando con el primero...${NC}"
    WEB_CONTAINER=$(echo "$CONTAINERS" | head -n 1)
    echo -e "${YELLOW}Usando: $WEB_CONTAINER${NC}"
fi

# 7. Verificar configuración de nginx dentro del contenedor
echo -e "\n${YELLOW}[7/10] Verificando configuración de nginx...${NC}"
if [ ! -z "$WEB_CONTAINER" ]; then
    echo -e "${BLUE}Ubicación de configuración nginx:${NC}"
    docker exec $WEB_CONTAINER sh -c "ls -la /etc/nginx/ 2>/dev/null || ls -la /usr/local/etc/nginx/ 2>/dev/null || echo 'No se encontró /etc/nginx'"

    echo -e "\n${BLUE}Virtual hosts configurados:${NC}"
    docker exec $WEB_CONTAINER sh -c "ls -la /etc/nginx/conf.d/ 2>/dev/null || ls -la /etc/nginx/sites-enabled/ 2>/dev/null || echo 'No se encontraron virtual hosts'"

    echo -e "\n${BLUE}Puertos expuestos:${NC}"
    docker port $WEB_CONTAINER 2>/dev/null || echo "No se pudo obtener información de puertos"
fi

# 8. Verificar si /var/www/html existe en el host
echo -e "\n${YELLOW}[8/10] Verificando directorio /var/www/html en el host...${NC}"
if [ -d "/var/www/html" ]; then
    echo -e "${GREEN}✓ Directorio /var/www/html existe${NC}"
    echo -e "${BLUE}Contenido:${NC}"
    ls -lah /var/www/html | head -10
else
    echo -e "${RED}✗ Directorio /var/www/html no existe${NC}"
    echo -e "${YELLOW}Se necesitará crear este directorio${NC}"
fi

# 9. Verificar volumenes montados
echo -e "\n${YELLOW}[9/10] Verificando volúmenes montados en el contenedor web...${NC}"
if [ ! -z "$WEB_CONTAINER" ]; then
    echo -e "${BLUE}Volúmenes:${NC}"
    docker inspect $WEB_CONTAINER | grep -A 10 "Mounts" || echo "No se pudo obtener información de volúmenes"
fi

# 10. Verificar puertos en uso
echo -e "\n${YELLOW}[10/10] Verificando puertos en uso...${NC}"
echo -e "${BLUE}Puerto 80:${NC}"
sudo netstat -tlnp | grep ":80 " || echo "Puerto 80 no está en uso"
echo -e "\n${BLUE}Puerto 443:${NC}"
sudo netstat -tlnp | grep ":443 " || echo "Puerto 443 no está en uso"

# Resumen y recomendaciones
echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}RESUMEN Y RECOMENDACIONES${NC}"
echo -e "${BLUE}========================================${NC}\n"

echo -e "${GREEN}Para servir SAPO desde sapo.radioslibres.info usando el contenedor nginx de AzuraCast:${NC}\n"

echo -e "${YELLOW}1. Montar /var/www/html en el contenedor${NC}"
echo "   - Añadir volumen en docker-compose.yml: /var/www/html:/var/www/html:ro"

echo -e "\n${YELLOW}2. Crear configuración de virtual host para nginx${NC}"
echo "   - Crear archivo de configuración para sapo.radioslibres.info"
echo "   - Montar en /etc/nginx/conf.d/ o /etc/nginx/sites-enabled/"

echo -e "\n${YELLOW}3. Reiniciar contenedor para aplicar cambios${NC}"
echo "   - docker-compose restart web (o el nombre del contenedor)"

echo -e "\n${YELLOW}4. Configurar DNS${NC}"
echo "   - Apuntar sapo.radioslibres.info a la IP del servidor"

echo -e "\n${BLUE}========================================${NC}"
echo -e "${GREEN}Diagnóstico completado.${NC}"
echo -e "${BLUE}========================================${NC}"

# Guardar información en un archivo
REPORT_FILE="/tmp/azuracast-sapo-diagnostic-$(date +%Y%m%d-%H%M%S).txt"
{
    echo "Diagnóstico AzuraCast-SAPO"
    echo "Fecha: $(date)"
    echo "AzuraCast Dir: $AZURACAST_DIR"
    echo "Web Container: $WEB_CONTAINER"
    echo ""
    echo "Contenedores:"
    echo "$CONTAINERS"
} > "$REPORT_FILE"

echo -e "\n${GREEN}Reporte guardado en: $REPORT_FILE${NC}"
