# Plugin SAPO Menu Integration para AzuraCast

Este plugin añade un enlace a SAPO en el menú lateral de AzuraCast.

## Instalación

### Paso 1: Copiar el plugin al servidor

Copia esta carpeta completa al directorio de plugins de AzuraCast:

```bash
# En tu servidor donde está AzuraCast
cd /var/azuracast

# Crear directorio de plugins si no existe
mkdir -p plugins

# Copiar el plugin (desde donde hayas descargado este código)
cp -r plugin-azuracast /var/azuracast/plugins/sapo-menu-integration
```

O usando Git:

```bash
cd /var/azuracast/plugins
git clone https://github.com/tu-usuario/SAPO.git sapo-temp
mv sapo-temp/plugin-azuracast ./sapo-menu-integration
rm -rf sapo-temp
```

### Paso 2: Ajustar permisos

```bash
cd /var/azuracast
chown -R azuracast:azuracast plugins/sapo-menu-integration
chmod -R 755 plugins/sapo-menu-integration
```

### Paso 3: Reiniciar AzuraCast

```bash
cd /var/azuracast
docker-compose restart
```

O si usas el script de actualización:

```bash
./docker.sh restart
```

### Paso 4: Verificar

1. Accede a tu panel de AzuraCast
2. Deberías ver "SAPO" en el menú lateral con un ícono de calendario
3. Al hacer clic, se abre https://sapo.radioslibres.info en una nueva pestaña

## Estructura del Plugin

```
sapo-menu-integration/
├── plugin.json          # Metadatos del plugin
├── events.php          # Código principal del plugin
└── README.md           # Este archivo
```

## Desinstalación

```bash
cd /var/azuracast
rm -rf plugins/sapo-menu-integration
docker-compose restart
```

## Soporte

Si tienes problemas:

1. Verifica los logs: `docker-compose logs -f web`
2. Verifica que el directorio existe: `ls -la /var/azuracast/plugins/`
3. Verifica permisos: `ls -la /var/azuracast/plugins/sapo-menu-integration/`

## Compatibilidad

- AzuraCast 0.x.x y superior
- Compatible con Docker y Ansible installations
