<?php
session_start();

// Auto-load core classes
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/../src/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize the database and ensure tables exist
Database::getConnection();

// Basic Router
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = trim($requestUri, '/');

// Handle logout explicitly
if ($route === 'logout') {
    Auth::logout();
    header('Location: /login');
    exit;
}

// Global Auth guard
if (!Auth::isLoggedIn() && $route !== 'login') {
    header('Location: /login');
    exit;
}

// Map routes to templates
$routes = [
    '' => 'dashboard.php',
    'dashboard' => 'dashboard.php',
    'login' => 'login.php',
    'commitments' => 'commitments.php',
    'categories' => 'categories.php',
];

if (array_key_exists($route, $routes)) {
    $template = __DIR__ . '/../templates/' . $routes[$route];
    if (file_exists($template)) {
        require $template;
    } else {
        echo "Template " . htmlspecialchars($routes[$route]) . " not found. Wait for templates to be implemented.";
    }
} else {
    http_response_code(404);
    echo "404 Not Found";
}
