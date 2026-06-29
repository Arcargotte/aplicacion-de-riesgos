<?php
session_start();
// Control estricto: Solo administradores de la sede central gestionan solicitudes
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login");
    exit;
}

// 1. IMPORTAR CONEXIÓN CENTRALIZADA
require_once('conexion.php');
include('header.php');

// 2. PROCESAR ACCIONES (APROBAR / RECHAZAR)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion'])) {
    $solicitud_id = intval($_POST['solicitud_id']);
    $accion = $_POST['accion']; // 'aprobar' o 'rechazar'
    $responsable_id = intval($_SESSION['usuario_id']);

    $conn->begin_transaction();
    try {
        // Obtener datos de la solicitud original bloqueándola para lectura concurrente
        $res_sol = $conn->query("SELECT * FROM solicitudes WHERE id = $solicitud_id FOR UPDATE");
        $solicitud = $res_sol->fetch_assoc();

        if (!$solicitud || $solicitud['estado'] !== 'pendiente') {
            throw new Exception("La solicitud no existe o ya fue procesada.");
        }

        $centro_destino_id = intval($solicitud['centro_id']);
        $centro_origen_id = 1; // Sede Central

        if ($accion === 'aprobar') {
            
            // PASO 1: VERIFICACIÓN ESTRICTA DE STOCK PARA TODOS LOS INSUMOS
            $detalles_solicitud = $conn->query("SELECT ds.insumo_id, ds.cantidad, i.nombre 
                                                FROM detalle_solicitudes ds 
                                                JOIN insumos i ON ds.insumo_id = i.id 
                                                WHERE ds.solicitud_id = $solicitud_id");
            
            $items_a_procesar = [];
            
            while ($det = $detalles_solicitud->fetch_assoc()) {
                $insumo_id = intval($det['insumo_id']);
                $cantidad_pedida = floatval($det['cantidad']);
                $nombre_insumo = $det['nombre'];

                // Buscar el stock en la Sede Central (centro_id = 1)
                $res_inv = $conn->query("SELECT cantidad FROM inventario WHERE centro_id = $centro_origen_id AND insumo_id = $insumo_id FOR UPDATE");
                
                if ($res_inv->num_rows > 0) {
                    $row_inv = $res_inv->fetch_assoc();
                    $stock_actual = floatval($row_inv['cantidad']);
                    
                    if ($stock_actual < $cantidad_pedida) {
                        throw new Exception("No hay suficiente stock en Sede Central para: " . $nombre_insumo);
                    }
                } else {
                    throw new Exception("No hay suficiente stock (No existe registro en inventario central) para: " . $nombre_insumo);
                }
                
                // Si pasa la validación, lo guardamos para procesarlo en el siguiente paso
                $items_a_procesar[] = [
                    'id' => $insumo_id,
                    'cantidad' => $cantidad_pedida
                ];
            }

            // PASO 2: TODO EL STOCK ES VÁLIDO -> PROCEDEMOS A DESCONTAR Y TRANSFERIR
            
            // 2.1 Cambiar estado de la solicitud
            $conn->query("UPDATE solicitudes SET estado = 'aprobado' WHERE id = $solicitud_id");

            // 2.2 Insertar el registro principal en `movimientos`
            $detalles_mov = "Donación / Traslado por aprobación de Solicitud #" . $solicitud_id;
            $conn->query("INSERT INTO movimientos (tipo_movimiento, centro_origen_id, centro_destino_id, usuario_id, detalles) 
                          VALUES ('Traslado a centro', $centro_origen_id, $centro_destino_id, $responsable_id, '$detalles_mov')");
            $movimiento_id = $conn->insert_id;

            // 2.3 Procesar cada item (Descontar, Sumar al destino, Guardar detalle_movimiento)
            foreach ($items_a_procesar as $item) {
                $id_ins = $item['id'];
                $cant = $item['cantidad'];

                // Descontar de Sede Central
                $conn->query("UPDATE inventario SET cantidad = cantidad - $cant WHERE centro_id = $centro_origen_id AND insumo_id = $id_ins");
                
                // Sumar al Centro Destino (Crear el registro si no existe en su inventario)
                $check_dest = $conn->query("SELECT id FROM inventario WHERE centro_id = $centro_destino_id AND insumo_id = $id_ins");
                if ($check_dest->num_rows > 0) {
                    $conn->query("UPDATE inventario SET cantidad = cantidad + $cant WHERE centro_id = $centro_destino_id AND insumo_id = $id_ins");
                } else {
                    $conn->query("INSERT INTO inventario (centro_id, insumo_id, cantidad) VALUES ($centro_destino_id, $id_ins, $cant)");
                }

                // Registrar en detalle_movimiento
                $conn->query("INSERT INTO detalles_movimiento (movimiento_id, insumo_id, cantidad) VALUES ($movimiento_id, $id_ins, $cant)");
            }
            
            $msg = "✅ Solicitud #$solicitud_id aprobada. Insumos descontados y transferidos exitosamente.";

        } else if ($accion === 'rechazar') {
            $justificacion = $conn->real_escape_string(trim($_POST['justificacion']));
            if (empty($justificacion)) {
                throw new Exception("Es obligatorio indicar una justificación para el rechazo.");
            }
            // Cambiar estado e insertar motivo de rechazo
            $conn->query("UPDATE solicitudes SET estado = 'rechazado', motivo_rechazo = '$justificacion' WHERE id = $solicitud_id");
            $msg = "❌ Solicitud #$solicitud_id rechazada correctamente.";
        }

        $conn->commit();
        echo "<script>alert('$msg'); window.location.href='gestion_solicitudes';</script>";
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('⚠️ Error: " . addslashes($e->getMessage()) . "'); window.location.href='gestion_solicitudes';</script>";
        exit;
    }
}

// 3. CONSULTA DE SOLICITUDES PENDIENTES
// Adaptación: Usamos c.direccion en lugar de c.ubicacion según el nuevo esquema
$solicitudes_pendientes = $conn->query("SELECT s.*, c.nombre AS centro_nombre, c.direccion AS centro_ubicacion, u.nombre AS voluntario_nombre 
                                        FROM solicitudes s
                                        JOIN centros_acopio c ON s.centro_id = c.id
                                        JOIN usuarios u ON s.usuario_id = u.id
                                        WHERE s.estado = 'pendiente'
                                        ORDER BY s.fecha_hora ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sede Central - Gestión de Solicitudes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        .dataTables_wrapper .dataTables_filter input { background-color: #f8fafc !important; border: 1px solid #e2e8f0 !important; border-radius: 0.5rem !important; padding: 0.4rem 0.8rem !important; font-size: 0.75rem !important; margin-left: 0.5rem !important; }
        .dataTables_wrapper .dataTables_length select { background-color: #f8fafc !important; border: 1px solid #e2e8f0 !important; border-radius: 0.5rem !important; padding: 0.3rem 1.5rem 0.3rem 0.5rem !important; font-size: 0.75rem !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #0f172a !important; color: white !important; border-radius: 0.5rem !important; border: none !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #f1f5f9 !important; color: #0f172a !important; border-radius: 0.5rem !important; }
        table.dataTable { border-collapse: collapse !important; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    
    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Solicitudes de Insumos Recibidas
        </h2>
        <p class="text-sm text-slate-500 font-medium">Panel de evaluación de requerimientos de Centros de Acopio</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
        
        <?php if ($solicitudes_pendientes->num_rows === 0): ?>
            <div class="text-center py-12 text-slate-400 text-sm font-medium">
                No hay solicitudes pendientes por procesar en este momento.
            </div>
        <?php else: ?>

            <div class="block md:hidden mb-4">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">🔍 Buscar Solicitud:</label>
                <input type="text" id="buscar-movil-solicitudes" placeholder="Escribe el nombre del centro o voluntario..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none">
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table id="tabla-solicitudes" class="w-full text-left text-sm border-collapse display">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                            <th class="p-3">ID</th>
                            <th class="p-3">Centro Solicitante</th>
                            <th class="p-3">Voluntario Emisor</th>
                            <th class="p-3">Insumos Requeridos</th>
                            <th class="p-3">Fecha Solicitud</th>
                            <th class="p-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php while($row = $solicitudes_pendientes->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/80 transition duration-100">
                                <td class="p-3 font-mono text-xs text-slate-400">#<?php echo $row['id']; ?></td>
                                <td class="p-3">
                                    <span class="font-bold text-slate-900 block"><?php echo htmlspecialchars($row['centro_nombre']); ?></span>
                                    <span class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($row['centro_ubicacion']); ?></span>
                                </td>
                                <td class="p-3 font-semibold text-slate-700"><?php echo htmlspecialchars($row['voluntario_nombre']); ?></td>
                                <td class="p-3 text-xs text-slate-600">
                                    <ul class="list-disc list-inside space-y-0.5">
                                        <?php 
                                        $sid = $row['id'];
                                        // ADAPTACIÓN: JOIN con tabla insumos para obtener nombre y unidad de medida
                                        $detalles = $conn->query("SELECT d.cantidad, i.nombre, i.unidad_medida 
                                                                  FROM detalle_solicitudes d 
                                                                  JOIN insumos i ON d.insumo_id = i.id 
                                                                  WHERE d.solicitud_id = $sid");
                                        while($d = $detalles->fetch_assoc()):
                                        ?>
                                            <li><strong><?php echo htmlspecialchars($d['nombre']); ?></strong> (<?php echo floatval($d['cantidad']) . ' ' . htmlspecialchars($d['unidad_medida']); ?>)</li>
                                        <?php endwhile; ?>
                                    </ul>
                                </td>
                                <td class="p-3 text-xs text-slate-500"><?php echo date('d/m/Y g:i A', strtotime($row['fecha_hora'])); ?></td>
                                <td class="p-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <form action="" method="POST" onsubmit="return confirm('¿Aprobar esta solicitud? Se descontará del stock central y se enviará al centro solicitante.');">
                                            <input type="hidden" name="solicitud_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="accion" value="aprobar">
                                            <button type="submit" class="px-2.5 py-1.5 bg-emerald-600 text-white font-bold text-xs rounded-lg hover:bg-emerald-700 shadow-sm cursor-pointer">Aceptar</button>
                                        </form>
                                        <button type="button" onclick="abrirModalRechazo(<?php echo $row['id']; ?>)" class="px-2.5 py-1.5 bg-rose-600 text-white font-bold text-xs rounded-lg hover:bg-rose-700 shadow-sm cursor-pointer">Rechazar</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div id="contenedor-movil-solicitudes" class="grid grid-cols-1 gap-3 md:hidden">
                <?php 
                $solicitudes_pendientes->data_seek(0); 
                while($row = $solicitudes_pendientes->fetch_assoc()): 
                ?>
                    <div class="tarjeta-movil-solicitud bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-3"
                         data-search="<?php echo strtolower(htmlspecialchars($row['centro_nombre'] . " " . $row['voluntario_nombre'])); ?>">
                        
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                            <span class="font-mono text-xs text-slate-400">Solicitud #<?php echo $row['id']; ?></span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800 tracking-wide">Pendiente</span>
                        </div>
                        
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wide">Centro de Acopio</div>
                            <div class="text-base font-black text-slate-900 leading-tight"><?php echo htmlspecialchars($row['centro_nombre']); ?></div>
                            <div class="text-xs text-slate-500 mt-0.5">Solicitante: <?php echo htmlspecialchars($row['voluntario_nombre']); ?></div>
                        </div>
                        
                        <div class="bg-white p-3 rounded-lg border border-slate-100 text-xs text-slate-700">
                            <div class="text-[10px] text-slate-400 uppercase font-bold mb-1">Carga Solicitada:</div>
                            <ul class="list-disc list-inside space-y-1">
                                <?php 
                                $sid = $row['id'];
                                $detalles = $conn->query("SELECT d.cantidad, i.nombre, i.unidad_medida FROM detalle_solicitudes d JOIN insumos i ON d.insumo_id = i.id WHERE d.solicitud_id = $sid");
                                while($d = $detalles->fetch_assoc()):
                                ?>
                                    <li><strong><?php echo htmlspecialchars($d['nombre']); ?></strong> (<?php echo floatval($d['cantidad']) . ' ' . htmlspecialchars($d['unidad_medida']); ?>)</li>
                                <?php endwhile; ?>
                            </ul>
                        </div>

                        <div class="text-[11px] text-slate-400 flex justify-between items-center border-t border-slate-200/40 pt-2">
                            <span>Fecha: <?php echo date('d/m/Y h:i A', strtotime($row['fecha_hora'])); ?></span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <form action="" method="POST" onsubmit="return confirm('¿Aprobar esta solicitud?');">
                                <input type="hidden" name="solicitud_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <button type="submit" class="w-full py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-sm">Aceptar</button>
                            </form>
                            <button type="button" onclick="abrirModalRechazo(<?php echo $row['id']; ?>)" class="w-full py-2 bg-rose-600 text-white font-bold text-xs rounded-xl shadow-sm">Rechazar</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="flex md:hidden items-center justify-between mt-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                <button id="prev-movil-solicitudes" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg disabled:opacity-50">Ant.</button>
                <span id="info-movil-solicitudes" class="text-xs font-semibold text-slate-500">Pág. 1</span>
                <button id="next-movil-solicitudes" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg disabled:opacity-50">Sig.</button>
            </div>

        <?php endif; ?>
    </div>
</div>

<div id="modal-rechazo" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 shadow-xl space-y-4">
        <div>
            <h3 class="text-base font-black text-slate-900 uppercase tracking-wide">❌ Rechazar Solicitud</h3>
            <p class="text-xs text-slate-500">Especifica los motivos obligatorios del rechazo para notificar al voluntario.</p>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="solicitud_id" id="modal-solicitud-id">
            <input type="hidden" name="accion" value="rechazar">
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-600">Justificación del rechazo:</label>
                <textarea name="justificacion" id="modal-justificacion" rows="3" required placeholder="Ej: No hay stock suficiente del insumo..."
                          class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400"></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="cerrarModalRechazo()" class="px-3 py-2 text-xs font-bold text-slate-500 hover:bg-slate-50 rounded-lg transition">Cancelar</button>
                <button type="submit" class="px-3 py-2 text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white rounded-lg shadow-sm transition cursor-pointer">Confirmar Rechazo</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        // Inicializar tabla escritorio
        $('#tabla-solicitudes').DataTable({
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50],
            "language": {
                "lengthMenu": "Mostrar _MENU_ solicitudes",
                "zeroRecords": "No se encontraron solicitudes pendientes coincidentes",
                "info": "Mostrando página _PAGE_ de _PAGES_",
                "search": "🔍 Buscar:",
                "paginate": { "next": "Sig.", "previous": "Ant." }
            },
            "order": [[0, "asc"]] // Mostramos las más antiguas primero para despachar por orden de llegada
        });

        // Motor de paginación móvil
        function inicializarMotorMovil() {
            let paginaActual = 1;
            const tarjetasPorPagina = 10;

            function renderizarMovil() {
                let filtro = $('#buscar-movil-solicitudes').val().toLowerCase();
                let tarjetasFiltradas = $('.tarjeta-movil-solicitud').filter(function() {
                    return $(this).attr('data-search').includes(filtro);
                });

                let totalTarjetas = tarjetasFiltradas.length;
                let totalPaginas = Math.ceil(totalTarjetas / tarjetasPorPagina) || 1;

                if (paginaActual > totalPaginas) paginaActual = totalPaginas;

                $('.tarjeta-movil-solicitud').addClass('hidden');

                let inicio = (paginaActual - 1) * tarjetasPorPagina;
                let fin = inicio + tarjetasPorPagina;
                tarjetasFiltradas.slice(inicio, fin).removeClass('hidden');

                $('#info-movil-solicitudes').text(`Pág. ${paginaActual} de ${totalPaginas}`);
                $('#prev-movil-solicitudes').prop('disabled', paginaActual === 1);
                $('#next-movil-solicitudes').prop('disabled', paginaActual === totalPaginas);
            }

            $('#prev-movil-solicitudes').click(function() { if (paginaActual > 1) { paginaActual--; renderizarMovil(); } });
            $('#next-movil-solicitudes').click(function() { paginaActual++; renderizarMovil(); });
            $('#buscar-movil-solicitudes').on('input', function() { paginaActual = 1; renderizarMovil(); });

            renderizarMovil();
        }

        if($('#contenedor-movil-solicitudes').length) {
            inicializarMotorMovil();
        }
    });

    function abrirModalRechazo(id) {
        document.getElementById('modal-solicitud-id').value = id;
        document.getElementById('modal-justificacion').value = '';
        document.getElementById('modal-rechazo').classList.remove('hidden');
    }

    function cerrarModalRechazo() {
        document.getElementById('modal-rechazo').classList.add('hidden');
    }
</script>

</body>
</html>
<?php $conn->close(); ?>