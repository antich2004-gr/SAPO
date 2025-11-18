/*
 * Script: Exportar Capítulos por Autor a PDF
 * Descripción: Exporta cada capítulo como PDF con nombre basado en autor y libro
 * Formato: [prefijo]_[NombreAutor]_[NombreLibro].pdf
 *
 * REQUISITOS:
 * - Cada capítulo debe tener una caja de texto independiente
 * - El nombre del autor debe estar en el estilo de párrafo "autor" dentro de cada caja
 * - Las cajas de texto de capítulos deben tener un nombre que las identifique (ej: "Capitulo1", "Cap_01", etc.)
 */

#target indesign

function main() {
    if (app.documents.length === 0) {
        alert("Por favor, abre un documento antes de ejecutar este script.");
        return;
    }

    var doc = app.activeDocument;

    // Paso 1: Mostrar diálogo de configuración principal
    var config = mostrarDialogoConfiguracion();
    if (!config) return; // Usuario canceló

    // Paso 2: Buscar todas las cajas de texto que coincidan con el prefijo
    var cajasCapitulo = buscarCajasCapitulo(doc, config.prefijoCaja);

    if (cajasCapitulo.length === 0) {
        alert("No se encontraron cajas de texto con el prefijo '" + config.prefijoCaja + "'.\n\n" +
              "Asegúrate de:\n" +
              "1. Nombrar las cajas de texto de cada capítulo\n" +
              "2. Usar un prefijo común (ej: Capitulo1, Capitulo2, etc.)\n" +
              "3. Verificar el prefijo en el panel de Capas");
        return;
    }

    // Paso 3: Extraer información de autor de cada caja
    var capitulos = procesarCapitulos(cajasCapitulo, config);

    // Paso 4: Mostrar previsualización y confirmar
    if (!mostrarPrevisualizacion(capitulos, config)) {
        return; // Usuario canceló
    }

    // Paso 5: Exportar PDFs
    exportarCapitulos(doc, capitulos, config);
}

// Diálogo de configuración principal
function mostrarDialogoConfiguracion() {
    var dialog = new Window("dialog", "Exportar Capítulos por Autor");
    dialog.orientation = "column";
    dialog.alignChildren = ["fill", "top"];
    dialog.spacing = 15;

    // Título
    var titulo = dialog.add("statictext", undefined, "Configuración de exportación de capítulos");
    titulo.graphics.font = ScriptUI.newFont(titulo.graphics.font.name, "BOLD", 12);

    // Grupo: Identificación de cajas
    var grupoCajas = dialog.add("panel", undefined, "Identificación de capítulos");
    grupoCajas.orientation = "column";
    grupoCajas.alignChildren = "left";
    grupoCajas.spacing = 10;

    grupoCajas.add("statictext", undefined, "Prefijo de las cajas de texto de capítulos:");
    var prefijoCajaInput = grupoCajas.add("edittext", undefined, "Capitulo");
    prefijoCajaInput.characters = 25;
    prefijoCajaInput.helpTip = "Las cajas deben nombrarse como: Capitulo1, Capitulo2, etc.";

    grupoCajas.add("statictext", undefined, "Nombre del estilo de párrafo que contiene el autor:");
    var estiloAutorInput = grupoCajas.add("edittext", undefined, "autor");
    estiloAutorInput.characters = 25;
    estiloAutorInput.helpTip = "Nombre exacto del estilo de párrafo (sensible a mayúsculas)";

    // Grupo: Nomenclatura de archivos
    var grupoNombre = dialog.add("panel", undefined, "Nomenclatura de archivos PDF");
    grupoNombre.orientation = "column";
    grupoNombre.alignChildren = "left";
    grupoNombre.spacing = 10;

    grupoNombre.add("statictext", undefined, "Prefijo del archivo (ej: prueba1, version_final, etc.):");
    var prefijoArchivoInput = grupoNombre.add("edittext", undefined, "prueba1");
    prefijoArchivoInput.characters = 25;

    grupoNombre.add("statictext", undefined, "Nombre del libro:");
    var nombreLibroInput = grupoNombre.add("edittext", undefined, "");
    nombreLibroInput.characters = 25;

    var grupoNumeracion = grupoNombre.add("group");
    grupoNumeracion.orientation = "row";
    grupoNumeracion.add("statictext", undefined, "Número inicial de numeración:");
    var numeroInicialInput = grupoNumeracion.add("edittext", undefined, "1");
    numeroInicialInput.characters = 5;
    numeroInicialInput.helpTip = "Número con el que empezará la numeración de capítulos";

    var formatoEjemplo = grupoNombre.add("statictext", undefined, "Formato: [prefijo][numero]_[NombreAutor]_[NombreLibro].pdf");
    formatoEjemplo.graphics.foregroundColor = formatoEjemplo.graphics.newPen(formatoEjemplo.graphics.PenType.SOLID_COLOR, [0.5, 0.5, 0.5], 1);

    var formatoEjemplo2 = grupoNombre.add("statictext", undefined, "Ejemplo: prueba1_01_Juan_Garcia_Mi_Libro.pdf");
    formatoEjemplo2.graphics.foregroundColor = formatoEjemplo2.graphics.newPen(formatoEjemplo2.graphics.PenType.SOLID_COLOR, [0.5, 0.5, 0.5], 1);

    // Grupo: Opciones de PDF
    var grupoPDF = dialog.add("panel", undefined, "Opciones de PDF");
    grupoPDF.orientation = "column";
    grupoPDF.alignChildren = "left";
    grupoPDF.spacing = 10;

    grupoPDF.add("statictext", undefined, "Preajuste de PDF:");

    // Obtener preajustes disponibles
    var preajustes = [];
    var preajustesObj = app.pdfExportPresets;
    for (var i = 0; i < preajustesObj.length; i++) {
        preajustes.push(preajustesObj[i].name);
    }

    var preajusteDropdown = grupoPDF.add("dropdownlist", undefined, preajustes);
    // Seleccionar "Press Quality" por defecto si existe
    for (var i = 0; i < preajustes.length; i++) {
        if (preajustes[i].indexOf("Press") !== -1 || preajustes[i].indexOf("Calidad de impresión") !== -1) {
            preajusteDropdown.selection = i;
            break;
        }
    }
    if (!preajusteDropdown.selection) {
        preajusteDropdown.selection = 0;
    }

    // Grupo: Carpeta de destino
    var grupoCarpeta = dialog.add("panel", undefined, "Carpeta de destino");
    grupoCarpeta.orientation = "row";
    grupoCarpeta.alignChildren = ["fill", "center"];

    var carpetaInput = grupoCarpeta.add("edittext", undefined, "~/Desktop/Capitulos_Autores");
    carpetaInput.characters = 30;

    var btnCarpeta = grupoCarpeta.add("button", undefined, "Examinar...");
    btnCarpeta.onClick = function() {
        var carpeta = Folder.selectDialog("Selecciona la carpeta de destino");
        if (carpeta) {
            carpetaInput.text = carpeta.fsName;
        }
    };

    // Botones
    var grupoBotones = dialog.add("group");
    grupoBotones.alignment = "center";
    grupoBotones.add("button", undefined, "Cancelar", {name: "cancel"});
    grupoBotones.add("button", undefined, "Continuar", {name: "ok"});

    if (dialog.show() === 1) {
        // Validaciones
        if (nombreLibroInput.text === "") {
            alert("Por favor, ingresa el nombre del libro.");
            return null;
        }

        var numeroInicial = parseInt(numeroInicialInput.text);
        if (isNaN(numeroInicial) || numeroInicial < 0) {
            alert("Por favor, ingresa un número inicial válido (mayor o igual a 0).");
            return null;
        }

        return {
            prefijoCaja: prefijoCajaInput.text,
            estiloAutor: estiloAutorInput.text,
            prefijoArchivo: prefijoArchivoInput.text,
            nombreLibro: limpiarNombreArchivo(nombreLibroInput.text),
            numeroInicial: numeroInicial,
            preajuste: preajusteDropdown.selection.text,
            carpeta: new Folder(carpetaInput.text)
        };
    }

    return null;
}

// Buscar cajas de texto que coincidan con el prefijo
function buscarCajasCapitulo(doc, prefijo) {
    var cajas = [];
    var allTextFrames = doc.textFrames;

    for (var i = 0; i < allTextFrames.length; i++) {
        var frame = allTextFrames[i];
        if (frame.name && frame.name.indexOf(prefijo) === 0) {
            cajas.push(frame);
        }
    }

    // Ordenar por nombre
    cajas.sort(function(a, b) {
        return a.name.localeCompare(b.name);
    });

    return cajas;
}

// Procesar cada capítulo y extraer información
function procesarCapitulos(cajas, config) {
    var capitulos = [];

    for (var i = 0; i < cajas.length; i++) {
        var caja = cajas[i];
        var nombreAutor = extraerNombreAutor(caja, config.estiloAutor);
        var paginas = obtenerPaginasDeCaja(caja);
        var numeroCapitulo = config.numeroInicial + i;

        capitulos.push({
            caja: caja,
            nombreCaja: caja.name,
            nombreAutor: nombreAutor,
            nombreAutorLimpio: limpiarNombreArchivo(nombreAutor),
            numeroCapitulo: numeroCapitulo,
            paginas: paginas,
            nombreArchivo: generarNombreArchivo(config.prefijoArchivo, numeroCapitulo, nombreAutor, config.nombreLibro, cajas.length + config.numeroInicial)
        });
    }

    return capitulos;
}

// Extraer nombre del autor del estilo de párrafo
function extraerNombreAutor(caja, estiloNombre) {
    try {
        // Buscar el estilo de párrafo
        var doc = app.activeDocument;
        var estilo = null;

        try {
            estilo = doc.paragraphStyles.itemByName(estiloNombre);
            if (!estilo.isValid) {
                estilo = null;
            }
        } catch (e) {
            // El estilo no existe
        }

        if (!estilo) {
            return "AutorDesconocido_" + caja.name;
        }

        // Buscar texto con ese estilo en la caja
        var textos = caja.parentStory.paragraphs;

        for (var i = 0; i < textos.length; i++) {
            var parrafo = textos[i];

            try {
                if (parrafo.appliedParagraphStyle.name === estiloNombre) {
                    var contenido = parrafo.contents;
                    // Limpiar espacios y saltos de línea
                    contenido = contenido.replace(/^\s+|\s+$/g, ''); // trim
                    contenido = contenido.replace(/\r\n|\n|\r/g, ' '); // reemplazar saltos de línea
                    contenido = contenido.replace(/\s+/g, ' '); // normalizar espacios

                    // Eliminar "Por " del inicio si existe
                    if (contenido.indexOf("Por ") === 0) {
                        contenido = contenido.substring(4); // Eliminar "Por " (4 caracteres)
                    }
                    // También manejar "por " en minúsculas
                    if (contenido.indexOf("por ") === 0) {
                        contenido = contenido.substring(4);
                    }

                    // Limpiar espacios nuevamente después de eliminar el prefijo
                    contenido = contenido.replace(/^\s+|\s+$/g, '');

                    if (contenido.length > 0) {
                        return contenido;
                    }
                }
            } catch (e) {
                // Continuar con el siguiente párrafo
            }
        }

        // Si no se encontró, intentar buscar en todo el texto
        var todosLosParrafos = caja.parentStory.paragraphs.everyItem().getElements();
        for (var i = 0; i < todosLosParrafos.length; i++) {
            try {
                if (todosLosParrafos[i].appliedParagraphStyle.name === estiloNombre) {
                    var contenido = todosLosParrafos[i].contents;
                    contenido = contenido.replace(/^\s+|\s+$/g, '');
                    contenido = contenido.replace(/\r\n|\n|\r/g, ' ');
                    contenido = contenido.replace(/\s+/g, ' ');

                    // Eliminar "Por " del inicio si existe
                    if (contenido.indexOf("Por ") === 0) {
                        contenido = contenido.substring(4);
                    }
                    if (contenido.indexOf("por ") === 0) {
                        contenido = contenido.substring(4);
                    }

                    contenido = contenido.replace(/^\s+|\s+$/g, '');

                    if (contenido.length > 0) {
                        return contenido;
                    }
                }
            } catch (e) {
                // Continuar
            }
        }

        return "AutorDesconocido_" + caja.name;

    } catch (e) {
        return "AutorDesconocido_" + caja.name;
    }
}

// Obtener páginas de una caja de texto
function obtenerPaginasDeCaja(caja) {
    var paginas = [];
    var paginasNumeros = [];

    try {
        var parent = caja.parent;
        while (parent && parent.constructor.name !== "Page" && parent.constructor.name !== "Spread") {
            parent = parent.parent;
        }

        if (parent) {
            if (parent.constructor.name === "Page") {
                var numPagina = parseInt(parent.name);
                if (!isNaN(numPagina)) {
                    paginasNumeros.push(numPagina);
                } else {
                    paginas.push(parent.name);
                    return paginas;
                }
            } else if (parent.constructor.name === "Spread") {
                for (var i = 0; i < parent.pages.length; i++) {
                    var numPagina = parseInt(parent.pages[i].name);
                    if (!isNaN(numPagina)) {
                        paginasNumeros.push(numPagina);
                    } else {
                        paginas.push(parent.pages[i].name);
                    }
                }
            }
        }

        // Si hay cajas enlazadas, incluir sus páginas
        var cajaActual = caja;
        var maxIteraciones = 100;
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

        // Ordenar y convertir a strings
        paginasNumeros.sort(function(a, b) { return a - b; });
        for (var i = 0; i < paginasNumeros.length; i++) {
            paginas.push(paginasNumeros[i].toString());
        }

    } catch (e) {
        // Intentar obtener al menos la página actual
        try {
            var parent = caja.parent;
            while (parent && parent.constructor.name !== "Page") {
                parent = parent.parent;
            }
            if (parent && parent.constructor.name === "Page") {
                paginas.push(parent.name);
            }
        } catch (e2) {
            paginas.push("1"); // Fallback
        }
    }

    return paginas;
}

// Generar nombre de archivo limpio con numeración
function generarNombreArchivo(prefijo, numeroCapitulo, nombreAutor, nombreLibro, totalCapitulos) {
    var nombreAutorLimpio = limpiarNombreArchivo(nombreAutor);
    var nombreLibroLimpio = limpiarNombreArchivo(nombreLibro);
    var prefijoLimpio = limpiarNombreArchivo(prefijo);

    // Determinar cuántos dígitos necesitamos (mínimo 2)
    var digitos = Math.max(2, totalCapitulos.toString().length);

    // Formatear número con ceros a la izquierda
    var numeroFormateado = padLeft(numeroCapitulo.toString(), digitos, "0");

    return prefijoLimpio + numeroFormateado + "_" + nombreAutorLimpio + "_" + nombreLibroLimpio + ".pdf";
}

// Función auxiliar para agregar ceros a la izquierda
function padLeft(str, longitud, caracter) {
    while (str.length < longitud) {
        str = caracter + str;
    }
    return str;
}

// Limpiar nombre de archivo (eliminar caracteres no válidos)
function limpiarNombreArchivo(texto) {
    // Eliminar o reemplazar caracteres no válidos en nombres de archivo
    var limpio = texto.replace(/[<>:"|?*\/\\]/g, ''); // Eliminar caracteres prohibidos
    limpio = limpio.replace(/\s+/g, '_'); // Reemplazar espacios por guiones bajos
    limpio = limpio.replace(/_{2,}/g, '_'); // Reemplazar múltiples guiones bajos por uno solo
    limpio = limpio.replace(/^_|_$/g, ''); // Eliminar guiones bajos al inicio/final

    // Limitar longitud
    if (limpio.length > 50) {
        limpio = limpio.substring(0, 50);
    }

    return limpio;
}

// Mostrar previsualización antes de exportar
function mostrarPrevisualizacion(capitulos, config) {
    var dialog = new Window("dialog", "Previsualización de exportación");
    dialog.orientation = "column";
    dialog.alignChildren = ["fill", "top"];

    dialog.add("statictext", undefined, "Se exportarán " + capitulos.length + " capítulos:");

    var lista = dialog.add("edittext", undefined, "", {multiline: true, readonly: true});
    lista.minimumSize = [600, 300];

    var texto = "";
    for (var i = 0; i < capitulos.length; i++) {
        var cap = capitulos[i];
        texto += (i + 1) + ". " + cap.nombreCaja + "\n";
        texto += "   Número: " + cap.numeroCapitulo + "\n";
        texto += "   Autor: " + cap.nombreAutor + "\n";
        texto += "   Páginas: " + cap.paginas.join(", ") + "\n";
        texto += "   Archivo: " + cap.nombreArchivo + "\n\n";
    }

    lista.text = texto;

    dialog.add("statictext", undefined, "Carpeta destino: " + config.carpeta.fsName);
    dialog.add("statictext", undefined, "Preajuste PDF: " + config.preajuste);

    var grupoBotones = dialog.add("group");
    grupoBotones.alignment = "center";
    grupoBotones.add("button", undefined, "Cancelar", {name: "cancel"});
    grupoBotones.add("button", undefined, "Exportar", {name: "ok"});

    return dialog.show() === 1;
}

// Exportar capítulos a PDF
function exportarCapitulos(doc, capitulos, config) {
    // Crear carpeta si no existe
    if (!config.carpeta.exists) {
        config.carpeta.create();
    }

    // Obtener preajuste
    var preajuste;
    try {
        preajuste = app.pdfExportPresets.itemByName(config.preajuste);
    } catch (e) {
        alert("Error: No se pudo cargar el preajuste de PDF.\nSe usará el preajuste por defecto.");
        preajuste = app.pdfExportPresets[0];
    }

    // Guardar configuración original
    var pdfExportPrefs = doc.pdfExportPreferences;
    var rangoOriginal = pdfExportPrefs.pageRange;

    // Barra de progreso
    var progreso = new Window("palette", "Exportando capítulos...");
    progreso.add("statictext", undefined, "Procesando capítulos por autor...");
    var barraProgreso = progreso.add("progressbar", undefined, 0, capitulos.length);
    barraProgreso.preferredSize = [400, 20];
    var textoProgreso = progreso.add("statictext", undefined, "0 de " + capitulos.length);
    textoProgreso.preferredSize = [400, 20];
    progreso.show();

    var exportados = 0;
    var errores = [];

    // Exportar cada capítulo
    for (var i = 0; i < capitulos.length; i++) {
        var cap = capitulos[i];

        barraProgreso.value = i;
        textoProgreso.text = (i + 1) + " de " + capitulos.length + " - " + cap.nombreAutor;
        progreso.update();

        try {
            if (cap.paginas.length === 0) {
                errores.push(cap.nombreCaja + ": No se detectaron páginas");
                continue;
            }

            var archivo = new File(config.carpeta + "/" + cap.nombreArchivo);

            // Configurar rango de páginas
            var rango = cap.paginas.join(",");
            pdfExportPrefs.pageRange = rango;

            // Exportar
            doc.exportFile(ExportFormat.PDF_TYPE, archivo, false, preajuste);

            exportados++;

        } catch (e) {
            errores.push(cap.nombreCaja + " (" + cap.nombreAutor + "): " + e.message);
        }
    }

    // Restaurar configuración
    pdfExportPrefs.pageRange = rangoOriginal;

    // Cerrar progreso
    progreso.close();

    // Mostrar resultado
    var resultado = "═══════════════════════════════════\n";
    resultado += "  EXPORTACIÓN COMPLETADA\n";
    resultado += "═══════════════════════════════════\n\n";
    resultado += "Capítulos exportados: " + exportados + " de " + capitulos.length + "\n";
    resultado += "Ubicación: " + config.carpeta.fsName + "\n";

    if (errores.length > 0) {
        resultado += "\n⚠️  ERRORES (" + errores.length + "):\n";
        resultado += "───────────────────────────────────\n";
        for (var i = 0; i < errores.length; i++) {
            resultado += (i + 1) + ". " + errores[i] + "\n";
        }
    } else {
        resultado += "\n✓ Todos los capítulos se exportaron correctamente";
    }

    alert(resultado);

    // Abrir carpeta
    if (confirm("¿Deseas abrir la carpeta con los PDFs?")) {
        config.carpeta.execute();
    }
}

// Ejecutar script
try {
    main();
} catch (e) {
    alert("ERROR CRÍTICO:\n\n" + e.message + "\n\nLínea: " + e.line + "\n\nContacta al administrador del script.");
}
