<?php
require_once dirname(__DIR__) . '/config/config.php';

// Clase para manejar JWT (JSON Web Tokens)
class JwtHandler {
    private $secret_key;
    private $algorithm = 'HS256';
    private $token_expiry;
    private $environment;
    
    public function __construct() {
        // Usar la configuración centralizada
        $this->secret_key = Config::get('jwt_secret');
        $this->token_expiry = Config::get('jwt_expiration');
        $this->environment = Config::getEnvironment();
    }

    // Generar un token JWT
    public function generateToken($user_id, $username, $role) {
        $issued_at = time();
        $expiration_time = $issued_at + $this->token_expiry;

        $payload = array(
            "iat" => $issued_at,
            "exp" => $expiration_time,
            "user_id" => $user_id,
            "username" => $username,
            "role" => $role
        );

        $jwt = $this->encode($payload);
        
        return $jwt;
    }

    // Verificar si un token es válido
    public function validateToken($jwt) {
        try {
            $decoded = $this->decode($jwt);
            
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return false; // Token expirado
            }
            
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }

    // Codificar un payload en formato JWT
    private function encode($payload) {
        $header = json_encode(array('typ' => 'JWT', 'alg' => $this->algorithm));
        $header = $this->base64UrlEncode($header);
        
        $payload = json_encode($payload);
        $payload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', "$header.$payload", $this->secret_key, true);
        $signature = $this->base64UrlEncode($signature);
        
        return "$header.$payload.$signature";
    }

    // Decodificar un token JWT
    private function decode($jwt) {
        list($header, $payload, $signature) = explode('.', $jwt);
        
        $calculatedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret_key, true)
        );
        
        if ($calculatedSignature !== $signature) {
            throw new Exception('Invalid signature');
        }
        
        $payload = json_decode($this->base64UrlDecode($payload));
        
        return $payload;
    }

    // Convierte un string a formato Base64URL (compatible con JWT)
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // Decodifica un string en formato Base64URL
    private function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
?>
