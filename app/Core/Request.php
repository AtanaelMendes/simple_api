<?php

namespace App\Core;

class Request
{
    private $attributes; // GET parameters
    private $params;     // POST parameters
    private $routeParams; // Route parameters (from URL patterns)
    private $headers;    // Request headers
    private $method;     // HTTP method
    private $uri;        // Request URI

    public function __construct()
    {
        $this->attributes = $_GET;
        $this->params = $this->parsePostData();
        $this->routeParams = [];
        $this->headers = $this->parseHeaders();
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Parse POST data (supports JSON and form data)
     * 
     * Enhanced with comprehensive detection and debugging
     */
    private function parsePostData()
    {        
        // Check for JSON data already parsed in global scope (index.php)
        if (isset($_REQUEST['_raw_json']) && is_array($_POST) && !empty($_POST)) {
            return $_POST;
        }
        
        // Check content type in all possible places
        $contentType = $this->getContentType();
        
        // First try: Standard php://input parsing
        // We do this for all POST/PUT/PATCH requests regardless of content type
        if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            
            if (!empty($input)) {
                $decoded = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                } else {
                    $firstChar = trim($input)[0] ?? '';
                    if ($firstChar == '{' || $firstChar == '[') {
                        error_log("Request::parsePostData - WARNING: Input looks like JSON but failed to parse");
                    }
                }
            } else {
                error_log("Request::parsePostData - Empty request body");
            }
        }
        
        // Second try: If Content-Type is JSON but $_POST is empty,
        // check if raw data was stored in a global variable
        if (strpos($contentType, 'application/json') !== false && empty($_POST) && isset($_REQUEST['_raw_json'])) {
            $raw = $_REQUEST['_raw_json'];
            $decoded = json_decode($raw, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }
    
    /**
     * Get content type from all possible sources
     */
    private function getContentType()
    {
        // Check standard location
        if (isset($_SERVER['CONTENT_TYPE'])) {
            return $_SERVER['CONTENT_TYPE'];
        }
        
        // Check alternate location
        if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            return $_SERVER['HTTP_CONTENT_TYPE'];
        }
        
        // Check all headers
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Content-Type'])) {
                return $headers['Content-Type'];
            }
            if (isset($headers['content-type'])) {
                return $headers['content-type'];
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'CONTENT_TYPE') !== false) {
                return $value;
            }
            if (strpos($key, 'HTTP_CONTENT_TYPE') !== false) {
                return $value;
            }
        }
        
        return '';
    }

    /**
     * Parse request headers
     */
    private function parseHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('HTTP_', '', $key);
                $header = str_replace('_', '-', $header);
                $header = strtolower($header);
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    /**
     * Get all GET parameters
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get all POST parameters
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get specific GET parameter
     */
    public function getAttribute($key, $default = null)
    {
        $value = $this->attributes[$key] ?? $default;
        return $this->sanitize($value);
    }

    /**
     * Get specific POST parameter
     */
    public function getParam($key, $default = null)
    {
        $value = $this->params[$key] ?? $default;
        return $this->sanitize($value);
    }

    /**
     * Get GET parameter as integer
     */
    public function getAttributeInt($key, $default = null)
    {
        $value = $this->attributes[$key] ?? null;
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $default;
    }

    /**
     * Get POST parameter as integer
     */
    public function getParamInt($key, $default = null)
    {
        $value = $this->params[$key] ?? null;
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $default;
    }

    /**
     * Get GET parameter as float
     */
    public function getAttributeFloat($key, $default = null)
    {
        $value = $this->attributes[$key] ?? null;
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? (float)$value : $default;
    }

    /**
     * Get POST parameter as float
     */
    public function getParamFloat($key, $default = null)
    {
        $value = $this->params[$key] ?? null;
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? (float)$value : $default;
    }

    /**
     * Get GET parameter as boolean
     */
    public function getAttributeBool($key, $default = null)
    {
        $value = $this->attributes[$key] ?? null;
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Get POST parameter as boolean
     */
    public function getParamBool($key, $default = null)
    {
        $value = $this->params[$key] ?? null;
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Get GET parameter as email
     */
    public function getAttributeEmail($key, $default = null)
    {
        $value = $this->attributes[$key] ?? null;
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : $default;
    }

    /**
     * Get POST parameter as email
     */
    public function getParamEmail($key, $default = null)
    {
        $value = $this->params[$key] ?? null;
        
        if ($value === null || $value === '') {
            return $default;
        }
        
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : $default;
    }

    /**
     * Sanitize input value
     */
    public function sanitize($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        
        if (is_string($value)) {
            // Remove tags HTML/PHP
            $value = strip_tags($value);
            // Remove caracteres especiais
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            // Trim whitespace
            $value = trim($value);
        }
        
        return $value;
    }

    /**
     * Sanitize for database (additional escaping)
     */
    public function sanitizeForDb($value)
    {
        $value = $this->sanitize($value);
        
        if (is_string($value)) {
            // Escape para prevenir SQL injection (básico)
            $value = addslashes($value);
        }
        
        return $value;
    }

    /**
     * Check if parameter exists in GET
     */
    public function hasAttribute($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Check if parameter exists in POST
     */
    public function hasParam($key)
    {
        return isset($this->params[$key]);
    }

    /**
     * Get HTTP method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get request URI
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Get request headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get specific header
     */
    public function getHeader($key, $default = null)
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax()
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Check if request is JSON
     */
    public function isJson()
    {
        $contentType = $this->getHeader('content-type', '');
        return strpos($contentType, 'application/json') !== false;
    }

    /**
     * Get client IP address
     */
    public function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Se houver múltiplos IPs, pegar o primeiro
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Get user agent
     */
    public function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Validate required parameters
     */
    public function validateRequired($params, $source = 'params')
    {
        $missing = [];
        $data = $source === 'params' ? $this->params : $this->attributes;
        
        foreach ($params as $param) {
            if (!isset($data[$param]) || empty($data[$param])) {
                $missing[] = $param;
            }
        }
        
        return empty($missing) ? true : $missing;
    }

    /**
     * Get raw input (useful for JSON)
     */
    public function getRawInput()
    {
        return file_get_contents('php://input');
    }

    /**
     * Get all data (GET + POST combined)
     */
    public function all()
    {
        return array_merge($this->attributes, $this->params);
    }

    /**
     * Get only specified keys from request
     */
    public function only($keys, $source = 'all')
    {
        $data = [];
        $sourceData = $source === 'params' ? $this->params : 
                     ($source === 'attributes' ? $this->attributes : $this->all());
        
        foreach ($keys as $key) {
            if (isset($sourceData[$key])) {
                $data[$key] = $sourceData[$key];
            }
        }
        
        return $data;
    }

    /**
     * Get all except specified keys
     */
    public function except($keys, $source = 'all')
    {
        $sourceData = $source === 'params' ? $this->params : 
                     ($source === 'attributes' ? $this->attributes : $this->all());
        
        return array_diff_key($sourceData, array_flip($keys));
    }
    
    /**
     * Set a route parameter
     */
    public function setParam($key, $value)
    {
        $this->routeParams[$key] = $value;
        return $this;
    }
    
    /**
     * Get route parameter (from URL patterns like /users/{id})
     */
    public function getRouteParam($key, $default = null)
    {
        return $this->routeParams[$key] ?? $default;
    }
    
    /**
     * Check if route parameter exists
     */
    public function hasRouteParam($key)
    {
        return isset($this->routeParams[$key]);
    }
    
    /**
     * Get all route parameters
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }
}
