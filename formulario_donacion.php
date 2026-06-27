<?php
session_start();
// Control de acceso
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['centro_id'])) {
    header("Location: login.php");
    exit;
}

include('header.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once('conexion.php');

// Obtenemos el ID del centro de forma estricta (ya no usamos NULL)
$centro_usuario = (int)$_SESSION['centro_id'];
$usuario_id = (int)$_SESSION['usuario_id'];

// Cargar datos para los selectores del formulario
$centros = $conn->query("SELECT id, nombre, direccion FROM centros_acopio WHERE id != $centro_usuario ORDER BY nombre ASC");
$insumos_disponibles = $conn->query("SELECT i.id, i.nombre, i.unidad_medida, inv.cantidad 
                                     FROM inventario inv 
                                     JOIN insumos i ON inv.insumo_id = i.id 
                                     WHERE inv.centro_id = $centro_usuario AND inv.cantidad > 0 
                                     ORDER BY i.nombre ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipo_receptor = $_POST['tipo_receptor'] ?? 'persona';
    $detalle = $conn->real_escape_string(trim($_POST['detalle_adicional'] ?? ''));

    $conn->begin_transaction();
    try {
        if (empty($_POST['insumos']) || !is_array($_POST['insumos'])) {
            throw new Exception('Debe seleccionar al menos un insumo y su cantidad.');
        }

        // Variables para el historial
        $persona_id = 'NULL';
        $organizacion_id = 'NULL';
        $centro_destino_id = 'NULL';
        $tipo_movimiento = 'salida'; 

        // 1. Lógica según quién recibe la donación
        if ($tipo_receptor === 'persona') {

            $nombre = !empty(trim($_POST['nombre'])) ? $conn->real_escape_string($_POST['nombre']) : 'Desconocido';
            $apellido = !empty(trim($_POST['apellido'])) ? $conn->real_escape_string($_POST['apellido']) : '';
            $cedula = !empty(trim($_POST['cedula'])) ? $conn->real_escape_string($_POST['cedula']) : 'X-XXXXXXXXX';
            $telefono = !empty(trim($_POST['telefono'])) ? $conn->real_escape_string($_POST['telefono']) : 'XXXX-XXXXXXX';

            $check = $conn->query("SELECT id FROM personas WHERE cedula = '$cedula'");
            if ($check->num_rows > 0) {
                $persona_id = $check->fetch_assoc()['id'];
            } else {
                $conn->query("INSERT INTO personas (cedula, nombre, apellido, telefono) VALUES ('$cedula', '$nombre', '$apellido', '$telefono')");
                $persona_id = $conn->insert_id;
            }

        } elseif ($tipo_receptor === 'organizacion') {
            $nombre_org = $conn->real_escape_string(trim($_POST['nombre_org'] ?? ''));
            $tipo_org = $conn->real_escape_string(trim($_POST['tipo_org'] ?? 'Otro'));
            $telefono_org = $conn->real_escape_string(trim($_POST['telefono_org'] ?? ''));
            $direccion_org = $conn->real_escape_string(trim($_POST['direccion_org'] ?? ''));

            if (empty($nombre_org)) {
                throw new Exception('Ingrese el nombre de la organización.');
            }

            $check = $conn->query("SELECT id FROM organizaciones WHERE nombre = '$nombre_org'");
            if ($check->num_rows > 0) {
                $organizacion_id = $check->fetch_assoc()['id'];
            } else {
                $conn->query("INSERT INTO organizaciones (nombre, tipo, telefono, direccion) VALUES ('$nombre_org', '$tipo_org', '$telefono_org', '$direccion_org')");
                $organizacion_id = $conn->insert_id;
            }

        } elseif ($tipo_receptor === 'centro') {
            if (empty($_POST['centro_id'])) { throw new Exception('Seleccione el centro destino.'); }
            $centro_destino_id = (int)$_POST['centro_id'];
            $tipo_movimiento = 'traslado';
        }

        // 2. Crear cabecera del movimiento en el historial
        $sql_mov = "INSERT INTO movimientos (tipo_movimiento, centro_origen_id, centro_destino_id, persona_id, organizacion_id, usuario_id, detalles) 
                    VALUES ('$tipo_movimiento', $centro_usuario, $centro_destino_id, $persona_id, $organizacion_id, $usuario_id, '$detalle')";
        $conn->query($sql_mov);
        $movimiento_id = $conn->insert_id;

        // 3. Procesar Insumos
        foreach ($_POST['insumos'] as $index => $insumo_id) {
            $insumo_id = (int)$insumo_id;
            $cantidad = floatval($_POST['cantidades'][$index] ?? 0);
            
            if ($cantidad <= 0) continue;

            // Restar stock del centro de origen
            $conn->query("UPDATE inventario SET cantidad = cantidad - $cantidad WHERE insumo_id = $insumo_id AND centro_id = $centro_usuario");

            // Registrar detalle en historial
            $conn->query("INSERT INTO detalles_movimiento (movimiento_id, insumo_id, cantidad) VALUES ($movimiento_id, $insumo_id, $cantidad)");

            // Si es un traslado a otro centro, sumar al destino
            if ($tipo_receptor === 'centro') {
                $conn->query("INSERT INTO inventario (centro_id, insumo_id, cantidad) 
                              VALUES ($centro_destino_id, $insumo_id, $cantidad) 
                              ON DUPLICATE KEY UPDATE cantidad = cantidad + $cantidad");
            }
        }

        $conn->commit();
        echo "<script>alert('¡Movimiento registrado correctamente en el historial!'); window.location.href='panel_central.php';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Despacho y Donaciones</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-3xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Registrar Entrega / Despacho
        </h2>
        <p class="text-sm text-slate-500 font-medium">Registra a quién se le entrega la mercancía para mantener el historial.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8">
        <form action="" method="POST" class="space-y-6">
            
            <div class="space-y-1.5">
                <label class="text-xs font-black text-slate-500 uppercase tracking-wider block">¿A quién se le entrega?</label>
                <select name="tipo_receptor" id="tipo_receptor" onchange="toggleReceptor()" required 
                        class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-semibold focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                    <option value="persona">Persona Natural (Beneficiario)</option>
                    <option value="organizacion">Organización / ONG / Empresa</option>
                    <option value="centro">Otro Centro de Acopio (Traslado)</option>
                </select>
            </div>

            <div id="campos_persona" class="bg-slate-50/50 p-4 rounded-xl border border-slate-200/60 space-y-4">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider border-b border-slate-200/60 pb-1.5 flex items-center gap-1.5">👤 Datos de la Persona</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Cédula (opcional):</label>
                        <input type="text" placeholder="Desconocido" name="cedula" id="cedula" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Nombre (opcional):</label>
                        <input type="text" placeholder="Desconocido" name="nombre" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Apellido (opcional):</label>
                        <input type="text" placeholder="Desconocido" name="apellido" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Teléfono (opcional):</label>
                        <input type="text" placeholder="Desconocido" name="telefono" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                    </div>
                </div>
            </div>

            <div id="campos_organizacion" class="bg-slate-50/50 p-4 rounded-xl border border-slate-200/60 space-y-4" style="display:none;">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider border-b border-slate-200/60 pb-1.5 flex items-center gap-1.5">🏢 Datos de la Organización</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Nombre de Entidad:</label>
                        <input type="text" name="nombre_org" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Tipo:</label>
                        <select name="tipo_org" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                            <option value="ONG">ONG</option>
                            <option value="Empresa">Empresa</option>
                            <option value="Fundacion">Fundación</option>
                            <option value="Gobierno">Gobierno</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Teléfono:</label>
                        <input type="text" name="telefono_org" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Dirección:</label>
                        <input type="text" name="direccion_org" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                    </div>
                </div>
            </div>

            <div id="campos_centro" class="bg-slate-50/50 p-4 rounded-xl border border-slate-200/60 space-y-3" style="display:none;">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider border-b border-slate-200/60 pb-1.5 flex items-center gap-1.5">🏥 Centro Destino</h3>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-500">Seleccionar Centro:</label>
                    <select name="centro_id" id="centro_id" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800">
                        <?php while($c = $centros->fetch_assoc()): ?>
                            <option value="<?=$c['id']?>"><?=$c['nombre']?> (<?=$c['direccion']?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="space-y-3">
                <div class="border-b border-slate-100 pb-2">
                    <h3 class="text-xs sm:text-sm font-black text-slate-800 uppercase tracking-wider">Insumos a Entregar</h3>
                </div>
                <input type="text" id="buscador_insumos" onkeyup="filtrarInsumos()" placeholder="Filtrar por nombre..." class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700">
                
                <div class="max-h-72 overflow-y-auto border border-slate-200 rounded-xl p-2 bg-slate-50/30" id="lista_contenedor_insumos">
                    <?php if($insumos_disponibles->num_rows == 0): ?>
                        <p class="text-slate-400 text-xs text-center py-6 font-medium">No hay inventario disponible.</p>
                    <?php endif; ?>
                    
                    <?php $index = 0; while($ins = $insumos_disponibles->fetch_assoc()): ?>
                        <div class="item-insumo flex flex-col sm:flex-row justify-between gap-3 p-3 hover:bg-slate-50/60" data-nombre="<?=strtolower($ins['nombre'])?>">
                            <div class="flex items-start gap-2.5">
                                <input type="checkbox" name="insumos[<?=$index?>]" value="<?=$ins['id']?>" id="chk_<?=$ins['id']?>" onchange="toggleCantidad(<?=$ins['id']?>)" class="mt-1 cursor-pointer"> 
                                <label for="chk_<?=$ins['id']?>" class="cursor-pointer text-sm text-slate-700">
                                    <strong class="text-slate-900"><?=$ins['nombre']?></strong> 
                                    <span class="text-xs text-slate-400">(Disp: <?=number_format($ins['cantidad'], 2)?> <?=$ins['unidad_medida']?>)</span>
                                </label>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <input type="number" step="0.01" name="cantidades[<?=$index?>]" id="cant_<?=$ins['id']?>" placeholder="0.00" disabled max="<?=$ins['cantidad']?>"
                                       class="w-24 text-center text-xs font-bold bg-white border border-slate-200 rounded-lg px-2 py-1 disabled:opacity-40">
                                <span class="text-xs font-bold text-slate-400 min-w-[35px]"><?=$ins['unidad_medida']?></span>
                            </div>
                        </div>
                    <?php $index++; endwhile; ?>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-black text-slate-500 uppercase tracking-wider block">Detalles del Historial:</label>
                <textarea name="detalle_adicional" rows="2" placeholder="Motivo de donación, quién despacha, etc..." 
                          class="w-full text-sm bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-200">
                <button type="submit" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-black text-sm uppercase tracking-wider rounded-xl cursor-pointer">
                    Registrar Movimiento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleReceptor() {
    const tipo = document.getElementById('tipo_receptor').value;
    document.getElementById('campos_persona').style.display = tipo === 'persona' ? 'block' : 'none';
    document.getElementById('campos_organizacion').style.display = tipo === 'organizacion' ? 'block' : 'none';
    document.getElementById('campos_centro').style.display = tipo === 'centro' ? 'block' : 'none';
}

function toggleCantidad(id) {
    const inputCant = document.getElementById('cant_' + id);
    inputCant.disabled = !document.getElementById('chk_' + id).checked;
    inputCant.required = !inputCant.disabled;
    if (!inputCant.disabled) inputCant.focus();
    else inputCant.value = ''; 
}

function filtrarInsumos() {
    const term = document.getElementById('buscador_insumos').value.toLowerCase().trim();
    document.querySelectorAll('.item-insumo').forEach(fila => {
        const check = fila.querySelector('input[type="checkbox"]').checked;
        fila.style.display = (fila.dataset.nombre.includes(term) || check) ? 'flex' : 'none';
    });
}
toggleReceptor();
</script>

</body>
</html>
<?php $conn->close(); ?>