# Exportar Capítulos a PDF - Guía de Uso

## Descripción

Este conjunto de scripts automatiza la exportación de capítulos individuales de un libro de InDesign a PDFs separados. Ideal para enviar capítulos individuales a diferentes autores o revisores.

## Instalación

1. **Ubicar la carpeta de Scripts de InDesign:**
   - **Windows:** `C:\Users\[TuUsuario]\AppData\Roaming\Adobe\InDesign\[Versión]\es_ES\Scripts\Scripts Panel`
   - **Mac:** `~/Library/Preferences/Adobe InDesign/[Versión]/es_ES/Scripts/Scripts Panel`

2. **Copiar los archivos:**
   - Copia `ExportarCapitulosPDF.jsx` y/o `ExportarCapitulosSimple.jsx` a la carpeta de Scripts

3. **Reiniciar InDesign** (si estaba abierto)

## Uso del Script Principal (ExportarCapitulosPDF.jsx)

### Método 1: Exportar por Nombre de Cajas de Texto (RECOMENDADO)

Este es el método más flexible y fácil de usar:

1. **Preparar el documento:**
   - Nombra cada caja de texto principal de cada capítulo
   - Ve a: Ventana > Objetos y capas > Capas
   - Selecciona la caja de texto del capítulo
   - En el panel de Capas, haz doble clic en el nombre del objeto
   - Nómbrala siguiendo un patrón: `Capitulo1`, `Capitulo2`, `Capitulo_Einstein`, etc.

2. **Ejecutar el script:**
   - Ve a: Ventana > Utilidades > Scripts
   - Haz doble clic en `ExportarCapitulosPDF.jsx`

3. **Configurar opciones:**
   - Selecciona "Por nombre de cajas de texto"
   - Ingresa el prefijo común (ej: "Capitulo")
   - Selecciona la carpeta de destino
   - Elige la calidad del PDF
   - Haz clic en "Exportar"

4. **Resultado:**
   - Se generará un PDF por cada caja que tenga el prefijo especificado
   - Los archivos se nombrarán según el nombre de la caja

### Método 2: Exportar por Rangos de Páginas

Si prefieres especificar manualmente los rangos:

1. **Ejecutar el script** y seleccionar "Por rangos de páginas"
2. **Ingresar rangos** en el formato: `1-5, 6-12, 13-20, 21-30`
3. Los PDFs se numerarán automáticamente: `Capitulo_1.pdf`, `Capitulo_2.pdf`, etc.

### Método 3: Exportar por Marcadores

Si ya tienes marcadores configurados en tu documento:

1. **Crear marcadores:**
   - Ve a: Ventana > Interactivo > Marcadores
   - Crea un marcador al inicio de cada capítulo
   - Nómbralos apropiadamente

2. **Ejecutar el script** y seleccionar "Por marcadores"
3. El script exportará desde cada marcador hasta el siguiente

## Uso del Script Simple (ExportarCapitulosSimple.jsx)

Para una exportación rápida sin configuraciones:

1. **Configura el script** editando las variables al inicio del archivo:
   ```javascript
   var PREFIJO_CAJA = "Capitulo";  // Prefijo de las cajas de texto
   var CARPETA_DESTINO = "~/Desktop/Capitulos_PDF";
   var PREFIJO_ARCHIVO = "Cap_";
   ```

2. **Ejecuta el script** desde el panel de Scripts

3. **Resultado:** PDFs generados automáticamente en la carpeta especificada

## Preajustes de PDF

El script ofrece varios preajustes de calidad:

- **Alta calidad:** Para revisión en pantalla con buena calidad
- **Calidad de impresión:** Para enviar a imprenta (RECOMENDADO)
- **Tamaño de archivo más pequeño:** Para envíos por email
- **PDF/X-1a:2001:** Estándar de imprenta
- **PDF/X-4:2008:** Estándar de imprenta moderno

## Solución de Problemas

### "No se encontraron cajas de texto con el prefijo..."

**Causa:** Las cajas no están nombradas o el prefijo no coincide

**Solución:**
1. Verifica que las cajas estén nombradas en el panel de Capas
2. Asegúrate de que el prefijo coincida exactamente (es sensible a mayúsculas)

### "Error al exportar..."

**Causas posibles:**
- La carpeta de destino no tiene permisos de escritura
- Hay un archivo PDF abierto con el mismo nombre
- La caja de texto está dañada o vacía

**Solución:**
- Cierra cualquier PDF abierto con ese nombre
- Verifica los permisos de la carpeta de destino
- Intenta exportar los capítulos problemáticos manualmente

### El PDF no incluye todas las páginas del capítulo

**Causa:** El script detecta páginas basándose en la caja de texto

**Solución:**
- Si tu capítulo tiene cajas enlazadas, el script debería detectarlas automáticamente
- Si el capítulo ocupa múltiples spreads sin cajas enlazadas, usa el método de "Rangos de páginas"

## Consejos y Mejores Prácticas

1. **Nomenclatura consistente:**
   - Usa un patrón claro: `Cap01`, `Cap02`, etc.
   - O nombres descriptivos: `Cap_Einstein`, `Cap_Hawking`

2. **Estructura del documento:**
   - Mantén una caja principal por capítulo (pueden estar enlazadas)
   - Usa capas para organizar mejor los elementos

3. **Prueba primero:**
   - Exporta 2-3 capítulos primero para verificar que todo funcione correctamente
   - Revisa que los rangos de páginas sean correctos

4. **Backup:**
   - Guarda una copia de tu documento antes de ejecutar scripts masivos

5. **Personalización:**
   - Los scripts son editables; puedes modificar nombres de archivo, rutas por defecto, etc.

## Personalización del Script

Puedes editar el script para ajustarlo a tus necesidades:

```javascript
// Cambiar el prefijo por defecto
var prefijoInput = cajasGroup.add("edittext", undefined, "MiPrefijo");

// Cambiar la carpeta por defecto
var carpetaInput = carpetaGroup.add("edittext", undefined, "~/Documents/PDFs");

// Cambiar el prefijo de archivos
var prefijoArchivoInput = archivoGroup.add("edittext", undefined, "Libro_");
```

## Ejemplos de Uso

### Ejemplo 1: Libro académico con autores diferentes

- **Cajas nombradas:** `Autor_Garcia`, `Autor_Lopez`, `Autor_Martinez`
- **Prefijo:** "Autor_"
- **Resultado:** 3 PDFs individuales con los nombres de los autores

### Ejemplo 2: Novela por capítulos numerados

- **Cajas nombradas:** `Cap01`, `Cap02`, ..., `Cap20`
- **Prefijo:** "Cap"
- **Resultado:** 20 PDFs numerados secuencialmente

### Ejemplo 3: Exportación mixta

- Algunos capítulos por nombre de caja
- Otros por rango de páginas (para capítulos complejos)
- Ejecutar el script dos veces con diferentes configuraciones

## Soporte y Modificaciones

Para modificaciones adicionales o funcionalidades específicas, edita los archivos `.jsx` con cualquier editor de texto.

## Notas Técnicas

- Los scripts usan ExtendScript (JavaScript para Adobe)
- Compatibles con InDesign CS6 y versiones posteriores
- Los preajustes de PDF deben existir en tu instalación de InDesign
- El script preserva la configuración original de exportación de tu documento
