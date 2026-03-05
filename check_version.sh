#!/bin/bash
# check_version.sh - Script para verificar la versión instalada de SAPO

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}    SAPO - Verificación de Versión${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

# Obtener directorio del script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

# 1. Verificar que estamos en un repositorio git
if [ ! -d ".git" ]; then
    echo -e "${RED}✗ Error: No se encuentra el directorio .git${NC}"
    echo -e "  Este script debe ejecutarse desde el directorio raíz de SAPO"
    exit 1
fi

echo -e "${GREEN}✓ Repositorio Git detectado${NC}"
echo ""

# 2. Obtener versión instalada desde config.php
echo -e "${BLUE}Versión Instalada:${NC}"
INSTALLED_VERSION=$(grep "define('SAPO_VERSION'" config.php | sed -E "s/.*'([^']+)'.*/\1/")
INSTALLED_DATE=$(grep "define('SAPO_VERSION_DATE'" config.php | sed -E "s/.*'([^']+)'.*/\1/")
CURRENT_COMMIT=$(git rev-parse --short HEAD)
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [ -n "$INSTALLED_VERSION" ]; then
    echo -e "  Versión: ${GREEN}$INSTALLED_VERSION${NC}"
    echo -e "  Fecha:   $INSTALLED_DATE"
else
    echo -e "  ${YELLOW}⚠ No se pudo detectar la versión en config.php${NC}"
fi

echo -e "  Commit:  $CURRENT_COMMIT"
echo -e "  Rama:    $CURRENT_BRANCH"
echo ""

# 3. Verificar estado del repositorio
echo -e "${BLUE}Estado del Repositorio:${NC}"

# Verificar si hay cambios sin commitear
if ! git diff-index --quiet HEAD --; then
    echo -e "  ${YELLOW}⚠ Hay cambios locales sin commitear${NC}"
    MODIFIED_COUNT=$(git status --porcelain | wc -l)
    echo -e "  Archivos modificados: $MODIFIED_COUNT"
else
    echo -e "  ${GREEN}✓ No hay cambios locales${NC}"
fi
echo ""

# 4. Comparar con el repositorio remoto
echo -e "${BLUE}Comparación con GitHub:${NC}"

# Hacer fetch para obtener la información más reciente (sin hacer merge)
echo -e "  Consultando GitHub..."
git fetch origin --quiet 2>/dev/null

if [ $? -eq 0 ]; then
    # Obtener el commit más reciente de la rama principal en el remoto
    MAIN_BRANCH="main"
    if ! git rev-parse --verify origin/$MAIN_BRANCH >/dev/null 2>&1; then
        MAIN_BRANCH="master"
    fi

    REMOTE_COMMIT=$(git rev-parse origin/$MAIN_BRANCH 2>/dev/null)
    REMOTE_COMMIT_SHORT=$(git rev-parse --short origin/$MAIN_BRANCH 2>/dev/null)

    if [ -n "$REMOTE_COMMIT" ]; then
        echo -e "  Último commit en origin/$MAIN_BRANCH: $REMOTE_COMMIT_SHORT"

        # Verificar si estamos actualizados
        if [ "$CURRENT_COMMIT" = "$(git rev-parse --short $REMOTE_COMMIT)" ]; then
            echo -e "  ${GREEN}✓ Tu instalación está actualizada con GitHub${NC}"
        else
            # Verificar cuántos commits estamos detrás
            COMMITS_BEHIND=$(git rev-list --count HEAD..origin/$MAIN_BRANCH 2>/dev/null)
            COMMITS_AHEAD=$(git rev-list --count origin/$MAIN_BRANCH..HEAD 2>/dev/null)

            if [ "$COMMITS_BEHIND" -gt 0 ]; then
                echo -e "  ${YELLOW}⚠ Tu instalación está $COMMITS_BEHIND commit(s) detrás de GitHub${NC}"
                echo ""
                echo -e "${YELLOW}  Últimos commits en GitHub:${NC}"
                git log --oneline HEAD..origin/$MAIN_BRANCH | head -5 | sed 's/^/    /'
            fi

            if [ "$COMMITS_AHEAD" -gt 0 ]; then
                echo -e "  ${BLUE}ℹ Tu instalación tiene $COMMITS_AHEAD commit(s) locales no publicados${NC}"
            fi
        fi
    else
        echo -e "  ${RED}✗ No se pudo obtener información del remoto${NC}"
    fi
else
    echo -e "  ${RED}✗ Error al conectar con GitHub${NC}"
    echo -e "  Verifica tu conexión a internet y acceso al repositorio"
fi
echo ""

# 5. Mostrar recomendaciones
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}Recomendaciones:${NC}"
echo ""

if [ "$COMMITS_BEHIND" -gt 0 ]; then
    echo -e "  ${YELLOW}Para actualizar a la última versión:${NC}"
    echo -e "    git pull origin $MAIN_BRANCH"
    echo ""
    echo -e "  ${YELLOW}Antes de actualizar, asegúrate de:${NC}"
    echo -e "    1. Hacer backup de tu base de datos (db.json)"
    echo -e "    2. Revisar el changelog de cambios"
    echo -e "    3. Verificar que no tengas cambios locales importantes"
else
    echo -e "  ${GREEN}✓ Tu instalación está al día${NC}"
fi

echo ""
echo -e "${BLUE}Para más información:${NC}"
echo -e "  - Ver versión en navegador: https://tu-servidor/sapo/version.php"
echo -e "  - Ver cambios recientes: git log --oneline -10"
echo -e "  - Ver archivos modificados: git status"
echo ""
