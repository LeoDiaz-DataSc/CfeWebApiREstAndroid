<?php
require_once 'models/Report.php';
require_once 'utils/auth.php';
require_once 'utils/api_response.php';

class MapController {
    private $db;
    private $report;
    private $auth;
    
    public function __construct($db) {
        $this->db = $db;
        $this->report = new Report($db);
        $this->auth = new Auth();
    }
    
    /**
     * Obtener todas las ubicaciones de reportes para visualizar en mapa
     * Formato optimizado para mapas (GeoJSON)
     */
    public function getReportLocations() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Consultar todos los reportes con ubicación
        $stmt = $this->report->getAllWithLocation();
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Crear estructura GeoJSON
            $geojson = [
                "type" => "FeatureCollection",
                "features" => []
            ];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                // Solo incluir reportes con coordenadas válidas
                if (!empty($latitude) && !empty($longitude)) {
                    $feature = [
                        "type" => "Feature",
                        "geometry" => [
                            "type" => "Point",
                            "coordinates" => [(float)$longitude, (float)$latitude]
                        ],
                        "properties" => [
                            "id" => $id,
                            "title" => "{$grupo_nombre} - {$anomalia_nombre}",
                            "matricula" => $matricula,
                            "status" => $status,
                            "created_at" => $created_at,
                            "user_role" => $user_role
                        ]
                    ];
                    
                    $geojson["features"][] = $feature;
                }
            }
            
            ApiResponse::success("Ubicaciones de reportes encontradas", $geojson);
        } else {
            ApiResponse::success("No se encontraron reportes con ubicación", [
                "type" => "FeatureCollection",
                "features" => []
            ]);
        }
    }
    
    /**
     * Obtener reportes filtrados para el mapa
     * @param string $status Estado de los reportes a filtrar
     */
    public function getFilteredLocations($status = null) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Consultar reportes filtrados
        $stmt = null;
        if ($status) {
            $this->report->status = $status;
            $stmt = $this->report->getByStatus();
        } else {
            $stmt = $this->report->getAllWithLocation();
        }
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Crear estructura GeoJSON
            $geojson = [
                "type" => "FeatureCollection",
                "features" => []
            ];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                // Solo incluir reportes con coordenadas válidas
                if (!empty($latitude) && !empty($longitude)) {
                    $feature = [
                        "type" => "Feature",
                        "geometry" => [
                            "type" => "Point",
                            "coordinates" => [(float)$longitude, (float)$latitude]
                        ],
                        "properties" => [
                            "id" => $id,
                            "title" => "{$grupo_nombre} - {$anomalia_nombre}",
                            "matricula" => $matricula,
                            "status" => $status,
                            "created_at" => $created_at,
                            "user_role" => $user_role
                        ]
                    ];
                    
                    $geojson["features"][] = $feature;
                }
            }
            
            ApiResponse::success("Ubicaciones filtradas encontradas", $geojson);
        } else {
            ApiResponse::success("No se encontraron reportes con el filtro especificado", [
                "type" => "FeatureCollection",
                "features" => []
            ]);
        }
    }
}
?>
