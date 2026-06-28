<?php
session_start();

// 1. CONTROL DE ACCESO: Entran voluntarios y administradores centrales
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['voluntario', 'administrador_centro','administrador_central'])) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once('conexion.php');
    
    $usuario_id = $_SESSION['usuario_id'];
    $es_admin = ($_SESSION['rol'] === 'administrador_central');
    $centro_id = intval($_SESSION['centro_id']); 

    // ==========================================
    // PROCESAR PROCESOS POST (REGISTRO Y EDICIÓN)
    // ==========================================
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
// A. REGISTRO DE NUEVA VÍCTIMA (Soporta envío tradicional y AJAX)
        if (isset($_POST['registrar_victima'])) {
            $es_ajax = (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');
            
            $centro_destino = $centro_id;
            $nombre = !empty(trim($_POST['nombre_apellido'])) ? $conn->real_escape_string($_POST['nombre_apellido']) : 'Desconocido';
            $cedula = !empty(trim($_POST['cedula'])) ? $conn->real_escape_string($_POST['cedula']) : 'X-XXXXXXXXX';
            $edad = intval($_POST['edad_aproximada']);
            $estado_logistico = $conn->real_escape_string($_POST['estado_logistico']);
            $gravedad = $conn->real_escape_string($_POST['gravedad_triaje']);
            $sintoma = $conn->real_escape_string($_POST['sintoma_principal']);
            $red_apoyo = $conn->real_escape_string($_POST['red_o_apoyo']);
            $observaciones = !empty(trim($_POST['observaciones'])) ? $conn->real_escape_string($_POST['observaciones']) : NULL;

            $conn->begin_transaction();
            $stmt = $conn->prepare("INSERT INTO victimas (centro_id, usuario_id, nombre_apellido, edad_aproximada, cedula, estado_logistico, gravedad_triaje, sintoma_principal, red_o_apoyo, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisissssss", $centro_destino, $usuario_id, $nombre, $edad, $cedula, $estado_logistico, $gravedad, $sintoma, $red_apoyo, $observaciones);
            $stmt->execute();
            $conn->commit();
            $stmt->close();
            
            if ($es_ajax) {
                echo json_encode(['status' => 'success', 'message' => 'Sincronizado correctamente']);
                exit;
            }

            echo "<script>alert('¡Paciente registrada con éxito!'); window.location.href='atencion_pacientes.php';</script>";
            exit;
        }

        // B. ACTUALIZACIÓN PARCIAL DE VÍCTIMA (EDICIÓN SOLICITADA)
        if (isset($_POST['editar_victima'])) {
            $id_victima = intval($_POST['id_victima']);
            $nombre = !empty(trim($_POST['edit_nombre_apellido'])) ? $conn->real_escape_string($_POST['edit_nombre_apellido']) : 'Desconocido';
            $cedula = !empty(trim($_POST['edit_cedula'])) ? $conn->real_escape_string($_POST['edit_cedula']) : 'X-XXXXXXXXX';
            $estado_logistico = $conn->real_escape_string($_POST['edit_estado_logistico']);
            $gravedad = $conn->real_escape_string($_POST['edit_gravedad_triaje']);
            $observaciones = !empty(trim($_POST['edit_observaciones'])) ? $conn->real_escape_string($_POST['edit_observaciones']) : NULL;

            $conn->begin_transaction();
            
            // Si es voluntario, protegemos que solo pueda editar pacientes de su propio centro por seguridad en backend
            if (!$es_admin) {
                $check = $conn->query("SELECT id FROM victimas WHERE id = $id_victima AND centro_id = $centro_id");
                if ($check->num_rows === 0) { throw new Exception("No tienes autorización para modificar este registro."); }
            }

            $stmt = $conn->prepare("UPDATE victimas SET nombre_apellido = ?, cedula = ?, estado_logistico = ?, gravedad_triaje = ?, observaciones = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $nombre, $cedula, $estado_logistico, $gravedad, $observaciones, $id_victima);
            $stmt->execute();
            $conn->commit();
            $stmt->close();

            echo "<script>alert('¡Registro del paciente actualizado!'); window.location.href='atencion_pacientes.php';</script>";
            exit;
        }

        if (isset($_POST['eliminar_victima'])) {
            $id_victima = intval($_POST['id_victima']);

            $conn->begin_transaction();
            
            // Protección de rol: Solo permite eliminar si pertenece a su centro (o si es admin central)
            if (!$es_admin) {
                $check = $conn->query("SELECT id FROM victimas WHERE id = $id_victima AND centro_id = $centro_id");
                if ($check->num_rows === 0) { throw new Exception("No tienes autorización para eliminar este registro."); }
            }

            $stmt = $conn->prepare("DELETE FROM victimas WHERE id = ?");
            $stmt->bind_param("i", $id_victima);
            $stmt->execute();
            $conn->commit();
            $stmt->close();

            echo "<script>alert('¡Registro de paciente eliminado correctamente!'); window.location.href='atencion_pacientes.php';</script>";
            exit;
        }

    }

    // 3. CONSULTA FILTRADA SEGÚN EL ROL
    if ($es_admin) {
        $query = "SELECT v.*, u.nombre AS voluntario_nombre, c.nombre AS centro_nombre 
                  FROM victimas v
                  JOIN usuarios u ON v.usuario_id = u.id
                  JOIN centros_acopio c ON v.centro_id = c.id
                  ORDER BY v.fecha_registro DESC";
    } else {
        $query = "SELECT v.*, u.nombre AS voluntario_nombre, c.nombre AS centro_nombre  
                  FROM victimas v
                  JOIN usuarios u ON v.usuario_id = u.id
                  JOIN centros_acopio c ON v.centro_id = c.id
                  WHERE v.centro_id = $centro_id 
                  ORDER BY v.fecha_registro DESC";
    }
    
    $victimas = $conn->query($query);
    $todos_los_centros = $es_admin ? $conn->query("SELECT id, nombre FROM centros_acopio ORDER BY nombre ASC") : null;

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) { $conn->rollback(); }
    echo "<script>alert('❌ Error: " . addslashes($e->getMessage()) . "'); window.location.href='atencion_pacientes.php';</script>";
    exit;
}

include('header.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Atención a Pacientes y Triaje</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        .dataTables_wrapper .dataTables_filter input { background-color: #f8fafc !important; border: 1px solid #e2e8f0 !important; border-radius: 0.5rem !important; padding: 0.4rem 0.8rem !important; font-size: 0.75rem !important; margin-left: 0.5rem !important; }
        .dataTables_wrapper .dataTables_length select { background-color: #f8fafc !important; border: 1px solid #e2e8f0 !important; border-radius: 0.5rem !important; padding: 0.3rem 1.5rem 0.3rem 0.5rem !important; font-size: 0.75rem !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #0f172a !important; color: white !important; border-radius: 0.5rem !important; border: none !important; }
        table.dataTable { border-collapse: collapse !important; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    
    <!-- CABECERA -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                 <?= $es_admin ? ' Monitoreo Global' : ' Triaje de Pacientes' ?>
            </h2>
            <p class="text-sm text-slate-500 font-medium">Control de damnificados, estados médicos y actualización inmediata de filiación</p>
        </div>
        <div>
            <button onclick="abrirModal()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 text-white font-bold text-sm rounded-xl hover:bg-slate-800 shadow-md transition cursor-pointer">
                ➕ Agregar Paciente
            </button>
        </div>
    </div>

    <!-- TABLA PRINCIPAL CARD -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
        
        <?php if ($victimas->num_rows === 0): ?>
            <div class="text-center py-12 text-slate-400 text-sm font-medium">
                No hay pacientes registradas bajo estos parámetros de búsqueda.
            </div>
        <?php else: ?>

            <!-- BUSCADOR MÓVIL -->
            <div class="block md:hidden mb-4">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">
                                            <span class="inline-flex items-center gap-1.5 font-bold text-slate-500 uppercase tracking-wider">
                        <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                        </svg> Buscar Pacientes:
                       </span>
                </label>
                <input type="text" id="buscar-movil-victimas" placeholder="Escribe nombre, cédula, lesión..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-hidden">
            </div>

            <!-- TABLA ESCRITORIO -->
            <div class="hidden md:block overflow-x-auto">
                <table id="tabla-victimas" class="w-full text-left text-sm border-collapse display">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                            <th class="p-3">Paciente / Cédula</th>
                            <th class="p-3">Estado Logístico</th>
                            <?php if($es_admin): ?> <th class="p-3">Centro</th> <?php endif; ?>
                            <th class="p-3">Edad</th>
                            <th class="p-3">Gravedad</th>
                            <th class="p-3">Síntoma Principal / Notas</th>
                            <th class="p-3">Red Apoyo</th>
                            <th class="p-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php while($row = $victimas->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/80 transition duration-100">
                                <td class="p-3">
                                    <span class="font-bold text-slate-900 block"><?= htmlspecialchars($row['nombre_apellido']); ?></span>
                                    <span class="text-xs font-mono text-slate-400"><?= htmlspecialchars($row['cedula']); ?></span>
                                </td>
                                <td class="p-3 text-xs font-bold">
                                    <?php 
                                    $est = $row['estado_logistico'];
                                    if($est == 'Sin Atender') echo '<span class="px-2 py-1 bg-slate-100 text-slate-700 border border-slate-300 rounded-lg">En Espera</span>';
                                    if($est == 'Atendido') echo '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-lg">Estabilizado</span>';
                                    if($est == 'Despachado') echo '<span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-lg">Dado de Alta</span>';
                                    if($est == 'Fallecido') echo '<span class="px-2 py-1 bg-neutral-900 text-white rounded-lg">Fallecido</span>';
                                    ?>
                                </td>
                                <?php if($es_admin): ?>
                                    <td class="p-3 font-bold text-xs text-slate-500">
                                        <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-200"><?= htmlspecialchars($row['centro_nombre']); ?></span>
                                    </td>
                                <?php endif; ?>
                                
                                <td class="p-3 font-semibold text-slate-600"><?= $row['edad_aproximada']; ?> <span class="text-xs text-slate-400 font-normal">años</span></td>
                                <td class="p-3">
                                    <?php if($row['gravedad_triaje'] === 'Leve'): ?>
                                        <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-emerald-100 text-emerald-800">Leve</span>
                                    <?php elseif($row['gravedad_triaje'] === 'Moderado'): ?>
                                        <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-amber-100 text-amber-800">Moderado</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-rose-100 text-rose-800 animate-pulse">Crítico</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-xs">
                                    <span class="font-bold text-slate-800 block"><?= htmlspecialchars($row['sintoma_principal']); ?></span>
                                    <?php if($row['observaciones']): ?>
                                        <span class="text-slate-400 italic block mt-0.5">"<?= htmlspecialchars($row['observaciones']); ?>"</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-xs font-bold">
                                    <?= $row['red_o_apoyo'] === 'Sí' ? '<span class="text-emerald-600">Acompañado</span>' : '<span class="text-amber-600">Solo / Buscando</span>'; ?>
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <!-- BOTÓN DE EDICIÓN DINÁMICA -->
                                    <button type="button" 
                                            onclick='abrirModalEdicion(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                            class="px-2.5 py-1.5 text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200 rounded-lg transition shadow-2xs cursor-pointer">
                                        ✏️ Editar
                                    </button>
                                    <form method="POST" action="" class="inline-block" onsubmit="return confirm('¿Estás seguro de que deseas eliminar permanentemente a este paciente?');">
                                        <input type="hidden" name="eliminar_victima" value="1">
                                        <input type="hidden" name="id_victima" value="<?= $row['id'] ?>">
                                        <button type="submit" class="px-2.5 py-1.5 text-xs font-bold bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 rounded-lg transition shadow-sm cursor-pointer">
                                            🗑️ Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- TARJETAS MÓVILES -->
            <div id="contenedor-movil-victimas" class="grid grid-cols-1 gap-3 md:hidden">
                <?php 
                $victimas->data_seek(0); 
                while($row = $victimas->fetch_assoc()): 
                ?>
                    <div class="tarjeta-movil bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col gap-2"
                         data-search="<?= strtolower(htmlspecialchars($row['nombre_apellido'] . " " . $row['cedula'] . " " . $row['sintoma_principal'] . " " . $row['centro_nombre'])); ?>">
                        
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                            <span class="font-mono text-[10px] text-slate-400">Centro: <?= htmlspecialchars($row['centro_nombre']); ?></span>
                            <div>
                                <?php if($row['gravedad_triaje'] === 'Leve'): ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-800 uppercase">Leve</span>
                                <?php elseif($row['gravedad_triaje'] === 'Moderado'): ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800 uppercase">Moderado</span>
                                <?php else: ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black bg-rose-100 text-rose-800 uppercase">Crítico</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-base font-black text-slate-900 leading-tight"><?= htmlspecialchars($row['nombre_apellido']); ?></div>
                                <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($row['cedula']); ?> • <?= $row['edad_aproximada']; ?> años</div>
                            </div>
                            <button type="button" onclick='abrirModalEdicion(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="text-sm p-1.5 bg-white border border-slate-200 rounded-lg shadow-2xs">✏️</button>
                        </div>
                        
                        <div class="bg-white p-2.5 rounded-lg border border-slate-100 text-xs text-slate-700 space-y-1">
                            <div><strong>Síntoma:</strong> <?= htmlspecialchars($row['sintoma_principal']); ?></div>
                            <?php if($row['observaciones']): ?>
                                <div class="text-slate-400 italic">"<?= htmlspecialchars($row['observaciones']); ?>"</div>
                            <?php endif; ?>
                        </div>

                        <div class="flex justify-between items-center border-t border-slate-200/40 pt-2 text-[11px]">
                            <span><?= $row['red_o_apoyo'] === 'Sí' ? 'Acompañado' : 'Buscando Familiar'; ?></span>
                            <strong><?= htmlspecialchars($row['estado_logistico']); ?></strong>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- PAGINACIÓN MÓVIL -->
            <div class="flex md:hidden items-center justify-between mt-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                <button id="prev-movil" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg disabled:opacity-50">Ant.</button>
                <span id="info-movil" class="text-xs font-semibold text-slate-500">Pág. 1</span>
                <button id="next-movil" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg disabled:opacity-50">Sig.</button>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL A: REGISTRO DE NUEVA VÍCTIMA         -->
<!-- ========================================== -->
<div id="modal-victima" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
    <div class="bg-white rounded-2xl border border-slate-200 max-w-xl w-full p-6 shadow-xl space-y-4 my-8 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <div>
                <h3 class="text-base font-black text-slate-900 uppercase tracking-wide">📋 Registro de Pacientes</h3>
                <p class="text-xs text-slate-500">Completa el formulario de ingreso al sistema.</p>
            </div>
            <button onclick="cerrarModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
        </div>

        <form id="form-registro-victima" action="" method="POST" class="space-y-4 text-left">
            <input type="hidden" name="registrar_victima" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600">Nombre y Apellido (Opcional):</label>
                    <input type="text" name="nombre_apellido" placeholder="Desconocido" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600">Cédula de Identidad (Opcional):</label>
                    <input type="text" name="cedula" placeholder="Desconocido" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600">Edad Aproximada <span class="text-rose-500">*</span>:</label>
                    <input type="number" name="edad_aproximada" required min="0" max="120" placeholder="Ej: 35" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600">¿Tiene Red de Apoyo / Familia? <span class="text-rose-500">*</span>:</label>
                    <select name="red_o_apoyo" required class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                        <option value="Sí">Sí (Está acompañado / Con comunicación)</option>
                        <option value="No">No (Está solo o buscando un familiar)</option>
                    </select>
                </div>
            </div>

            <div class="space-y-1 bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                <label class="text-xs font-bold text-slate-700 block mb-1">Estado Logístico Actual <span class="text-rose-500">*</span>:</label>
                <select name="estado_logistico" required class="w-full text-sm bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden mb-2">
                    <option value="Sin Atender">Sin Atender</option>
                    <option value="Atendido">Atendido</option>
                    <option value="Despachado">Despachado</option>
                    <option value="Fallecido">Fallecido</option>
                </select>
            </div>

            <div class="p-3 bg-rose-50/50 border border-rose-100 rounded-xl space-y-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-800 block">Condición Médica (Triaje Rápido)</span>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div class="sm:col-span-1 space-y-1">
                        <label class="text-xs font-bold text-slate-600">Gravedad <span class="text-rose-500">*</span>:</label>
                        <select name="gravedad_triaje" required class="w-full text-sm bg-white border border-slate-200 rounded-xl px-2 py-2 text-slate-800 focus:outline-hidden">
                            <option value="Leve">Leve</option>
                            <option value="Moderado">Moderado</option>
                            <option value="Crítico">Crítico</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 space-y-1">
                        <label class="text-xs font-bold text-slate-600">Lesión o Síntoma Principal <span class="text-rose-500">*</span>:</label>
                        <input type="text" name="sintoma_principal" required placeholder="Ej: Fractura, Deshidratación..." class="w-full text-sm bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                    </div>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-600">Acción Requerida / Observaciones Adicionales:</label>
                <textarea name="observaciones" rows="2" placeholder="Ej: Notas..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-800 focus:outline-hidden"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="cerrarModal()" class="px-3 py-2 text-xs font-bold text-slate-500 hover:bg-slate-50 rounded-lg transition">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white rounded-lg shadow-sm transition">Guardar Registro</button>
            </div>
        </form> 
        <script>
            $(document).ready(function() {
                // Inicialización de DataTable
    $(document).ready(function() {
        $('#tabla-victimas').DataTable({
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50],
            "language": {
                "lengthMenu": "Mostrar _MENU_  pacientes",
                "zeroRecords": "No se encontraron pacientes con esos criterios",
                "info": "Mostrando página _PAGE_ de _PAGES_",
                "search": `<span class="inline-flex items-center gap-1.5 font-bold text-slate-500 uppercase tracking-wider">
                        <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                        </svg>
                       </span>`,
                "paginate": { "next": "Sig.", "previous": "Ant." }
            },
            "order": [[0, "desc"]]
        });

        function inicializarMotorMovil() {
            let paginaActual = 1;
            const tarjetasPorPagina = 10;

            function renderizarMovil() {
                let filtro = $('#buscar-movil-victimas').val().toLowerCase();
                let tarjetasFiltradas = $('.tarjeta-movil').filter(function() {
                    return $(this).attr('data-search').includes(filtro);
                });

                let totalTarjetas = tarjetasFiltradas.length;
                let totalPaginas = Math.ceil(totalTarjetas / tarjetasPorPagina) || 1;

                if (paginaActual > totalPaginas) paginaActual = totalPaginas;

                $('.tarjeta-movil').addClass('hidden');

                let inicio = (paginaActual - 1) * tarjetasPorPagina;
                let fin = inicio + tarjetasPorPagina;
                tarjetasFiltradas.slice(inicio, fin).removeClass('hidden');

                $('#info-movil').text(`Pág. ${paginaActual} de ${totalPaginas}`);
                $('#prev-movil').prop('disabled', paginaActual === 1);
                $('#next-movil').prop('disabled', paginaActual === totalPaginas);
            }

            $('#prev-movil').click(function() { if (paginaActual > 1) { paginaActual--; renderizarMovil(); } });
            $('#next-movil').click(function() { paginaActual++; renderizarMovil(); });
            $('#buscar-movil-victimas').on('input', function() { paginaActual = 1; renderizarMovil(); });

            renderizarMovil();
        }

        if($('#contenedor-movil-victimas').length) {
            inicializarMotorMovil();
        }
    });
                // Interceptar el envío del formulario de registro
                const formRegistro = document.getElementById('form-registro-victima');
                if(formRegistro) {
                    formRegistro.addEventListener('submit', function(e) {
                        // Verificar si el dispositivo está offline
                        if (!navigator.onLine) {
                            e.preventDefault(); // Detener el envío al servidor en seco
                            
                            // Extraer los datos del formulario
                            const formData = new FormData(formRegistro);
                            const datosVictima = {};
                            formData.forEach((value, key) => { datosVictima[key] = value; });
                            
                            // Generar un ID único temporal para el LocalStorage
                            datosVictima['timestamp_local'] = Date.now();

                            // Obtener registros anteriores en cola o iniciar array vacío
                            let colaOffline = JSON.parse(localStorage.getItem('cola_victimas_offline')) || [];
                            colaOffline.push(datosVictima);
                            
                            // Guardar de vuelta en LocalStorage
                            localStorage.setItem('cola_victimas_offline', JSON.stringify(colaOffline));

                            alert('⚠️ SIN CONEXIÓN: Los datos se han guardado de forma segura en tu teléfono/computador de manera temporal. Se subirán al servidor automáticamente apenas recuperes señal de internet.');
                            
                            formRegistro.reset();
                            cerrarModal();
                            actualizarContadorOffline();
                        }
                    });
                }

                // Función para enviar la cola offline al servidor
                function sincronizarDatosOffline() {
                    let colaOffline = JSON.parse(localStorage.getItem('cola_victimas_offline')) || [];
                    if (colaOffline.length === 0) return;

                    console.log(`Intentando sincronizar ${colaOffline.length} registros pendientes...`);

                    // Usamos recursividad/promesas en serie para no sobrecargar el servidor
                    let promesas = colaOffline.map(data => {
                        // Agregar flag indicando que es una petición AJAX interna
                        let bodyData = new URLSearchParams();
                        for (let key in data) { bodyData.append(key, data[key]); }
                        bodyData.append('is_ajax', '1');

                        return fetch('atencion_pacientes.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: bodyData
                        })
                        .then(res => res.json())
                        .then(resData => {
                            if(resData.status === 'success') { return data.timestamp_local; }
                            return null; 
                        })
                        .catch(() => null); // Si vuelve a fallar la red en pleno envío, retorna null
                    });

                    Promise.all(promesas).then(resultados => {
                        // Filtrar de la cola los registros que se subieron con éxito
                        let IDsExitosos = resultados.filter(id => id !== null);
                        let nuevaCola = colaOffline.filter(item => !IDsExitosos.includes(item.timestamp_local));
                        
                        localStorage.setItem('cola_victimas_offline', JSON.stringify(nuevaCola));
                        actualizarContadorOffline();

                        if (IDsExitosos.length > 0) {
                            alert(`✅ ¡Conexión restablecida! Se han sincronizado exitosamente ${IDsExitosos.length} pacientes guardadas offline.`);
                            window.location.reload(); // Recargar para ver los nuevos datos en la tabla
                        }
                    });
                }

                // Función visual para alertar al voluntario de registros pendientes en la UI
                function actualizarContadorOffline() {
                    let colaOffline = JSON.parse(localStorage.getItem('cola_victimas_offline')) || [];
                    let banner = document.getElementById('banner-offline');
                    
                    if (colaOffline.length > 0) {
                        if (!banner) {
                            $('.max-w-7xl').first().prepend(`
                                <div id="banner-offline" class="mb-4 bg-amber-500 text-white px-4 py-3 rounded-xl font-bold text-sm flex justify-between items-center animate-pulse">
                                    <span>⏳ Tienes ${colaOffline.length} registros pendientes por sincronizar debido a fallas de red.</span>
                                    <button id="btn-sync-manual" class="bg-white text-slate-900 px-2.5 py-1 rounded-lg text-xs cursor-pointer">Sincronizar Ahora</button>
                                </div>
                            `);
                        } else {
                            banner.querySelector('span').innerText = `⏳ Tienes ${colaOffline.length} registros pendientes por sincronizar debido a fallas de red.`;
                        }
                    } else if (banner) {
                        banner.remove();
                    }
                }

                // Escuchadores de red del navegador globales
                window.addEventListener('online', sincronizarDatosOffline);
                $(document).on('click', '#btn-sync-manual', sincronizarDatosOffline);

                // Ejecutar revisiones iniciales al cargar la página
                actualizarContadorOffline();
                if (navigator.onLine) { sincronizarDatosOffline(); }
            });

            // Controladores de modales
            function abrirModal() { document.getElementById('modal-victima').classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
            function cerrarModal() { document.getElementById('modal-victima').classList.add('hidden'); document.body.style.overflow = 'auto'; }
            function abrirModalEdicion(victima) {
                document.getElementById('edit_id_victima').value = victima.id;
                document.getElementById('edit_nombre_apellido').value = victima.nombre_apellido === 'Desconocido' ? '' : victima.nombre_apellido;
                document.getElementById('edit_cedula').value = victima.cedula === 'Desconocido' ? '' : victima.cedula;
                document.getElementById('edit_estado_logistico').value = victima.estado_logistico;
                document.getElementById('edit_gravedad_triaje').value = victima.gravedad_triaje;
                document.getElementById('edit_observaciones').value = victima.observaciones || '';
                document.getElementById('modal-editar-victima').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            function cerrarModalEdicion() { document.getElementById('modal-editar-victima').classList.add('hidden'); document.body.style.overflow = 'auto'; }
        </script>
    </div>
</div>


<!-- ========================================== -->
<!-- MODAL B: EDICIÓN / ACTUALIZACIÓN DE VÍCTIMA  -->
<!-- ========================================== -->
<div id="modal-editar-victima" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
    <div class="bg-white rounded-2xl border border-slate-200 max-w-xl w-full p-6 shadow-xl space-y-4 my-8 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <div>
                <h3 class="text-base font-black text-slate-900 uppercase tracking-wide">✏️ Editar Datos del Paciente</h3>
                <p class="text-xs text-slate-500">Actualiza la identidad o cambia el estado logístico/médico del paciente.</p>
            </div>
            <button onclick="cerrarModalEdicion()" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
        </div>

        <form action="" method="POST" class="space-y-4 text-left">
            <input type="hidden" name="editar_victima" value="1">
            <input type="hidden" name="id_victima" id="edit_id_victima">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600">Nombre y Apellido:</label>
                    <input type="text" name="edit_nombre_apellido" id="edit_nombre_apellido" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600">Cédula de Identidad:</label>
                    <input type="text" name="edit_cedula" id="edit_cedula" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                </div>
            </div>

            <div class="space-y-1 bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                <label class="text-xs font-bold text-slate-700 block mb-1">Modificar Estado Logístico <span class="text-rose-500">*</span>:</label>
                <select name="edit_estado_logistico" id="edit_estado_logistico" required class="w-full text-sm bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                    <option value="Sin Atender">Sin Atender</option>
                    <option value="Atendido">Atendido</option>
                    <option value="Despachado">Despachado</option>
                    <option value="Fallecido">Fallecido</option>
                </select>
            </div>

            <div class="space-y-1 bg-rose-50/40 p-3 rounded-xl border border-rose-100">
                <label class="text-xs font-bold text-rose-900 block mb-1">Reevaluar Gravedad del Triaje <span class="text-rose-500">*</span>:</label>
                <select name="edit_gravedad_triaje" id="edit_gravedad_triaje" required class="w-full text-sm bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden">
                    <option value="Leve">Leve</option>
                    <option value="Moderado">Moderado</option>
                    <option value="Crítico">Crítico</option>
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-600">Acción Requerida / Observaciones Adicionales:</label>
                <textarea name="edit_observaciones" id="edit_observaciones" rows="2" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-800 focus:outline-hidden"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="cerrarModalEdicion()" class="px-3 py-2 text-xs font-bold text-slate-500 hover:bg-slate-50 rounded-lg transition">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white rounded-lg shadow-sm transition">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>


<script>
    // Controladores del Modal A (Registro)
    function abrirModal() {
        document.getElementById('modal-victima').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function cerrarModal() {
        document.getElementById('modal-victima').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Controladores del Modal B (Edición Dinámica)
    function abrirModalEdicion(victima) {
        // Cargar los inputs del formulario usando los valores del objeto JSON
        document.getElementById('edit_id_victima').value = victima.id;
        document.getElementById('edit_nombre_apellido').value = victima.nombre_apellido === 'Desconocido' ? '' : victima.nombre_apellido;
        document.getElementById('edit_cedula').value = victima.cedula === 'Desconocido' ? '' : victima.cedula;
        document.getElementById('edit_estado_logistico').value = victima.estado_logistico;
        document.getElementById('edit_gravedad_triaje').value = victima.gravedad_triaje;
        document.getElementById('edit_observaciones').value = victima.observaciones || '';

        document.getElementById('modal-editar-victima').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function cerrarModalEdicion() {
        document.getElementById('modal-editar-victima').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
</script>

</body>
</html>
<?php $conn->close(); ?>