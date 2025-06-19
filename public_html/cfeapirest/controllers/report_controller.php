<?php
require_once 'models/Report.php';
require_once 'utils/auth.php';
require_once 'utils/api_response.php';
require_once 'utils/validator.php';
require_once 'utils/upload_manager.php';

class ReportController {
    private $db;
    private $report;
    private $auth;
    private $upload_dir;
    
    public function __construct($db) {
        $this->db = $db;
        $this->report = new Report($db);
        $this->auth = new Auth();
        $this->upload_dir = Config::get('images_dir');
    }
    
    // Obtener todos los reportes (accesible para roles administrativos)
    public function getAllReports() {
        // Verificar autenticación (cualquier usuario puede ver reportes)
        $user_data = $this->auth->validateToken();
        
        // Consultar todos los reportes
        $stmt = $this->report->getAll();
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $reports_arr = array();
            $reports_arr["reports"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $report_item = array(
                    "id" => $id,
                    "matricula" => $matricula,
                    "grupo" => [
                        "id" => $grupo_id,
                        "name" => $grupo_nombre
                    ],
                    "anomalia" => [
                        "id" => $anomalia_id,
                        "name" => $anomalia_nombre
                    ],
                    "material" => [
                        "id" => $material_id,
                        "name" => $material_nombre
                    ],
                    "descripcion" => $descripcion,
                    "location" => [
                        "latitude" => $latitude,
                        "longitude" => $longitude
                    ],
                    "status" => $status,
                    "image_path" => $image_path,
                    "user" => [
                        "id" => $user_id,
                        "username" => $username,
                        "role" => $user_role
                    ],
                    "created_at" => $created_at,
                    "updated_at" => $updated_at
                );
                
                array_push($reports_arr["reports"], $report_item);
            }
            
            ApiResponse::success("Reportes encontrados", $reports_arr);
        } else {
            ApiResponse::success("No se encontraron reportes", ["reports" => []]);
        }
    }
    
    // Obtener reportes del usuario actual
    public function getMyReports() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Asignar ID de usuario y consultar sus reportes
        $this->report->user_id = $user_data->user_id;
        $stmt = $this->report->getByUserId();
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $reports_arr = array();
            $reports_arr["reports"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $report_item = array(
                    "id" => $id,
                    "matricula" => $matricula,
                    "grupo" => [
                        "id" => $grupo_id,
                        "name" => $grupo_nombre
                    ],
                    "anomalia" => [
                        "id" => $anomalia_id,
                        "name" => $anomalia_nombre
                    ],
                    "material" => [
                        "id" => $material_id,
                        "name" => $material_nombre
                    ],
                    "descripcion" => $descripcion,
                    "location" => [
                        "latitude" => $latitude,
                        "longitude" => $longitude
                    ],
                    "status" => $status,
                    "image_path" => $image_path,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at
                );
                
                array_push($reports_arr["reports"], $report_item);
            }
            
            ApiResponse::success("Mis reportes", $reports_arr);
        } else {
            ApiResponse::success("No tienes reportes", ["reports" => []]);
        }
    }
    
    // Obtener un reporte por ID
    public function getReportById($id) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Asignar ID y buscar el reporte
        $this->report->id = $id;
        
        if ($this->report->getById()) {
            // Verificar permisos: solo el creador o un administrador puede ver detalles completos
            if ($user_data->user_id != $this->report->user_id && $user_data->role != 'JEFE_AREA' && $user_data->role != 'SOBRESTANTE') {
                ApiResponse::forbidden("No tiene permisos para ver este reporte");
                return;
            }
            
            $report_arr = array(
                "id" => $this->report->id,
                "matricula" => $this->report->matricula,
                "grupo" => [
                    "id" => $this->report->grupo_id,
                    "name" => $this->report->grupo_nombre
                ],
                "anomalia" => [
                    "id" => $this->report->anomalia_id,
                    "name" => $this->report->anomalia_nombre
                ],
                "material" => [
                    "id" => $this->report->material_id,
                    "name" => $this->report->material_nombre
                ],
                "descripcion" => $this->report->descripcion,
                "location" => [
                    "latitude" => $this->report->latitude,
                    "longitude" => $this->report->longitude
                ],
                "status" => $this->report->status,
                "image_path" => $this->report->image_path,
                "user" => [
                    "id" => $this->report->user_id,
                    "username" => $this->report->username,
                    "role" => $this->report->user_role
                ],
                "created_at" => $this->report->created_at,
                "updated_at" => $this->report->updated_at,
                "images" => $this->report->images
            );
            
            ApiResponse::success("Reporte encontrado", $report_arr);
        } else {
            ApiResponse::notFound("Reporte no encontrado");
        }
    }
    
    // Crear un nuevo reporte
    public function createReport() {
        try {
            // Verificar autenticación
            $user_data = $this->auth->validateToken();
            
            // Verificar si hay archivo subido
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("No se ha subido una imagen válida");
            }
            
            // Validar tipo de archivo (solo imágenes)
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                throw new Exception("Tipo de archivo no permitido. Solo se permiten JPG, PNG y GIF.");
            }
            
            // Validar tamaño máximo (2MB)
            $max_size = 2 * 1024 * 1024; // 2MB
            if ($_FILES['image']['size'] > $max_size) {
                throw new Exception("El archivo es demasiado grande. Máximo permitido: 2MB.");
            }
            
            // Obtener datos del formulario
            $data = array(
                'matricula' => $_POST['matricula'] ?? '',
                'grupo_id' => $_POST['grupo_id'] ?? '',
                'anomalia_id' => $_POST['anomalia_id'] ?? '',
                'material_id' => $_POST['material_id'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'latitude' => $_POST['latitude'] ?? '',
                'longitude' => $_POST['longitude'] ?? ''
            );
            
            // Validar campos obligatorios
            $required = ['matricula', 'grupo_id', 'anomalia_id', 'material_id', 'descripcion', 'latitude', 'longitude'];
            $missing = Validator::required($data, $required);
            if (!empty($missing)) {
                throw new Exception("Faltan datos obligatorios: " . implode(', ', $missing));
            }
            
            // Validar lat/long
            if (!is_numeric($data['latitude']) || !is_numeric($data['longitude'])) {
                throw new Exception("Latitud y longitud deben ser numéricos");
            }
            
            // Preparar datos para el reporte
            $this->report->matricula = $data['matricula'];
            $this->report->grupo_id = $data['grupo_id'];
            $this->report->anomalia_id = $data['anomalia_id'];
            $this->report->material_id = $data['material_id'];
            $this->report->descripcion = $data['descripcion'];
            $this->report->latitude = $data['latitude'];
            $this->report->longitude = $data['longitude'];
            $this->report->status = 'PENDIENTE';
            $this->report->user_id = $user_data->user_id;
            
            // Subir la imagen
            $upload_manager = new UploadManager($this->upload_dir);
            $image_path = $upload_manager->uploadFile($_FILES['image'], 'reports');
            $this->report->image_path = $image_path;
            
            // Intentar crear el reporte
            if ($this->report->create()) {
                // Obtener el reporte recién creado
                $this->report->id = $this->db->lastInsertId();
                $this->report->getById();

                // Preparar la respuesta con el reporte completo
                $report_arr = array(
                    "id" => $this->report->id,
                    "matricula" => $this->report->matricula,
                    "grupo" => [
                        "id" => $this->report->grupo_id,
                        "name" => $this->report->grupo_nombre
                    ],
                    "anomalia" => [
                        "id" => $this->report->anomalia_id,
                        "name" => $this->report->anomalia_nombre
                    ],
                    "material" => [
                        "id" => $this->report->material_id,
                        "name" => $this->report->material_nombre
                    ],
                    "descripcion" => $this->report->descripcion,
                    "location" => [
                        "latitude" => $this->report->latitude,
                        "longitude" => $this->report->longitude
                    ],
                    "status" => $this->report->status,
                    "image_path" => $this->report->image_path,
                    "user" => [
                        "id" => $this->report->user_id,
                        "username" => $this->report->username,
                        "role" => $this->report->user_role
                    ],
                    "created_at" => $this->report->created_at,
                    "updated_at" => $this->report->updated_at
                );

                ApiResponse::success("Reporte creado exitosamente", $report_arr);
            } else {
                throw new Exception("No se pudo crear el reporte");
            }
        } catch (Exception $e) {
            // Registrar el error
            error_log("Error en createReport: " . $e->getMessage());
            
            // Formatear el mensaje de error
            $error_message = $e->getMessage();
            if (strpos($error_message, 'database error') !== false) {
                $error_message = "Error en la base de datos. Por favor, intenta de nuevo.";
            }
            
            ApiResponse::error($error_message, 500);
        }
    }
    
    // Actualizar un reporte
    public function updateReport($id) {
        try {
            // Verificar autenticación
            $user_data = $this->auth->validateToken();
            
            // Asignar ID y buscar el reporte
            $this->report->id = $id;
            
            if (!$this->report->getById()) {
                throw new Exception("Reporte no encontrado");
            }
            
            // Verificar permisos: solo el creador o un administrador puede actualizar
            if ($user_data->user_id != $this->report->user_id && $user_data->role != 'JEFE_AREA' && $user_data->role != 'SOBRESTANTE') {
                throw new Exception("No tiene permisos para actualizar este reporte");
            }
            
            // Obtener datos enviados
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!$data) {
                throw new Exception("Datos JSON inválidos");
            }
            
            // Validaciones opcionales si campos vienen
            if (isset($data['latitude']) && !is_numeric($data['latitude'])) {
                throw new Exception("Latitud inválida");
            }
            if (isset($data['longitude']) && !is_numeric($data['longitude'])) {
                throw new Exception("Longitud inválida");
            }
            if (isset($data['descripcion']) && !Validator::maxLength($data['descripcion'], 255)) {
                throw new Exception("La descripción supera el máximo de 255 caracteres");
            }
            
            // Actualizar campos
            $this->report->matricula = $data['matricula'] ?? $this->report->matricula;
            $this->report->grupo_id = $data['grupo_id'] ?? $this->report->grupo_id;
            $this->report->anomalia_id = $data['anomalia_id'] ?? $this->report->anomalia_id;
            $this->report->material_id = $data['material_id'] ?? $this->report->material_id;
            $this->report->descripcion = $data['descripcion'] ?? $this->report->descripcion;
            $this->report->latitude = $data['latitude'] ?? $this->report->latitude;
            $this->report->longitude = $data['longitude'] ?? $this->report->longitude;
            $this->report->status = $data['status'] ?? $this->report->status;
            $this->report->image_path = $data['image_path'] ?? $this->report->image_path;
            
            // Intentar actualizar el reporte
            if ($this->report->update()) {
                ApiResponse::success("Reporte actualizado exitosamente");
            } else {
                throw new Exception("No se pudo actualizar el reporte");
            }
        } catch (Exception $e) {
            // Registrar el error
            error_log("Error en updateReport: " . $e->getMessage());
            
            // Formatear el mensaje de error
            $error_message = $e->getMessage();
            if (strpos($error_message, 'database error') !== false) {
                $error_message = "Error en la base de datos. Por favor, intenta de nuevo.";
            }
            
            ApiResponse::error($error_message, 500);
        $this->report->material_id = $data['material_id'] ?? $this->report->material_id;
        $this->report->descripcion = $data['descripcion'] ?? $this->report->descripcion;
        $this->report->latitude = $data['latitude'] ?? $this->report->latitude;
        $this->report->longitude = $data['longitude'] ?? $this->report->longitude;
        $this->report->status = $data['status'] ?? $this->report->status;
        $this->report->image_path = $data['image_path'] ?? $this->report->image_path;
        
        // Intentar actualizar el reporte
        if ($this->report->update()) {
            ApiResponse::success("Reporte actualizado exitosamente");
        } else {
            ApiResponse::error("No se pudo actualizar el reporte", 500);
        }
    }
    
    // Eliminar un reporte
    public function deleteReport($id) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Asignar ID y buscar el reporte
        $this->report->id = $id;
        
        if (!$this->report->getById()) {
            ApiResponse::notFound("Reporte no encontrado");
            return;
        }
        
        // Verificar permisos: solo el creador o un administrador puede eliminar
        if ($user_data->user_id != $this->report->user_id && $user_data->role != 'JEFE_AREA') {
            ApiResponse::forbidden("No tiene permisos para eliminar este reporte");
            return;
        }
        
        // Intentar eliminar el reporte y sus imágenes
        if ($this->report->delete()) {
            // Si hay imágenes asociadas, eliminarlas del sistema de archivos
            foreach ($this->report->images as $image) {
                $image_path = $this->upload_dir . basename($image['image_path']);
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
                
                if (!empty($image['thumbnail_path'])) {
                    $thumbnail_path = $this->upload_dir . basename($image['thumbnail_path']);
                    if (file_exists($thumbnail_path)) {
                        unlink($thumbnail_path);
                    }
                }
            }
            
            ApiResponse::success("Reporte eliminado exitosamente");
        } else {
            ApiResponse::error("No se pudo eliminar el reporte", 500);
        }
    }
    
    // Subir imagen para un reporte
    public function uploadImage($report_id) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Asignar ID y buscar el reporte
        $this->report->id = $report_id;
        
        if (!$this->report->getById()) {
            ApiResponse::notFound("Reporte no encontrado");
            return;
        }
        
        // Verificar permisos: solo el creador o un administrador puede subir imágenes
        if ($user_data->user_id != $this->report->user_id && $user_data->role != 'JEFE_AREA' && $user_data->role != 'SOBRESTANTE') {
            ApiResponse::forbidden("No tiene permisos para subir imágenes a este reporte");
            return;
        }
        
        // Usar UploadManager
        $result = UploadManager::upload($_FILES['image'] ?? null, $this->upload_dir);
        if (!$result['success']) {
            ApiResponse::error($result['error'], 400);
            return;
        }
        
        $filename = $result['filename'];
        
        // Actualizar imagen principal si no existe
        if (empty($this->report->image_path)) {
            $this->report->image_path = $filename;
            $this->report->update();
        }
        
        // Guardar en BD
        $image_id = $this->report->addImage($filename);
        if ($image_id) {
            ApiResponse::success("Imagen subida exitosamente", [
                "image_id" => $image_id,
                "filename" => $filename,
                "path" => "uploads/images/" . $filename
            ]);
        } else {
            ApiResponse::error("Error al guardar la información de la imagen", 500);
        }
    }
}
?>
