<?php
// 1. Inicializar la sesión y validar permisos como en tu archivo original
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') { 
    exit("Acceso no autorizado."); 
}

// 2. Desactivar salida de errores crudos en pantalla para que no corrompan el binario del Excel
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 3. Requerir dependencias de Composer y tu archivo de conexión
require_once 'vendor/autoload.php';
require_once 'conexion.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Detectar el tipo de reporte solicitado
$tipo_reporte = isset($_GET['reporte']) ? $_GET['reporte'] : '';
if (empty($tipo_reporte)) {
    exit("Debe seleccionar un reporte válido.");
}

// Definir nombre de archivo con la extensión moderna .xlsx
$filename = $tipo_reporte . "_" . date('Ymd_His') . ".xlsx";

// 4. Inicializar objeto de hoja de cálculo nativo
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- CONFIGURACIÓN DE ESTILOS PROFESIONALES (Equivalente a tu CSS anterior) ---
$estiloCabecera = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']], // Tu color azul oscuro #0f172a
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];

$estiloBordes = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']], // Gris claro suave
    ],
];

// Variable de control para saber cuántas celdas estilizar al final
$columnaMaxima = 'A';

// 5. Procesar consultas y rellenar celdas por caso
switch ($tipo_reporte) {
            
    case 'inventario_global':
        $sheet->setTitle('Inventario Global');
        $columnaMaxima = 'E';
        
        // Cabeceras de columna
        $cabeceras = ['Centro de Acopio', 'Dirección', 'Insumo', 'Stock Disponible', 'Última Actualización'];
        $sheet->fromArray($cabeceras, NULL, 'A1');
        
        $query = "SELECT 
                    c.nombre AS centro_nombre,
                    c.direccion AS centro_direccion,
                    i.nombre AS insumo_nombre,
                    inv.cantidad,
                    i.unidad_medida,
                    inv.ultima_actualizacion
                  FROM inventario inv
                  INNER JOIN centros_acopio c ON inv.centro_id = c.id
                  INNER JOIN insumos i ON inv.insumo_id = i.id
                  WHERE inv.cantidad > 0
                  ORDER BY c.nombre ASC, i.nombre ASC";
                  
        $res = $conn->query($query);
        $fila = 2;
        while($r = $res->fetch_assoc()) {
            $sheet->setCellValue('A' . $fila, $r['centro_nombre']);
            $sheet->setCellValue('B' . $fila, $r['centro_direccion'] ?? 'No registrada');
            $sheet->setCellValue('C' . $fila, $r['insumo_nombre']);
            $sheet->setCellValue('D' . $fila, $r['cantidad'] . ' ' . $r['unidad_medida']);
            $sheet->setCellValue('E' . $fila, $r['ultima_actualizacion']);
            $fila++;
        }
        
        // Aplicar estilos al rango de datos generado
        $sheet->getStyle('A1:E1')->applyFromArray($estiloCabecera);
        $sheet->getStyle('A1:E' . ($fila - 1))->applyFromArray($estiloBordes);
        break;

    case 'insumos_centro':
        $sheet->setTitle('Insumos por Centro');
        $columnaMaxima = 'D';
        
        $cabeceras = ['Centro', 'ID Insumo', 'Insumo', 'Stock Disponible'];
        $sheet->fromArray($cabeceras, NULL, 'A1');
        
        $query = "SELECT 
                    c.nombre as centro, 
                    i.id as insumo_id, 
                    i.nombre as insumo, 
                    inv.cantidad,
                    i.unidad_medida
                  FROM inventario inv
                  INNER JOIN insumos i ON inv.insumo_id = i.id 
                  INNER JOIN centros_acopio c ON inv.centro_id = c.id 
                  ORDER BY c.nombre ASC, i.nombre ASC";
                  
        $res = $conn->query($query);
        $fila = 2;
        while($r = $res->fetch_assoc()) {
            $sheet->setCellValue('A' . $fila, $r['centro']);
            $sheet->setCellValue('B' . $fila, $r['insumo_id']);
            $sheet->setCellValue('C' . $fila, $r['insumo']);
            $sheet->setCellValue('D' . $fila, $r['cantidad'] . ' ' . $r['unidad_medida']);
            $fila++;
        }
        
        $sheet->getStyle('A1:D1')->applyFromArray($estiloCabecera);
        $sheet->getStyle('A1:D' . ($fila - 1))->applyFromArray($estiloBordes);
        break;

    case 'historico_movimientos':
        $sheet->setTitle('Histórico de Movimientos');
        $columnaMaxima = 'G';
        
        $cabeceras = ['ID Movimiento', 'Fecha/Hora', 'Tipo', 'Centro Origen', 'Centro Destino', 'Detalles', 'Usuario Registra'];
        $sheet->fromArray($cabeceras, NULL, 'A1');
        
        $query = "SELECT m.id, m.fecha, m.tipo_movimiento, 
                         co.nombre as origen, cd.nombre as destino, 
                         m.detalles, u.nombre as usuario 
                  FROM movimientos m
                  LEFT JOIN centros_acopio co ON m.centro_origen_id = co.id
                  LEFT JOIN centros_acopio cd ON m.centro_destino_id = cd.id
                  JOIN usuarios u ON m.usuario_id = u.id
                  ORDER BY m.fecha DESC";
                  
        $res = $conn->query($query);
        $fila = 2;
        while($r = $res->fetch_assoc()) {
            $tipo = !empty($r['tipo_movimiento']) ? ucfirst($r['tipo_movimiento']) : 'No definido';
            
            $sheet->setCellValue('A' . $fila, $r['id']);
            $sheet->setCellValue('B' . $fila, $r['fecha']);
            $sheet->setCellValue('C' . $fila, $tipo);
            $sheet->setCellValue('D' . $fila, $r['origen'] ?? 'Externo / Donante');
            $sheet->setCellValue('E' . $fila, $r['destino'] ?? 'Consumo Final / Víctima');
            $sheet->setCellValue('F' . $fila, $r['detalles'] ?? '—');
            $sheet->setCellValue('G' . $fila, $r['usuario']);
            $fila++;
        }
        
        $sheet->getStyle('A1:G1')->applyFromArray($estiloCabecera);
        $sheet->getStyle('A1:G' . ($fila - 1))->applyFromArray($estiloBordes);
        break;

    case 'lista_victimas':
        $sheet->setTitle('Lista de Víctimas');
        $columnaMaxima = 'H';
        
        $cabeceras = ['ID', 'Nombre y Apellido', 'Cédula', 'Edad aprox.', 'Centro de Atención', 'Gravedad (Triaje)', 'Estatus Logístico', 'Fecha Registro'];
        $sheet->fromArray($cabeceras, NULL, 'A1');
        
        $query = "SELECT 
                    v.id, 
                    v.nombre_apellido,
                    v.cedula,
                    v.edad_aproximada,
                    c.nombre as centro, 
                    v.gravedad_triaje,
                    v.estado_logistico, 
                    v.fecha_registro
                  FROM victimas v
                  LEFT JOIN centros_acopio c ON v.centro_id = c.id
                  ORDER BY v.fecha_registro DESC";
                  
        $res = $conn->query($query);
        $fila = 2;
        if ($res) {
            while($r = $res->fetch_assoc()) {
                $sheet->setCellValue('A' . $fila, $r['id']);
                $sheet->setCellValue('B' . $fila, $r['nombre_apellido'] ?? 'Desconocido');
                $sheet->setCellValue('C' . $fila, $r['cedula'] ?? 'No Posee');
                $sheet->setCellValue('D' . $fila, $r['edad_aproximada'] . ' años');
                $sheet->setCellValue('E' . $fila, $r['centro'] ?? 'No Asignado');
                $sheet->setCellValue('F' . $fila, $r['gravedad_triaje']);
                $sheet->setCellValue('G' . $fila, $r['estado_logistico']);
                $sheet->setCellValue('H' . $fila, $r['fecha_registro']);
                $fila++;
            }
        }
        
        $sheet->getStyle('A1:H1')->applyFromArray($estiloCabecera);
        $sheet->getStyle('A1:H' . ($fila - 1))->applyFromArray($estiloBordes);
        break;

    default:
        $conn->close();
        exit("Reporte inválido.");
}

// 6. Autoajustar el ancho de las columnas según su contenido de forma dinámica
foreach (range('A', $columnaMaxima) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// 7. LIMPIEZA CRÍTICA: Vaciar búfers previos para prevenir inyección de caracteres corruptos
if (ob_get_length()) {
    ob_end_clean();
}

// 8. Cabeceras HTTP Oficiales para descarga de documentos modernos .xlsx (OpenXML)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); 
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

// 9. Escribir archivo final directo al output binario del navegador
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit;