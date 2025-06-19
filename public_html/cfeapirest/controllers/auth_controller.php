<?php
require_once 'models/User.php';
require_once 'utils/auth.php';
require_once 'utils/validator.php';

class AuthController {
    private $db;
    private $user;
    private $auth;
    
    public function __construct($db) {
        $this->db = $db;
        $this->user = new User($db);
        $this->auth = new Auth();
    }
    
    // Método para el login de usuarios
    public function login() {
        // Verificar que sea una solicitud POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ApiResponse::error("Método no permitido", 405);
            return;
        }
        
        // Obtener datos enviados
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validar campos requeridos
        $missing = Validator::required($data, ['username', 'password']);
        if (!empty($missing)) {
            ApiResponse::error("Faltan datos obligatorios: " . implode(', ', $missing), 400);
            return;
        }
        
        // Longitud de contraseña (máx 32 para hash futuro)
        if (!Validator::maxLength($data['password'], 32)) {
            ApiResponse::error("La contraseña excede la longitud permitida", 400);
            return;
        }
        
        // Asignar valores
        $this->user->username = $data['username'];
        $this->user->password = $data['password'];
        
        // Intentar login
        if ($this->user->login()) {
            // Generar token JWT
            $token = $this->auth->generateAuthToken(
                $this->user->id,
                $this->user->username,
                $this->user->role_name
            );
            
            // Devolver respuesta exitosa con token y datos de usuario
            ApiResponse::success("Login exitoso", [
                "token" => $token,
                "user" => [
                    "id" => $this->user->id,
                    "username" => $this->user->username,
                    "role" => $this->user->role_name,
                    "full_name" => $this->user->full_name
                ]
            ]);
        } else {
            // Devolver error si las credenciales son incorrectas
            ApiResponse::error("Credenciales inválidas", 401);
        }
    }
    
    // Método para registro de nuevos usuarios (solo disponible para administradores)
    public function register() {
        // Verificar que sea una solicitud POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ApiResponse::error("Método no permitido", 405);
            return;
        }
        
        // Verificar autenticación y permisos
        $user_data = $this->auth->requireRole('JEFE_AREA');
        
        // Obtener datos enviados
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validar campos requeridos
        $missing = Validator::required($data, ['username', 'password', 'role_id']);
        if (!empty($missing)) {
            ApiResponse::error("Faltan datos obligatorios: " . implode(', ', $missing), 400);
            return;
        }
        
        // Validar longitud contraseña máx 12
        if (!Validator::maxLength($data['password'], 14)) {
            ApiResponse::error("La contraseña debe tener máximo 14 caracteres", 400);
            return;
        }
        
        // Si se envía email debe ser válido
        if (isset($data['email']) && !Validator::email($data['email'])) {
            ApiResponse::error("Formato de email inválido", 400);
            return;
        }
        
        $this->user->username = $data['username'];
        $this->user->password = $data['password'];
        $this->user->email = $data['email'] ?? null;
        $this->user->full_name = $data['full_name'] ?? $data['username'];
        $this->user->role_id = $data['role_id'];
        $this->user->active = $data['active'] ?? 1;
        
        // Intentar crear el usuario
        if ($this->user->create()) {
            ApiResponse::success("Usuario creado exitosamente", [
                "id" => $this->user->id,
                "username" => $this->user->username
            ]);
        } else {
            ApiResponse::error("No se pudo crear el usuario", 500);
        }
    }
}
?>
