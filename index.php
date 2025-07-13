<?php
/**
 * Point d'entrée principal pour Heroku
 * Gère le routage entre frontend et backend
 */

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Supprimer les paramètres de query string pour le routage
$path = parse_url($request_uri, PHP_URL_PATH);

// Routage
if (strpos($path, '/backend/') === 0) {
    // Requêtes API backend
    $backend_path = substr($path, 9); // Enlever '/backend/'
    
    if (empty($backend_path)) {
        $backend_path = 'index.php';
    }
    
    $backend_file = __DIR__ . '/backend/' . $backend_path;
    
    if (file_exists($backend_file) && is_file($backend_file)) {
        // Inclure le fichier backend
        chdir(__DIR__ . '/backend');
        include $backend_file;
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'API endpoint not found',
            'path' => $backend_path
        ]);
    }
    
} elseif (strpos($path, '/frontend/') === 0 || $path === '/') {
    // Requêtes frontend
    if ($path === '/') {
        $frontend_path = 'index.html';
    } else {
        $frontend_path = substr($path, 10); // Enlever '/frontend/'
    }
    
    $frontend_file = __DIR__ . '/frontend/' . $frontend_path;
    
    if (file_exists($frontend_file) && is_file($frontend_file)) {
        $extension = pathinfo($frontend_file, PATHINFO_EXTENSION);
        
        // Définir le type MIME
        switch ($extension) {
            case 'html':
                header('Content-Type: text/html; charset=utf-8');
                break;
            case 'css':
                header('Content-Type: text/css');
                break;
            case 'js':
                header('Content-Type: application/javascript');
                break;
            case 'json':
                header('Content-Type: application/json');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'gif':
                header('Content-Type: image/gif');
                break;
            case 'svg':
                header('Content-Type: image/svg+xml');
                break;
            default:
                header('Content-Type: text/plain');
        }
        
        readfile($frontend_file);
    } else {
        // Page 404 personnalisée
        http_response_code(404);
        if (file_exists(__DIR__ . '/frontend/404.html')) {
            include __DIR__ . '/frontend/404.html';
        } else {
            echo '<h1>404 - Page non trouvée</h1>';
        }
    }
    
} else {
    // Redirection par défaut vers le frontend
    header('Location: /frontend/index.html');
    exit;
}
?>
