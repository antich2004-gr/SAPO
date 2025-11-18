# Guía de Uso: Exportar Capítulos por Autor

## Resumen

Este script automatiza la exportación de capítulos de InDesign a PDFs individuales, extrayendo automáticamente el nombre del autor de cada capítulo y generando archivos con nomenclatura personalizada.

**Formato de archivo:** `[prefijo][numero]_[NombreAutor]_[NombreLibro].pdf`

**Ejemplo:** `prueba1_01_Juan_Garcia_Mi_Libro.pdf`

---

## Preparación del Documento

### 1. Nombrar las Cajas de Texto

Cada capítulo debe tener una caja de texto principal con un nombre identificable:

1. Selecciona la caja de texto del capítulo
2. Abre el panel de **Capas** (Ventana > Capas)
3. Localiza el objeto seleccionado en el panel
4. Haz doble clic en el nombre del objeto
5. Asigna un nombre siguiendo un patrón consistente:
   - `Capitulo1`, `Capitulo2`, `Capitulo3`...
   - `Cap_01`, `Cap_02`, `Cap_03`...
   - O cualquier otro patrón con un prefijo común

**Importante:** Todas las cajas deben compartir el mismo prefijo.

### 2. Aplicar Estilo de Párrafo "autor"

El nombre del autor se extrae automáticamente del texto que tenga aplicado el estilo de párrafo "autor":

1. Crea un estilo de párrafo llamado exactamente **"autor"** (minúsculas, sin espacios)
2. Aplica este estilo al nombre del autor en cada capítulo
3. El script extraerá automáticamente el texto con este estilo

**Ejemplo:**

```
[Título del Capítulo]

Juan García Martínez  ← Este texto tiene el estilo "autor"

[Contenido del capítulo...]
```

**Notas:**
- El estilo debe llamarse exactamente "autor"
- Si el estilo tiene otro nombre, puedes cambiarlo en el diálogo del script
- Si no se encuentra el estilo, el script usará "AutorDesconocido_[NombreCaja]"

---

## Uso del Script

### Paso 1: Ejecutar el Script

1. Abre tu documento de InDesign
2. Ve a **Ventana > Utilidades > Scripts**
3. Haz doble clic en **ExportarCapitulosAutores.jsx**

### Paso 2: Configurar Identificación de Capítulos

**Prefijo de las cajas de texto:**
- Ingresa el prefijo común de tus cajas (ej: "Capitulo", "Cap", etc.)
- Debe coincidir exactamente con el inicio del nombre de las cajas

**Nombre del estilo de párrafo:**
- Por defecto: "autor"
- Cámbialo si usas otro nombre para el estilo
- Es sensible a mayúsculas/minúsculas

### Paso 3: Configurar Nomenclatura de Archivos

**Prefijo del archivo:**
- Ejemplo: "prueba1", "version_final", "borrador"
- Este texto aparecerá al inicio del nombre de cada PDF

**Nombre del libro:**
- El título del libro completo
- Se usará en todos los nombres de archivo
- Ejemplo: "Antología Literaria 2024"

**Número inicial de numeración:**
- Número con el que empezará la numeración
- Por defecto: 1
- Si tienes 10 capítulos empezando en 1, generará: 01, 02, 03...10
- Si tienes 15 capítulos empezando en 5, generará: 05, 06, 07...19

### Paso 4: Seleccionar Preajuste de PDF

Elige el perfil de PDF que deseas usar:

- **[Press Quality]** - Recomendado para imprenta
- **[High Quality Print]** - Alta calidad para pantalla
- **[Smallest File Size]** - Para envíos por email
- **[PDF/X-1a:2001]** - Estándar de imprenta tradicional
- **[PDF/X-4:2008]** - Estándar moderno de imprenta

El script mostrará todos los preajustes disponibles en tu instalación de InDesign.

### Paso 5: Seleccionar Carpeta de Destino

- Elige dónde se guardarán los PDFs
- Por defecto: `~/Desktop/Capitulos_Autores`
- Puedes usar el botón "Examinar" para seleccionar otra carpeta

### Paso 6: Previsualización

Antes de exportar, el script mostrará una previsualización:

```
1. Capitulo1
   Número: 1
   Autor: Juan García Martínez
   Páginas: 5, 6, 7
   Archivo: prueba1_01_Juan_Garcia_Martinez_Antologia_2024.pdf

2. Capitulo2
   Número: 2
   Autor: María López Fernández
   Páginas: 8, 9, 10
   Archivo: prueba1_02_Maria_Lopez_Fernandez_Antologia_2024.pdf
```

**Revisa cuidadosamente:**
- Que los autores se hayan detectado correctamente
- Que las páginas sean las esperadas
- Que los nombres de archivo sean correctos

### Paso 7: Exportación

1. Si todo es correcto, haz clic en "Exportar"
2. El script mostrará una barra de progreso
3. Al finalizar, mostrará un resumen con:
   - Número de capítulos exportados
   - Ubicación de los archivos
   - Lista de errores (si los hubo)
4. Opcionalmente, puedes abrir la carpeta con los PDFs generados

---

## Ejemplos de Uso

### Ejemplo 1: Antología con 20 Autores

**Configuración:**
- Prefijo cajas: "Capitulo"
- Estilo párrafo: "autor"
- Prefijo archivo: "Antologia2024"
- Nombre libro: "Voces Contemporaneas"
- Número inicial: 1

**Cajas nombradas:**
- Capitulo1, Capitulo2, ... Capitulo20

**Resultado:**
```
Antologia2024_01_Juan_Garcia_Voces_Contemporaneas.pdf
Antologia2024_02_Maria_Lopez_Voces_Contemporaneas.pdf
...
Antologia2024_20_Pedro_Sanchez_Voces_Contemporaneas.pdf
```

### Ejemplo 2: Libro Colaborativo con Numeración Personalizada

**Configuración:**
- Prefijo archivo: "LibroV2"
- Nombre libro: "Investigación Científica"
- Número inicial: 5 (porque los capítulos 1-4 están en otro documento)

**Resultado:**
```
LibroV2_05_Ana_Martin_Investigacion_Cientifica.pdf
LibroV2_06_Carlos_Ruiz_Investigacion_Cientifica.pdf
LibroV2_07_Elena_Torres_Investigacion_Cientifica.pdf
```

### Ejemplo 3: Múltiples Versiones

**Primera exportación:**
- Prefijo: "borrador1"
- Los autores revisan

**Segunda exportación:**
- Prefijo: "version_final"
- Mismo documento, nueva nomenclatura

---

## Solución de Problemas

### El script no encuentra cajas de texto

**Problema:** "No se encontraron cajas de texto con el prefijo 'Capitulo'"

**Soluciones:**
1. Verifica que las cajas estén nombradas en el panel de Capas
2. Comprueba que el prefijo coincida exactamente (es sensible a mayúsculas)
3. Asegúrate de que el documento esté abierto

### El autor aparece como "AutorDesconocido"

**Problema:** El script no detecta el nombre del autor

**Soluciones:**
1. Verifica que el estilo de párrafo se llame exactamente "autor"
2. Comprueba que el estilo esté aplicado al texto del nombre del autor
3. Asegúrate de que el texto del autor esté dentro de la caja del capítulo
4. Si el estilo tiene otro nombre, cámbialo en el diálogo del script

### El PDF no incluye todas las páginas

**Problema:** Faltan páginas en el PDF exportado

**Soluciones:**
1. Si el capítulo tiene cajas enlazadas, el script debería detectarlas automáticamente
2. Verifica que las cajas estén correctamente enlazadas
3. Comprueba en la previsualización que las páginas sean las correctas

### Caracteres extraños en nombres de archivo

**Problema:** Los nombres tienen caracteres raros o se pierden acentos

**Solución:**
- El script limpia automáticamente caracteres no válidos
- Los espacios se convierten en guiones bajos
- Caracteres prohibidos se eliminan: `< > : " | ? * / \`
- Los acentos y ñ se mantienen si el sistema operativo los soporta

### Error al exportar un capítulo específico

**Problema:** Un capítulo falla pero los demás se exportan correctamente

**Soluciones:**
1. Revisa que la caja no esté dañada
2. Verifica que el capítulo tenga contenido
3. Comprueba que las páginas existan en el documento
4. Intenta exportar ese capítulo manualmente para identificar el problema

---

## Consejos y Mejores Prácticas

### Organización del Documento

1. **Consistencia en nombres:** Usa un patrón claro y consistente para nombrar cajas
2. **Numeración lógica:** Aunque las cajas se pueden nombrar libremente, usa números consecutivos
3. **Capas organizadas:** Mantén las cajas de capítulos en una capa específica para fácil identificación

### Estilos de Párrafo

1. **Estilo único para autores:** No uses el estilo "autor" para otros propósitos
2. **Solo el nombre:** Aplica el estilo solo al nombre del autor, no a títulos adicionales
3. **Formato consistente:** Mantén un formato uniforme en todos los nombres de autor

### Nomenclatura de Archivos

1. **Prefijos descriptivos:** Usa prefijos que identifiquen claramente la versión
   - ✓ "revision1", "final", "impresion"
   - ✗ "v1", "test", "aaa"

2. **Nombres de libro cortos:** Evita nombres muy largos que compliquen la gestión de archivos
   - ✓ "Antologia_2024"
   - ✗ "Antologia_Completa_De_Literatura_Contemporanea_Hispanoamericana_2024"

3. **Numeración apropiada:** El script ajusta automáticamente los dígitos necesarios
   - 1-9 capítulos: 01, 02, 03...
   - 10-99 capítulos: 01, 02, ..., 10, 11...
   - 100+ capítulos: 001, 002, ..., 100, 101...

### Proceso de Trabajo Recomendado

1. **Prueba inicial:** Exporta 2-3 capítulos primero para verificar configuración
2. **Revisión de previsualización:** Siempre revisa la previsualización antes de exportar todo
3. **Backup:** Guarda una copia del documento antes de hacer cambios masivos
4. **Versionado:** Usa prefijos diferentes para cada revisión (borrador1, borrador2, final)

### Optimización

1. **Exportación por lotes:** Si tienes muchos capítulos, el script puede tardar varios minutos
2. **Recursos del sistema:** InDesign puede consumir mucha memoria durante la exportación
3. **Cierra otros documentos:** Para mejor rendimiento, cierra documentos no necesarios

---

## Personalización del Script

Si necesitas modificar el comportamiento del script, puedes editar el archivo `.jsx` con cualquier editor de texto.

### Cambiar el estilo de párrafo por defecto:

Busca la línea:
```javascript
var estiloAutorInput = grupoCajas.add("edittext", undefined, "autor");
```

Cámbiala a:
```javascript
var estiloAutorInput = grupoCajas.add("edittext", undefined, "nombre_autor");
```

### Cambiar la carpeta por defecto:

Busca la línea:
```javascript
var carpetaInput = grupoCarpeta.add("edittext", undefined, "~/Desktop/Capitulos_Autores");
```

### Cambiar el formato del nombre de archivo:

Busca la función `generarNombreArchivo` y modifica el return:
```javascript
return prefijoLimpio + numeroFormateado + "_" + nombreAutorLimpio + "_" + nombreLibroLimpio + ".pdf";
```

---

## Preguntas Frecuentes

**P: ¿Puedo usar este script con libros de InDesign (.indb)?**
R: El script funciona con documentos individuales (.indd). Para libros, ejecuta el script en cada documento del libro.

**P: ¿Funciona con cajas de texto enlazadas?**
R: Sí, el script sigue automáticamente las cadenas de cajas de texto enlazadas.

**P: ¿Puedo exportar solo algunos capítulos?**
R: Actualmente el script exporta todos los capítulos que encuentre. Para exportar solo algunos, puedes:
- Renombrar temporalmente las cajas que no quieres exportar
- O crear un script personalizado con selección manual

**P: ¿Qué versiones de InDesign soporta?**
R: InDesign CS6 y versiones posteriores (CC 2014, 2015, 2018, 2019, 2020, 2021, 2022, 2023, 2024).

**P: ¿Los nombres de archivo soportan acentos y ñ?**
R: Sí, pero depende del sistema operativo. En Windows y Mac modernos funcionan correctamente.

**P: ¿Puedo cambiar el separador del nombre de archivo (el "_")?**
R: Sí, editando la función `generarNombreArchivo` en el script. Puedes usar "-" o cualquier otro carácter válido.

---

## Soporte

Para problemas, sugerencias o mejoras:
- Revisa primero esta guía y la sección de solución de problemas
- Verifica que el script esté actualizado
- Asegúrate de tener la última versión de InDesign con todas las actualizaciones

## Licencia

Este script es de uso libre para propósitos educativos y comerciales.
