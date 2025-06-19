<?php
require_once 'jwt_handler.php';
require_once 'api_response.php';
require_once 'utils/logger.php';
require_once dirname(__DIR__) . '/config/config.php';

// Polyfill para servidores donde getallheaders() no está disponible (cgi/fpm)
if (!function_exists('getallheaders')) {
    function getallheaders(): array {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }
}

// Clase para manejar la autenticación en la API
class Auth {
    private $jwt_handler;
    private $production_mode;
    
    public function __construct() {
        $this->jwt_handler = new JwtHandler();
        $this->production_mode = Config::isProduction();
    }
    
    // Valida que el token JWT sea válido
    public function validateToken() {
        // Obtener headers
        $headers = getallheaders();
        $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        // Verificar si hay un header de autorización
        if (empty($auth_header) || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
            // Registrar intento fallido
            if (function_exists('api_log')) {
                api_log('warning', 'Auth failure: Token no proporcionado', [
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'endpoint' => $_SERVER['REQUEST_URI']
                ]);
            }
            ApiResponse::unauthorized("Token de autenticación no proporcionado");
        }
        
        $jwt = $matches[1];
        $decoded = $this->jwt_handler->validateToken($jwt);
        
        if (!$decoded) {
            // Registrar intento fallido
            if (function_exists('api_log')) {
                api_log('warning', 'Auth failure: Token inválido', [
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'endpoint' => $_SERVER['REQUEST_URI']
                ]);
            }
            ApiResponse::unauthorized("Token de autenticación inválido o expirado");
        }
        
        // Verificar restricciones adicionales en producción
        if ($this->production_mode) {
            // Verificar IP si es necesario para mayor seguridad
            $this->validateAdditionalSecurity($decoded);
        }
        
        // Registrar acceso exitoso
        if (function_exists('api_log')) {
            api_log('info', 'Auth success', [
                'user_id' => $decoded->user_id,
                'role' => $decoded->role
            ]);
        }
        
        return $decoded;
    }
    
    // Método para validaciones adicionales de seguridad en producción
    private function validateAdditionalSecurity($decoded) {
        // Aquí se pueden implementar verificaciones adicionales como:
        // - Verificar IP conocida
        // - Comprobar si el usuario está bloqueado
        // - Verificar acceso fuera de horario laboral
        // - Comprobar sesiones concurrentes
        
        // Por ahora solo registramos la información
        if (function_exists('api_log')) {
            api_log('debug', 'Security check passed', [
                'user_id' => $decoded->user_id
            ]);
        }
    }
    
    // Verifica si el usuario tiene el rol requerido
    public function requireRole($required_role) {
        $user = $this->validateToken();
        
        if ($user->role !== $required_role && $user->role !== 'JEFE_AREA') {
            ApiResponse::forbidden("No tiene permisos suficientes para esta acción");
        }
        
        return $user;
    }
    
    // Genera un nuevo token de autenticación
    public function generateAuthToken($user_id, $username, $role) {
        return $this->jwt_handler->generateToken($user_id, $username, $role);
    }
}
?>
