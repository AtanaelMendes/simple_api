<?php
/**
 * Simple API - Entry Point
 *
 * Main entry point for all API requests.
 * Configuration and bootstrap are handled in bootstrap.php
 */

// Error logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
$path = __DIR__ . '/logs/simple_api_' . date('d-m-Y') . '.log';
ini_set('log_errors', 1);
ini_set('error_log', $path);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Include bootstrap file that handles all initialization
require_once __DIR__ . '/bootstrap.php';

// CORS configuration — add your allowed origins here
$allowed_origins = [
    'http://localhost:5173',
    'http://localhost:3000',
    'http://localhost',
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if (!headers_sent()) {
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// Enhanced global JSON request handling
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] :
               (isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : '');

$allHeaders = getallheaders();
if (empty($contentType) && isset($allHeaders['Content-Type'])) {
    $contentType = $allHeaders['Content-Type'];
} elseif (empty($contentType) && isset($allHeaders['content-type'])) {
    $contentType = $allHeaders['content-type'];
}

// For POST, PUT, PATCH, DELETE requests, parse JSON input
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $rawInput = file_get_contents('php://input');

    if (!empty($rawInput)) {
        $_REQUEST['_raw_json'] = $rawInput;

        $jsonData = json_decode($rawInput, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            $_POST = array_merge($_POST, $jsonData);
            $_REQUEST = array_merge($_REQUEST, $jsonData);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload classes
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'App\\') === 0) {
        $class_name = substr($class_name, 4);
    }

    $filename = "";
    if (preg_match('/Controllers/', $class_name)) {
        $className = str_replace('Controllers\\', '', $class_name);
        $filename = "app/Controllers/" . $className . ".php";
    } elseif (preg_match('/Models/', $class_name)) {
        $className = str_replace('Models\\', '', $class_name);
        $filename = "app/Models/" . $className . ".php";
    } elseif (preg_match('/Core/', $class_name)) {
        $className = str_replace('Core\\', '', $class_name);
        $filename = "app/Core/" . $className . ".php";
    } elseif (preg_match('/Repository/', $class_name)) {
        $className = str_replace('Repository\\', '', $class_name);
        $filename = "app/Repository/" . $className . ".php";
    } elseif (preg_match('/Services/', $class_name)) {
        $className = str_replace('Services\\', '', $class_name);
        $filename = "app/Services/" . $className . ".php";
    } elseif (preg_match('/Config/', $class_name)) {
        $className = str_replace('Config\\', '', $class_name);
        $filename = "app/config/" . $className . ".php";
    } elseif (preg_match('/Utils/', $class_name)) {
        $className = str_replace('Utils\\', '', $class_name);
        $filename = "app/Utils/" . $className . ".php";
    }

    if ($filename && file_exists($filename)) {
        require_once($filename);
    }
});

include 'app.php';
include 'routes.php';

// Get HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Get request URI
$uri = $_SERVER['REQUEST_URI'];

// Remove query string
$uri = strtok($uri, '?');

// Resolve path for both rewrite and non-rewrite scenarios
if (isset($_GET['route'])) {
    $path = $_GET['route'];
} else {
    $absolutPath = str_replace("\\", "/", __DIR__);
    $relativePath = str_replace("\\", "/", $_SERVER["DOCUMENT_ROOT"]);
    $relativePath = str_replace($relativePath, '', $absolutPath)."/";
    $path = str_replace($relativePath, '', $uri);
}

$routeResult = $app->getRoute($method, $path);

if (empty($routeResult)) {
    http_response_code(404);
    echo json_encode([
        "error" => "Route not found",
        "requested_path" => $path,
        "method" => $method
    ], JSON_PRETTY_PRINT);
    exit;
}

// Execute the route
$app->executeRoute($routeResult);
