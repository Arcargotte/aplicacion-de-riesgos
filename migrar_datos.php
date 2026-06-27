<?php
require_once('conexion.php');

// 1. Migrar Ingresos
$res_ingresos = $conn->query("SELECT * FROM ingresos_donaciones");
while($row = $res_ingresos->fetch_assoc()) {
    // Escapamos los valores para seguridad
    $nombre = $conn->real_escape_string($row['nombre_donante']);
    $detalles = $conn->real_escape_string($row['detalles']);
    
    $sql = "INSERT INTO movimientos_inventario (tipo_movimiento, centro_destino_id, nombre_donante, usuario_id, detalles, fecha_hora) 
            VALUES ('ingreso', 1, '$nombre', {$row['usuario_id']}, '$detalles', '{$row['fecha_registro']}')";
    $conn->query($sql);
}

// 2. Migrar Entregas
$res_entregas = $conn->query("SELECT * FROM entregas");
while($row = $res_entregas->fetch_assoc()) {
    $tipo = ($row['tipo_receptor'] == 'centro') ? 'traslado' : 'despacho';
    
    // Manejo de valores NULL de forma segura
    $c_origen = $row['centro_id'] ? $row['centro_id'] : 'NULL';
    $p_beneficiaria = $row['persona_id'] ? $row['persona_id'] : 'NULL';
    $detalles = $conn->real_escape_string($row['detalle_adicional']);
    
    $sql = "INSERT INTO movimientos_inventario (tipo_movimiento, centro_origen_id, persona_beneficiaria_id, usuario_id, detalles, fecha_hora) 
            VALUES ('$tipo', $c_origen, $p_beneficiaria, 1, '$detalles', '{$row['fecha_hora']}')";
            
    if (!$conn->query($sql)) {
        echo "Error en entrega ID " . $row['id'] . ": " . $conn->error . "<br>";
    }
}
echo "Migración completada con éxito.";
?>