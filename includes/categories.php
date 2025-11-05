<?php
// includes/categories.php - Gestión de categorías de podcasts

function getUserCategories($username) {
    $db = getDB();
    return $db['user_categories'][$username] ?? [];
}

function saveUserCategory($username, $category) {
    $db = getDB();
    if (!isset($db['user_categories'][$username])) {
        $db['user_categories'][$username] = [];
    }

    $sanitized = sanitizePodcastName($category);
    if (!in_array($sanitized, $db['user_categories'][$username])) {
        $db['user_categories'][$username][] = $sanitized;
        sort($db['user_categories'][$username]);
        saveDB($db);
        return $sanitized;
    }
    return false;
}

function deleteUserCategory($username, $category) {
    // Verificar si la categoría está en uso
    $podcasts = readServerList($username);
    foreach ($podcasts as $podcast) {
        if ($podcast['category'] === $category) {
            return false; // Categoría en uso, no se puede eliminar
        }
    }
    
    $db = getDB();
    if (isset($db['user_categories'][$username])) {
        $db['user_categories'][$username] = array_values(
            array_filter($db['user_categories'][$username], function($cat) use ($category) {
                return $cat !== $category;
            })
        );
        saveDB($db);
        
        // También eliminar de caducidades.txt
        deleteCaducidad($username, $category);
        
        return true;
    }
    return false;
}

function importCategoriesFromServerList($username) {
    $podcasts = readServerList($username);
    $db = getDB();
    
    if (!isset($db['user_categories'][$username])) {
        $db['user_categories'][$username] = [];
    }
    
    $categoriesFound = [];
    $existingCategories = $db['user_categories'][$username];
    
    foreach ($podcasts as $podcast) {
        $category = $podcast['category'];
        if (!in_array($category, $existingCategories)) {
            $db['user_categories'][$username][] = $category;
            $categoriesFound[] = $category;
        }
    }
    
    if (!empty($categoriesFound)) {
        $db['user_categories'][$username] = array_unique($db['user_categories'][$username]);
        sort($db['user_categories'][$username]);
        saveDB($db);
    }
    
    return $categoriesFound;
}

function isCategoryInUse($username, $category) {
    $podcasts = readServerList($username);
    foreach ($podcasts as $podcast) {
        if ($podcast['category'] === $category) {
            return true;
        }
    }
    return false;
}

?>
