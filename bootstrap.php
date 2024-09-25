<?php
/**
 * Bootstrap file for Simple API
 * This file handles all initializations needed before the main application starts
 */

// Set up error reporting and logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
$path = __DIR__ . '/logs/php_error-' . date('d-m-Y') . '.log';
ini_set('log_errors', 1);
ini_set('error_log', $path);
error_reporting(E_ALL);

// Load Composer autoloader first
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    die('Composer dependencies not installed. Run "composer install" first.');
}
require $composerAutoload;

// Load environment variables before defining constants
try {
    // Check for newer version of .env file with correct encoding
    $envFile = '.env';
    
    // Manual parsing of .env file to avoid encoding issues
    $envPath = __DIR__ . '/' . $envFile;
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^(["\']).*\1$/', $value)) {
                    $value = substr($value, 1, -1);
                }
                
                // Set in multiple places for compatibility
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        error_log("Loaded environment variables from $envFile");
    } else {
        error_log("Warning: No .env file found");
    }
} catch (\Exception $e) {
    error_log('Error loading .env file: ' . $e->getMessage());
}

// Define constants based on environment variables when available
if (!defined('DEBUG')) {
    // Use APP_DEBUG from .env if available, otherwise default to false for production safety
    define('DEBUG', isset($_ENV['APP_DEBUG']) ? filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN) : false);
}

// Register error handler for better debug information
if (DEBUG) {
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            // This error code is not included in error_reporting
            return;
        }
        error_log(sprintf("[%s] %s in %s on line %d", 
            date('d-M-Y H:i:s'),
            $message, 
            $file, 
            $line
        ));
    });
}

// Register any additional autoloaders if needed
spl_autoload_register(function ($class_name) {
    // Check if it's a namespaced class
    if (strpos($class_name, '\\') !== false) {
        // Remove the initial "App\" namespace if present
        if (strpos($class_name, 'App\\') === 0) {
            $class_name = substr($class_name, 4); // Remove 'App\'
        }
        
        $parts = explode('\\', $class_name);
        $className = array_pop($parts); // Get last part as class name
        $namespace = implode('\\', $parts); // Remaining namespace parts
        
        // Determine path based on namespace
        $path = '';
        if (strpos($namespace, 'Controllers') !== false) {
            $path = "app/Controllers/{$className}.php";
        } elseif (strpos($namespace, 'Models') !== false) {
            $path = "app/Models/{$className}.php";
        } elseif (strpos($namespace, 'Repository') !== false) {
            $path = "app/Repository/{$className}.php";
        } elseif (strpos($namespace, 'Core') !== false) {
            $path = "app/Core/{$className}.php";
        } else {
            // Try a direct mapping as fallback
            $path = "app/" . str_replace('\\', '/', $class_name) . ".php";
        }
        
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    return false;
});

// Other initializations as needed