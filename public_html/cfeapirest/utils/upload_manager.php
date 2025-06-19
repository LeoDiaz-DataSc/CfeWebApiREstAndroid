<?php
/**
 * UploadManager
 * Encapsula la lógica de subida de archivos (principalmente imágenes)
 * para reducir duplicación y mejorar seguridad.
 */
class UploadManager {
    /**
     * Sube un archivo al directorio destino.
     *
     * @param array  $file          Array de $_FILES["field"]
     * @param string $destDir       Directorio absoluto de destino (con barra final)
     * @param array  $allowedTypes  Tipos MIME permitidos
     * @param int    $maxSize       Tamaño máximo en bytes (5 MB por defecto)
     *
     * @return array [success => bool, 'filename' => string, 'error' => string]
     */
    public static function upload(array $file, string $destDir, array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], int $maxSize = 5242880): array {
        // Validar errores de PHP
        if (!isset($file) || !isset($file['error'])) {
            return ['success' => false, 'error' => 'Archivo no proporcionado'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Error de subida: ' . $file['error']];
        }
        // Validar tipo MIME
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
        }
        // Validar tamaño
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'El archivo excede el tamaño máximo'];
        }
        // Sanitizar nombre y generar único
        $originalName = preg_replace('/[^A-Za-z0-9._-]/', '', basename($file['name']));
        $filename = uniqid('', true) . '_' . $originalName;
        // Asegurar que el directorio exista
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                return ['success' => false, 'error' => 'No se pudo crear el directorio de destino'];
            }
        }
        $targetFile = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return ['success' => true, 'filename' => $filename];
        }
        return ['success' => false, 'error' => 'No se pudo mover el archivo'];
    }
}
?>
