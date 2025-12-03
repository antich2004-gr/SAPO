#!/bin/bash

# Script de verificación post-instalación
# Verifica que SAPO está correctamente configurado en AzuraCast

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

SUBDOMAIN="sapo.radioslibres.info"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Verificación de Configuración SAPO${NC}"
echo -e "${BLUE}========================================${NC}\n"

# 1. Verificar que AzuraCast está corriendo
echo -e "${YELLOW}[1/8] Verificando contenedores de AzuraCast...${NC}"
CONTAINERS=$(docker ps --filter "name=azuracast" --format "{{.Names}}" | grep -E "(web|nginx)" || echo "")
if [ -z "$CONTAINERS" ]; then
    echo -e "${RED}✗ No hay contenedores web de AzuraCast en ejecución${NC}"
    exit 1
else
    WEB_CONTAINER=$(echo "$CONTAINERS" | head -n 1)
    echo -e "${GREEN}✓ Contenedor web en ejecución: $WEB_CONTAINER${NC}"
fi

# 2. Verificar que /var/www/html está montado
echo -e "\n${YELLOW}[2/8] Verificando montaje de /var/www/html...${NC}"
MOUNT_CHECK=$(docker exec $WEB_CONTAINER ls -la /var/www/html 2>/dev/null || echo "")
if [ -z "$MOUNT_CHECK" ]; then
    echo -e "${RED}✗ /var/www/html no está accesible en el contenedor${NC}"
else
    echo -e "${GREEN}✓ /var/www/html está montado:${NC}"
    docker exec $WEB_CONTAINER ls -lh /var/www/html | head -5
fi

# 3. Verificar configuración de nginx
echo -e "\n${YELLOW}[3/8] Verificando configuración de nginx...${NC}"
NGINX_CONFIG=$(docker exec $WEB_CONTAINER cat /etc/nginx/conf.d/sapo.conf 2>/dev/null || echo "")
if [ -z "$NGINX_CONFIG" ]; then
    echo -e "${RED}✗ No se encontró /etc/nginx/conf.d/sapo.conf${NC}"
else
    echo -e "${GREEN}✓ Configuración de SAPO encontrada${NC}"
    echo -e "${BLUE}Primeras líneas:${NC}"
    echo "$NGINX_CONFIG" | head -10
fi

# 4. Verificar sintaxis de nginx
echo -e "\n${YELLOW}[4/8] Verificando sintaxis de nginx...${NC}"
NGINX_TEST=$(docker exec $WEB_CONTAINER nginx -t 2>&1 || echo "error")
if [[ "$NGINX_TEST" == *"successful"* ]]; then
    echo -e "${GREEN}✓ Sintaxis de nginx correcta${NC}"
else
    echo -e "${RED}✗ Error en la sintaxis de nginx:${NC}"
    echo "$NGINX_TEST"
fi

# 5. Verificar que nginx está escuchando en puerto 80
echo -e "\n${YELLOW}[5/8] Verificando puertos...${NC}"
PORTS=$(docker port $WEB_CONTAINER 2>/dev/null || echo "")
if [[ "$PORTS" == *"80"* ]]; then
    echo -e "${GREEN}✓ Puerto 80 está mapeado:${NC}"
    echo "$PORTS" | grep "80"
else
    echo -e "${RED}✗ Puerto 80 no está mapeado${NC}"
fi

# 6. Probar acceso HTTP local
echo -e "\n${YELLOW}[6/8] Probando acceso HTTP local...${NC}"
HTTP_TEST=$(curl -s -H "Host: $SUBDOMAIN" http://localhost -o /dev/null -w "%{http_code}" 2>/dev/null || echo "000")
if [ "$HTTP_TEST" == "200" ]; then
    echo -e "${GREEN}✓ Respuesta HTTP 200 OK${NC}"
    echo -e "${BLUE}Primeras líneas del contenido:${NC}"
    curl -s -H "Host: $SUBDOMAIN" http://localhost | head -10
elif [ "$HTTP_TEST" == "000" ]; then
    echo -e "${RED}✗ No se pudo conectar${NC}"
else
    echo -e "${YELLOW}⚠ Código HTTP: $HTTP_TEST${NC}"
fi

# 7. Verificar logs
echo -e "\n${YELLOW}[7/8] Verificando logs de nginx...${NC}"
echo -e "${BLUE}Últimas líneas del log de acceso:${NC}"
docker exec $WEB_CONTAINER tail -5 /var/log/nginx/sapo-access.log 2>/dev/null || echo "Log vacío o no disponible"

echo -e "\n${BLUE}Últimas líneas del log de errores:${NC}"
docker exec $WEB_CONTAINER tail -5 /var/log/nginx/sapo-error.log 2>/dev/null || echo "Log vacío o no disponible"

# 8. Verificar DNS (si está configurado)
echo -e "\n${YELLOW}[8/8] Verificando DNS...${NC}"
DNS_CHECK=$(nslookup $SUBDOMAIN 2>/dev/null | grep -A 1 "Name:" || echo "")
if [ -z "$DNS_CHECK" ]; then
    echo -e "${YELLOW}⚠ DNS aún no está configurado o propagado${NC}"
    echo -e "${BLUE}Configura un registro A en tu DNS apuntando $SUBDOMAIN a:${NC}"
    echo -e "  $(curl -s ifconfig.me || hostname -I | awk '{print $1}')"
else
    echo -e "${GREEN}✓ DNS configurado:${NC}"
    echo "$DNS_CHECK"
fi

# Resumen
echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}RESUMEN${NC}"
echo -e "${BLUE}========================================${NC}\n"

echo -e "${GREEN}Para acceder a SAPO:${NC}"
echo -e "  1. Asegúrate de que el DNS está configurado"
echo -e "  2. Accede a: http://$SUBDOMAIN"
echo -e "\n${YELLOW}Para probar localmente sin DNS:${NC}"
echo -e "  curl -H 'Host: $SUBDOMAIN' http://localhost"
echo -e "\n${YELLOW}O añade a /etc/hosts:${NC}"
echo -e "  127.0.0.1 $SUBDOMAIN"

echo -e "\n${BLUE}========================================${NC}"
