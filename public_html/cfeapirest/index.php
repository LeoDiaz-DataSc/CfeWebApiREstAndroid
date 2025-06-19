<?php
// Incluir archivo de configuración
require_once 'config/config.php';

// Configurar manejo de errores según el entorno
if (Config::get('show_errors')) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}

// Iniciar registro de tiempo de ejecución
$start_time = microtime(true);

// Configurar headers CORS según el entorno
$allowed_origins = Config::get('cors_allowed_origins');

// En desarrollo permitir todos los orígenes, en producción solo los configurados
if (in_array('*', $allowed_origins) || Config::isDevelopment()) {
    header("Access-Control-Allow-Origin: *");
} else {
    // Verificar si el origen está permitido
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (in_array($origin, $allowed_origins)) {
}
    
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Expose-Headers: Content-Length, X-JSON");

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Max-Age: 3600");
    header("Content-Length: 0");
    exit(0);
}

//Victoria de dios, no nuestra
// Definir variables para el enrutamiento
$controller = isset($_GET['controller']) ? $_GET['controller'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Incluir archivos necesarios
include_once 'config/database.php';
include_once 'utils/api_response.php';

// Iniciar sistema de logs usando utilidad centralizada
include_once 'utils/logger.php';

// Obtener el método de la solicitud
$request_method = $_SERVER["REQUEST_METHOD"];

// Crear conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar límite de tasa (rate limiting) en producción
if (Config::get('rate_limiting') && Config::isProduction()) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $rate_key = "rate_limit:{$client_ip}";
    
    // En una implementación real, aquí se usaría Redis o una base de datos
    // para almacenar y verificar los límites de tasa
    // Por ahora, simplemente registramos la intención
    api_log('debug', "Rate limiting check for IP: {$client_ip}");
}

// Registrar solicitud en el log
api_log('info', 'API Request', [
    'controller' => $controller,
    'action' => $action,
    'method' => $request_method,
    'id' => $id
]);

// Determinar qué controlador usar según la solicitud
switch ($controller) {
    case 'auth':
        include_once 'controllers/auth_controller.php';
        $auth_controller = new AuthController($db);
        
        switch ($action) {
            case 'login':
                $auth_controller->login();
                break;
            case 'register':
                $auth_controller->register();
                break;
            default:
                ApiResponse::notFound("Acción no encontrada");
        }
        break;
    
    case 'users':
        include_once 'controllers/user_controller.php';
        $user_controller = new UserController($db);
        
        switch ($action) {
            case '':
                if ($request_method == 'GET') {
                    $user_controller->getAllUsers();
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            case 'profile':
                if ($request_method == 'GET') {
                    $user_controller->getUserProfile();
                } else if ($request_method == 'PUT') {
                    $user_controller->updateUserProfile();
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            default:
                if (is_numeric($action)) {
                    $id = $action;
                    
                    if ($request_method == 'GET') {
                        $user_controller->getUserById($id);
                    } else if ($request_method == 'PUT') {
                        $user_controller->updateUser($id);
                    } else if ($request_method == 'DELETE') {
                        $user_controller->deleteUser($id);
                    } else {
                        ApiResponse::error("Método no permitido", 405);
                    }
                } else {
                    ApiResponse::notFound("Acción no encontrada");
                }
        }
        break;
    
    case 'reports':
        include_once 'controllers/report_controller.php';
        $report_controller = new ReportController($db);
        
        switch ($action) {
            case '':
                if ($request_method == 'GET') {
                    $report_controller->getAllReports();
                } else if ($request_method == 'POST') {
                    $report_controller->createReport();
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            case 'my':
                if ($request_method == 'GET') {
                    $report_controller->getMyReports();
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            case 'images':
                if ($request_method == 'POST') {
                    $report_controller->uploadImage($id);
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            default:
                if (is_numeric($action)) {
                    $id = $action;
                    
                    if ($request_method == 'GET') {
                        $report_controller->getReportById($id);
                    } else if ($request_method == 'PUT') {
                        $report_controller->updateReport($id);
                    } else if ($request_method == 'DELETE') {
                        $report_controller->deleteReport($id);
                    } else {
                        ApiResponse::error("Método no permitido", 405);
                    }
                } else if ($action == 'upload-image' && $id) {
                    if ($request_method == 'POST') {
                        $report_controller->uploadReportImage($id);
                    } else {
                        ApiResponse::error("Método no permitido", 405);
                    }
                } else {
                    ApiResponse::notFound("Acción no encontrada");
                }
        }
        break;
    
    case 'map':
        include_once 'controllers/map_controller.php';
        // Asegurarnos de incluir las extensiones del modelo Report
        include_once 'models/Report.php';
        include_once 'models/Report_extension.php';
        
        $map_controller = new MapController($db);
        
        switch ($action) {
            case '':
            case 'locations':
                if ($request_method == 'GET') {
                    $map_controller->getReportLocations();
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            case 'filter':
                if ($request_method == 'GET') {
                    $status = isset($_GET['status']) ? $_GET['status'] : null;
                    $map_controller->getFilteredLocations($status);
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            default:
                ApiResponse::notFound("Acción no encontrada");
        }
        break;
    
    case 'export':
        include_once 'controllers/export_controller.php';
        // Asegurarnos de incluir las extensiones del modelo Report
        include_once 'models/Report.php';
        include_once 'models/Report_extension.php';
        
        $export_controller = new ExportController($db);
        
        switch ($action) {
            case 'reports':
            case 'xlsx':
                if ($request_method == 'GET') {
                    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
                    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
                    $status = isset($_GET['status']) ? $_GET['status'] : null;
                    $export_controller->exportReportsToXlsx($start_date, $end_date, $status);
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            default:
                ApiResponse::notFound("Acción no encontrada");
        }
        break;
    
    case 'catalog':
        include_once 'controllers/catalog_controller.php';
        $catalog_controller = new CatalogController($db);
        
        switch ($action) {
            case 'groups':
                if ($request_method == 'GET') {
                    $catalog_controller->getAllGroups();
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            case 'anomalies':
                if ($request_method == 'GET') {
                    $group_id = isset($_GET['group_id']) ? $_GET['group_id'] : null;
                    if ($group_id) {
                        $catalog_controller->getAnomaliesByGroup($group_id);
                    } else {
                        $catalog_controller->getAllAnomalies();
                    }
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            case 'materials':
                if ($request_method == 'GET') {
                    $group_id = isset($_GET['group_id']) ? $_GET['group_id'] : null;
                    if ($group_id) {
                        $catalog_controller->getMaterialsByGroup($group_id);
                    } else {
                        $catalog_controller->getAllMaterials();
                    }
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            default:
                ApiResponse::notFound("Acción no encontrada");
        }
        break;
    
    case 'chat':
        include_once 'controllers/chat_controller.php';
        $chat_controller = new ChatController($db);
        
        switch ($action) {
            case '':
                if ($request_method == 'GET') {
                    $chat_controller->getAllMessages();
                } else if ($request_method == 'POST') {
                    $chat_controller->sendMessage();
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            case 'global':
                if ($request_method == 'GET') {
                    $chat_controller->getGlobalMessages();
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            case 'read':
                if ($request_method == 'PUT') {
                    $chat_controller->markAsRead($id);
                } else {
                    ApiResponse::error("Método no permitido", 405);
                }
                break;
            default:
                if (is_numeric($action)) {
                    $id = $action;
                    
                    if ($request_method == 'GET') {
                        $chat_controller->getMessagesWithUser($id);
                    } else if ($request_method == 'DELETE') {
                        $chat_controller->deleteMessage($id);
                    } else {
                        ApiResponse::error("Método no permitido", 405);
                    }
                } else {
                    ApiResponse::notFound("Acción no encontrada");
                }
        }
        break;
    
    default:
        // Si no se especifica el controlador, mostrar información de la API
        echo json_encode(array(
            "status" => "success",
            "message" => "API CFE",
            "endpoints" => array(
                "auth"    => "/index.php?controller=auth&action=login",
                "users"   => "/index.php?controller=users",
                "reports" => "/index.php?controller=reports",
                "catalog" => "/index.php?controller=catalog&action=groups",
                "chat"    => "/index.php?controller=chat"
            )
        ));
}
?>
