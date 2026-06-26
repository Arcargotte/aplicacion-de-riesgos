<?php
session_start();
// Control estricto: Solo administradores de la sede central entran aquí
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

// Incluimos el navbar que acabamos de crear
include('header.php');

$conn = new mysqli("localhost", "root", "", "ong_inventario");
if ($conn->connect_error) { die("Conexión fallida: " . $conn->connect_error); }

// Consultas requeridas
$inventario_actual = $conn->query("SELECT * FROM insumos WHERE estado = 'disponible' ORDER BY fecha_ingreso DESC");
$inventario_historico = $conn->query("SELECT * FROM insumos WHERE estado = 'donado' ORDER BY fecha_ingreso DESC");
?>

<style>
    .panel-title { margin-bottom: 25px; color: #111827; }
    .grid-sections { display: flex; flex-direction: column; gap: 40px; }
    
    /* Tablas Estilizadas */
    .table-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .table-container h3 { margin-bottom: 15px; font-size: 18px; display: flex; align-items: center; gap: 10px; }
    .badge-count { background: #e5e7eb; font-size: 13px; padding: 2px 8px; border-radius: 12px; color: #374151; }
    
    table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; }
    th { background-color: #f9fafb; color: #4b5563; font-weight: 600; text-transform: uppercase; font-size: 12px; }
    tr:hover { background-color: #f3f4f6; }
    
    .badge-estado { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
    .badge-disponible { background-color: #dcfce7; color: #15803d; }
    .badge-donado { background-color: #fee2e2; color: #b91c1c; }
</style>

<div class="container">
    <h2 class="panel-title">Sede Central (Montalbán) — Inventario General</h2>

    <div class="grid-sections">
        
        <div class="table-container">
            <h3>
                📦 Insumos Disponibles Actualmente 
                <span class="badge-count"><?php echo $inventario_actual->num_rows; ?> ítems</span>
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Insumo</th>
                        <th>Cantidad Disponible</th>
                        <th>Unidad</th>
                        <th>Estado</th>
                        <th>Fecha Ingreso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($inventario_actual->num_rows === 0): ?>
                        <tr><td colspan="6" style="color: #9ca3af; text-align: center;">No hay insumos disponibles en almacén.</td></tr>
                    <?php else: ?>
                        <?php while($row = $inventario_actual->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                                <td><?php echo number_format($row['cantidad'], 2); ?></td>
                                <td><?php echo $row['unidad_medida']; ?></td>
                                <td><span class="badge-estado badge-disponible">Disponible</span></td>
                                <td><?php echo date('d/m/Y g:i A', strtotime($row['fecha_ingreso'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <h3>
                📜 Historial de Insumos Donados / Despachados
                <span class="badge-count"><?php echo $inventario_historico->num_rows; ?> registros</span>
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Insumo</th>
                        <th>Cantidad Cant. Donada</th>
                        <th>Unidad</th>
                        <th>Estado</th>
                        <th>Fecha de Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($inventario_historico->num_rows === 0): ?>
                        <tr><td colspan="6" style="color: #9ca3af; text-align: center;">No se registran donaciones históricas aún.</td></tr>
                    <?php else: ?>
                        <?php while($row = $inventario_historico->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                                <td><?php echo number_format($row['cantidad'], 2); ?></td>
                                <td><?php echo $row['unidad_medida']; ?></td>
                                <td><span class="badge-estado badge-donado">Donado / Entregado</span></td>
                                <td><?php echo date('d/m/Y g:i A', strtotime($row['fecha_ingreso'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>