<?php
// test_db_connection.php
// Script sencillo para comprobar la conexión PDO con la base de datos
// Usa las mismas credenciales que la API (Database class)

require_once __DIR__ . '/cfeapirest/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if ($pdo instanceof PDO) {
        echo "✅ Conexión exitosa a la base de datos: " . $pdo->query('select database()')->fetchColumn();
    } else {
        echo "❌ No se pudo establecer la conexión.";
    }
} catch (Throwable $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
    // Opcional: registrar el error en log
}
?>
