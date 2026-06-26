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

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    
    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Historial de Donaciones y Entregas Realizadas
        </h2>
        <p class="text-sm text-slate-500 font-medium">Registro cronológico de despachos de insumos</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
        
        <?php if ($resultado_entregas->num_rows === 0): ?>
            <div class="text-center py-12 text-slate-400 text-sm font-medium">
                ⚠️ No se han registrado entregas o donaciones en el sistema todavía.
            </div>
        <?php else: ?>
            
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                            <th class="p-3 w-24">ID Entrega</th>
                            <th class="p-3 w-40">Fecha y Hora</th>
                            <th class="p-3 w-64">Receptor / Beneficiario</th>
                            <th class="p-3">Insumos Entregados</th>
                            <th class="p-3 w-56">Detalles Adicionales</th>
                            <th class="p-3 w-44">Responsable</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 vertical-align-top">
                        <?php while($entrega = $resultado_entregas->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/80 transition duration-100">
                                <td class="p-3 font-mono font-bold text-slate-900">#<?php echo $entrega['entrega_id']; ?></td>
                                <td class="p-3 leading-tight">
                                    <div class="font-semibold text-slate-800"><?php echo date('d/m/Y', strtotime($entrega['fecha_hora'])); ?></div>
                                    <div class="text-xs text-slate-400 mt-0.5"><?php echo date('g:i A', strtotime($entrega['fecha_hora'])); ?></div>
                                </td>
                                <td class="p-3">
                                    <?php if ($entrega['tipo_receptor'] === 'persona'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-sky-100 text-sky-800 mb-1">Persona Natural</span>
                                        <div class="font-bold text-slate-900"><?php echo htmlspecialchars($entrega['p_nombre'] . " " . $entrega['p_apellido']); ?></div>
                                        <div class="text-xs text-slate-500 font-mono">V- <?php echo htmlspecialchars($entrega['p_cedula']); ?></div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800 mb-1">Centro de Acopio</span>
                                        <div class="font-bold text-slate-900"><?php echo htmlspecialchars($entrega['centro_nombre']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <ul class="space-y-1 list-disc pl-4 text-xs text-slate-600">
                                        <?php
                                        $ent_id = $entrega['entrega_id'];
                                        $sql_detalles = "SELECT de.cantidad_donada, i.nombre, i.unidad_medida 
                                                         FROM detalle_entregas de
                                                         JOIN insumos i ON de.insumo_id = i.id
                                                         WHERE de.entrega_id = $ent_id";
                                        $resultado_detalles = $conn->query($sql_detalles);
                                        while($detalle = $resultado_detalles->fetch_assoc()) {
                                            echo "<li><span class='font-bold text-slate-800'>" . number_format($detalle['cantidad_donada'], 2) . " " . htmlspecialchars($detalle['unidad_medida']) . "</span> de <strong>" . htmlspecialchars($detalle['nombre']) . "</strong></li>";
                                        }
                                        ?>
                                    </ul>
                                </td>
                                <td class="p-3 text-xs text-slate-500 italic max-w-xs break-words">
                                    <?php echo !empty($entrega['detalle_adicional']) ? htmlspecialchars($entrega['detalle_adicional']) : '<span class="text-slate-300 not-italic">Sin observaciones</span>'; ?>
                                </td>
                                <td class="p-3 text-xs font-medium text-slate-600">
                                    👤 <?php echo htmlspecialchars($entrega['responsable_entrega']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:hidden">
                <?php 
                $resultado_entregas->data_seek(0); // Reiniciar el puntero de la consulta para el bucle móvil
                while($entrega = $resultado_entregas->fetch_assoc()): 
                ?>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col gap-3">
                        
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                            <span class="font-mono text-xs font-black text-slate-900">Entrega #<?php echo $entrega['entrega_id']; ?></span>
                            <div class="text-right">
                                <span class="text-xs font-bold text-slate-800 block"><?php echo date('d/m/Y', strtotime($entrega['fecha_hora'])); ?></span>
                                <span class="text-[10px] text-slate-400 block"><?php echo date('g:i A', strtotime($entrega['fecha_hora'])); ?></span>
                            </div>
                        </div>
                        
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-black tracking-wider block mb-0.5">Destinatario</span>
                            <?php if ($entrega['tipo_receptor'] === 'persona'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold uppercase bg-sky-100 text-sky-800 mb-1">Persona Natural</span>
                                <div class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($entrega['p_nombre'] . " " . $entrega['p_apellido']); ?></div>
                                <div class="text-xs text-slate-500 font-mono">V- <?php echo htmlspecialchars($entrega['p_cedula']); ?></div>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold uppercase bg-amber-100 text-amber-800 mb-1">Centro de Acopio</span>
                                <div class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($entrega['centro_nombre']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-white p-3 rounded-lg border border-slate-100">
                            <span class="text-[10px] text-slate-400 uppercase font-black tracking-wider block mb-1.5">Artículos Entregados</span>
                            <ul class="space-y-1.5 list-none m-0 p-0 text-xs text-slate-700">
                                <?php
                                $ent_id = $entrega['entrega_id'];
                                $sql_detalles = "SELECT de.cantidad_donada, i.nombre, i.unidad_medida 
                                                 FROM detalle_entregas de
                                                 JOIN insumos i ON de.insumo_id = i.id
                                                 WHERE de.entrega_id = $ent_id";
                                $resultado_detalles = $conn->query($sql_detalles);
                                while($detalle = $resultado_detalles->fetch_assoc()) {
                                    echo "<li class='border-b border-slate-50 pb-1 last:border-0 last:pb-0'>⚡ <span class='font-black text-slate-900'>" . number_format($detalle['cantidad_donada'], 2) . " " . htmlspecialchars($detalle['unidad_medida']) . "</span> de " . htmlspecialchars($detalle['nombre']) . "</li>";
                                }
                                ?>
                            </ul>
                        </div>

                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-black tracking-wider block mb-0.5">Observaciones</span>
                            <p class="text-xs text-slate-600 bg-white/50 p-2 rounded border border-slate-200/40 italic break-words">
                                <?php echo !empty($entrega['detalle_adicional']) ? htmlspecialchars($entrega['detalle_adicional']) : '<span class="text-slate-300 not-italic">Sin observaciones</span>'; ?>
                            </p>
                        </div>

                        <div class="border-t border-slate-200/60 pt-2 flex items-center justify-between text-[11px] text-slate-500">
                            <span>Despachado por:</span>
                            <span class="font-bold text-slate-700">👤 <?php echo htmlspecialchars($entrega['responsable_entrega']); ?></span>
                        </div>
                        
                    </div>
                <?php endwhile; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>