<?php
require_once 'models/Report.php';
require_once 'utils/auth.php';
require_once 'utils/api_response.php';
require_once 'vendor/autoload.php'; // Asumiendo que tienes Composer para PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportController {
    private $db;
    private $report;
    private $auth;
    
    public function __construct($db) {
        $this->db = $db;
        $this->report = new Report($db);
        $this->auth = new Auth();
    }
    
    /**
     * Exportar reportes a XLSX según filtros
     * @param string $start_date Fecha inicial (YYYY-MM-DD)
     * @param string $end_date Fecha final (YYYY-MM-DD)
     * @param string $status Estado del reporte
     */
    public function exportReportsToXlsx($start_date = null, $end_date = null, $status = null) {
        // Verificar autenticación (solo roles administrativos)
        $user_data = $this->auth->validateToken();
        
        // Verificar permisos administrativos
        if ($user_data->role != 'JEFE_AREA' && $user_data->role != 'SOBRESTANTE') {
            ApiResponse::forbidden("No tiene permisos para exportar reportes");
            return;
        }
        
        // Obtener los datos filtrados
        $stmt = $this->report->getForExport($start_date, $end_date, $status);
        $num = $stmt->rowCount();
        
        if ($num == 0) {
            ApiResponse::error("No hay datos para exportar con los filtros especificados", 404);
            return;
        }
        
        // Crear una nueva hoja de cálculo
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reportes');
        
        // Definir encabezados de columnas
        $headers = [
            'ID', 'Matrícula', 'Grupo', 'Anomalía', 'Material', 'Descripción',
            'Latitud', 'Longitud', 'Estado', 'Creado por', 'Rol', 'Fecha Creación', 'Última Actualización'
        ];
        // Estilo para encabezados
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '086C4C'], // Color primario de la aplicación
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ];
        // Aplicar encabezados y estilo
        foreach ($headers as $colIndex => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . '1', $header);
        }
        $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
        // Insertar datos
        $row = 2;
        while ($report = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sheet->setCellValue('A' . $row, $report['id']);
            $sheet->setCellValue('B' . $row, $report['matricula']);
            $sheet->setCellValue('C' . $row, $report['grupo']);
            $sheet->setCellValue('D' . $row, $report['anomalia']);
            $sheet->setCellValue('E' . $row, $report['material']);
            $sheet->setCellValue('F' . $row, $report['descripcion']);
            $sheet->setCellValue('G' . $row, $report['latitude']);
            $sheet->setCellValue('H' . $row, $report['longitude']);
            $sheet->setCellValue('I' . $row, $report['status']);
            $sheet->setCellValue('J' . $row, $report['creado_por']);
            $sheet->setCellValue('K' . $row, $report['rol_usuario']);
            $sheet->setCellValue('L' . $row, $report['created_at']);
            $sheet->setCellValue('M' . $row, $report['updated_at']);
            
            $row++;
        }
        
        // Auto-ajustar anchos de columna
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Crear nombre de archivo
        $filename = 'reportes_' . date('Y-m-d_His') . '.xlsx';
        $filepath = '../exports/' . $filename;
        
        // Asegurar que el directorio existe
        if (!is_dir('../exports')) {
            mkdir('../exports', 0755, true);
        }
        
        // Guardar el archivo
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        // Devolver la URL para descargar
        $downloadUrl = '/exports/' . $filename;
        
        // Registrar la exportación en el registro de actividad (si existe la tabla)
        try {
            $stmt = $this->conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'export_reports', ?)");
            $details = json_encode([
                'filters' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'status' => $status
                ],
                'count' => $num,
                'file' => $filename
            ]);
            $stmt->bindParam(1, $user_data->user_id);
            $stmt->bindParam(2, $details);
            $stmt->execute();
        } catch (Exception $e) {
            // Ignorar errores de registro, no es crítico
        }
        
        ApiResponse::success("Reporte generado exitosamente", [
            'download_url' => $downloadUrl,
            'filename' => $filename,
            'record_count' => $num
        ]);
    }
}
?>
