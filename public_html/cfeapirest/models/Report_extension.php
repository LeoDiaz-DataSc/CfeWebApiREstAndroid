<?php
// Añadimos estos métodos a la clase Report existente
// Copiar y agregar en el archivo models/Report.php

    /**
     * Obtener todos los reportes con ubicación válida para el mapa
     */
    public function getAllWithLocation() {
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
                    r.latitude IS NOT NULL 
                    AND r.longitude IS NOT NULL
                ORDER BY 
                    r.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener reportes por estado
     */
    public function getByStatus() {
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
                    r.status = ?
                    AND r.latitude IS NOT NULL 
                    AND r.longitude IS NOT NULL
                ORDER BY 
                    r.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->status);
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener reportes para exportación con formato específico
     */
    public function getForExport($start_date = null, $end_date = null, $status = null) {
        $conditions = [];
        $params = [];
        
        // Construir condiciones de filtrado
        if ($start_date && $end_date) {
            $conditions[] = "r.created_at BETWEEN ? AND ?";
            $params[] = $start_date . " 00:00:00";
            $params[] = $end_date . " 23:59:59";
        }
        
        if ($status) {
            $conditions[] = "r.status = ?";
            $params[] = $status;
        }
        
        $where_clause = "";
        if (!empty($conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }
        
        $query = "SELECT 
                    r.id,
                    r.matricula,
                    g.name as grupo,
                    a.name as anomalia,
                    m.name as material,
                    r.descripcion,
                    r.latitude,
                    r.longitude,
                    r.status,
                    u.username as creado_por,
                    ro.name as rol_usuario,
                    r.created_at,
                    r.updated_at
                FROM 
                    " . $this->table_name . " r
                    LEFT JOIN report_groups g ON r.grupo_id = g.id
                    LEFT JOIN anomalies a ON r.anomalia_id = a.id
                    LEFT JOIN materials m ON r.material_id = m.id
                    LEFT JOIN users u ON r.user_id = u.id
                    LEFT JOIN roles ro ON u.role_id = ro.id
                " . $where_clause . "
                ORDER BY 
                    r.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        
        // Enlazar parámetros si hay filtros
        for ($i = 0; $i < count($params); $i++) {
            $stmt->bindParam($i + 1, $params[$i]);
        }
        
        $stmt->execute();
        
        return $stmt;
    }
?>
