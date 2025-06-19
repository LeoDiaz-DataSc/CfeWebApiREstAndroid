<?php
class Catalog {
    // Conexión a la base de datos
    private $conn;
    
    // Constructor con la conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Obtener todos los grupos
    public function getAllGroups() {
        $query = "SELECT * FROM report_groups WHERE active = 1 ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener anomalías por grupo
    public function getAnomaliesByGroup($group_id) {
        $query = "SELECT * FROM anomalies 
                  WHERE group_id = ? AND active = 1 
                  ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $group_id);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener materiales por grupo
    public function getMaterialsByGroup($group_id) {
        $query = "SELECT * FROM materials 
                  WHERE group_id = ? AND active = 1 
                  ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $group_id);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener grupo por ID
    public function getGroupById($id) {
        $query = "SELECT * FROM report_groups WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener grupo por nombre
    public function getGroupByName($name) {
        $query = "SELECT * FROM report_groups WHERE name = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $name);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener anomalía por ID
    public function getAnomalyById($id) {
        $query = "SELECT a.*, g.name as group_name 
                  FROM anomalies a
                  LEFT JOIN report_groups g ON a.group_id = g.id
                  WHERE a.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener anomalía por nombre y grupo
    public function getAnomalyByNameAndGroup($name, $group_id) {
        $query = "SELECT * FROM anomalies WHERE name = ? AND group_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $name);
        $stmt->bindParam(2, $group_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener material por ID
    public function getMaterialById($id) {
        $query = "SELECT m.*, g.name as group_name 
                  FROM materials m
                  LEFT JOIN report_groups g ON m.group_id = g.id
                  WHERE m.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener material por nombre y grupo
    public function getMaterialByNameAndGroup($name, $group_id) {
        $query = "SELECT * FROM materials WHERE name = ? AND group_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $name);
        $stmt->bindParam(2, $group_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
