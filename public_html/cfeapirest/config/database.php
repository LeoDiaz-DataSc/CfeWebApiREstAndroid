<?php
class Database {
    // Credenciales de la base de datos
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;
    private $environment;
    
    public function __construct() {
        // Detectar el entorno automáticamente según el servidor
        $this->environment = $this->detectEnvironment();
        $this->setCredentials();
    }
    
    private function detectEnvironment() {
        // Primero, respetar la variable de entorno APP_ENV si está definida
        $envFromEnv = getenv('APP_ENV');
        if ($envFromEnv) {
            return $envFromEnv;
        }
        
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        
        // Verificar si estamos en el host de producción
        if (strpos($server_name, 'cfedis.space') !== false) {
            return 'production'; 
        }
        
        // Por defecto, usar desarrollo
        return 'development';
    }
    
    private function setCredentials() {
        // Prioridad 1: variables de entorno
        $envHost = getenv('DB_HOST');
        $envName = getenv('DB_NAME');
        $envUser = getenv('DB_USER');
        $envPass = getenv('DB_PASS');
        
        if ($envHost && $envName && $envUser && $envPass) {
            $this->host = $envHost;
            $this->db_name = $envName;
            $this->username = $envUser;
            $this->password = $envPass;
            return;
        }
        
        // Configuración según el entorno si no hay variables de entorno completas
        if ($this->environment === 'production') {
            // Credenciales de producción (Hostinger)
            $this->host = "srv1925.hstgr.io"; // O el host que te indique Hostinger
            $this->db_name = "u804274557_cfe";
            $this->username = "u804274557_cfeuser";
            $this->password = "Licantropo.02";
        }else {
            // Credenciales de desarrollo local
            $this->host = "localhost";
            $this->db_name = "cfe_app";
            $this->username = "root";
            $this->password = "TlalocanQuetzal11";
        }
    }

    // Obtener la conexión a la base de datos
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>
