<?php
// test_category.php - Script de prueba para añadir categorías
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once INCLUDES_DIR . '/session.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/utils.php';
require_once INCLUDES_DIR . '/categories.php';
require_once INCLUDES_DIR . '/podcasts.php';

initSession();

echo "<h1>Test de Categorías</h1>";
echo "<pre>";

// Verificar sesión
echo "=== SESIÓN ===\n";
echo "Logueado: " . (isLoggedIn() ? "Sí" : "No") . "\n";
if (isLoggedIn()) {
    echo "Usuario: " . $_SESSION['username'] . "\n";
    echo "Es admin: " . (isAdmin() ? "Sí" : "No") . "\n";
    echo "Nombre emisora: " . $_SESSION['station_name'] . "\n";
}

// Mostrar categorías actuales
echo "\n=== CATEGORÍAS ACTUALES ===\n";
if (isLoggedIn()) {
    $categories = getUserCategories($_SESSION['username']);
    echo "Total: " . count($categories) . "\n";
    print_r($categories);
}

// Probar añadir categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_category'])) {
    echo "\n=== INTENTANDO AÑADIR CATEGORÍA ===\n";
    $categoryName = trim($_POST['test_category']);
    echo "Nombre recibido: '$categoryName'\n";

    if (isLoggedIn() && !isAdmin()) {
        $result = saveUserCategory($_SESSION['username'], $categoryName);
        echo "Resultado: " . ($result ? $result : 'FALSE') . "\n";

        // Verificar que se guardó
        $categories = getUserCategories($_SESSION['username']);
        echo "Categorías después: \n";
        print_r($categories);
    } else {
        echo "ERROR: Usuario es admin o no está logueado\n";
    }
}

// Verificar permisos de db.json
echo "\n=== PERMISOS DB.JSON ===\n";
echo "Ruta: " . DB_FILE . "\n";
echo "Existe: " . (file_exists(DB_FILE) ? "Sí" : "No") . "\n";
echo "Legible: " . (is_readable(DB_FILE) ? "Sí" : "No") . "\n";
echo "Escribible: " . (is_writable(DB_FILE) ? "Sí" : "No") . "\n";
echo "Permisos: " . substr(sprintf('%o', fileperms(DB_FILE)), -4) . "\n";

echo "</pre>";
?>

<hr>
<h2>Probar añadir categoría</h2>
<form method="POST">
    <label>Nombre de categoría:</label><br>
    <input type="text" name="test_category" placeholder="Prueba123" required><br><br>
    <button type="submit">Añadir Categoría</button>
</form>

<hr>
<p><a href="index.php">← Volver a SAPO</a></p>
