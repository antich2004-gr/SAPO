/*
 * Script: Exportar Capítulos a PDF
 * Descripción: Exporta automáticamente cada capítulo de un libro de InDesign como PDF individual
 * Autor: Script generado para automatización de InDesign
 * Versión: 1.0
 *
 * INSTRUCCIONES DE USO:
 * 1. Abre tu documento de InDesign
 * 2. Ve a Ventana > Utilidades > Scripts
 * 3. Haz doble clic en este script
 * 4. Configura las opciones en el diálogo que aparece
 * 5. El script generará un PDF por cada capítulo
 */

#target indesign

// Función principal
function main() {
    if (app.documents.length === 0) {
        alert("Por favor, abre un documento antes de ejecutar este script.");
        return;
    }

    var doc = app.activeDocument;

    // Mostrar diálogo de configuración
    var config = mostrarDialogoConfiguracion();
    if (!config) return; // Usuario canceló

    // Ejecutar exportación según el método seleccionado
    switch(config.metodo) {
        case 0: // Por nombre de caja de texto
            exportarPorCajasTexto(doc, config);
            break;
        case 1: // Por páginas (rango manual)
            exportarPorRangos(doc, config);
            break;
        case 2: // Por marcadores
            exportarPorMarcadores(doc, config);
            break;
    }
}

// Diálogo de configuración
function mostrarDialogoConfiguracion() {
    var dialog = new Window("dialog", "Exportar Capítulos a PDF");

    // Método de exportación
    dialog.add("statictext", undefined, "Método de exportación:");
    var metodoGroup = dialog.add("group");
    metodoGroup.orientation = "column";
    metodoGroup.alignChildren = "left";

    var rbCajas = metodoGroup.add("radiobutton", undefined, "Por nombre de cajas de texto");
    var rbRangos = metodoGroup.add("radiobutton", undefined, "Por rangos de páginas (manual)");
    var rbMarcadores = metodoGroup.add("radiobutton", undefined, "Por marcadores");
    rbCajas.value = true;

    // Configuración para cajas de texto
    dialog.add("statictext", undefined, "Configuración (cajas de texto):");
    var cajasGroup = dialog.add("group");
    cajasGroup.add("statictext", undefined, "Prefijo del nombre:");
    var prefijoInput = cajasGroup.add("edittext", undefined, "Capitulo");
    prefijoInput.characters = 20;

    // Carpeta de destino
    dialog.add("statictext", undefined, "Carpeta de destino:");
    var carpetaGroup = dialog.add("group");
    var carpetaInput = carpetaGroup.add("edittext", undefined, "~/Desktop/Capitulos");
    carpetaInput.characters = 30;
    var btnCarpeta = carpetaGroup.add("button", undefined, "Examinar...");

    btnCarpeta.onClick = function() {
        var carpeta = Folder.selectDialog("Selecciona la carpeta de destino");
        if (carpeta) {
            carpetaInput.text = carpeta.fsName;
        }
    };

    // Prefijo de archivos PDF
    var archivoGroup = dialog.add("group");
    archivoGroup.add("statictext", undefined, "Prefijo PDF:");
    var prefijoArchivoInput = archivoGroup.add("edittext", undefined, "Capitulo_");
    prefijoArchivoInput.characters = 20;

    // Calidad del PDF
    dialog.add("statictext", undefined, "Preajuste de PDF:");
    var calidadDropdown = dialog.add("dropdownlist", undefined,
        ["[Alta calidad]", "[Calidad de impresión]", "[Tamaño de archivo más pequeño]", "[PDF/X-1a:2001]", "[PDF/X-4:2008]"]);
    calidadDropdown.selection = 1;

    // Botones
    var botonesGroup = dialog.add("group");
    botonesGroup.add("button", undefined, "Cancelar", {name: "cancel"});
    botonesGroup.add("button", undefined, "Exportar", {name: "ok"});

    if (dialog.show() === 1) {
        var metodo = rbCajas.value ? 0 : (rbRangos.value ? 1 : 2);
        return {
            metodo: metodo,
            prefijo: prefijoInput.text,
            carpeta: new Folder(carpetaInput.text),
            prefijoArchivo: prefijoArchivoInput.text,
            calidad: calidadDropdown.selection.index
        };
    }
    return null;
}

// Método 1: Exportar por cajas de texto
function exportarPorCajasTexto(doc, config) {
    var cajasCapitulo = [];
    var allTextFrames = doc.textFrames;

    // Buscar todas las cajas que coincidan con el prefijo
    for (var i = 0; i < allTextFrames.length; i++) {
        var frame = allTextFrames[i];
        if (frame.name.indexOf(config.prefijo) === 0) {
            cajasCapitulo.push(frame);
        }
    }

    if (cajasCapitulo.length === 0) {
        alert("No se encontraron cajas de texto con el prefijo '" + config.prefijo + "'.\n\n" +
              "Asegúrate de nombrar tus cajas de texto (ej: Capitulo1, Capitulo2, etc.)");
        return;
    }

    // Ordenar por nombre
    cajasCapitulo.sort(function(a, b) {
        return a.name.localeCompare(b.name);
    });

    // Crear carpeta si no existe
    if (!config.carpeta.exists) {
        config.carpeta.create();
    }

    var preajuste = obtenerPreajustePDF(config.calidad);
    var exportados = 0;

    // Exportar cada capítulo
    for (var i = 0; i < cajasCapitulo.length; i++) {
        var caja = cajasCapitulo[i];

        try {
            // Obtener las páginas que contiene esta caja
            var paginas = obtenerPaginasDeCaja(caja);
            if (paginas.length === 0) continue;

            // Nombre del archivo
            var nombreArchivo = config.prefijoArchivo + caja.name + ".pdf";
            var archivo = new File(config.carpeta + "/" + nombreArchivo);

            // Exportar a PDF
            exportarRangoPDF(doc, paginas, archivo, preajuste);
            exportados++;

        } catch (e) {
            alert("Error al exportar " + caja.name + ":\n" + e.message);
        }
    }

    alert("Exportación completada!\n" + exportados + " capítulos exportados a:\n" + config.carpeta.fsName);
}

// Método 2: Exportar por rangos de páginas
function exportarPorRangos(doc, config) {
    var dialogoRangos = new Window("dialog", "Definir Rangos de Capítulos");

    dialogoRangos.add("statictext", undefined, "Ingresa los rangos de páginas para cada capítulo:");
    dialogoRangos.add("statictext", undefined, "Formato: 1-5, 6-12, 13-20, etc.");

    var rangosInput = dialogoRangos.add("edittext", undefined, "", {multiline: true});
    rangosInput.characters = 40;
    rangosInput.minimumSize = [300, 100];

    var botonesGroup = dialogoRangos.add("group");
    botonesGroup.add("button", undefined, "Cancelar", {name: "cancel"});
    botonesGroup.add("button", undefined, "Exportar", {name: "ok"});

    if (dialogoRangos.show() !== 1) return;

    var rangosTexto = rangosInput.text.split(",");

    if (!config.carpeta.exists) {
        config.carpeta.create();
    }

    var preajuste = obtenerPreajustePDF(config.calidad);
    var exportados = 0;

    for (var i = 0; i < rangosTexto.length; i++) {
        var rango = rangosTexto[i].replace(/\s/g, "");
        if (rango === "") continue;

        try {
            var nombreArchivo = config.prefijoArchivo + (i + 1) + ".pdf";
            var archivo = new File(config.carpeta + "/" + nombreArchivo);

            // Exportar con el rango especificado
            doc.exportFile(ExportFormat.PDF_TYPE, archivo, false, preajuste);

            // Configurar rango de páginas
            var pdfExportPrefs = doc.pdfExportPreferences;
            pdfExportPrefs.pageRange = rango;

            doc.exportFile(ExportFormat.PDF_TYPE, archivo, false, preajuste);
            exportados++;

        } catch (e) {
            alert("Error al exportar rango " + rango + ":\n" + e.message);
        }
    }

    alert("Exportación completada!\n" + exportados + " capítulos exportados.");
}

// Método 3: Exportar por marcadores
function exportarPorMarcadores(doc, config) {
    var bookmarks = doc.bookmarks;

    if (bookmarks.length === 0) {
        alert("No se encontraron marcadores en el documento.\n" +
              "Crea marcadores para cada capítulo antes de usar este método.");
        return;
    }

    if (!config.carpeta.exists) {
        config.carpeta.create();
    }

    var preajuste = obtenerPreajustePDF(config.calidad);
    var exportados = 0;

    for (var i = 0; i < bookmarks.length; i++) {
        var bookmark = bookmarks[i];

        try {
            var paginaInicio = bookmark.destination.destinationPage.name;
            var paginaFin = (i < bookmarks.length - 1) ?
                bookmarks[i + 1].destination.destinationPage.name :
                doc.pages[-1].name;

            var rango = paginaInicio + "-" + paginaFin;
            var nombreArchivo = config.prefijoArchivo + bookmark.name + ".pdf";
            var archivo = new File(config.carpeta + "/" + nombreArchivo);

            var pdfExportPrefs = doc.pdfExportPreferences;
            pdfExportPrefs.pageRange = rango;

            doc.exportFile(ExportFormat.PDF_TYPE, archivo, false, preajuste);
            exportados++;

        } catch (e) {
            alert("Error al exportar marcador " + bookmark.name + ":\n" + e.message);
        }
    }

    alert("Exportación completada!\n" + exportados + " capítulos exportados.");
}

// Función auxiliar: Obtener páginas que contiene una caja de texto
function obtenerPaginasDeCaja(caja) {
    var paginas = [];

    try {
        var parent = caja.parent;
        while (parent && parent.constructor.name !== "Page" && parent.constructor.name !== "Spread") {
            parent = parent.parent;
        }

        if (parent && parent.constructor.name === "Page") {
            paginas.push(parent.name);
        } else if (parent && parent.constructor.name === "Spread") {
            for (var i = 0; i < parent.pages.length; i++) {
                paginas.push(parent.pages[i].name);
            }
        }

        // Si la caja tiene enlaces a otras páginas, incluirlas
        if (caja.nextTextFrame) {
            var siguientePaginas = obtenerPaginasDeCaja(caja.nextTextFrame);
            for (var j = 0; j < siguientePaginas.length; j++) {
                if (paginas.indexOf(siguientePaginas[j]) === -1) {
                    paginas.push(siguientePaginas[j]);
                }
            }
        }
    } catch (e) {
        // Ignorar errores
    }

    return paginas;
}

// Función auxiliar: Exportar un rango de páginas a PDF
function exportarRangoPDF(doc, paginas, archivo, preajuste) {
    var rango = paginas.join(",");

    var pdfExportPrefs = doc.pdfExportPreferences;
    var rangoOriginal = pdfExportPrefs.pageRange;

    try {
        pdfExportPrefs.pageRange = rango;
        doc.exportFile(ExportFormat.PDF_TYPE, archivo, false, preajuste);
    } finally {
        pdfExportPrefs.pageRange = rangoOriginal;
    }
}

// Función auxiliar: Obtener preajuste de PDF según la selección
function obtenerPreajustePDF(indice) {
    var preajustes = [
        "[High Quality Print]",
        "[Press Quality]",
        "[Smallest File Size]",
        "[PDF/X-1a:2001]",
        "[PDF/X-4:2008]"
    ];

    var nombrePreajuste = preajustes[indice];

    try {
        return app.pdfExportPresets.itemByName(nombrePreajuste);
    } catch (e) {
        // Si no se encuentra, usar el primero disponible
        return app.pdfExportPresets[0];
    }
}

// Ejecutar el script
main();
