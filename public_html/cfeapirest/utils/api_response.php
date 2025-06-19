<?php
// Clase para manejar respuestas de la API de manera estandarizada
require_once 'utils/logger.php';

class ApiResponse {
    
    // Respuesta exitosa (código 200)
    public static function success($message = "Operación exitosa", $data = null) {
        Logger::info($message);
        http_response_code(200);
        return self::output(true, $message, $data);
    }
    
    // Respuesta de error (código personalizable)
    public static function error($message = "Error en la operación", $code = 400, $data = null) {
        Logger::warning($message);
        http_response_code($code);
        return self::output(false, $message, $data);
    }
    
    // Error de autenticación (código 401)
    public static function unauthorized($message = "No autorizado") {
        Logger::warning($message);
        http_response_code(401);
        return self::output(false, $message);
    }
    
    // Error de permisos (código 403)
    public static function forbidden($message = "Acceso prohibido") {
        Logger::warning($message);
        http_response_code(403);
        return self::output(false, $message);
    }
    
    // Error de recurso no encontrado (código 404)
    public static function notFound($message = "Recurso no encontrado") {
        Logger::info($message);
        http_response_code(404);
        return self::output(false, $message);
    }
    
    // Error interno del servidor (código 500)
    public static function serverError($message = "Error interno del servidor") {
        Logger::error($message);
        http_response_code(500);
        return self::output(false, $message);
    }
    
    // Genera la estructura de respuesta JSON estandarizada
    private static function output($success, $message, $data = null) {
        $response = [
            "success" => $success,
            "message" => $message
        ];
        
        if ($data !== null) {
            $response["data"] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
}
?>
