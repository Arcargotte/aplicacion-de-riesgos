<?php
session_start();
// Control de acceso: Solo administradores centrales
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

include('header.php');

require_once('conexion.php');

// 1. CONSULTA DE ENTRADAS: Donaciones recibidas por la ONG
$sql_ingresos = "SELECT 
                    i.id AS ingreso_id,
                    i.origen_donante,
                    i.nombre_donante,
                    i.detalles,
                    i.fecha_registro,
                    u.nombre AS usuario_nombre
                 FROM ingresos_donaciones i
                 JOIN usuarios u ON i.usuario_id = u.id
                 ORDER BY i.fecha_registro DESC";
$resultado_ingresos = $conn->query($sql_ingresos);

// 2. CONSULTA DE SALIDAS: Entregas distribuidas a beneficiarios
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

<!-- Estilos para adaptar DataTables al diseño elegante de Tailwind -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper .dataTables_filter input {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 0.5rem !important;
        padding: 0.4rem 0.8rem !important;
        font-size: 0.75rem !important;
        margin-left: 0.5rem !important;
    }
    .dataTables_wrapper .dataTables_length select {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 0.5rem !important;
        padding: 0.3rem 1.5rem 0.3rem 0.5rem !important;
        font-size: 0.75rem !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #0f172a !important;
        color: white !important;
        border-radius: 0.5rem !important;
        border: none !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border-radius: 0.5rem !important;
    }
    table.dataTable  {
        border-collapse: collapse !important;
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 space-y-12">
    
    <!-- CABECERA GENERAL -->
    <div class="border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Historial General de Inventario
        </h2>
        <p class="text-sm text-slate-500 font-medium">Auditoría completa de flujos de carga (Entradas y Salidas)</p>
    </div>

    <!-- ==========================================
         SECCIÓN 1: ENTRADAS (DONACIONES RECIBIDAS) 
         ========================================== -->
    <div class="space-y-4">
        <div class="flex flex-col">
            <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                Lotes Recibidos (Entradas al Almacén)
            </h3>
            <p class="text-xs text-slate-500 font-medium">Donaciones aportadas por personas, empresas o el gobierno.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
            <?php if ($resultado_ingresos->num_rows === 0): ?>
                <div class="text-center py-8 text-slate-400 text-sm font-medium">
                    No se han registrado ingresos de donaciones en el sistema todavía.
                </div>
            <?php else: ?>
                
                <!-- BUSCADOR MÓVIL INGRESOS -->
                <div class="block lg:hidden mb-4">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">
                                            <span class="inline-flex items-center gap-1.5 font-bold text-slate-500 uppercase tracking-wider">
                        <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                        </svg> Buscar en Lotes Recibidos:
                       </span>    


                    </label>
                    <input type="text" id="buscar-movil-ingresos" placeholder="Filtrar por donante, ID, notas..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                </div>

                <!-- Tabla Escritorio (LG) -->
                <div class="hidden lg:block overflow-x-auto">
                    <table id="tabla-ingresos" class="w-full text-left text-sm border-collapse display">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="p-3 w-24">ID Ingreso</th>
                                <th class="p-3 w-40">Fecha y Hora</th>
                                <th class="p-3 w-64">Donante / Proveedor</th>
                                <th class="p-3">Insumos Añadidos</th>
                                <th class="p-3 w-56">Notas de Recepción</th>
                                <th class="p-3 w-44">Registrado Por</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 vertical-align-top">
                            <?php while($ingreso = $resultado_ingresos->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/80 transition duration-100">
                                    <td class="p-3 font-mono font-bold text-slate-900">#ING-<?php echo $ingreso['ingreso_id']; ?></td>
                                    <td class="p-3 leading-tight">
                                        <div class="font-semibold text-slate-800"><?php echo date('d/m/Y', strtotime($ingreso['fecha_registro'])); ?></div>
                                        <div class="text-xs text-slate-400 mt-0.5"><?php echo date('g:i A', strtotime($ingreso['fecha_registro'])); ?></div>
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 mb-1">
                                            <?php echo htmlspecialchars($ingreso['origen_donante']); ?>
                                        </span>
                                        <div class="font-bold text-slate-900"><?php echo htmlspecialchars($ingreso['nombre_donante']); ?></div>
                                    </td>
                                    <td class="p-3">
                                        <ul class="space-y-1 list-disc pl-4 text-xs text-slate-600">
                                            <?php
                                            $ing_id = $ingreso['ingreso_id'];
                                            $sql_det_ing = "SELECT di.cantidad_recibida, i.nombre, i.unidad_medida 
                                                            FROM detalle_ingresos_donaciones di
                                                            JOIN insumos i ON di.insumo_id = i.id
                                                            WHERE di.ingreso_id = $ing_id";
                                            $res_det_ing = $conn->query($sql_det_ing);
                                            $insumos_texto_movil = ""; // Variable auxiliar para búsquedas en móvil
                                            while($det_ing = $res_det_ing->fetch_assoc()) {
                                                $insumos_texto_movil .= $det_ing['nombre'] . " ";
                                                echo "<li><span class='font-bold text-emerald-700'>+" . number_format($det_ing['cantidad_recibida'], 2) . " " . htmlspecialchars($det_ing['unidad_medida']) . "</span> de <strong>" . htmlspecialchars($det_ing['nombre']) . "</strong></li>";
                                            }
                                            ?>
                                        </ul>
                                    </td>
                                    <td class="p-3 text-xs text-slate-500 italic max-w-xs break-words">
                                        <?php echo !empty($ingreso['detalles']) ? htmlspecialchars($ingreso['detalles']) : '<span class="text-slate-300 not-italic">Sin observaciones</span>'; ?>
                                    </td>
                                    <td class="p-3 text-xs font-medium text-slate-600">
                                        👤 <?php echo htmlspecialchars($ingreso['usuario_nombre']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Tarjetas Móvil (LG-Hidden) -->
                <div id="contenedor-movil-ingresos" class="grid grid-cols-1 gap-4 lg:hidden">
                    <?php 
                    $resultado_ingresos->data_seek(0);
                    while($ingreso = $resultado_ingresos->fetch_assoc()): 
                        // Re-ejecutar subconsulta para guardar texto de insumos en la metadata de búsqueda móvil
                        $ing_id = $ingreso['ingreso_id'];
                        $res_det_ing = $conn->query("SELECT i.nombre FROM detalle_ingresos_donaciones di JOIN insumos i ON di.insumo_id = i.id WHERE di.ingreso_id = $ing_id");
                        $insumos_search = "";
                        while($d = $res_det_ing->fetch_assoc()) { $insumos_search .= $d['nombre'] . " "; }
                    ?>
                        <div class="tarjeta-movil-ingreso bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col gap-3" 
                             data-search="<?php echo strtolower(htmlspecialchars($ingreso['ingreso_id'] . " " . $ingreso['nombre_donante'] . " " . $ingreso['origen_donante'] . " " . $ingreso['detalles'] . " " . $insumos_search)); ?>">
                            
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                                <span class="font-mono text-xs font-black text-slate-900">Ingreso #ING-<?php echo $ingreso['ingreso_id']; ?></span>
                                <div class="text-right">
                                    <span class="text-xs font-bold text-slate-800 block"><?php echo date('d/m/Y', strtotime($ingreso['fecha_registro'])); ?></span>
                                    <span class="text-[10px] text-slate-400 block"><?php echo date('g:i A', strtotime($ingreso['fecha_registro'])); ?></span>
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-black tracking-wider block mb-0.5">Donante</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold uppercase bg-emerald-100 text-emerald-800 mb-1"><?php echo htmlspecialchars($ingreso['origen_donante']); ?></span>
                                <div class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($ingreso['nombre_donante']); ?></div>
                            </div>
                            <div class="bg-white p-3 rounded-lg border border-slate-100">
                                <span class="text-[10px] text-slate-400 uppercase font-black tracking-wider block mb-1.5">Artículos Ingresados</span>
                                <ul class="space-y-1.5 list-none m-0 p-0 text-xs text-slate-700">
                                    <?php
                                    $res_det_ing = $conn->query("SELECT di.cantidad_recibida, i.nombre, i.unidad_medida FROM detalle_ingresos_donaciones di JOIN insumos i ON di.insumo_id = i.id WHERE di.ingreso_id = $ing_id");
                                    while($det_ing = $res_det_ing->fetch_assoc()) {
                                        echo "<li class='border-b border-slate-50 pb-1 last:border-0 last:pb-0'> <span class='font-black text-emerald-700'>+" . number_format($det_ing['cantidad_recibida'], 2) . " " . htmlspecialchars($det_ing['unidad_medida']) . "</span> de " . htmlspecialchars($det_ing['nombre']) . "</li>";
                                    }
                                    ?>
                                </ul>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-black tracking-wider block mb-0.5">Notas</span>
                                <p class="text-xs text-slate-600 bg-white/50 p-2 rounded border border-slate-200/40 italic break-words">
                                    <?php echo !empty($ingreso['detalles']) ? htmlspecialchars($ingreso['detalles']) : 'Sin observaciones'; ?>
                                </p>
                            </div>
                            <div class="border-t border-slate-200/60 pt-2 flex items-center justify-between text-[11px] text-slate-500">
                                <span>Recibido por:</span>
                                <span class="font-bold text-slate-700">👤 <?php echo htmlspecialchars($ingreso['usuario_nombre']); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- PAGINACIÓN MÓVIL INGRESOS -->
                <div class="flex lg:hidden items-center justify-between mt-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <button id="prev-movil-ingresos" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg shadow-2xs disabled:opacity-50">Ant.</button>
                    <span id="info-movil-ingresos" class="text-xs font-semibold text-slate-500">Pág. 1</span>
                    <button id="next-movil-ingresos" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg shadow-2xs disabled:opacity-50">Sig.</button>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- ==========================================
         SECCIÓN 2: SALIDAS (ENTREGAS REALIZADAS) 
         ========================================== -->
    <div class="space-y-4">
        <div class="flex flex-col">
            <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                Despachos Realizados (Salidas de Inventario)
            </h3>
            <p class="text-xs text-slate-500 font-medium">Asignaciones distribuidas a personas vulnerables o centros de acopio.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
            <?php if ($resultado_entregas->num_rows === 0): ?>
                <div class="text-center py-8 text-slate-400 text-sm font-medium">
                    ⚠️ No se han registrado despachos en el sistema todavía.
                </div>
            <?php else: ?>

                <!-- BUSCADOR MÓVIL ENTREGAS -->
                <div class="block lg:hidden mb-4">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">
                                            <span class="inline-flex items-center gap-1.5 font-bold text-slate-500 uppercase tracking-wider">
                        <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                        </svg> Buscar en Despachos:
                       </span>    
                    </label>
                    <input type="text" id="buscar-movil-entregas" placeholder="Filtrar por beneficiario, responsable, ID..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                </div>

                <!-- Tabla Escritorio (LG) -->
                <div class="hidden lg:block overflow-x-auto">
                    <table id="tabla-entregas" class="w-full text-left text-sm border-collapse display">
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
                                    <td class="p-3 font-mono font-bold text-slate-900">#OUT-<?php echo $entrega['entrega_id']; ?></td>
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
                                                echo "<li><span class='font-bold text-rose-700'>-" . number_format($detalle['cantidad_donada'], 2) . " " . htmlspecialchars($detalle['unidad_medida']) . "</span> de <strong>" . htmlspecialchars($detalle['nombre']) . "</strong></li>";
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

                <!-- Tarjetas Móvil (LG-Hidden) -->
                <div id="contenedor-movil-entregas" class="grid grid-cols-1 gap-4 lg:hidden">
                    <?php 
                    $resultado_entregas->data_seek(0);
                    while($entrega = $resultado_entregas->fetch_assoc()): 
                        $ent_id = $entrega['entrega_id'];
                        $res_det_ent = $conn->query("SELECT i.nombre FROM detalle_entregas de JOIN insumos i ON de.insumo_id = i.id WHERE de.entrega_id = $ent_id");
                        $insumos_out_search = "";
                        while($d = $res_det_ent->fetch_assoc()) { $insumos_out_search .= $d['nombre'] . " "; }
                        $receptor_texto = ($entrega['tipo_receptor'] === 'persona') ? $entrega['p_nombre'] . " " . $entrega['p_apellido'] . " " . $entrega['p_cedula'] : $entrega['centro_nombre'];
                    ?>
                        <div class="tarjeta-movil-entrega bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col gap-3"
                             data-search="<?php echo strtolower(htmlspecialchars($entrega['entrega_id'] . " " . $receptor_texto . " " . $entrega['detalle_adicional'] . " " . $entrega['responsable_entrega'] . " " . $insumos_out_search)); ?>">
                            
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                                <span class="font-mono text-xs font-black text-slate-900">Despacho #OUT-<?php echo $entrega['entrega_id']; ?></span>
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
                                    $resultado_detalles = $conn->query("SELECT de.cantidad_donada, i.nombre, i.unidad_medida FROM detalle_entregas de JOIN insumos i ON de.insumo_id = i.id WHERE de.entrega_id = $ent_id");
                                    while($detalle = $resultado_detalles->fetch_assoc()) {
                                        echo "<li class='border-b border-slate-50 pb-1 last:border-0 last:pb-0'><span class='font-black text-rose-700'>-" . number_format($detalle['cantidad_donada'], 2) . " " . htmlspecialchars($detalle['unidad_medida']) . "</span> de " . htmlspecialchars($detalle['nombre']) . "</li>";
                                    }
                                    ?>
                                </ul>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-black tracking-wider block mb-0.5">Observaciones</span>
                                <p class="text-xs text-slate-600 bg-white/50 p-2 rounded border border-slate-200/40 italic break-words">
                                    <?php echo !empty($entrega['detalle_adicional']) ? htmlspecialchars($entrega['detalle_adicional']) : 'Sin observaciones'; ?>
                                </p>
                            </div>
                            <div class="border-t border-slate-200/60 pt-2 flex items-center justify-between text-[11px] text-slate-500">
                                <span>Despachado por:</span>
                                <span class="font-bold text-slate-700">👤 <?php echo htmlspecialchars($entrega['responsable_entrega']); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- PAGINACIÓN MÓVIL ENTREGAS -->
                <div class="flex lg:hidden items-center justify-between mt-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <button id="prev-movil-entregas" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg shadow-2xs disabled:opacity-50">Ant.</button>
                    <span id="info-movil-entregas" class="text-xs font-semibold text-slate-500">Pág. 1</span>
                    <button id="next-movil-entregas" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg shadow-2xs disabled:opacity-50">Sig.</button>
                </div>

            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Scripts de DataTables e inicialización dinámica -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        // --- 1. CONFIGURACIÓN DATA TABLES (ESCRITORIO) ---
        var configuracionLenguaje = {
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando página _PAGE_ de _PAGES_",
            "infoEmpty": "No hay registros disponibles",
            "infoFiltered": "(filtrado de un total de _MAX_ registros)",
            "search": `<span class="inline-flex items-center gap-1.5 font-bold text-slate-500 uppercase tracking-wider">
                    <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                    </svg>
                    </span>`,
            "paginate": { "first": "Primero", "last": "Último", "next": "Sig.", "previous": "Ant." }
        };

        $('#tabla-ingresos').DataTable({ "pageLength": 10, "lengthMenu": [5, 10, 25, 50], "language": configuracionLenguaje, "order": [[1, "desc"]] });
        $('#tabla-entregas').DataTable({ "pageLength": 10, "lengthMenu": [5, 10, 25, 50], "language": configuracionLenguaje, "order": [[1, "desc"]] });


        // --- 2. MOTOR DE PAGINACIÓN Y BÚSQUEDA MÓVIL NATIVO ---
        function crearPaginacionMovil(claseTarjeta, idPrev, idNext, idInfo, idBuscar) {
            let paginaActual = 1;
            const tarjetasPorPagina = 4;

            function actualizarUI() {
                let filtro = $(idBuscar).val().toLowerCase();
                let tarjetasVisibles = $(claseTarjeta).filter(function() {
                    return $(this).attr('data-search').includes(filtro);
                });

                let totalTarjetas = tarjetasVisibles.length;
                let totalPaginas = Math.ceil(totalTarjetas / tarjetasPorPagina) || 1;

                if (paginaActual > totalPaginas) paginaActual = totalPaginas;

                // Ocultar todas primero
                $(claseTarjeta).addClass('hidden');

                // Mostrar solo el bloque de la página actual filtrada
                let inicio = (paginaActual - 1) * tarjetasPorPagina;
                let fin = inicio + tarjetasPorPagina;
                
                tarjetasVisibles.slice(inicio, fin).removeClass('hidden');

                // Actualizar info y estado de botones
                $(idInfo).text(`Pág. ${paginaActual} de ${totalPaginas}`);
                $(idPrev).prop('disabled', paginaActual === 1);
                $(idNext).prop('disabled', paginaActual === totalPaginas);
            }

            $(idPrev).click(function() { if (paginaActual > 1) { paginaActual--; actualizarUI(); } });
            $(idNext).click(function() { paginaActual++; actualizarUI(); });
            $(idBuscar).on('input', function() { paginaActual = 1; actualizarUI(); });

            actualizarUI(); // Ejecución inicial
        }

        // Inicializar los motores independientes para móviles
        crearPaginacionMovil('.tarjeta-movil-ingreso', '#prev-movil-ingresos', '#next-movil-ingresos', '#info-movil-ingresos', '#buscar-movil-ingresos');
        crearPaginacionMovil('.tarjeta-movil-entrega', '#prev-movil-entregas', '#next-movil-entregas', '#info-movil-entregas', '#buscar-movil-entregas');
    });
</script>

</body>
</html>
<?php $conn->close(); ?>