<?php
session_start();
// Control de acceso: Solo administradores centrales
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

include('header.php');

$conn = new mysqli("localhost", "root", "", "ong_inventario");
if ($conn->connect_error) { 
    die("Conexión fallida: " . $conn->connect_error); 
}

// Consulta principal para obtener las cabeceras de las entregas y sus respectivos receptores
$sql_entregas = "SELECT 
                    e.id AS entrega_id,
                    e.tipo_receptor,
                    e.detalle_adicional,
                    e.fecha_hora,
                    e.responsable_entrega,
                    p.nombre AS p_nombre, p.apellido AS p_apellido, p.cedula AS p_cedula,
                    c.nombre AS centro_nombre
                 FROM entregas e
                 LEFT JOIN personas_beneficiarias p ON e.persona_id = p.id
                 LEFT JOIN centros_acopio c ON e.centro_id = c.id
                 ORDER BY e.fecha_hora DESC";

$resultado_entregas = $conn->query($sql_entregas);
?>

<style>
    .page-title { margin-bottom: 25px; color: #111827; }
    .table-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    
    table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    th { background-color: #f9fafb; color: #4b5563; font-weight: 600; text-transform: uppercase; font-size: 12px; }
    tr:hover { background-color: #f3f4f6; }
    
    .badge-receptor { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; margin-bottom: 5px; }
    .badge-persona { background-color: #e0f2fe; color: #0369a1; }
    .badge-centro { background-color: #fef3c7; color: #b45309; }
    
    .lista-insumos { padding-left: 18px; margin: 0; font-size: 13px; color: #374151; }
    .lista-insumos li { margin-bottom: 3px; }
    .text-detalle { font-size: 13px; color: #6b7280; font-style: italic; max-width: 250px; word-wrap: break-word; }
</style>

<div class="container">
    <h2 class="page-title">Historial de Donaciones y Entregas Realizadas</h2>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">ID Entrega</th>
                    <th style="width: 160px;">Fecha y Hora</th>
                    <th style="width: 250px;">Receptor / Beneficiario</th>
                    <th>Insumos Entregados</th>
                    <th style="width: 200px;">Detalles Adicionales</th>
                    <th style="width: 180px;">Responsable</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado_entregas->num_rows === 0): ?>
                    <tr>
                        <td colspan="6" style="color: #9ca3af; text-align: center; padding: 30px;">
                            No se han registrado entregas o donaciones en el sistema todavía.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while($entrega = $resultado_entregas->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $entrega['entrega_id']; ?></strong></td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($entrega['fecha_hora'])); ?><br>
                                <span style="color: #6b7280; font-size: 12px;"><?php echo date('g:i A', strtotime($entrega['fecha_hora'])); ?></span>
                            </td>
                            <td>
                                <?php if ($entrega['tipo_receptor'] === 'persona'): ?>
                                    <span class="badge-receptor badge-persona">Persona Natural</span><br>
                                    <strong><?php echo htmlspecialchars($entrega['p_nombre'] . " " . $entrega['p_apellido']); ?></strong><br>
                                    <span style="color: #4b5563; font-size: 12px;">V- <?php echo htmlspecialchars($entrega['p_cedula']); ?></span>
                                <?php else: ?>
                                    <span class="badge-receptor badge-centro">Centro de Acopio</span><br>
                                    <strong><?php echo htmlspecialchars($entrega['centro_nombre']); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <ul class="lista-insumos">
                                    <?php
                                    // Subconsulta para buscar los insumos específicos vinculados a esta entrega
                                    $ent_id = $entrega['entrega_id'];
                                    $sql_detalles = "SELECT de.cantidad_donada, i.nombre, i.unidad_medida 
                                                     FROM detalle_entregas de
                                                     JOIN insumos i ON de.insumo_id = i.id
                                                     WHERE de.entrega_id = $ent_id";
                                    
                                    $resultado_detalles = $conn->query($sql_detalles);
                                    
                                    while($detalle = $resultado_detalles->fetch_assoc()) {
                                        echo "<li>" . number_format($detalle['cantidad_donada'], 2) . " " . $detalle['unidad_medida'] . " de <strong>" . htmlspecialchars($detalle['nombre']) . "</strong></li>";
                                    }
                                    ?>
                                </ul>
                            </td>
                            <td>
                                <div class="text-detalle">
                                    <?php 
                                    echo !empty($entrega['detalle_adicional']) 
                                        ? htmlspecialchars($entrega['detalle_adicional']) 
                                        : '<span style="color:#d1d5db;">Sin observaciones</span>'; 
                                    ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 500; color: #4b5563;">
                                    <?php echo htmlspecialchars($entrega['responsable_entrega']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>