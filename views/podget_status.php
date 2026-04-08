<?php
// views/podget_status.php - Vista de estado de ejecución de Podget
?>
<div class="card">
    <h2>Estado de Podget</h2>

<div class="alert alert-info">
        <strong>ℹ️ Información:</strong><br>
        Esta página muestra el estado de las descargas de Podget.
        Los registros se actualizan automáticamente cuando se ejecutan las descargas.
    </div>

    <div id="podget-status-page">
        <div class="alert alert-info">🔍 Cargando estado...</div>
    </div>

    <script>
        // Verificar el estado al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            checkPodgetStatus();
        });
    </script>
</div>
