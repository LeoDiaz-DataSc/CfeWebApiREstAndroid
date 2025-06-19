<?php
/**
 * Archivo de configuración general para la API
 * Define variables según el entorno (desarrollo o producción)
 */
class Config {
    // Constantes principales
    private static $environment;
    private static $config = [];

    // Inicializar la configuración
    public static function init() {
        // Detectar el entorno
        self::$environment = self::detectEnvironment();
        
        // Configurar según el entorno
        self::loadConfig();
    }

    // Detectar el entorno actual basado en el servidor
    private static function detectEnvironment() {
        // Permitir forzar entorno con variable de entorno APP_ENV
        $env = getenv('APP_ENV');
        if ($env) {
            return strtolower($env);
        }

        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        if (strpos($server_name, 'cfedis.space') !== false) {
            return 'production';
        }
        return 'development';
    }

    // Cargar la configuración según el entorno
    private static function loadConfig() {
        // Configuración común para todos los entornos
        self::$config = [
            // API settings
            'api_version' => 'v1',
            'api_name' => 'CFE App API',
            
            // Seguridad
            'jwt_secret' => 'Tu_Clave_Secreta_JWT_Aqui', // ¡Cambiar en producción!
            'jwt_expiration' => 86400, // 24 horas en segundos
            
            // Carpetas
            'upload_dir' => dirname(__DIR__) . '/uploads/',
            'images_dir' => dirname(__DIR__) . '/uploads/images/',
            
            // Logs
            'log_enabled' => true,
            'log_file' => dirname(__DIR__) . '/logs/api.log',
            
            // Configuración de API
            'cors_allowed_origins' => ['https://cfedis.space', 'https://www.cfedis.space'],
        ];

        // Configuración específica para el entorno
        if (self::$environment === 'production') {
            // Sobreescribir valores para producción
            self::$config = array_merge(self::$config, [
                'base_url' => getenv('BASE_URL') ?: 'https://cfeapirest.cfedis.space/',
                'debug_mode' => true,
                'show_errors' => true,
                'jwt_secret' => getenv('JWT_SECRET') ?: 'CAMBIA_ESTO',
                'cors_allowed_origins' => ['https://cfedis.space', 'https://www.cfedis.space'],
                'rate_limiting' => true,
                'max_requests' => 100,
                'log_level' => 'error',
            ]);
        } else {
            // Configuración para desarrollo
            self::$config = array_merge(self::$config, [
                'base_url' => getenv('BASE_URL') ?: 'http://localhost/v1/',
                'debug_mode' => true,
                'show_errors' => true,
                'rate_limiting' => false,
                'log_level' => 'debug',
            ]);
        }
    }

    // Método para obtener una configuración específica
    public static function get($key) {
        // Inicializar si no se ha hecho
        if (empty(self::$config)) {
            self::init();
        }
        
        return isset(self::$config[$key]) ? self::$config[$key] : null;
    }

    // Obtener el entorno actual
    public static function getEnvironment() {
        if (empty(self::$environment)) {
            self::$environment = self::detectEnvironment();
        }
        return self::$environment;
    }

    // Verificar si estamos en producción
    public static function isProduction() {
        return self::getEnvironment() === 'production';
    }

    // Verificar si estamos en desarrollo
    public static function isDevelopment() {
        return self::getEnvironment() === 'development';
    }
}

// Inicializar la configuración automáticamente
Config::init();
?>
