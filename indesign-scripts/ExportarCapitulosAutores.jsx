// Script: Exportar Capitulos por Autor a PDF
// Descripcion: Exporta cada capitulo como PDF con nombre basado en autor y libro
// Formato: [prefijo][numero]_[NombreAutor]_[NombreLibro].pdf

#target indesign

function main() {
    if (app.documents.length === 0) {
        alert("Por favor, abre un documento antes de ejecutar este script.");
        return;
    }

    var doc = app.activeDocument;

    var config = mostrarDialogoConfiguracion();
    if (!config) return;

    var cajasCapitulo = buscarCajasCapitulo(doc, config);

    if (cajasCapitulo.length === 0) {
        if (config.modoAutomatico) {
            alert("No se encontraron cajas de texto con el estilo '" + config.estiloAutor + "'.\n\nAsegurate de:\n1. Tener cajas de texto en el documento\n2. Aplicar el estilo '" + config.estiloAutor + "' al nombre del autor en cada capitulo\n3. O usa el modo 'Manual' si tus cajas tienen nombres especificos");
        } else {
            alert("No se encontraron cajas de texto con el prefijo '" + config.prefijoCaja + "'.\n\nAsegurate de:\n1. Nombrar las cajas de texto de cada capitulo\n2. Usar un prefijo comun (ej: Capitulo1, Capitulo2, etc.)\n3. Verificar el prefijo en el panel de Capas\n4. O usa el modo 'Automatico' para detectar todas las cajas");
        }
        return;
    }

    var capitulos = procesarCapitulos(cajasCapitulo, config);

    if (!mostrarPrevisualizacion(capitulos, config)) {
        return;
    }

    exportarCapitulos(doc, capitulos, config);
}

function mostrarDialogoConfiguracion() {
    var dialog = new Window("dialog", "Exportar Capitulos por Autor");
    dialog.orientation = "column";
    dialog.alignChildren = ["fill", "top"];
    dialog.spacing = 15;

    var titulo = dialog.add("statictext", undefined, "Configuracion de exportacion de capitulos");
    titulo.graphics.font = ScriptUI.newFont(titulo.graphics.font.name, "BOLD", 12);

    var grupoCajas = dialog.add("panel", undefined, "Identificacion de capitulos");
    grupoCajas.orientation = "column";
    grupoCajas.alignChildren = "left";
    grupoCajas.spacing = 10;

    grupoCajas.add("statictext", undefined, "Metodo de deteccion de capitulos:");

    var grupoMetodo = grupoCajas.add("group");
    grupoMetodo.orientation = "column";
    grupoMetodo.alignChildren = "left";

    var rbAutomatico = grupoMetodo.add("radiobutton", undefined, "Automatico: Detectar todas las cajas de texto con estilo 'autor'");
    var rbPrefijo = grupoMetodo.add("radiobutton", undefined, "Manual: Solo cajas con un prefijo especifico");
    rbAutomatico.value = true;

    var grupoPrefijo = grupoCajas.add("group");
    grupoPrefijo.orientation = "row";
    grupoPrefijo.enabled = false;
    grupoPrefijo.add("statictext", undefined, "Prefijo:");
    var prefijoCajaInput = grupoPrefijo.add("edittext", undefined, "Capitulo");
    prefijoCajaInput.characters = 20;

    rbPrefijo.onClick = function() {
        grupoPrefijo.enabled = true;
    };

    rbAutomatico.onClick = function() {
        grupoPrefijo.enabled = false;
    };

    grupoCajas.add("statictext", undefined, "Estilo de parrafo que contiene el autor:");

    var doc = app.activeDocument;
    var estilosParrafo = [];

    function obtenerTodosLosEstilos(estilos, prefijo) {
        for (var i = 0; i < estilos.length; i++) {
            var estilo = estilos[i];
            var nombreCompleto = prefijo ? prefijo + " > " + estilo.name : estilo.name;

            if (estilo.name.indexOf("[") !== 0) {
                estilosParrafo.push(nombreCompleto);
            }
        }
    }

    obtenerTodosLosEstilos(doc.allParagraphStyles, "");

    if (estilosParrafo.length === 0) {
        estilosParrafo.push("autor");
    }

    var estiloAutorDropdown = grupoCajas.add("dropdownlist", undefined, estilosParrafo);
    estiloAutorDropdown.preferredSize = [300, 25];

    var indiceAutor = -1;
    for (var i = 0; i < estilosParrafo.length; i++) {
        if (estilosParrafo[i].toLowerCase().indexOf("autor") !== -1) {
            indiceAutor = i;
            break;
        }
    }
    if (indiceAutor !== -1) {
        estiloAutorDropdown.selection = indiceAutor;
    } else {
        estiloAutorDropdown.selection = 0;
    }

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
    grupoNumeracion.add("statictext", undefined, "Numero inicial de numeracion:");
    var numeroInicialInput = grupoNumeracion.add("edittext", undefined, "1");
    numeroInicialInput.characters = 5;
    numeroInicialInput.helpTip = "Numero con el que empezara la numeracion de capitulos";

    var formatoEjemplo = grupoNombre.add("statictext", undefined, "Formato: [prefijo][numero]_[NombreAutor]_[NombreLibro].pdf");
    formatoEjemplo.graphics.foregroundColor = formatoEjemplo.graphics.newPen(formatoEjemplo.graphics.PenType.SOLID_COLOR, [0.5, 0.5, 0.5], 1);

    var formatoEjemplo2 = grupoNombre.add("statictext", undefined, "Ejemplo: prueba1_01_Juan_Garcia_Mi_Libro.pdf");
    formatoEjemplo2.graphics.foregroundColor = formatoEjemplo2.graphics.newPen(formatoEjemplo2.graphics.PenType.SOLID_COLOR, [0.5, 0.5, 0.5], 1);

    var grupoPDF = dialog.add("panel", undefined, "Opciones de PDF");
    grupoPDF.orientation = "column";
    grupoPDF.alignChildren = "left";
    grupoPDF.spacing = 10;

    grupoPDF.add("statictext", undefined, "Preajuste de PDF:");

    var preajustes = [];
    var preajustesObj = app.pdfExportPresets;
    for (var i = 0; i < preajustesObj.length; i++) {
        preajustes.push(preajustesObj[i].name);
    }

    var preajusteDropdown = grupoPDF.add("dropdownlist", undefined, preajustes);
    for (var i = 0; i < preajustes.length; i++) {
        if (preajustes[i].indexOf("Press") !== -1 || preajustes[i].indexOf("Calidad de impresion") !== -1) {
            preajusteDropdown.selection = i;
            break;
        }
    }
    if (!preajusteDropdown.selection) {
        preajusteDropdown.selection = 0;
    }

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

    var grupoBotones = dialog.add("group");
    grupoBotones.alignment = "center";
    grupoBotones.add("button", undefined, "Cancelar", {name: "cancel"});
    grupoBotones.add("button", undefined, "Continuar", {name: "ok"});

    if (dialog.show() === 1) {
        if (nombreLibroInput.text === "") {
            alert("Por favor, ingresa el nombre del libro.");
            return null;
        }

        var numeroInicial = parseInt(numeroInicialInput.text);
        if (isNaN(numeroInicial) || numeroInicial < 0) {
            alert("Por favor, ingresa un numero inicial valido (mayor o igual a 0).");
            return null;
        }

        return {
            modoAutomatico: rbAutomatico.value,
            prefijoCaja: prefijoCajaInput.text,
            estiloAutor: estiloAutorDropdown.selection.text,
            prefijoArchivo: prefijoArchivoInput.text,
            nombreLibro: limpiarNombreArchivo(nombreLibroInput.text),
            numeroInicial: numeroInicial,
            preajuste: preajusteDropdown.selection.text,
            carpeta: new Folder(carpetaInput.text)
        };
    }

    return null;
}

function buscarCajasCapitulo(doc, config) {
    var cajas = [];
    var allTextFrames = doc.textFrames;

    if (config.modoAutomatico) {
        var estiloNombre = config.estiloAutor;
        var nombreEstiloBuscar = estiloNombre;

        if (estiloNombre.indexOf(" > ") !== -1) {
            var partes = estiloNombre.split(" > ");
            nombreEstiloBuscar = partes[partes.length - 1];
        }

        var estilo = null;

        try {
            var todosLosEstilos = doc.allParagraphStyles;
            for (var idx = 0; idx < todosLosEstilos.length; idx++) {
                if (todosLosEstilos[idx].name === nombreEstiloBuscar) {
                    estilo = todosLosEstilos[idx];
                    break;
                }
            }
        } catch (e) {
        }

        if (!estilo) {
            alert("ADVERTENCIA: No se encontro el estilo de parrafo '" + nombreEstiloBuscar + "'.\nSe detectaran todas las cajas de texto principales del documento.");
        }

        var cajasYaProcesadas = [];

        for (var i = 0; i < allTextFrames.length; i++) {
            var frame = allTextFrames[i];

            var yaIncluida = false;
            for (var j = 0; j < cajasYaProcesadas.length; j++) {
                if (frame.id === cajasYaProcesadas[j].id) {
                    yaIncluida = true;
                    break;
                }
            }
            if (yaIncluida) continue;

            var esPrincipal = !frame.previousTextFrame;

            if (esPrincipal) {
                var tieneEstiloAutor = false;

                if (estilo) {
                    try {
                        var parrafos = frame.parentStory.paragraphs;
                        for (var k = 0; k < Math.min(parrafos.length, 50); k++) {
                            try {
                                if (parrafos[k].appliedParagraphStyle.name === nombreEstiloBuscar) {
                                    tieneEstiloAutor = true;
                                    break;
                                }
                            } catch (e) {}
                        }
                    } catch (e) {}
                }

                if (tieneEstiloAutor || !estilo) {
                    cajas.push(frame);

                    var cajaActual = frame;
                    cajasYaProcesadas.push(cajaActual);
                    while (cajaActual.nextTextFrame) {
                        cajaActual = cajaActual.nextTextFrame;
                        cajasYaProcesadas.push(cajaActual);
                    }
                }
            }
        }

        cajas.sort(function(a, b) {
            try {
                var pageA = obtenerNumeroPaginaPrincipal(a);
                var pageB = obtenerNumeroPaginaPrincipal(b);
                return pageA - pageB;
            } catch (e) {
                return 0;
            }
        });

    } else {
        var prefijo = config.prefijoCaja;
        for (var i = 0; i < allTextFrames.length; i++) {
            var frame = allTextFrames[i];
            if (frame.name && frame.name.indexOf(prefijo) === 0) {
                cajas.push(frame);
            }
        }

        cajas.sort(function(a, b) {
            return a.name.localeCompare(b.name);
        });
    }

    return cajas;
}

function obtenerNumeroPaginaPrincipal(caja) {
    try {
        var parent = caja.parent;
        while (parent && parent.constructor.name !== "Page") {
            parent = parent.parent;
        }
        if (parent && parent.constructor.name === "Page") {
            var numPagina = parseInt(parent.name);
            if (!isNaN(numPagina)) {
                return numPagina;
            }
        }
    } catch (e) {}
    return 0;
}

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

function extraerNombreAutor(caja, estiloNombre) {
    try {
        var doc = app.activeDocument;
        var estilo = null;

        var nombreEstiloBuscar = estiloNombre;
        if (estiloNombre.indexOf(" > ") !== -1) {
            var partes = estiloNombre.split(" > ");
            nombreEstiloBuscar = partes[partes.length - 1];
        }

        try {
            var todosLosEstilos = doc.allParagraphStyles;
            for (var idx = 0; idx < todosLosEstilos.length; idx++) {
                if (todosLosEstilos[idx].name === nombreEstiloBuscar) {
                    estilo = todosLosEstilos[idx];
                    break;
                }
            }
        } catch (e) {
        }

        if (!estilo) {
            return "AutorDesconocido_" + caja.name;
        }

        var textos = caja.parentStory.paragraphs;

        for (var i = 0; i < textos.length; i++) {
            var parrafo = textos[i];

            try {
                if (parrafo.appliedParagraphStyle.name === nombreEstiloBuscar) {
                    var contenido = parrafo.contents;
                    contenido = contenido.replace(/^\s+|\s+$/g, '');
                    contenido = contenido.replace(/\r\n|\n|\r/g, ' ');
                    contenido = contenido.replace(/\s+/g, ' ');

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
            }
        }

        var todosLosParrafos = caja.parentStory.paragraphs.everyItem().getElements();
        for (var i = 0; i < todosLosParrafos.length; i++) {
            try {
                if (todosLosParrafos[i].appliedParagraphStyle.name === nombreEstiloBuscar) {
                    var contenido = todosLosParrafos[i].contents;
                    contenido = contenido.replace(/^\s+|\s+$/g, '');
                    contenido = contenido.replace(/\r\n|\n|\r/g, ' ');
                    contenido = contenido.replace(/\s+/g, ' ');

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
            }
        }

        return "AutorDesconocido_" + caja.name;

    } catch (e) {
        return "AutorDesconocido_" + caja.name;
    }
}

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

        paginasNumeros.sort(function(a, b) { return a - b; });
        for (var i = 0; i < paginasNumeros.length; i++) {
            paginas.push(paginasNumeros[i].toString());
        }

    } catch (e) {
        try {
            var parent = caja.parent;
            while (parent && parent.constructor.name !== "Page") {
                parent = parent.parent;
            }
            if (parent && parent.constructor.name === "Page") {
                paginas.push(parent.name);
            }
        } catch (e2) {
            paginas.push("1");
        }
    }

    return paginas;
}

function generarNombreArchivo(prefijo, numeroCapitulo, nombreAutor, nombreLibro, totalCapitulos) {
    var nombreAutorLimpio = limpiarNombreArchivo(nombreAutor);
    var nombreLibroLimpio = limpiarNombreArchivo(nombreLibro);
    var prefijoLimpio = limpiarNombreArchivo(prefijo);

    var digitos = Math.max(2, totalCapitulos.toString().length);
    var numeroFormateado = padLeft(numeroCapitulo.toString(), digitos, "0");

    return prefijoLimpio + numeroFormateado + "_" + nombreAutorLimpio + "_" + nombreLibroLimpio + ".pdf";
}

function padLeft(str, longitud, caracter) {
    while (str.length < longitud) {
        str = caracter + str;
    }
    return str;
}

function limpiarNombreArchivo(texto) {
    var limpio = texto.replace(/[<>:"|?*\/\\]/g, '');
    limpio = limpio.replace(/\s+/g, '_');
    limpio = limpio.replace(/_{2,}/g, '_');
    limpio = limpio.replace(/^_|_$/g, '');

    if (limpio.length > 50) {
        limpio = limpio.substring(0, 50);
    }

    return limpio;
}

function mostrarPrevisualizacion(capitulos, config) {
    var dialog = new Window("dialog", "Previsualizacion de exportacion");
    dialog.orientation = "column";
    dialog.alignChildren = ["fill", "top"];

    dialog.add("statictext", undefined, "Se exportaran " + capitulos.length + " capitulos:");

    var lista = dialog.add("edittext", undefined, "", {multiline: true, readonly: true});
    lista.minimumSize = [600, 300];

    var texto = "";
    for (var i = 0; i < capitulos.length; i++) {
        var cap = capitulos[i];
        texto += (i + 1) + ". " + cap.nombreCaja + "\n";
        texto += "   Numero: " + cap.numeroCapitulo + "\n";
        texto += "   Autor: " + cap.nombreAutor + "\n";
        texto += "   Paginas: " + cap.paginas.join(", ") + "\n";
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

function exportarCapitulos(doc, capitulos, config) {
    if (!config.carpeta.exists) {
        config.carpeta.create();
    }

    var preajuste;
    try {
        preajuste = app.pdfExportPresets.itemByName(config.preajuste);
    } catch (e) {
        alert("Error: No se pudo cargar el preajuste de PDF.\nSe usara el preajuste por defecto.");
        preajuste = app.pdfExportPresets[0];
    }

    var pdfExportPrefs = doc.pdfExportPreferences;
    var rangoOriginal = pdfExportPrefs.pageRange;

    var progreso = new Window("palette", "Exportando capitulos...");
    progreso.add("statictext", undefined, "Procesando capitulos por autor...");
    var barraProgreso = progreso.add("progressbar", undefined, 0, capitulos.length);
    barraProgreso.preferredSize = [400, 20];
    var textoProgreso = progreso.add("statictext", undefined, "0 de " + capitulos.length);
    textoProgreso.preferredSize = [400, 20];
    progreso.show();

    var exportados = 0;
    var errores = [];

    for (var i = 0; i < capitulos.length; i++) {
        var cap = capitulos[i];

        barraProgreso.value = i;
        textoProgreso.text = (i + 1) + " de " + capitulos.length + " - " + cap.nombreAutor;
        progreso.update();

        try {
            if (cap.paginas.length === 0) {
                errores.push(cap.nombreCaja + ": No se detectaron paginas");
                continue;
            }

            var archivo = new File(config.carpeta + "/" + cap.nombreArchivo);

            var rango = cap.paginas.join(",");
            pdfExportPrefs.pageRange = rango;

            doc.exportFile(ExportFormat.PDF_TYPE, archivo, false, preajuste);

            exportados++;

        } catch (e) {
            errores.push(cap.nombreCaja + " (" + cap.nombreAutor + "): " + e.message);
        }
    }

    pdfExportPrefs.pageRange = rangoOriginal;

    progreso.close();

    var resultado = "===================================\n";
    resultado += "  EXPORTACION COMPLETADA\n";
    resultado += "===================================\n\n";
    resultado += "Capitulos exportados: " + exportados + " de " + capitulos.length + "\n";
    resultado += "Ubicacion: " + config.carpeta.fsName + "\n";

    if (errores.length > 0) {
        resultado += "\nERRORES (" + errores.length + "):\n";
        resultado += "-----------------------------------\n";
        for (var i = 0; i < errores.length; i++) {
            resultado += (i + 1) + ". " + errores[i] + "\n";
        }
    } else {
        resultado += "\nTodos los capitulos se exportaron correctamente";
    }

    alert(resultado);

    if (confirm("Deseas abrir la carpeta con los PDFs?")) {
        config.carpeta.execute();
    }
}

try {
    main();
} catch (e) {
    alert("ERROR CRITICO:\n\n" + e.message + "\n\nLinea: " + e.line + "\n\nContacta al administrador del script.");
}
