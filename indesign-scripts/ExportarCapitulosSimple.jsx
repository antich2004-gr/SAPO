/*
 * Script: Exportar Capítulos a PDF (VERSIÓN SIMPLE)
 * Descripción: Versión simplificada para exportación rápida de capítulos
 *
 * CONFIGURACIÓN RÁPIDA:
 * 1. Edita las variables abajo según tus necesidades
 * 2. Nombra las cajas de texto de tus capítulos con un prefijo común
 * 3. Ejecuta el script
 *
 * INSTRUCCIONES:
 * - Nombra tus cajas: Capitulo1, Capitulo2, Capitulo3, etc.
 * - O usa cualquier otro prefijo consistente
 */

#target indesign

// ========================================
// CONFIGURACIÓN - EDITA ESTAS VARIABLES
// ========================================

var PREFIJO_CAJA = "Capitulo";  // Prefijo de las cajas de texto de tus capítulos
var CARPETA_DESTINO = "~/Desktop/Capitulos_PDF";  // Carpeta donde se guardarán los PDFs
var PREFIJO_ARCHIVO = "Cap_";  // Prefijo para los nombres de archivo PDF
var PREAJUSTE_PDF = "[Press Quality]";  // Calidad del PDF

// Otras opciones de PREAJUSTE_PDF:
// "[High Quality Print]" - Alta calidad para pantalla
// "[Smallest File Size]" - Tamaño reducido para email
// "[PDF/X-1a:2001]" - Estándar de imprenta
// "[PDF/X-4:2008]" - Estándar moderno de imprenta

// ========================================
// CÓDIGO DEL SCRIPT - NO EDITAR DEBAJO
// ========================================

function main() {
    // Verificar que hay un documento abierto
    if (app.documents.length === 0) {
        alert("ERROR: No hay ningún documento abierto.\n\nAbre tu documento de InDesign antes de ejecutar este script.");
        return;
    }

    var doc = app.activeDocument;

    // Buscar cajas de texto que coincidan con el prefijo
    var cajasCapitulo = [];
    var allTextFrames = doc.textFrames;

    for (var i = 0; i < allTextFrames.length; i++) {
        var frame = allTextFrames[i];
        if (frame.name && frame.name.indexOf(PREFIJO_CAJA) === 0) {
            cajasCapitulo.push(frame);
        }
    }

    // Verificar que se encontraron cajas
    if (cajasCapitulo.length === 0) {
        alert("ERROR: No se encontraron cajas de texto con el prefijo '" + PREFIJO_CAJA + "'.\n\n" +
              "SOLUCIÓN:\n" +
              "1. Selecciona la caja de texto de cada capítulo\n" +
              "2. Ve a Ventana > Capas\n" +
              "3. Haz doble clic en el nombre de la caja en el panel\n" +
              "4. Nómbrala como: " + PREFIJO_CAJA + "1, " + PREFIJO_CAJA + "2, etc.\n\n" +
              "O edita la variable PREFIJO_CAJA en el script para que coincida con tus nombres.");
        return;
    }

    // Ordenar cajas alfabéticamente por nombre
    cajasCapitulo.sort(function(a, b) {
        return a.name.localeCompare(b.name);
    });

    // Mostrar confirmación antes de exportar
    var mensaje = "Se encontraron " + cajasCapitulo.length + " capítulos:\n\n";
    for (var i = 0; i < Math.min(cajasCapitulo.length, 10); i++) {
        mensaje += "  • " + cajasCapitulo[i].name + "\n";
    }
    if (cajasCapitulo.length > 10) {
        mensaje += "  ... y " + (cajasCapitulo.length - 10) + " más\n";
    }
    mensaje += "\nCarpeta destino: " + CARPETA_DESTINO + "\n\n¿Continuar con la exportación?";

    if (!confirm(mensaje)) {
        return;
    }

    // Crear carpeta de destino si no existe
    var carpeta = new Folder(CARPETA_DESTINO);
    if (!carpeta.exists) {
        if (!carpeta.create()) {
            alert("ERROR: No se pudo crear la carpeta:\n" + CARPETA_DESTINO + "\n\n" +
                  "Verifica la ruta y los permisos.");
            return;
        }
    }

    // Obtener preajuste de PDF
    var preajuste;
    try {
        preajuste = app.pdfExportPresets.itemByName(PREAJUSTE_PDF);
    } catch (e) {
        alert("ADVERTENCIA: No se encontró el preajuste '" + PREAJUSTE_PDF + "'.\n" +
              "Se usará el preajuste por defecto.");
        preajuste = app.pdfExportPresets[0];
    }

    // Guardar configuración original
    var pdfExportPrefs = doc.pdfExportPreferences;
    var rangoOriginal = pdfExportPrefs.pageRange;

    // Barra de progreso
    var progreso = new Window("palette", "Exportando capítulos...");
    progreso.add("statictext", undefined, "Procesando capítulos...");
    var barraProgreso = progreso.add("progressbar", undefined, 0, cajasCapitulo.length);
    var textoProgreso = progreso.add("statictext", undefined, "0 de " + cajasCapitulo.length);
    progreso.show();

    var exportados = 0;
    var errores = [];

    // Exportar cada capítulo
    for (var i = 0; i < cajasCapitulo.length; i++) {
        var caja = cajasCapitulo[i];
        barraProgreso.value = i;
        textoProgreso.text = (i + 1) + " de " + cajasCapitulo.length + " - " + caja.name;
        progreso.update();

        try {
            // Obtener páginas de la caja
            var paginas = obtenerPaginasDeCaja(caja);

            if (paginas.length === 0) {
                errores.push(caja.name + ": No se pudieron detectar páginas");
                continue;
            }

            // Crear nombre de archivo
            var nombreArchivo = PREFIJO_ARCHIVO + caja.name + ".pdf";
            var archivo = new File(carpeta + "/" + nombreArchivo);

            // Exportar a PDF
            var rango = paginas.join(",");
            pdfExportPrefs.pageRange = rango;
            doc.exportFile(ExportFormat.PDF_TYPE, archivo, false, preajuste);

            exportados++;

        } catch (e) {
            errores.push(caja.name + ": " + e.message);
        }
    }

    // Restaurar configuración original
    pdfExportPrefs.pageRange = rangoOriginal;

    // Cerrar barra de progreso
    progreso.close();

    // Mostrar resultado
    var resultado = "EXPORTACIÓN COMPLETADA\n\n";
    resultado += "Capítulos exportados: " + exportados + " de " + cajasCapitulo.length + "\n";
    resultado += "Ubicación: " + carpeta.fsName + "\n";

    if (errores.length > 0) {
        resultado += "\n⚠️ ERRORES (" + errores.length + "):\n";
        for (var i = 0; i < Math.min(errores.length, 5); i++) {
            resultado += "  • " + errores[i] + "\n";
        }
        if (errores.length > 5) {
            resultado += "  ... y " + (errores.length - 5) + " más\n";
        }
    }

    alert(resultado);

    // Abrir carpeta de destino
    if (confirm("¿Deseas abrir la carpeta con los PDFs?")) {
        carpeta.execute();
    }
}

// Función auxiliar: Obtener páginas que contiene una caja de texto
function obtenerPaginasDeCaja(caja) {
    var paginas = [];
    var paginasNumeros = [];

    try {
        // Buscar la página padre
        var parent = caja.parent;
        while (parent && parent.constructor.name !== "Page" && parent.constructor.name !== "Spread") {
            parent = parent.parent;
        }

        // Agregar páginas del parent
        if (parent) {
            if (parent.constructor.name === "Page") {
                var numPagina = parseInt(parent.name);
                if (!isNaN(numPagina)) {
                    paginasNumeros.push(numPagina);
                }
            } else if (parent.constructor.name === "Spread") {
                for (var i = 0; i < parent.pages.length; i++) {
                    var numPagina = parseInt(parent.pages[i].name);
                    if (!isNaN(numPagina)) {
                        paginasNumeros.push(numPagina);
                    }
                }
            }
        }

        // Si la caja tiene texto enlazado, seguir la cadena
        var cajaActual = caja;
        var maxIteraciones = 100; // Prevenir bucles infinitos
        var iteraciones = 0;

        while (cajaActual.nextTextFrame && iteraciones < maxIteraciones) {
            cajaActual = cajaActual.nextTextFrame;
            iteraciones++;

            parent = cajaActual.parent;
            while (parent && parent.constructor.name !== "Page" && parent.constructor.name !== "Spread") {
                parent = parent.parent;
            }

            if (parent) {
                if (parent.constructor.name === "Page") {
                    var numPagina = parseInt(parent.name);
                    if (!isNaN(numPagina) && paginasNumeros.indexOf(numPagina) === -1) {
                        paginasNumeros.push(numPagina);
                    }
                } else if (parent.constructor.name === "Spread") {
                    for (var i = 0; i < parent.pages.length; i++) {
                        var numPagina = parseInt(parent.pages[i].name);
                        if (!isNaN(numPagina) && paginasNumeros.indexOf(numPagina) === -1) {
                            paginasNumeros.push(numPagina);
                        }
                    }
                }
            }
        }

        // Ordenar páginas numéricamente
        paginasNumeros.sort(function(a, b) { return a - b; });

        // Convertir a array de strings
        for (var i = 0; i < paginasNumeros.length; i++) {
            paginas.push(paginasNumeros[i].toString());
        }

    } catch (e) {
        // Si hay error, intentar obtener al menos la página actual
        try {
            var parent = caja.parent;
            while (parent && parent.constructor.name !== "Page") {
                parent = parent.parent;
            }
            if (parent && parent.constructor.name === "Page") {
                paginas.push(parent.name);
            }
        } catch (e2) {
            // Ignorar
        }
    }

    return paginas;
}

// Ejecutar script
try {
    main();
} catch (e) {
    alert("ERROR CRÍTICO:\n" + e.message + "\n\nLínea: " + e.line);
}
