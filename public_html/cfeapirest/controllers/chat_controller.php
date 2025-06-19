<?php
require_once 'models/Chat.php';
require_once 'models/User.php';
require_once 'utils/auth.php';
require_once 'utils/api_response.php';
require_once 'utils/validator.php';

class ChatController {
    private $db;
    private $chat;
    private $user;
    private $auth;
    
    public function __construct($db) {
        $this->db = $db;
        $this->chat = new Chat($db);
        $this->user = new User($db);
        $this->auth = new Auth();
    }
    
    // Obtener todos los mensajes del usuario actual
    public function getMyMessages() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Consultar mensajes
        $stmt = $this->chat->getMessages($user_data->user_id);
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $messages_arr = array();
            $messages_arr["messages"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $message_item = array(
                    "id" => $id,
                    "sender" => [
                        "id" => $sender_id,
                        "username" => $sender_username
                    ],
                    "receiver" => $receiver_id ? [
                        "id" => $receiver_id,
                        "username" => $receiver_username
                    ] : null,
                    "message" => $message,
                    "read_status" => $read_status,
                    "created_at" => $created_at,
                    "is_mine" => ($sender_id == $user_data->user_id)
                );
                
                array_push($messages_arr["messages"], $message_item);
            }
            
            // Marcar todos los mensajes como leídos
            $this->chat->markAllAsRead($user_data->user_id);
            
            ApiResponse::success("Mensajes encontrados", $messages_arr);
        } else {
            ApiResponse::success("No tienes mensajes", ["messages" => []]);
        }
    }
    
    // Obtener mensajes entre el usuario actual y otro usuario
    public function getMessagesWithUser($other_user_id) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Verificar que el otro usuario exista
        $this->user->id = $other_user_id;
        if (!$this->user->getById()) {
            ApiResponse::notFound("Usuario no encontrado");
            return;
        }
        
        // Consultar mensajes entre los dos usuarios
        $stmt = $this->chat->getMessages($user_data->user_id, $other_user_id);
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $messages_arr = array();
            $messages_arr["messages"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $message_item = array(
                    "id" => $id,
                    "sender" => [
                        "id" => $sender_id,
                        "username" => $sender_username
                    ],
                    "receiver" => [
                        "id" => $receiver_id,
                        "username" => $receiver_username
                    ],
                    "message" => $message,
                    "read_status" => $read_status,
                    "created_at" => $created_at,
                    "is_mine" => ($sender_id == $user_data->user_id)
                );
                
                array_push($messages_arr["messages"], $message_item);
            }
            
            // Marcar todos los mensajes recibidos como leídos
            $this->chat->markAllAsRead($user_data->user_id);
            
            ApiResponse::success("Mensajes con usuario", $messages_arr);
        } else {
            ApiResponse::success("No hay mensajes con este usuario", ["messages" => []]);
        }
    }
    
    // Obtener mensajes globales
    public function getGlobalMessages() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Consultar mensajes globales
        $stmt = $this->chat->getGlobalMessages();
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $messages_arr = array();
            $messages_arr["messages"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $message_item = array(
                    "id" => $id,
                    "sender" => [
                        "id" => $sender_id,
                        "username" => $sender_username
                    ],
                    "message" => $message,
                    "created_at" => $created_at,
                    "is_mine" => ($sender_id == $user_data->user_id)
                );
                
                array_push($messages_arr["messages"], $message_item);
            }
            
            ApiResponse::success("Mensajes globales", $messages_arr);
        } else {
            ApiResponse::success("No hay mensajes globales", ["messages" => []]);
        }
    }
    
    // Enviar un mensaje
    public function sendMessage() {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Obtener datos enviados como array
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            ApiResponse::error("Datos JSON inválidos", 400);
            return;
        }
        
        // Validar mensaje obligatorio y longitud
        $missing = Validator::required($data, ['message']);
        if (!empty($missing)) {
            ApiResponse::error("El mensaje es obligatorio", 400);
            return;
        }
        if (!Validator::maxLength($data['message'], 500)) {
            ApiResponse::error("El mensaje excede el máximo de 500 caracteres", 400);
            return;
        }
        
        // Asignar valores
        $this->chat->sender_id = $user_data->user_id;
        $this->chat->message = $data['message'];
        
        // Si se especifica un receptor, verificar que exista
        if (isset($data['receiver_id']) && !empty($data['receiver_id'])) {
            if (!is_numeric($data['receiver_id'])) {
                ApiResponse::error("ID de receptor inválido", 400);
                return;
            }
            $this->user->id = $data['receiver_id'];
            if (!$this->user->getById()) {
                ApiResponse::notFound("Usuario receptor no encontrado");
                return;
            }
            $this->chat->receiver_id = $data['receiver_id'];
        } else {
            $this->chat->receiver_id = null; // Mensaje global
        }
        
        // Intentar crear el mensaje
        if ($this->chat->create()) {
            ApiResponse::success("Mensaje enviado exitosamente", [
                "message_id" => $this->chat->id
            ]);
        } else {
            ApiResponse::error("No se pudo enviar el mensaje", 500);
        }
    }
    
    // Marcar un mensaje como leído
    public function markAsRead($message_id) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Asignar ID al mensaje
        $this->chat->id = $message_id;
        
        // Marcar como leído
        if ($this->chat->markAsRead()) {
            ApiResponse::success("Mensaje marcado como leído");
        } else {
            ApiResponse::error("No se pudo marcar el mensaje como leído", 500);
        }
    }
    
    // Eliminar un mensaje
    public function deleteMessage($message_id) {
        // Verificar autenticación
        $user_data = $this->auth->validateToken();
        
        // Asignar ID al mensaje
        $this->chat->id = $message_id;
        
        // Intentar eliminar el mensaje
        if ($this->chat->delete()) {
            ApiResponse::success("Mensaje eliminado exitosamente");
        } else {
            ApiResponse::error("No se pudo eliminar el mensaje", 500);
        }
    }
}
?>
