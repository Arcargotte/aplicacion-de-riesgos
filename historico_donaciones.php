<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { 
    header("Location: login.php"); 
    exit; 
}

include('header.php');
require_once('conexion.php');

$centro_id = isset($_SESSION['centro_id']) ? intval($_SESSION['centro_id']) : 1; 

// ─────────────────────────────────────────────
// TABLA 1: ENTRADAS
// ─────────────────────────────────────────────
$sql_entradas = "
    SELECT 
        m.id, 
        m.fecha AS fecha_hora, 
        m.tipo_movimiento,
        COALESCE(ca_orig.nombre, org.nombre, CONCAT(p.nombre, ' ', COALESCE(p.apellido, '')), 'Donación Externa Anón') AS origen,
        m.detalles, 
        u.nombre AS usuario_nombre,
        GROUP_CONCAT(ins.nombre ORDER BY ins.nombre SEPARATOR ', ') AS insumos,
        GROUP_CONCAT(dm.cantidad ORDER BY ins.nombre SEPARATOR ', ') AS cantidades
    FROM movimientos m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN centros_acopio ca_orig ON m.centro_origen_id = ca_orig.id
    LEFT JOIN organizaciones org ON m.organizacion_id = org.id
    LEFT JOIN personas p ON m.persona_id = p.id
    LEFT JOIN detalles_movimiento dm ON dm.movimiento_id = m.id
    LEFT JOIN insumos ins ON dm.insumo_id = ins.id
    WHERE m.centro_destino_id = $centro_id
    GROUP BY m.id
    ORDER BY m.fecha DESC
";
$res_entradas = $conn->query($sql_entradas);
$entradas = [];
if ($res_entradas) {
    while ($fila = $res_entradas->fetch_assoc()) {
        $entradas[] = $fila;
    }
}

// ─────────────────────────────────────────────
// TABLA 2: SALIDAS
// ─────────────────────────────────────────────
$sql_salidas = "
    SELECT 
        m.id, 
        m.fecha AS fecha_hora, 
        m.tipo_movimiento,
        COALESCE(ca_dest.nombre, org.nombre, CONCAT(p.nombre, ' ', COALESCE(p.apellido, '')), 'Destino No Registrado') AS destino,
        m.detalles, 
        u.nombre AS usuario_nombre,
        GROUP_CONCAT(ins.nombre ORDER BY ins.nombre SEPARATOR ', ') AS insumos,
        GROUP_CONCAT(dm.cantidad ORDER BY ins.nombre SEPARATOR ', ') AS cantidades
    FROM movimientos m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN centros_acopio ca_dest ON m.centro_destino_id = ca_dest.id
    LEFT JOIN organizaciones org ON m.organizacion_id = org.id
    LEFT JOIN personas p ON m.persona_id = p.id
    LEFT JOIN detalles_movimiento dm ON dm.movimiento_id = m.id
    LEFT JOIN insumos ins ON dm.insumo_id = ins.id
    WHERE m.centro_origen_id = $centro_id
    GROUP BY m.id
    ORDER BY m.fecha DESC
";
$res_salidas = $conn->query($sql_salidas);
$salidas = [];
if ($res_salidas) {
    while ($fila = $res_salidas->fetch_assoc()) {
        $salidas[] = $fila;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Histórico de Movimientos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        /* Ajustes base DataTables */
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
        table.dataTable { border-collapse: collapse !important; }

        /* MAGIA CSS: Transformación Adaptativa a Tarjetas en Móviles (Menor a 1024px) */
        @media (max-width: 1023px) {
            .dataTables_wrapper .dataTables_filter {
                float: none !important;
                text-align: left !important;
                margin-bottom: 1rem;
            }
            .dataTables_wrapper .dataTables_length {
                float: none !important;
                text-align: left !important;
                margin-bottom: 1rem;
            }
            table.dataTable uppercase, 
            table.dataTable thead { 
                display: none !important; 
            }
            table.dataTable tbody tr { 
                display: block !important;
                background: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 0.75rem !important;
                margin-bottom: 1rem !important;
                padding: 1rem !important;
                box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
            }
            table.dataTable tbody td { 
                display: block !important;
                text-align: left !important;
                padding: 0.25rem 0 !important;
                border: none !important;
                font-size: 0.875rem !important;
            }
            /* Generación de etiquetas en móviles para mantener contexto */
            table.dataTable tbody td:nth-child(1):before { content: "ID: "; font-weight: 800; color: #64748b; }
            table.dataTable tbody td:nth-child(2):before { content: "Fecha: "; font-weight: 800; color: #64748b; }
            table.dataTable tbody td:nth-child(3):before { content: "Entidad: "; font-weight: 800; color: #64748b; }
            table.dataTable tbody td:nth-child(4):before { content: "Detalle: "; font-weight: 800; color: #64748b; display: block; margin-top: 4px;}
            table.dataTable tbody td:nth-child(5):before { content: "Obs: "; font-weight: 800; color: #64748b; }
            table.dataTable tbody td:nth-child(6):before { content: "Responsable: "; font-weight: 800; color: #64748b; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 space-y-12">

    <div class="border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Historial de Inventario de mi Centro</h2>
        <p class="text-sm text-slate-500 font-medium">Auditoría de todos los insumos que entran y salen de tus instalaciones.</p>
    </div>

    <div class="space-y-4">
        <div class="flex flex-col">
            <h3 class="text-lg font-black text-emerald-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                Insumos Recibidos (Entradas)
            </h3>
            <p class="text-xs text-slate-500 font-medium">Todo lo que ingresó a tu centro, desde fuera u otros centros.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
            <?php if (empty($entradas)): ?>
                <div class="text-center py-8 text-slate-400 text-sm font-medium">No hay entradas registradas para este centro.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table id="tabla-ingresos" class="w-full text-left text-sm border-collapse display">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="p-3">ID</th>
                                <th class="p-3">Fecha</th>
                                <th class="p-3">Origen</th>
                                <th class="p-3">Insumos y Cantidad</th>
                                <th class="p-3">Observaciones</th>
                                <th class="p-3">Registrado Por</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php foreach ($entradas as $in): ?>
                                <tr class="hover:bg-slate-50/80 transition duration-100">
                                    <td class="p-3 font-mono font-bold text-slate-900">#IN-<?php echo $in['id']; ?></td>
                                    <td class="p-3"><?php echo date('d/m/Y g:i A', strtotime($in['fecha_hora'])); ?></td>
                                    <td class="p-3 font-semibold text-slate-800">
                                        <?php if(str_contains(strtolower($in['tipo_movimiento']), 'traslado')): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded uppercase mr-1">Traslado</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($in['origen']); ?>
                                    </td>
                                    <td class="p-3 text-slate-700">
                                        <?php if ($in['insumos']): ?>
                                            <span class="font-medium text-emerald-700"><?php echo htmlspecialchars($in['insumos']); ?></span>
                                            <br><span class="text-xs text-slate-500">Cantidades: <?php echo htmlspecialchars($in['cantidades']); ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-xs">Sin detalles</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-xs"><?php echo htmlspecialchars($in['detalles'] ?: '—'); ?></td>
                                    <td class="p-3 text-slate-700 text-xs"><?php echo htmlspecialchars($in['usuario_nombre']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="flex flex-col">
            <h3 class="text-lg font-black text-rose-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                Insumos Despachados (Salidas)
            </h3>
            <p class="text-xs text-slate-500 font-medium">Todo lo que salió de tu centro hacia beneficiarios u otros centros.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
            <?php if (empty($salidas)): ?>
                <div class="text-center py-8 text-slate-400 text-sm font-medium">No hay despachos registrados para este centro.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table id="tabla-despachos" class="w-full text-left text-sm border-collapse display">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="p-3">ID</th>
                                <th class="p-3">Fecha</th>
                                <th class="p-3">Enviado A</th>
                                <th class="p-3">Insumos y Cantidad</th>
                                <th class="p-3">Observaciones</th>
                                <th class="p-3">Despachado Por</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php foreach ($salidas as $out): ?>
                                <tr class="hover:bg-slate-50/80 transition duration-100">
                                    <td class="p-3 font-mono font-bold text-slate-900">#OUT-<?php echo $out['id']; ?></td>
                                    <td class="p-3"><?php echo date('d/m/Y g:i A', strtotime($out['fecha_hora'])); ?></td>
                                    <td class="p-3 font-semibold text-slate-900">
                                        <?php if(str_contains(strtolower($out['tipo_movimiento']), 'traslado')): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-amber-100 text-amber-800 px-2 py-0.5 rounded uppercase mr-1">Traslado</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($out['destino']); ?>
                                    </td>
                                    <td class="p-3 text-slate-700">
                                        <?php if ($out['insumos']): ?>
                                            <span class="font-medium text-rose-700"><?php echo htmlspecialchars($out['insumos']); ?></span>
                                            <br><span class="text-xs text-slate-500">Cantidades: <?php echo htmlspecialchars($out['cantidades']); ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-xs">Sin detalles</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-xs"><?php echo htmlspecialchars($out['detalles'] ?: '—'); ?></td>
                                    <td class="p-3 text-slate-700 text-xs"><?php echo htmlspecialchars($out['usuario_nombre']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    // Definimos el idioma de DataTables
    var lang = {
        "lengthMenu":   "Mostrar _MENU_ registros",
        "zeroRecords":  "No se encontraron resultados",
        "info":         "Pág _PAGE_ de _PAGES_",
        "infoEmpty":    "No hay registros",
        "infoFiltered": "(filtrado de _MAX_)",
        "search":       "Buscar:",
        "paginate": { "first": "Primero", "last": "Último", "next": "Sig.", "previous": "Ant." }
    };

    // Evaluamos el ancho de pantalla en tiempo de ejecución
    // 1024px es el breakpoint estándar 'lg' de Tailwind CSS
    var cantidadRegistros = ($(window).width() < 1024) ? 3 : 10;

    // Inicializamos la tabla de ingresos
    $('#tabla-ingresos').DataTable({ 
        pageLength: cantidadRegistros, 
        lengthMenu: [3, 5, 10, 25, 50], 
        language: lang, 
        order: [[1, 'desc']] 
    });

    // Inicializamos la tabla de despachos
    $('#tabla-despachos').DataTable({ 
        pageLength: cantidadRegistros, 
        lengthMenu: [3, 5, 10, 25, 50], 
        language: lang, 
        order: [[1, 'desc']] 
    });
});
</script>

</body>
</html>