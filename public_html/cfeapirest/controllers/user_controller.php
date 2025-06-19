<?php
require_once 'models/User.php';
require_once 'utils/auth.php';
require_once 'utils/api_response.php';

class UserController {
    private $db;
    private $user;
    private $auth;
    
    public function __construct($db) {
        $this->db = $db;
        $this->user = new User($db);
        $this->auth = new Auth();
    }
    
    // Obtener todos los usuarios (solo accesible para roles administrativos)
    public function getAllUsers() {
        // Verificar autenticación y permisos
        $user_data = $this->auth->requireRole('JEFE_AREA');
        
        // Consultar todos los usuarios
        $stmt = $this->user->getAll();
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $users_arr = array();
            $users_arr["users"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $user_item = array(
                    "id" => $id,
                    "username" => $username,
                    "email" => $email,
                    "full_name" => $full_name,
                    "role_id" => $role_id,
                    "role_name" => $role_name,
                    "active" => $active,
                    "created_at" => $created_at,
                    "last_login" => $last_login
                );
                
                array_push($users_arr["users"], $user_item);
            }
            
            ApiResponse::success("Usuarios encontrados", $users_arr);
        } else {
            ApiResponse::success("No se encontraron usuarios", ["users" => []]);
        }
    }
    
    // Obtener un usuario por ID (solo para administradores)
    public function getUserById($id) {
        // Verificar autenticación y permisos
        $user_data = $this->auth->requireRole('JEFE_AREA');
        
        // Asignar ID y buscar el usuario
        $this->user->id = $id;
        
        if ($this->user->getById()) {
            $user_arr = array(
                "id" => $this->user->id,
                "username" => $this->user->username,
                "email" => $this->user->email,
                "full_name" => $this->user->full_name,
                "role_id" => $this->user->role_id,
                "role_name" => $this->user->role_name,
                "active" => $this->user->active,
                "created_at" => $this->user->created_at,
                "last_login" => $this->user->last_login
            );
            
            ApiResponse::success("Usuario encontrado", $user_arr);
        } else {
            ApiResponse::notFound("Usuario no encontrado");
        }
    }
    
    // Obtener perfil del usuario actual
    public function getUserProfile() {
        // Verificar autenticación (cualquier rol puede acceder a su propio perfil)
        $user_data = $this->auth->validateToken();
        
        // Asignar ID y buscar el usuario
        $this->user->id = $user_data->user_id;
        
        if ($this->user->getById()) {
            $user_arr = array(
                "id" => $this->user->id,
                "username" => $this->user->username,
                "email" => $this->user->email,
                "full_name" => $this->user->full_name,
                "role_name" => $this->user->role_name,
                "active" => $this->user->active,
                "created_at" => $this->user->created_at,
                "last_login" => $this->user->last_login
            );
            
            ApiResponse::success("Perfil de usuario", $user_arr);
        } else {
            ApiResponse::notFound("Usuario no encontrado");
        }
    }
    
    // Actualizar perfil del usuario actual
    public function updateUserProfile() {
        // Verificar autenticación (cualquier rol puede actualizar su propio perfil)
        $user_data = $this->auth->validateToken();
        
        // Obtener datos enviados
        $data = json_decode(file_get_contents("php://input"));
        
        // Asignar ID y buscar el usuario
        $this->user->id = $user_data->user_id;
        
        if (!$this->user->getById()) {
            ApiResponse::notFound("Usuario no encontrado");
            return;
        }
        
        // Actualizar solo los campos permitidos para actualización de perfil
        $this->user->email = isset($data->email) ? $data->email : $this->user->email;
        $this->user->full_name = isset($data->full_name) ? $data->full_name : $this->user->full_name;
        $this->user->password = isset($data->password) ? $data->password : "";
        
        // Verificar que la contraseña tenga máximo 8 caracteres
        if (!empty($this->user->password) && strlen($this->user->password) > 8) {
            ApiResponse::error("La contraseña debe tener máximo 8 caracteres", 400);
            return;
        }
        
        // Intentar actualizar el usuario
        if ($this->user->update()) {
            ApiResponse::success("Perfil actualizado exitosamente");
        } else {
            ApiResponse::error("No se pudo actualizar el perfil", 500);
        }
    }
    
    // Actualizar un usuario (solo para administradores)
    public function updateUser($id) {
        // Verificar autenticación y permisos
        $user_data = $this->auth->requireRole('JEFE_AREA');
        
        // Obtener datos enviados
        $data = json_decode(file_get_contents("php://input"));
        
        // Asignar ID y buscar el usuario
        $this->user->id = $id;
        
        if (!$this->user->getById()) {
            ApiResponse::notFound("Usuario no encontrado");
            return;
        }
        
        // Actualizar campos
        $this->user->email = isset($data->email) ? $data->email : $this->user->email;
        $this->user->full_name = isset($data->full_name) ? $data->full_name : $this->user->full_name;
        $this->user->role_id = isset($data->role_id) ? $data->role_id : $this->user->role_id;
        $this->user->active = isset($data->active) ? $data->active : $this->user->active;
        $this->user->password = isset($data->password) ? $data->password : "";
        
        // Verificar que la contraseña tenga máximo 8 caracteres
        if (!empty($this->user->password) && strlen($this->user->password) > 8) {
            ApiResponse::error("La contraseña debe tener máximo 8 caracteres", 400);
            return;
        }
        
        // Intentar actualizar el usuario
        if ($this->user->update()) {
            ApiResponse::success("Usuario actualizado exitosamente");
        } else {
            ApiResponse::error("No se pudo actualizar el usuario", 500);
        }
    }
    
    // Eliminar un usuario (solo para administradores)
    public function deleteUser($id) {
        // Verificar autenticación y permisos
        $user_data = $this->auth->requireRole('JEFE_AREA');
        
        // No permitir eliminar el propio usuario
        if ($user_data->user_id == $id) {
            ApiResponse::error("No puede eliminar su propio usuario", 400);
            return;
        }
        
        // Asignar ID y buscar el usuario
        $this->user->id = $id;
        
        if (!$this->user->getById()) {
            ApiResponse::notFound("Usuario no encontrado");
            return;
        }
        
        // Intentar eliminar el usuario
        if ($this->user->delete()) {
            ApiResponse::success("Usuario eliminado exitosamente");
        } else {
            ApiResponse::error("No se pudo eliminar el usuario", 500);
        }
    }
}
?>
