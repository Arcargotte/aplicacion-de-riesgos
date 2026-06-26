<?php
session_start();
// Control de acceso: solo voluntarios de centro
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "ong_inventario");
    
    $usuario_id = $_SESSION['usuario_id'];
    $centro_id = $_SESSION['centro_id']; // El centro asociado al voluntario en la BD

    // Insertar cabecera de la solicitud
    $conn->query("INSERT INTO solicitudes (usuario_id, centro_id) VALUES ($usuario_id, $centro_id)");
    $solicitud_id = $conn->insert_id;

    // Recuperar los arreglos dinámicos enviados por JS
    $nombres_insumos = $_POST['nombre_insumo'];
    $cantidades = $_POST['cantidad_insumo'];

    if (is_array($nombres_insumos)) {
        for ($i = 0; $i < count($nombres_insumos); $i++) {
            $nombre = $conn->real_escape_string($nombres_insumos[$i]);
            $cantidad = $conn->real_escape_string($cantidades[$i]);

            if (!empty($nombre) && !empty($cantidad)) {
                $conn->query("INSERT INTO detalle_solicitudes (solicitud_id, nombre_insumo, cantidad) VALUES ($solicitud_id, '$nombre', '$cantidad')");
            }
        }
    }
    echo "<script>alert('¡Solicitud enviada a Montalbán correctamente!'); window.location.href='formulario_solicitud.php';</script>";
    $conn->close();
}

include('header.php');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Insumos - Centro de Acopio</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 20px; }
        .solicitud-container { max-width: 650px; background: white; padding: 25px; margin: auto; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .fila-insumo { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .fila-insumo input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-add { background: #28a745; color: white; border: none; padding: 8px 12px; cursor: pointer; border-radius: 4px; margin-bottom: 15px;}
        .btn-remove { background: #dc3545; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px; }
        .btn-submit { background: #007bff; color: white; border: none; padding: 10px 20px; width: 100%; cursor: pointer; border-radius: 4px; font-size: 16px; }
        .meta-info { background: #e9ecef; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

<div class="solicitud-container">
    <h2>Nueva Solicitud de Insumos</h2>
    
    <div class="meta-info">
        <strong>Responsable de la Solicitud:</strong> <?php echo $_SESSION['nombre']; ?> (Voluntario)<br>
        <strong>Fecha:</strong> <?php echo date("Y-m-d H:i:s"); ?> (Automática)
    </div>

    <form action="" method="POST">
        <label style="font-weight: bold; display: block; margin-bottom: 10px;">Insumos Requeridos (Texto Libre):</label>
        
        <button type="button" class="btn-add" onclick="agregarFila()">+ Añadir otro insumo</button>
        
        <div id="contenedor-insumos">
            <div class="fila-insumo">
                <input type="text" name="nombre_insumo[]" placeholder="Ej: Harina de maíz, Pañales G..." style="flex: 2;" required>
                <input type="text" name="cantidad_insumo[]" placeholder="Ej: 50 cajas, 20 kg..." style="flex: 1;" required>
                <button type="button" class="btn-remove" onclick="eliminarFila(this)">X</button>
            </div>
        </div>

        <button type="submit" class="btn-submit" style="margin-top: 20px;">Enviar Solicitud a Sede Central</button>
    </form>
</div>

<script>
function agregarFila() {
    const contenedor = document.getElementById('contenedor-insumos');
    const nuevaFila = document.createElement('div');
    nuevaFila.className = 'fila-insumo';
    
    nuevaFila.innerHTML = `
        <input type="text" name="nombre_insumo[]" placeholder="Ej: Insumo..." style="flex: 2;" required>
        <input type="text" name="cantidad_insumo[]" placeholder="Ej: Cantidad..." style="flex: 1;" required>
        <button type="button" class="btn-remove" onclick="eliminarFila(this)">X</button>
    `;
    contenedor.appendChild(nuevaFila);
}

function eliminarFila(btn) {
    const filas = document.querySelectorAll('.fila-insumo');
    // Evitar que borren la última fila obligatoria
    if(filas.length > 1) {
        btn.parentElement.remove();
    } else {
        alert("Debes solicitar al menos un insumo.");
    }
}
</script>

</body>
</html>