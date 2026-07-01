<?php
session_start();

// CONTROL DE ACCESO
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['voluntario', 'administrador_centro','administrador_central'])) {
    header("Location: login");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once('conexion.php');
    
    $usuario_id = $_SESSION['usuario_id'];
    $es_admin = ($_SESSION['rol'] === 'administrador_central');
    $centro_id = intval($_SESSION['centro_id'] ?? 1); 

    // ==========================================
    // PROCESAR POST (REGISTRO, EDICIÓN, ELIMINACIÓN)
    // ==========================================
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // A. REGISTRO DE NUEVA FAMILIA (CENSO)
        if (isset($_POST['registrar_familia'])) {
            $conn->begin_transaction();

            // 1. Insertar Cabecera
            $iglesia = $conn->real_escape_string($_POST['iglesia']);
            $contacto = $conn->real_escape_string($_POST['contacto_principal']);
            $direccion = $conn->real_escape_string($_POST['direccion_principal']);
            $perdidas = $conn->real_escape_string($_POST['perdidas_vivienda']);
            $perdida_miembro = $conn->real_escape_string($_POST['perdida_miembro']);
            $necesidades = $conn->real_escape_string($_POST['necesidades_basicas']);
            $observaciones = $conn->real_escape_string($_POST['observaciones']);

            $sql_familia = "INSERT INTO censo_familias 
                            (usuario_id, iglesia, contacto_principal, direccion_principal, perdidas_vivienda, perdida_miembro, necesidades_basicas, observaciones) 
                            VALUES 
                            ($usuario_id, '$iglesia', '$contacto', '$direccion', '$perdidas', '$perdida_miembro', '$necesidades', '$observaciones')";
            $conn->query($sql_familia);
            $familia_id = $conn->insert_id;

            // 2. Insertar Miembros
            if (isset($_POST['miembro_nombre']) && is_array($_POST['miembro_nombre'])) {
                $stmt_miembro = $conn->prepare("INSERT INTO censo_miembros_familia (familia_id, nombre_apellido, parentesco, edad, telefono) VALUES (?, ?, ?, ?, ?)");
                for ($i = 0; $i < count($_POST['miembro_nombre']); $i++) {
                    $nombre = trim($_POST['miembro_nombre'][$i]);
                    if (!empty($nombre)) {
                        $parentesco = $_POST['miembro_parentesco'][$i];
                        $edad = intval($_POST['miembro_edad'][$i]);
                        $tel = $_POST['miembro_telefono'][$i];
                        $stmt_miembro->bind_param("issis", $familia_id, $nombre, $parentesco, $edad, $tel);
                        $stmt_miembro->execute();
                    }
                }
                $stmt_miembro->close();
            }

            // 3. Insertar Salud
            if (isset($_POST['salud_nombre']) && is_array($_POST['salud_nombre'])) {
                $stmt_salud = $conn->prepare("INSERT INTO censo_salud (familia_id, nombre_familiar, enfermedad, medicamento) VALUES (?, ?, ?, ?)");
                for ($i = 0; $i < count($_POST['salud_nombre']); $i++) {
                    $nombre_salud = trim($_POST['salud_nombre'][$i]);
                    if (!empty($nombre_salud)) {
                        $enfermedad = $_POST['salud_enfermedad'][$i];
                        $med = $_POST['salud_medicamento'][$i];
                        $stmt_salud->bind_param("isss", $familia_id, $nombre_salud, $enfermedad, $med);
                        $stmt_salud->execute();
                    }
                }
                $stmt_salud->close();
            }
            
            $conn->commit();
            echo "<script>alert('¡Familia censada con éxito!'); window.location.href='gestion_censo';</script>";
            exit;
        }

        // B. ACTUALIZACIÓN DE CABECERA DE FAMILIA, MIEMBROS Y SALUD
        if (isset($_POST['editar_familia'])) {
            $conn->begin_transaction();

            $id_familia = intval($_POST['id_familia']);
            $iglesia = $conn->real_escape_string($_POST['edit_iglesia']);
            $contacto = $conn->real_escape_string($_POST['edit_contacto_principal']);
            $direccion = $conn->real_escape_string($_POST['edit_direccion_principal']);
            $perdidas = $conn->real_escape_string($_POST['edit_perdidas_vivienda']);
            $perdida_miembro = $conn->real_escape_string($_POST['edit_perdida_miembro']);
            $necesidades = $conn->real_escape_string($_POST['edit_necesidades_basicas']);
            $observaciones = $conn->real_escape_string($_POST['edit_observaciones']);

            // 1. Actualizar datos principales
            $stmt = $conn->prepare("UPDATE censo_familias SET iglesia=?, contacto_principal=?, direccion_principal=?, perdidas_vivienda=?, perdida_miembro=?, necesidades_basicas=?, observaciones=? WHERE id=?");
            $stmt->bind_param("sssssssi", $iglesia, $contacto, $direccion, $perdidas, $perdida_miembro, $necesidades, $observaciones, $id_familia);
            $stmt->execute();
            $stmt->close();

            // 2. Reemplazar Miembros
            $conn->query("DELETE FROM censo_miembros_familia WHERE familia_id = $id_familia");
            if (isset($_POST['edit_miembro_nombre']) && is_array($_POST['edit_miembro_nombre'])) {
                $stmt_miembro = $conn->prepare("INSERT INTO censo_miembros_familia (familia_id, nombre_apellido, parentesco, edad, telefono) VALUES (?, ?, ?, ?, ?)");
                for ($i = 0; $i < count($_POST['edit_miembro_nombre']); $i++) {
                    $nombre = trim($_POST['edit_miembro_nombre'][$i]);
                    if (!empty($nombre)) {
                        $parentesco = $_POST['edit_miembro_parentesco'][$i];
                        $edad = intval($_POST['edit_miembro_edad'][$i]);
                        $tel = $_POST['edit_miembro_telefono'][$i];
                        $stmt_miembro->bind_param("issis", $id_familia, $nombre, $parentesco, $edad, $tel);
                        $stmt_miembro->execute();
                    }
                }
                $stmt_miembro->close();
            }

            // 3. Reemplazar Salud
            $conn->query("DELETE FROM censo_salud WHERE familia_id = $id_familia");
            if (isset($_POST['edit_salud_nombre']) && is_array($_POST['edit_salud_nombre'])) {
                $stmt_salud = $conn->prepare("INSERT INTO censo_salud (familia_id, nombre_familiar, enfermedad, medicamento) VALUES (?, ?, ?, ?)");
                for ($i = 0; $i < count($_POST['edit_salud_nombre']); $i++) {
                    $nombre_salud = trim($_POST['edit_salud_nombre'][$i]);
                    if (!empty($nombre_salud)) {
                        $enfermedad = $_POST['edit_salud_enfermedad'][$i];
                        $med = $_POST['edit_salud_medicamento'][$i];
                        $stmt_salud->bind_param("isss", $id_familia, $nombre_salud, $enfermedad, $med);
                        $stmt_salud->execute();
                    }
                }
                $stmt_salud->close();
            }

            $conn->commit();
            echo "<script>alert('¡Datos de la familia y miembros actualizados!'); window.location.href='gestion_censo';</script>";
            exit;
        }

        // C. ELIMINAR FAMILIA (El ON DELETE CASCADE en SQL borrará miembros y salud automáticamente)
        if (isset($_POST['eliminar_familia'])) {
            $id_familia = intval($_POST['id_familia']);
            $stmt = $conn->prepare("DELETE FROM censo_familias WHERE id = ?");
            $stmt->bind_param("i", $id_familia);
            $stmt->execute();
            $stmt->close();

            echo "<script>alert('¡Registro eliminado correctamente!'); window.location.href='gestion_censo';</script>";
            exit;
        }
    }

    // ==========================================
    // OBTENER DATOS PARA LA VISTA
    // ==========================================
    $query = "SELECT c.*, u.nombre AS voluntario_nombre, 
              (SELECT COUNT(*) FROM censo_miembros_familia WHERE familia_id = c.id) as total_miembros 
              FROM censo_familias c
              JOIN usuarios u ON c.usuario_id = u.id
              ORDER BY c.fecha_registro DESC";
    
    $resultado_familias = $conn->query($query);
    $familias = [];
    $total_familias = $resultado_familias->num_rows;

    while ($row = $resultado_familias->fetch_assoc()) {
        $f_id = intval($row['id']);
        
        // Obtener miembros
        $miembros_query = $conn->query("SELECT * FROM censo_miembros_familia WHERE familia_id = $f_id");
        $miembros = [];
        while ($m = $miembros_query->fetch_assoc()) { $miembros[] = $m; }
        $row['miembros'] = $miembros;

        // Obtener salud
        $salud_query = $conn->query("SELECT * FROM censo_salud WHERE familia_id = $f_id");
        $salud = [];
        while ($s = $salud_query->fetch_assoc()) { $salud[] = $s; }
        $row['salud'] = $salud;

        $familias[] = $row;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) { $conn->rollback(); }
    echo "<script>alert('❌ Error: " . addslashes($e->getMessage()) . "'); window.location.href='gestion_censo';</script>";
    exit;
}

include('header.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Censo de Familias</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Censo de Familias Afectadas</h2>
            <p class="text-sm text-slate-500 font-medium">Control de núcleos familiares y requerimientos básicos</p>
        </div>
        <div>
            <button onclick="abrirModal('modal-agregar')" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 shadow-md transition cursor-pointer">
                ➕ Censar Nueva Familia
            </button>
        </div>
    </div>

    <p class="text-sm text-slate-500 font-medium mb-4">Total de familias censadas: <?= $total_familias ?> </p>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
        
        <?php if ($total_familias === 0): ?>
            <div class="text-center py-12 text-slate-400 text-sm font-medium">
                No hay familias registradas en el censo.
            </div>
        <?php else: ?>

            <div class="block md:hidden mb-4">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Buscar Familias:</label>
                <input type="text" id="buscar-movil-familias" placeholder="Escribe dirección, iglesia, contacto..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none">
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table id="tabla-familias" class="w-full text-left text-sm border-collapse display">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                            <th class="p-3">ID / Voluntario</th>
                            <th class="p-3">Dirección Principal</th>
                            <th class="p-3">Integrantes del Núcleo</th>
                            <th class="p-3">Contacto e Iglesia</th>
                            <th class="p-3">Daños Vivienda</th>
                            <th class="p-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach($familias as $row): ?>
                            <tr class="hover:bg-slate-50/80 transition duration-100 align-top">
                                <td class="p-3">
                                    <span class="font-bold text-slate-900 block">Planilla #<?= $row['id'] ?></span>
                                    <span class="text-xs text-slate-400">Reg: <?= htmlspecialchars($row['voluntario_nombre']); ?></span>
                                </td>
                                <td class="p-3 font-medium text-slate-800">
                                    <?= htmlspecialchars($row['direccion_principal']); ?>
                                </td>
                                <td class="p-3 text-xs">
                                    <span class="px-2.5 py-0.5 bg-sky-100 text-sky-800 font-bold rounded-lg text-[10px] inline-block mb-1 border border-sky-200"><?= $row['total_miembros'] ?> Personas</span>
                                    <ul class="list-disc list-inside text-slate-600 space-y-0.5 mt-1">
                                        <?php foreach($row['miembros'] as $m): ?>
                                            <li><strong><?= htmlspecialchars($m['nombre_apellido']) ?></strong> (<?= $m['edad'] ?> años) - <?= htmlspecialchars($m['parentesco']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td class="p-3 text-xs">
                                    <span class="font-bold text-slate-800 block"> <?= htmlspecialchars($row['contacto_principal']); ?></span>
                                    <span class="text-slate-500"> <?= htmlspecialchars($row['iglesia']); ?></span>
                                </td>
                                <td class="p-3">
                                    <?php 
                                    $daños = $row['perdidas_vivienda'];
                                    if($daños == 'Sin Daños') echo '<span class="text-xs font-bold text-emerald-600">Sin Daños</span>';
                                    elseif($daños == 'Pérdida Total') echo '<span class="text-xs font-bold text-rose-600">Pérdida Total</span>';
                                    else echo '<span class="text-xs font-bold text-amber-600">'.htmlspecialchars($daños).'</span>';
                                    ?>
                                    <div class="text-[10px] text-slate-500 italic mt-1 max-w-[150px] truncate" title="<?= htmlspecialchars($row['necesidades_basicas'] ?: 'No especificadas'); ?>">
                                        Nec: <?= htmlspecialchars($row['necesidades_basicas'] ?: 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-2 flex-col lg:flex-row">
                                        <button type="button" onclick='abrirModalEdicion(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="px-2.5 py-1.5 text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200 rounded-lg transition shadow-sm cursor-pointer w-full lg:w-auto">
                                            ✏️ Editar
                                        </button>
                                        <form method="POST" action="" class="w-full lg:w-auto" onsubmit="return confirm('¿Estás seguro de eliminar este censo? Se borrarán todos los familiares asociados.');">
                                            <input type="hidden" name="eliminar_familia" value="1">
                                            <input type="hidden" name="id_familia" value="<?= $row['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1.5 text-xs font-bold bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 rounded-lg transition shadow-sm cursor-pointer w-full">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="contenedor-movil-familias" class="grid grid-cols-1 gap-3 md:hidden">
                <?php foreach($familias as $row): ?>
                    <div class="tarjeta-movil bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-2"
                         data-search="<?= strtolower(htmlspecialchars($row['direccion_principal'] . " " . $row['contacto_principal'] . " " . $row['iglesia'])); ?>">
                        
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                            <span class="font-mono text-xs font-bold text-slate-700">ID: <?= $row['id'] ?></span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-sky-100 text-sky-800 border border-sky-200"><?= $row['total_miembros'] ?> Miembros</span>
                        </div>
                        
                        <div class="flex justify-between items-start mt-1">
                            <div class="w-full pr-2">
                                <div class="text-sm font-black text-slate-900 leading-tight"><?= htmlspecialchars($row['direccion_principal']); ?></div>
                                <div class="text-xs text-slate-500 mt-1"> <?= htmlspecialchars($row['contacto_principal']); ?> |  <?= htmlspecialchars($row['iglesia']); ?></div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button type="button" onclick='abrirModalEdicion(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="text-sm p-1.5 bg-white border border-slate-200 rounded-lg shadow-sm">✏️</button>
                                <form method="POST" action="" class="inline-block" onsubmit="return confirm('¿Eliminar este censo?');">
                                    <input type="hidden" name="eliminar_familia" value="1">
                                    <input type="hidden" name="id_familia" value="<?= $row['id'] ?>">
                                    <button type="submit" class="text-sm p-1.5 bg-white border border-rose-200 text-rose-500 rounded-lg shadow-sm w-full">🗑️</button>
                                </form>
                            </div>
                        </div>

                        <div class="bg-white p-2 rounded-lg border border-slate-100 text-xs text-slate-600 mt-1">
                            <div class="font-bold text-[10px] text-slate-400 uppercase tracking-wide mb-1 border-b border-slate-100 pb-1">Integrantes Familiares</div>
                            <ul class="list-disc list-inside space-y-0.5">
                                <?php foreach($row['miembros'] as $m): ?>
                                    <li class="truncate"><strong><?= htmlspecialchars($m['nombre_apellido']) ?></strong> (<?= htmlspecialchars($m['parentesco']) ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="bg-white p-2 rounded-lg border border-slate-100 text-xs text-slate-700 mt-1">
                            <span class="font-bold block mb-0.5">Vivienda: <?= htmlspecialchars($row['perdidas_vivienda']); ?></span>
                            <span class="italic text-slate-500 truncate block">Nec: <?= htmlspecialchars($row['necesidades_basicas'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex md:hidden items-center justify-between mt-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                <button id="prev-movil" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg">Ant.</button>
                <span id="info-movil" class="text-xs font-semibold text-slate-500">Pág. 1</span>
                <button id="next-movil" class="px-3 py-1.5 text-xs font-bold bg-white border border-slate-300 text-slate-700 rounded-lg">Sig.</button>
            </div>

        <?php endif; ?>
    </div>
</div>

<div id="modal-agregar" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
    <div class="bg-white rounded-2xl border border-slate-200 max-w-4xl w-full p-6 shadow-xl space-y-4 my-8 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-lg font-black text-slate-900 uppercase tracking-wide">📋 Nuevo Censo Familiar</h3>
                <p class="text-xs text-slate-500">Registra todos los datos del núcleo y sus integrantes.</p>
            </div>
            <button type="button" onclick="cerrarModal('modal-agregar')" class="text-slate-400 hover:text-slate-600 text-2xl font-bold cursor-pointer">&times;</button>
        </div>

        <form action="" method="POST" class="space-y-6">
            <input type="hidden" name="registrar_familia" value="1">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Iglesia</label>
                    <input type="text" name="iglesia" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" required>
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Contacto Principal</label>
                    <input type="text" name="contacto_principal" placeholder="Ej. 04149059437" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Dirección del Grupo Familiar</label>
                    <input type="text" name="direccion_principal" placeholder="Canton, cerro los cachos Maiquetia..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" required>
                </div>
            </div>

            <div class="space-y-3 pt-4 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="text-sm font-black text-slate-800 uppercase tracking-wider border-l-4 border-emerald-500 pl-2">
                        1. Personas que conforman el núcleo familiar
                    </label>
                    <button type="button" onclick="agregarFilaMiembro('contenedor-miembros', 'miembro_')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-slate-900 text-white hover:bg-slate-800 rounded-lg transition cursor-pointer">
                        ➕ Añadir Familiar
                    </button>
                </div>
                <div id="contenedor-miembros" class="space-y-3"></div>
            </div>

            <div class="space-y-3 pt-4 border-t border-slate-100">
                <label class="block text-sm font-black text-slate-800 uppercase tracking-wider border-l-4 border-emerald-500 pl-2 mb-3">
                    2. Pérdidas Materiales (Vivienda)
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <label class="flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                        <input type="radio" name="perdidas_vivienda" value="Pérdida Total" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-slate-700 font-medium">Pérdida Total</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                        <input type="radio" name="perdidas_vivienda" value="Daños Graves / Estructurales" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-slate-700 font-medium leading-tight">Daños Graves / Estructurales</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                        <input type="radio" name="perdidas_vivienda" value="Daños Leves / Reparables" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-slate-700 font-medium leading-tight">Daños Leves / Reparables</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                        <input type="radio" name="perdidas_vivienda" value="Sin Daños" checked class="w-4 h-4 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-slate-700 font-medium">Sin Daños</span>
                    </label>
                </div>
            </div>

            <div class="space-y-3 pt-4 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="text-sm font-black text-slate-800 uppercase tracking-wider border-l-4 border-emerald-500 pl-2">
                        3. Salud y Medicamentos de Emergencia
                    </label>
                    <button type="button" onclick="agregarFilaSalud()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-slate-900 text-white hover:bg-slate-800 rounded-lg transition cursor-pointer">
                        ➕ Añadir Registro Médico
                    </button>
                </div>
                <div id="contenedor-salud" class="space-y-3"></div>
            </div>

            <div class="space-y-3 pt-4 border-t border-slate-100">
                <label class="block text-sm font-black text-slate-800 uppercase tracking-wider border-l-4 border-emerald-500 pl-2 mb-2">
                    4. Apoyo y Acompañamiento Familiar
                </label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="perdida_miembro" value="Sí" class="w-4 h-4 text-emerald-600">
                        <span class="text-sm text-slate-700 font-medium">Sí, pérdida física de un miembro</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="perdida_miembro" value="No" checked class="w-4 h-4 text-emerald-600">
                        <span class="text-sm text-slate-700 font-medium">No</span>
                    </label>
                </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-slate-100">
                <div>
                    <label class="block text-sm font-black text-slate-800 uppercase tracking-wider border-l-4 border-emerald-500 pl-2 mb-1">
                        5. Necesidades Básicas Inmediatas
                    </label>
                    <input type="text" name="necesidades_basicas" placeholder="Ej. Comida, Uso Personal..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-black text-slate-800 uppercase tracking-wider border-l-4 border-emerald-500 pl-2 mb-1">
                        6. Observación
                    </label>
                    <textarea name="observaciones" rows="2" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 transition resize-none"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                <button type="button" onclick="cerrarModal('modal-agregar')" class="px-4 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition cursor-pointer">Cancelar</button>
                <button type="submit" class="px-5 py-2.5 text-sm font-black bg-emerald-600 hover:bg-emerald-700 text-white uppercase tracking-wide rounded-xl shadow-md transition cursor-pointer">
                    Guardar Familia
                </button>
            </div>
        </form>
    </div>
</div>


<div id="modal-editar" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
    <div class="bg-white rounded-2xl border border-slate-200 max-w-4xl w-full p-6 shadow-xl space-y-4 my-8 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-lg font-black text-slate-900 uppercase tracking-wide">✏️ Editar Datos del Censo</h3>
                <p class="text-xs text-slate-500">Actualiza la información y el listado de miembros y salud de la familia.</p>
            </div>
            <button type="button" onclick="cerrarModal('modal-editar')" class="text-slate-400 hover:text-slate-600 text-2xl font-bold cursor-pointer">&times;</button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="editar_familia" value="1">
            <input type="hidden" name="id_familia" id="edit_id_familia">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Iglesia</label>
                    <input type="text" name="edit_iglesia" id="edit_iglesia" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500" required>
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Contacto Principal</label>
                    <input type="text" name="edit_contacto_principal" id="edit_contacto_principal" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Dirección del Grupo Familiar</label>
                    <input type="text" name="edit_direccion_principal" id="edit_direccion_principal" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500" required>
                </div>
            </div>

            <div class="space-y-3 pt-4 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="text-sm font-black text-slate-800 uppercase tracking-wider border-l-4 border-sky-500 pl-2">
                        1. Personas del núcleo familiar
                    </label>
                    <button type="button" onclick="agregarFilaMiembro('edit-contenedor-miembros', 'edit_miembro_')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-slate-900 text-white hover:bg-slate-800 rounded-lg transition cursor-pointer">
                        ➕ Añadir Familiar
                    </button>
                </div>
                <div id="edit-contenedor-miembros" class="space-y-3"></div>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <label class="block text-xs font-bold text-slate-700 mb-1 uppercase border-l-2 border-sky-500 pl-1">2. Pérdidas de Vivienda</label>
                <select name="edit_perdidas_vivienda" id="edit_perdidas_vivienda" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500">
                    <option value="Sin Daños">Sin Daños</option>
                    <option value="Daños Leves / Reparables">Daños Leves / Reparables</option>
                    <option value="Daños Graves / Estructurales">Daños Graves / Estructurales</option>
                    <option value="Pérdida Total">Pérdida Total</option>
                </select>
            </div>

            <div class="space-y-3 pt-4 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="text-sm font-black text-slate-800 uppercase tracking-wider border-l-4 border-sky-500 pl-2">
                        3. Salud y Medicamentos de Emergencia
                    </label>
                    <button type="button" onclick="agregarFilaSaludEdit()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-slate-900 text-white hover:bg-slate-800 rounded-lg transition cursor-pointer">
                        ➕ Añadir Registro Médico
                    </button>
                </div>
                <div id="edit-contenedor-salud" class="space-y-3"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1 uppercase border-l-2 border-sky-500 pl-1">4. ¿Pérdida física de miembro?</label>
                    <select name="edit_perdida_miembro" id="edit_perdida_miembro" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <option value="No">No</option>
                        <option value="Sí">Sí</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1 uppercase border-l-2 border-sky-500 pl-1">5. Necesidades Básicas</label>
                    <input type="text" name="edit_necesidades_basicas" id="edit_necesidades_basicas" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1 uppercase border-l-2 border-sky-500 pl-1">6. Observaciones</label>
                <textarea name="edit_observaciones" id="edit_observaciones" rows="2" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 resize-none focus:outline-none focus:ring-2 focus:ring-sky-500"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                <button type="button" onclick="cerrarModal('modal-editar')" class="px-4 py-2 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition cursor-pointer">Cancelar</button>
                <button type="submit" class="px-5 py-2 text-sm font-black bg-sky-600 hover:bg-sky-700 text-white rounded-xl shadow-md transition cursor-pointer">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>


<script>
// ==========================================
// LÓGICA DE DATATABLES Y VISTA MÓVIL
// ==========================================
$(document).ready(function() {
    $('#tabla-familias').DataTable({
        "pageLength": 10,
        "language": {
            "lengthMenu": "Mostrar _MENU_ familias",
            "zeroRecords": "No se encontraron registros",
            "info": "Página _PAGE_ de _PAGES_",
            "search": `<span class="inline-flex items-center gap-1.5 font-bold text-slate-500 uppercase tracking-wider">
                        <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                        </svg>
                       </span>`,
            "paginate": { "next": "Sig.", "previous": "Ant." }
        },
        "order": [[0, "desc"]]
    });

    if($('#contenedor-movil-familias').length) {
        let paginaActual = 1;
        const tarjetasPorPagina = 10;

        function renderizarMovil() {
            let filtro = $('#buscar-movil-familias').val().toLowerCase();
            let tarjetas = $('.tarjeta-movil').filter(function() { return $(this).attr('data-search').includes(filtro); });
            let totalPaginas = Math.ceil(tarjetas.length / tarjetasPorPagina) || 1;
            if (paginaActual > totalPaginas) paginaActual = totalPaginas;

            $('.tarjeta-movil').addClass('hidden');
            tarjetas.slice((paginaActual - 1) * tarjetasPorPagina, paginaActual * tarjetasPorPagina).removeClass('hidden');

            $('#info-movil').text(`Pág. ${paginaActual} de ${totalPaginas}`);
            $('#prev-movil').prop('disabled', paginaActual === 1);
            $('#next-movil').prop('disabled', paginaActual === totalPaginas);
        }

        $('#prev-movil').click(function() { if (paginaActual > 1) { paginaActual--; renderizarMovil(); } });
        $('#next-movil').click(function() { paginaActual++; renderizarMovil(); });
        $('#buscar-movil-familias').on('input', function() { paginaActual = 1; renderizarMovil(); });
        renderizarMovil();
    }
});

// ==========================================
// CONTROLADORES DE MODALES
// ==========================================
function abrirModal(id) { 
    document.getElementById(id).classList.remove('hidden'); 
    document.body.style.overflow = 'hidden'; 
}

function cerrarModal(id) { 
    document.getElementById(id).classList.add('hidden'); 
    document.body.style.overflow = 'auto'; 
}

// Inicializar el modal de edición inyectando todos los datos de la familia + miembros + salud
function abrirModalEdicion(familia) {
    document.getElementById('edit_id_familia').value = familia.id;
    document.getElementById('edit_iglesia').value = familia.iglesia;
    document.getElementById('edit_contacto_principal').value = familia.contacto_principal;
    document.getElementById('edit_direccion_principal').value = familia.direccion_principal;
    document.getElementById('edit_perdidas_vivienda').value = familia.perdidas_vivienda;
    document.getElementById('edit_perdida_miembro').value = familia.perdida_miembro;
    document.getElementById('edit_necesidades_basicas').value = familia.necesidades_basicas;
    document.getElementById('edit_observaciones').value = (familia.observaciones || '').replace(/\\r\\n/g, '\n');
    
    // Limpiar el contenedor de miembros actual
    const contenedorMiembros = document.getElementById('edit-contenedor-miembros');
    contenedorMiembros.innerHTML = '';
    
    // Limpiar el contenedor de salud actual
    const contenedorSalud = document.getElementById('edit-contenedor-salud');
    contenedorSalud.innerHTML = '';

    // Si existen miembros, agregarlos dinámicamente uno por uno
    if(familia.miembros && familia.miembros.length > 0) {
        familia.miembros.forEach(m => {
            agregarFilaMiembro('edit-contenedor-miembros', 'edit_miembro_', m.nombre_apellido, m.parentesco, m.edad, m.telefono);
        });
    } else {
        agregarFilaMiembro('edit-contenedor-miembros', 'edit_miembro_');
    }

    // Si existen registros de salud, agregarlos dinámicamente
    if(familia.salud && familia.salud.length > 0) {
        familia.salud.forEach(s => {
            agregarFilaSaludEdit(s.nombre_familiar, s.enfermedad, s.medicamento);
        });
    }

    abrirModal('modal-editar');
}

// ==========================================
// LÓGICA DINÁMICA DE FAMILIARES Y SALUD
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    // Añadir la primera fila obligatoria al modal de registro cuando carga la página
    agregarFilaMiembro('contenedor-miembros', 'miembro_'); 
});

// Actualiza las opciones del Select en Salud para "Crear"
function actualizarOpcionesSalud() {
    const inputsNombres = document.querySelectorAll('input[name="miembro_nombre[]"]');
    let nombres = [];
    inputsNombres.forEach(input => { if (input.value.trim()) nombres.push(input.value.trim()); });

    document.querySelectorAll('select[name="salud_nombre[]"]').forEach(select => {
        const valActual = select.value;
        select.innerHTML = '<option value="">Seleccione familiar...</option>';
        nombres.forEach(nom => {
            const op = document.createElement('option');
            op.value = nom; op.textContent = nom;
            if (nom === valActual) op.selected = true;
            select.appendChild(op);
        });
    });
}

// Actualiza las opciones del Select en Salud para "Editar"
function actualizarOpcionesSaludEdit() {
    const inputsNombres = document.querySelectorAll('input[name="edit_miembro_nombre[]"]');
    let nombres = [];
    inputsNombres.forEach(input => { if (input.value.trim()) nombres.push(input.value.trim()); });

    document.querySelectorAll('select[name="edit_salud_nombre[]"]').forEach(select => {
        const valActual = select.value;
        select.innerHTML = '<option value="">Seleccione familiar...</option>';
        nombres.forEach(nom => {
            const op = document.createElement('option');
            op.value = nom; op.textContent = nom;
            if (nom === valActual) op.selected = true;
            select.appendChild(op);
        });
    });
}

// Agrega una fila de familiar en ambos modales
function agregarFilaMiembro(contenedorID, prefijoInput, nombreVal = '', parenVal = '', edadVal = '', telVal = '') {
    const cont = document.getElementById(contenedorID);
    const div = document.createElement('div');
    const esEdit = prefijoInput === 'edit_miembro_';
    const colorBorde = esEdit ? 'border-sky-200/60 focus:ring-sky-500' : 'border-slate-200/60 focus:ring-emerald-500';
    const funcActualizar = esEdit ? 'actualizarOpcionesSaludEdit()' : 'actualizarOpcionesSalud()';
    
    div.className = `${esEdit ? 'edit-fila-miembro' : 'fila-miembro'} grid grid-cols-1 sm:grid-cols-12 gap-2 bg-slate-50/50 p-2 rounded-lg border ${colorBorde} items-center`;
    
    div.innerHTML = `
        <div class="sm:col-span-4">
            <input type="text" name="${prefijoInput}nombre[]" value="${nombreVal}" oninput="${funcActualizar}" placeholder="Nombre y Apellido" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 ${colorBorde}" required>
        </div>
        <div class="sm:col-span-3">
            <input type="text" name="${prefijoInput}parentesco[]" value="${parenVal}" placeholder="Parentesco" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 ${colorBorde}" required>
        </div>
        <div class="sm:col-span-2">
            <input type="number" name="${prefijoInput}edad[]" value="${edadVal}" placeholder="Edad" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 ${colorBorde}" required>
        </div>
        <div class="sm:col-span-2">
            <input type="text" name="${prefijoInput}telefono[]" value="${telVal}" placeholder="Teléfono" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 ${colorBorde}">
        </div>
        <div class="sm:col-span-1 flex justify-end">
            <button type="button" onclick="eliminarFilaMiembro(this, '${esEdit ? '.edit-fila-miembro' : '.fila-miembro'}')" class="text-rose-500 hover:text-rose-700 p-1 font-bold cursor-pointer transition">❌</button>
        </div>
    `;
    cont.appendChild(div);
    if(esEdit) actualizarOpcionesSaludEdit(); else actualizarOpcionesSalud();
}

// Agrega una fila médica para "Crear"
function agregarFilaSalud() {
    let hayNombres = false;
    document.querySelectorAll('input[name="miembro_nombre[]"]').forEach(i => { if(i.value.trim()) hayNombres = true; });
    if(!hayNombres) return alert("Primero registre el nombre de un miembro en la Sección 1.");

    const cont = document.getElementById('contenedor-salud');
    const div = document.createElement('div');
    div.className = 'fila-salud grid grid-cols-1 sm:grid-cols-12 gap-2 bg-rose-50/30 p-2 rounded-lg border border-rose-100 items-center';
    div.innerHTML = `
        <div class="sm:col-span-4"><select name="salud_nombre[]" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500" required></select></div>
        <div class="sm:col-span-4"><input type="text" name="salud_enfermedad[]" placeholder="Enfermedad" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500" required></div>
        <div class="sm:col-span-3"><input type="text" name="salud_medicamento[]" placeholder="Medicamento" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500" required></div>
        <div class="sm:col-span-1 flex justify-end"><button type="button" onclick="eliminarFila(this, '.fila-salud')" class="text-rose-500 hover:text-rose-700 p-1 font-bold cursor-pointer transition">❌</button></div>
    `;
    cont.appendChild(div);
    actualizarOpcionesSalud();
}

// Agrega una fila médica para "Editar"
function agregarFilaSaludEdit(nombreVal = '', enfVal = '', medVal = '') {
    let hayNombres = false;
    document.querySelectorAll('input[name="edit_miembro_nombre[]"]').forEach(i => { if(i.value.trim()) hayNombres = true; });
    if(!hayNombres) return alert("Primero registre el nombre de un miembro en la Sección 1.");

    const cont = document.getElementById('edit-contenedor-salud');
    const div = document.createElement('div');
    div.className = 'edit-fila-salud grid grid-cols-1 sm:grid-cols-12 gap-2 bg-sky-50/30 p-2 rounded-lg border border-sky-100 items-center';
    div.innerHTML = `
        <div class="sm:col-span-4"><select name="edit_salud_nombre[]" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-sky-500" required></select></div>
        <div class="sm:col-span-4"><input type="text" name="edit_salud_enfermedad[]" value="${enfVal}" placeholder="Enfermedad" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-sky-500" required></div>
        <div class="sm:col-span-3"><input type="text" name="edit_salud_medicamento[]" value="${medVal}" placeholder="Medicamento" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-sky-500" required></div>
        <div class="sm:col-span-1 flex justify-end"><button type="button" onclick="eliminarFila(this, '.edit-fila-salud')" class="text-rose-500 hover:text-rose-700 p-1 font-bold cursor-pointer transition">❌</button></div>
    `;
    cont.appendChild(div);
    actualizarOpcionesSaludEdit();
    
    if(nombreVal) {
        div.querySelector('select').value = nombreVal;
    }
}

// Elimina una fila de Familiar en ambos modales y limpia dependencias de salud
function eliminarFilaMiembro(btn, claseFila) {
    const fila = btn.closest(claseFila);
    const filas = document.querySelectorAll(claseFila);
    
    if (filas.length <= 1) return alert("Debe haber al menos un miembro familiar.");
    
    // Al borrar un familiar en "Crear"
    if (claseFila === '.fila-miembro') {
        const nom = fila.querySelector('input[name="miembro_nombre[]"]').value.trim();
        if (nom) {
            document.querySelectorAll('.fila-salud').forEach(fs => {
                const sel = fs.querySelector('select[name="salud_nombre[]"]');
                if (sel && sel.value === nom) fs.remove();
            });
        }
        fila.remove();
        actualizarOpcionesSalud();
    } 
    // Al borrar un familiar en "Editar"
    else if (claseFila === '.edit-fila-miembro') {
        const nom = fila.querySelector('input[name="edit_miembro_nombre[]"]').value.trim();
        if (nom) {
            document.querySelectorAll('.edit-fila-salud').forEach(fs => {
                const sel = fs.querySelector('select[name="edit_salud_nombre[]"]');
                if (sel && sel.value === nom) fs.remove();
            });
        }
        fila.remove();
        actualizarOpcionesSaludEdit();
    }
}

function eliminarFila(btn, clase) {
    btn.closest(clase).remove();
}
</script>

</body>
</html>
<?php if(isset($conn)) $conn->close(); ?>