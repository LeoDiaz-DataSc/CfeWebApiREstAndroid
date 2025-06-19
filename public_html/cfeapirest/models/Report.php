<?php
class Report {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "reports";
    
    // Propiedades del objeto
    public $id;
    public $matricula;
    public $grupo_id;
    public $anomalia_id;
    public $material_id;
    public $descripcion;
    public $latitude;
    public $longitude;
    public $status;
    public $image_path;
    public $user_id;
    public $created_at;
    public $updated_at;
    
    // Propiedades extendidas para joins
    public $grupo_nombre;
    public $anomalia_nombre;
    public $material_nombre;
    public $username;
    public $user_role;
    public $images = [];
    
    // Constructor con la conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Obtener todos los reportes con información relacionada
    public function getAll() {
        $query = "SELECT 
                    r.*, 
                    g.name as grupo_nombre,
                    a.name as anomalia_nombre,
                    m.name as material_nombre,
                    u.username,
                    ro.name as user_role
                FROM 
                    " . $this->table_name . " r
                    LEFT JOIN report_groups g ON r.grupo_id = g.id
                    LEFT JOIN anomalies a ON r.anomalia_id = a.id
                    LEFT JOIN materials m ON r.material_id = m.id
                    LEFT JOIN users u ON r.user_id = u.id
                    LEFT JOIN roles ro ON u.role_id = ro.id
                ORDER BY 
                    r.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener reportes de un usuario específico
    public function getByUserId() {
        $query = "SELECT 
                    r.*, 
                    g.name as grupo_nombre,
                    a.name as anomalia_nombre,
                    m.name as material_nombre,
                    u.username,
                    ro.name as user_role
                FROM 
                    " . $this->table_name . " r
                    LEFT JOIN report_groups g ON r.grupo_id = g.id
                    LEFT JOIN anomalies a ON r.anomalia_id = a.id
                    LEFT JOIN materials m ON r.material_id = m.id
                    LEFT JOIN users u ON r.user_id = u.id
                    LEFT JOIN roles ro ON u.role_id = ro.id
                WHERE 
                    r.user_id = ?
                ORDER BY 
                    r.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->user_id);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener un reporte por ID
    public function getById() {
        $query = "SELECT 
                    r.*, 
                    g.name as grupo_nombre,
                    a.name as anomalia_nombre,
                    m.name as material_nombre,
                    u.username,
                    ro.name as user_role
                FROM 
                    " . $this->table_name . " r
                    LEFT JOIN report_groups g ON r.grupo_id = g.id
                    LEFT JOIN anomalies a ON r.anomalia_id = a.id
                    LEFT JOIN materials m ON r.material_id = m.id
                    LEFT JOIN users u ON r.user_id = u.id
                    LEFT JOIN roles ro ON u.role_id = ro.id
                WHERE 
                    r.id = ?
                LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Establecer valores
            $this->id = $row['id'];
            $this->matricula = $row['matricula'];
            $this->grupo_id = $row['grupo_id'];
            $this->anomalia_id = $row['anomalia_id'];
            $this->material_id = $row['material_id'];
            $this->descripcion = $row['descripcion'];
            $this->latitude = $row['latitude'];
            $this->longitude = $row['longitude'];
            $this->status = $row['status'];
            $this->image_path = $row['image_path'];
            $this->user_id = $row['user_id'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            // Valores de relaciones
            $this->grupo_nombre = $row['grupo_nombre'];
            $this->anomalia_nombre = $row['anomalia_nombre'];
            $this->material_nombre = $row['material_nombre'];
            $this->username = $row['username'];
            $this->user_role = $row['user_role'];
            
            // Obtener imágenes relacionadas
            $this->getImages();
            
            return true;
        }
        
        return false;
    }
    
    // Obtener imágenes relacionadas con el reporte
    public function getImages() {
        $query = "SELECT * FROM report_images 
                  WHERE report_id = ? 
                  ORDER BY created_at";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $this->images = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->images[] = $row;
        }
    }
    
    // Crear un nuevo reporte
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (matricula, grupo_id, anomalia_id, material_id, descripcion, 
                   latitude, longitude, status, image_path, user_id)
                  VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->matricula = htmlspecialchars(strip_tags($this->matricula));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->image_path = htmlspecialchars(strip_tags($this->image_path));
        
        // Valores por defecto
        if (empty($this->status)) {
            $this->status = "Pendiente";
        }
        
        // Bind de parámetros
        $stmt->bindParam(1, $this->matricula);
        $stmt->bindParam(2, $this->grupo_id);
        $stmt->bindParam(3, $this->anomalia_id);
        $stmt->bindParam(4, $this->material_id);
        $stmt->bindParam(5, $this->descripcion);
        $stmt->bindParam(6, $this->latitude);
        $stmt->bindParam(7, $this->longitude);
        $stmt->bindParam(8, $this->status);
        $stmt->bindParam(9, $this->image_path);
        $stmt->bindParam(10, $this->user_id);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Actualizar un reporte existente
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    matricula = ?,
                    grupo_id = ?,
                    anomalia_id = ?,
                    material_id = ?,
                    descripcion = ?,
                    latitude = ?,
                    longitude = ?,
                    status = ?,
                    image_path = ?
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->matricula = htmlspecialchars(strip_tags($this->matricula));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->image_path = htmlspecialchars(strip_tags($this->image_path));
        
        // Bind de parámetros
        $stmt->bindParam(1, $this->matricula);
        $stmt->bindParam(2, $this->grupo_id);
        $stmt->bindParam(3, $this->anomalia_id);
        $stmt->bindParam(4, $this->material_id);
        $stmt->bindParam(5, $this->descripcion);
        $stmt->bindParam(6, $this->latitude);
        $stmt->bindParam(7, $this->longitude);
        $stmt->bindParam(8, $this->status);
        $stmt->bindParam(9, $this->image_path);
        $stmt->bindParam(10, $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Actualizar solo el status de un reporte
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . "
                SET status = ?
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        // Bind de parámetros
        $stmt->bindParam(1, $this->status);
        $stmt->bindParam(2, $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Eliminar un reporte
    public function delete() {
        // Primero, eliminar todas las imágenes relacionadas
        $query = "DELETE FROM report_images WHERE report_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        // Luego, eliminar el reporte
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Agregar una imagen al reporte
    public function addImage($image_path, $thumbnail_path = null) {
        $query = "INSERT INTO report_images (report_id, image_path, thumbnail_path)
                  VALUES (?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $image_path = htmlspecialchars(strip_tags($image_path));
        if ($thumbnail_path) {
            $thumbnail_path = htmlspecialchars(strip_tags($thumbnail_path));
        }
        
        // Bind de parámetros
        $stmt->bindParam(1, $this->id);
        $stmt->bindParam(2, $image_path);
        $stmt->bindParam(3, $thumbnail_path);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Eliminar una imagen del reporte
    public function deleteImage($image_id) {
        $query = "DELETE FROM report_images 
                  WHERE id = ? AND report_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $image_id);
        $stmt->bindParam(2, $this->id);
        
        return $stmt->execute();
    }
}
?>
