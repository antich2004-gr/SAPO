# Script cliente_rrll.sh

Este script automatiza la gestión de podcasts descargados mediante `podget` en una emisora gestionada por AzuraCast. Incluye tareas de limpieza, renombrado, control de duración, y generación de informes. Funciona bajo entornos GNU/Linux Debian con bash y herramientas comunes como `awk`, `find`, `ffprobe` y `podget`.

## Versión
**0.9.4**  
**Última modificación:** 17-05-2025

## Parámetros de uso

```bash
./cliente_rrll.sh --emisora NOMBRE [--sinpodget]
```

- `--emisora`: Nombre de la emisora (obligatorio).
- `--sinpodget`: Omite la ejecución de podget (opcional).

## Funcionalidades principales

- ⛔ **Bloqueo concurrente por emisora**
- 📥 **Descarga automatizada de podcasts** con `podget`
- 🛠️ **Corrección automática de extensiones malformadas**
- 🗃️ **Renombrado automático** basado en nombre de carpeta y fecha
- ♻️ **Eliminación automática de archivos antiguos** por:
  - Reemplazo (mantiene solo el archivo más reciente)
  - Caducidad (según días definidos por carpeta o valor por defecto)
  - Exceso de duración (más de 5 min respecto a lo definido por carpeta)
- 📊 **Informe diario automático** con:
  - Total de podcasts descargados y archivos eliminados (detalle por causa)
  - Listado de últimos archivos descargados y eliminados (hoy y anteriores)
  - Carpetas vacías ordenadas por días sin contenido
  - Errores detectados en `podget`
  - Emisiones en directo detectadas en los logs de Liquidsoap

## Archivos requeridos por emisora

- `podgetrc.NOMBRE`: Configuración de podget
- `caducidades.txt`: Lista de carpetas y días de caducidad (`carpeta:días`)
- `duraciones.txt`: Duración por carpeta (`carpeta:1H`, `2H`, `3H`)

## Requisitos

- `bash`, `awk`, `find`, `date`, `ffprobe`, `podget`
- Acceso a carpetas `/mnt/emisoras/NOMBRE/media/`, especialmente:
  - `Podcast/`
  - `Suscripciones/`
  - `Informes/`
  - `config/liquidsoap.log`

## Históricos

- `historico_renombrados.txt`
- `historico_eliminados.txt`

Ambos se conservan con un máximo de 365 días de antigüedad.

## Notas

- El script debe ejecutarse con permisos de escritura sobre la ruta de la emisora.
- Requiere `ffprobe` para verificar duraciones.

## Ejemplo de uso

```bash
./cliente_rrll.sh --emisora radiotopo
```

## Integración con AzuraCast

SAPO puede integrarse fácilmente en el menú lateral de AzuraCast para acceso rápido desde la interfaz de administración.

📖 **Ver guía completa**: [INTEGRACION_AZURACAST.md](INTEGRACION_AZURACAST.md)

### Instalación rápida:

1. Accede a `/admin/branding` en tu instalación de AzuraCast
2. En "Custom JS for Internal Pages", copia el contenido de: `assets/azuracast-sapo-menu.js`
3. (Opcional) En "Custom CSS for Internal Pages", copia: `assets/azuracast-sapo-menu.css`
4. Guarda los cambios

Aparecerá un nuevo elemento "SAPO" en el menú lateral con un enlace a `https://sapo.radioslibres.info`

