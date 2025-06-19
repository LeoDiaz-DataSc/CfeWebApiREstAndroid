<?php
class Chat {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "chat_messages";
    
    // Propiedades del objeto
    public $id;
    public $sender_id;
    public $receiver_id;
    public $message;
    public $read_status;
    public $created_at;
    
    // Propiedades extendidas para joins
    public $sender_username;
    public $receiver_username;
    
    // Constructor con la conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Obtener mensajes para un usuario específico
    public function getMessages($user_id, $with_user_id = null) {
        // Si with_user_id es null, retorna todos los mensajes del usuario
        if ($with_user_id === null) {
            $query = "SELECT 
                        c.*,
                        s.username as sender_username,
                        r.username as receiver_username
                      FROM 
                        " . $this->table_name . " c
                        LEFT JOIN users s ON c.sender_id = s.id
                        LEFT JOIN users r ON c.receiver_id = r.id
                      WHERE 
                        (c.sender_id = ? OR c.receiver_id = ? OR c.receiver_id IS NULL)
                      ORDER BY 
                        c.created_at ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->bindParam(2, $user_id);
        } 
        // Si with_user_id está definido, retorna solo los mensajes entre estos dos usuarios
        else {
            $query = "SELECT 
                        c.*,
                        s.username as sender_username,
                        r.username as receiver_username
                      FROM 
                        " . $this->table_name . " c
                        LEFT JOIN users s ON c.sender_id = s.id
                        LEFT JOIN users r ON c.receiver_id = r.id
                      WHERE 
                        (c.sender_id = ? AND c.receiver_id = ?) OR
                        (c.sender_id = ? AND c.receiver_id = ?)
                      ORDER BY 
                        c.created_at ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->bindParam(2, $with_user_id);
            $stmt->bindParam(3, $with_user_id);
            $stmt->bindParam(4, $user_id);
        }
        
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener mensajes globales (receiver_id = NULL)
    public function getGlobalMessages() {
        $query = "SELECT 
                    c.*,
                    s.username as sender_username
                  FROM 
                    " . $this->table_name . " c
                    LEFT JOIN users s ON c.sender_id = s.id
                  WHERE 
                    c.receiver_id IS NULL
                  ORDER BY 
                    c.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Crear un nuevo mensaje
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (sender_id, receiver_id, message, read_status)
                  VALUES (?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->message = htmlspecialchars(strip_tags($this->message));
        
        // Valores por defecto
        $read_status = 0; // No leído por defecto
        
        // Bind de parámetros
        $stmt->bindParam(1, $this->sender_id);
        $stmt->bindParam(2, $this->receiver_id);
        $stmt->bindParam(3, $this->message);
        $stmt->bindParam(4, $read_status);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Marcar un mensaje como leído
    public function markAsRead() {
        $query = "UPDATE " . $this->table_name . "
                  SET read_status = 1
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        return $stmt->execute();
    }
    
    // Marcar todos los mensajes enviados a un usuario como leídos
    public function markAllAsRead($user_id) {
        $query = "UPDATE " . $this->table_name . "
                  SET read_status = 1
                  WHERE receiver_id = ? AND read_status = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        
        return $stmt->execute();
    }
    
    // Obtener la cantidad de mensajes no leídos para un usuario
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as unread_count
                  FROM " . $this->table_name . "
                  WHERE receiver_id = ? AND read_status = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['unread_count'];
    }
    
    // Eliminar un mensaje
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        return $stmt->execute();
    }
}
?>
