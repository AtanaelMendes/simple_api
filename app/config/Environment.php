<?php
namespace App\Config;

class Environment
{
    private static $variables = [];
    private static $loaded = false;

    /**
     * Carrega as variáveis do arquivo .env
     */
    public static function load($path = null)
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $path ?: __DIR__ . '/../../.env';
        
        if (!file_exists($envFile)) {
            throw new \Exception("Arquivo .env não encontrado: $envFile");
        }

        // Read file with BOM handling
        $content = file_get_contents($envFile);
        // Remove BOM if present
        $content = str_replace("\xEF\xBB\xBF", '', $content);
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Skip comments
            if (strpos($line, '#') === 0) {
                continue;
            }

            // Separate key and value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                $value = trim($value, '"\'');

                // Store in static variable and environment
                self::$variables[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtém uma variável de ambiente
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$variables[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Define uma variável de ambiente
     */
    public static function set($key, $value)
    {
        self::$variables[$key] = $value;
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }

    /**
     * Verifica se uma variável existe
     */
    public static function has($key)
    {
        if (!self::$loaded) {
            self::load();
        }

        // Explicitly check self::$variables first - must both exist and not be empty
        return (isset(self::$variables[$key]) && self::$variables[$key] !== '') || 
               (isset($_ENV[$key]) && $_ENV[$key] !== '') || 
               (getenv($key) !== false && getenv($key) !== '');
    }

    /**
     * Obtém todas as variáveis carregadas
     */
    public static function all()
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$variables;
    }
    
    /**
     * Debug function to get environment loading status
     */
    public static function debug()
    {
        if (!self::$loaded) {
            self::load();
        }
        
        $debug = [
            'loaded' => self::$loaded,
            'variables' => self::$variables,
            'env_vars' => $_ENV,
            'getenv_test' => [
                'DB_HOST' => getenv('DB_HOST'),
                'DB_DATABASE' => getenv('DB_DATABASE')
            ]
        ];
        
        return $debug;
    }
}
