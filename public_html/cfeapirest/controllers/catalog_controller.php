<?php
require_once 'models/Catalog.php';
require_once 'utils/auth.php';
require_once 'utils/api_response.php';
require_once 'utils/validator.php';

class CatalogController {
    private $db;
    private $catalog;
    private $auth;
    
    public function __construct($db) {
        $this->db = $db;
        $this->catalog = new Catalog($db);
        $this->auth = new Auth();
    }
    
    // Obtener todos los grupos
    public function getAllGroups() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Consultar todos los grupos
        $stmt = $this->catalog->getAllGroups();
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $groups_arr = array();
            $groups_arr["groups"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $group_item = array(
                    "id" => $id,
                    "name" => $name,
                    "description" => $description
                );
                
                array_push($groups_arr["groups"], $group_item);
            }
            
            ApiResponse::success("Grupos encontrados", $groups_arr);
        } else {
            ApiResponse::success("No se encontraron grupos", ["groups" => []]);
        }
    }
    
    // Obtener anomalías por grupo
    public function getAnomaliesByGroup($group_id) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        if (!is_numeric($group_id)) {
            ApiResponse::error("ID de grupo inválido", 400);
            return;
        }
        
        // Consultar anomalías por grupo
        $stmt = $this->catalog->getAnomaliesByGroup($group_id);
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $anomalies_arr = array();
            $anomalies_arr["anomalies"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $anomaly_item = array(
                    "id" => $id,
                    "name" => $name,
                    "group_id" => $group_id,
                    "description" => $description
                );
                
                array_push($anomalies_arr["anomalies"], $anomaly_item);
            }
            
            ApiResponse::success("Anomalías encontradas", $anomalies_arr);
        } else {
            ApiResponse::success("No se encontraron anomalías para este grupo", ["anomalies" => []]);
        }
    }
    
    // Obtener materiales por grupo
    public function getMaterialsByGroup($group_id) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        if (!is_numeric($group_id)) {
            ApiResponse::error("ID de grupo inválido", 400);
            return;
        }
        
        // Consultar materiales por grupo
        $stmt = $this->catalog->getMaterialsByGroup($group_id);
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $materials_arr = array();
            $materials_arr["materials"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $material_item = array(
                    "id" => $id,
                    "name" => $name,
                    "group_id" => $group_id,
                    "description" => $description
                );
                
                array_push($materials_arr["materials"], $material_item);
            }
            
            ApiResponse::success("Materiales encontrados", $materials_arr);
        } else {
            ApiResponse::success("No se encontraron materiales para este grupo", ["materials" => []]);
        }
    }
    
    // Obtener grupo por nombre (util para la aplicación Android)
    public function getGroupByName() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Obtener nombre de grupo
        $name = isset($_GET['name']) ? $_GET['name'] : '';
        
        $missing = Validator::required(['name' => $name], ['name']);
        if (!empty($missing)) {
            ApiResponse::error("Se requiere el parámetro name", 400);
            return;
        }
        
        // Buscar grupo por nombre
        $group = $this->catalog->getGroupByName($name);
        
        if ($group) {
            ApiResponse::success("Grupo encontrado", $group);
        } else {
            ApiResponse::notFound("Grupo no encontrado");
        }
    }
    
    // Obtener anomalía por nombre y grupo (util para la aplicación Android)
    public function getAnomalyByNameAndGroup() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Obtener parámetros
        $name = isset($_GET['name']) ? $_GET['name'] : '';
        $group_id = isset($_GET['group_id']) ? $_GET['group_id'] : '';
        
        $missing = Validator::required(['name' => $name, 'group_id' => $group_id], ['name', 'group_id']);
        if (!empty($missing)) {
            ApiResponse::error("Se requieren los parámetros name y group_id", 400);
            return;
        }
        if (!is_numeric($group_id)) {
            ApiResponse::error("ID de grupo inválido", 400);
            return;
        }
        
        // Buscar anomalía por nombre y grupo
        $anomaly = $this->catalog->getAnomalyByNameAndGroup($name, $group_id);
        
        if ($anomaly) {
            ApiResponse::success("Anomalía encontrada", $anomaly);
        } else {
            ApiResponse::notFound("Anomalía no encontrada");
        }
    }
    
    // Obtener material por nombre y grupo (util para la aplicación Android)
    public function getMaterialByNameAndGroup() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Obtener parámetros
        $name = isset($_GET['name']) ? $_GET['name'] : '';
        $group_id = isset($_GET['group_id']) ? $_GET['group_id'] : '';
        
        $missing = Validator::required(['name' => $name, 'group_id' => $group_id], ['name', 'group_id']);
        if (!empty($missing)) {
            ApiResponse::error("Se requieren los parámetros name y group_id", 400);
            return;
        }
        if (!is_numeric($group_id)) {
            ApiResponse::error("ID de grupo inválido", 400);
            return;
        }
        
        // Buscar material por nombre y grupo
        $material = $this->catalog->getMaterialByNameAndGroup($name, $group_id);
        
        if ($material) {
            ApiResponse::success("Material encontrado", $material);
        } else {
            ApiResponse::notFound("Material no encontrado");
        }
    }
}
?>
