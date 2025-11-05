<?php
// includes/categories.php - Gestión de categorías de podcasts

function getUserCategories($username) {
    $userData = getUserDB($username);
    return $userData['categories'] ?? [];
}

function saveUserCategory($username, $category) {
    $userData = getUserDB($username);
    if (!isset($userData['categories'])) {
        $userData['categories'] = [];
    }

    $sanitized = sanitizePodcastName($category);
    if (!in_array($sanitized, $userData['categories'])) {
        $userData['categories'][] = $sanitized;
        sort($userData['categories']);
        saveUserDB($username, $userData);
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

    $userData = getUserDB($username);
    if (isset($userData['categories'])) {
        $userData['categories'] = array_values(
            array_filter($userData['categories'], function($cat) use ($category) {
                return $cat !== $category;
            })
        );
        saveUserDB($username, $userData);

        // También eliminar de caducidades.txt
        deleteCaducidad($username, $category);

        return true;
    }
    return false;
}

function importCategoriesFromServerList($username) {
    $podcasts = readServerList($username);
    $userData = getUserDB($username);

    if (!isset($userData['categories'])) {
        $userData['categories'] = [];
    }

    $categoriesFound = [];
    $existingCategories = $userData['categories'];

    foreach ($podcasts as $podcast) {
        $category = $podcast['category'];
        if (!in_array($category, $existingCategories)) {
            $userData['categories'][] = $category;
            $categoriesFound[] = $category;
        }
    }

    if (!empty($categoriesFound)) {
        $userData['categories'] = array_unique($userData['categories']);
        sort($userData['categories']);
        saveUserDB($username, $userData);
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
