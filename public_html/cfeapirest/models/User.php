<?php
class User {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "users";
    
    // Propiedades del objeto
    public $id;
    public $username;
    public $password;
    public $email;
    public $full_name;
    public $role_id;
    public $active;
    public $created_at;
    public $last_login;
    public $role_name; // Para almacenar el nombre del rol (no está en la tabla)
    
    // Constructor con la conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Obtener todos los usuarios
    public function getAll() {
        $query = "SELECT u.*, r.name as role_name FROM " . $this->table_name . " u
                  LEFT JOIN roles r ON u.role_id = r.id
                  ORDER BY u.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener un solo usuario por ID
    public function getById() {
        $query = "SELECT u.*, r.name as role_name FROM " . $this->table_name . " u
                  LEFT JOIN roles r ON u.role_id = r.id
                  WHERE u.id = ?
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->full_name = $row['full_name'];
            $this->role_id = $row['role_id'];
            $this->active = $row['active'];
            $this->created_at = $row['created_at'];
            $this->last_login = $row['last_login'];
            $this->role_name = $row['role_name'];
            return true;
        }
        
        return false;
    }
    
    // Verificar si existe un usuario con un nombre de usuario específico
    public function getByUsername() {
        $query = "SELECT u.*, r.name as role_name FROM " . $this->table_name . " u
                  LEFT JOIN roles r ON u.role_id = r.id
                  WHERE u.username = ?
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->username);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->password = $row['password'];
            $this->email = $row['email'];
            $this->full_name = $row['full_name'];
            $this->role_id = $row['role_id'];
            $this->active = $row['active'];
            $this->created_at = $row['created_at'];
            $this->last_login = $row['last_login'];
            $this->role_name = $row['role_name'];
            return true;
        }
        
        return false;
    }
    
    // Crear un nuevo usuario
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                 (username, password, email, full_name, role_id, active)
                 VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        
        // Encriptar contraseña
        $password_hash = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Bind de parámetros
        $stmt->bindParam(1, $this->username);
        $stmt->bindParam(2, $password_hash);
        $stmt->bindParam(3, $this->email);
        $stmt->bindParam(4, $this->full_name);
        $stmt->bindParam(5, $this->role_id);
        $stmt->bindParam(6, $this->active);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Actualizar un usuario existente
    public function update() {
        // Si la contraseña no cambia
        if (empty($this->password)) {
            $query = "UPDATE " . $this->table_name . "
                    SET
                        email = ?,
                        full_name = ?,
                        role_id = ?,
                        active = ?
                    WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            
            // Sanitizar datos
            $this->email = htmlspecialchars(strip_tags($this->email));
            $this->full_name = htmlspecialchars(strip_tags($this->full_name));
            
            // Bind de parámetros
            $stmt->bindParam(1, $this->email);
            $stmt->bindParam(2, $this->full_name);
            $stmt->bindParam(3, $this->role_id);
            $stmt->bindParam(4, $this->active);
            $stmt->bindParam(5, $this->id);
        }
        // Si la contraseña cambia
        else {
            $query = "UPDATE " . $this->table_name . "
                    SET
                        password = ?,
                        email = ?,
                        full_name = ?,
                        role_id = ?,
                        active = ?
                    WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            
            // Sanitizar datos
            $this->email = htmlspecialchars(strip_tags($this->email));
            $this->full_name = htmlspecialchars(strip_tags($this->full_name));
            
            // Encriptar contraseña
            $password_hash = password_hash($this->password, PASSWORD_DEFAULT);
            
            // Bind de parámetros
            $stmt->bindParam(1, $password_hash);
            $stmt->bindParam(2, $this->email);
            $stmt->bindParam(3, $this->full_name);
            $stmt->bindParam(4, $this->role_id);
            $stmt->bindParam(5, $this->active);
            $stmt->bindParam(6, $this->id);
        }
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Actualizar el último login
    public function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . "
                SET last_login = NOW()
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        return $stmt->execute();
    }
    
    // Eliminar un usuario
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Verificar si las credenciales son válidas para iniciar sesión
    public function login() {
        // Conservar la contraseña en texto plano antes de cargar datos del usuario
        $plainPassword = $this->password;

        // Verificar si el usuario existe
        if ($this->getByUsername()) {
            // Verificar si el usuario está activo
            if ($this->active != 1) {
                // Log del intento fallido de login (usuario inactivo)
                api_log('warning', 'Login fallido: usuario inactivo', ['username' => $this->username]);
                return false;
            }
            
            // Verificar contraseña comparando la ingresada (texto plano) con el hash almacenado
            if (password_verify($plainPassword, $this->password)) {
                // Actualizar último login
                $this->updateLastLogin();
                return true;
            } else {
                // Fallback para compatibilidad con contraseñas antiguas (no hasheadas)
                // Solo para migración - esto debe eliminarse después de la migración completa
                if ($plainPassword === $this->password) {
                    // Actualizar la contraseña al nuevo formato hash
                    $this->updateToHashedPassword($plainPassword);
                    $this->updateLastLogin();
                    return true;
                }
                // Log del intento fallido de login (contraseña incorrecta)
                api_log('warning', 'Login fallido: contraseña incorrecta', ['username' => $this->username]);
            }
        } else {
            // Log del intento fallido de login (usuario no existe)
            api_log('warning', 'Login fallido: usuario no existe', ['username' => $this->username]);
        }
        
        return false;
    }
    
    // Actualiza la contraseña de un usuario al formato hash seguro
    private function updateToHashedPassword($plainPassword) {
        $password_hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        
        $query = "UPDATE " . $this->table_name . " 
                SET password = ? 
                WHERE id = ?"; 
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $password_hash);
        $stmt->bindParam(2, $this->id);
        
        return $stmt->execute();
    }
}
?>
