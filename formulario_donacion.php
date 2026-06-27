<?php
session_start();
// Control de acceso: solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once('conexion.php');

// 1. PROCESAR EL FORMULARIO CUANDO SE ENVÍA (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipo_receptor = $_POST['tipo_receptor'];
    $responsable = $conn->real_escape_string($_POST['responsable_entrega']);
    $detalle = $conn->real_escape_string($_POST['detalle_adicional']);
    
    $persona_id = "NULL";
    $centro_id = "NULL";

    $error_stock = false;

    // Iniciamos la transacción antes de evaluar e insertar datos dependientes
    $conn->begin_transaction();

    try {
        if ($tipo_receptor === 'persona') {
            $cedula = $conn->real_escape_string($_POST['cedula']);
            $nombre = $conn->real_escape_string($_POST['nombre']);
            $apellido = $conn->real_escape_string($_POST['apellido']);
            $telefono = $conn->real_escape_string($_POST['telefono']);

            $check_persona = $conn->query("SELECT id FROM personas_beneficiarias WHERE cedula = '$cedula'");
            if ($check_persona->num_rows > 0) {
                $p = $check_persona->fetch_assoc();
                $persona_id = $p['id'];
            } else {
                $conn->query("INSERT INTO personas_beneficiarias (cedula, nombre, apellido, telefono) VALUES ('$cedula', '$nombre', '$apellido', '$telefono')");
                $persona_id = $conn->insert_id;
            }
        } else {
            $centro_id = intval($_POST['centro_id']);
        }

        // Insertar la cabecera de la entrega
        $sql_entrega = "INSERT INTO entregas (tipo_receptor, persona_id, centro_id, detalle_adicional, responsable_entrega) 
                        VALUES ('$tipo_receptor', $persona_id, $centro_id, '$detalle', '$responsable')";
        $conn->query($sql_entrega);
        $entrega_id = $conn->insert_id;
        
        // Procesar los insumos y las cantidades ingresadas
        if (isset($_POST['insumos']) && isset($_POST['cantidades'])) {
            foreach ($_POST['insumos'] as $index => $insumo_id) {
                $insumo_id = intval($insumo_id);
                $cantidad_a_donar = floatval($_POST['cantidades'][$index]);

                if ($cantidad_a_donar > 0) {
                    // Validar stock actual (Bloqueamos la fila temporalmente para evitar condiciones de carrera)
                    $res_ins = $conn->query("SELECT cantidad FROM insumos WHERE id = $insumo_id FOR UPDATE");
                    $ins_data = $res_ins->fetch_assoc();
                    $stock_actual = floatval($ins_data['cantidad']);

                    if ($cantidad_a_donar <= $stock_actual) {
                        $nuevo_stock = $stock_actual - $cantidad_a_donar;
                        $estado = ($nuevo_stock == 0) ? 'donado' : 'disponible';
                        
                        $conn->query("UPDATE insumos SET cantidad = $nuevo_stock, estado = '$estado' WHERE id = $insumo_id");
                        $conn->query("INSERT INTO detalle_entregas (entrega_id, insumo_id, cantidad_donada) VALUES ($entrega_id, $insumo_id, $cantidad_a_donar)");
                    } else {
                        $error_stock = true; 
                        throw new Exception("Stock insuficiente para el insumo ID: $insumo_id");
                    }
                }
            }
        }
        
        // Si todo anduvo excelente, confirmamos la transacción completa
        $conn->commit();
        echo "<script>alert('¡Donación registrada con éxito y stock actualizado!'); window.location.href='panel_central.php';</script>";
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        
        $msj_alerta = ($error_stock) 
            ? '❌ Error: Uno o más insumos no contaban con stock suficiente. No se guardó ninguna modificación.' 
            : '❌ Error interno en la transacción de donación: ' . $e->getMessage();
            
        echo "<script>alert('" . addslashes($msj_alerta) . "'); window.location.href='panel_central.php';</script>";
        exit;
    }
}

// 2. OBTENER DATOS DE LA BD PARA LOS DROPDOWNS
try {
    $centros = $conn->query("SELECT * FROM centros_acopio");
    $insumos_disponibles = $conn->query("SELECT * FROM insumos WHERE estado = 'disponible'");
} catch (Exception $e) {
    die("Error recuperando catálogos: " . $e->getMessage());
}

include('header.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Despacho de Insumos - Sede Central</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-3xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    
    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Registrar Nueva Entrega / Donación
        </h2>
        <p class="text-sm text-slate-500 font-medium">Egreso de mercancía y actualización automática de inventario</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8">
        <form action="" method="POST" class="space-y-6">
            
            <div class="space-y-1.5">
                <label for="tipo_receptor" class="text-xs font-black text-slate-500 uppercase tracking-wider block">Tipo de Destinatario:</label>
                <select name="tipo_receptor" id="tipo_receptor" onchange="toggleReceptor()" required 
                        class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-semibold focus:outline-hidden focus:ring-2 focus:ring-slate-400 focus:border-transparent transition">
                    <option value="persona">Persona Natural (Caso de vulnerabilidad)</option>
                    <option value="centro">Centro de Acopio (Sede alterna / Refugio)</option>
                </select>
            </div>

            <div id="campos_persona" class="bg-slate-50/50 p-4 rounded-xl border border-slate-200/60 space-y-4">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider border-b border-slate-200/60 pb-1.5 flex items-center gap-1.5">
                    👤 Datos del Beneficiario
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Cédula:</label>
                        <input type="text" name="cedula" id="cedula" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Nombre:</label>
                        <input type="text" name="nombre" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Apellido:</label>
                        <input type="text" name="apellido" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500">Teléfono:</label>
                        <input type="text" name="telefono" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                    </div>
                </div>
            </div>

            <div id="campos_centro" class="bg-slate-50/50 p-4 rounded-xl border border-slate-200/60 space-y-3" style="display:none;">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider border-b border-slate-200/60 pb-1.5 flex items-center gap-1.5">
                    🏢 Sede o Unidad Temática Destino
                </h3>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-500">Centro Destino:</label>
                    <select name="centro_id" id="centro_id" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                        <?php while($c = $centros->fetch_assoc()): ?>
                            <option value="<?=$c['id']?>"><?=$c['nombre']?> (<?=$c['ubicacion']?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="space-y-3">
                <div class="border-b border-slate-100 pb-2">
                    <h3 class="text-xs sm:text-sm font-black text-slate-800 uppercase tracking-wider">Detalle de la Entrega (Insumos)</h3>
                    <p class="text-xs text-slate-400">Selecciona los elementos que van a retirarse de los estantes.</p>
                </div>

                <div class="relative">
                    <input type="text" id="buscador_insumos" onkeyup="filtrarInsumos()" placeholder="Filtrar existencias por nombre..." 
                           class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-slate-400 transition">
                </div>

                <div class="max-h-72 overflow-y-auto border border-slate-200 rounded-xl p-2 bg-slate-50/30 divide-y divide-slate-100" id="lista_contenedor_insumos">
                    
                    <?php if($insumos_disponibles->num_rows == 0): ?>
                        <p class="text-slate-400 text-xs text-center py-6 font-medium" id="sin_insumos_msg">No hay insumos aptos o disponibles en stock.</p>
                    <?php endif; ?>
                    
                    <?php 
                    $index = 0;
                    while($ins = $insumos_disponibles->fetch_assoc()): 
                    ?>
                        <div class="item-insumo flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 transition duration-700 hover:bg-slate-50/60" data-nombre="<?=strtolower($ins['nombre'])?>">
                            <div class="flex items-start gap-2.5 sm:max-w-md">
                                <input type="checkbox" name="insumos[<?=$index?>]" value="<?=$ins['id']?>" id="chk_<?=$ins['id']?>" onchange="toggleCantidad(<?=$ins['id']?>)"
                                       class="mt-1 w-4 h-4 rounded-sm border-slate-300 text-slate-900 focus:ring-slate-400 cursor-pointer"> 
                                <label for="chk_<?=$ins['id']?>" class="cursor-pointer text-xs sm:text-sm text-slate-700 select-none leading-tight">
                                    <strong class="text-slate-900 font-bold block sm:inline"><?=$ins['nombre']?></strong> 
                                    <span class="text-xs text-slate-400 sm:ml-1 block sm:inline font-medium">(Disp: <?=number_format($ins['cantidad'], 2)?> <?=$ins['unidad_medida']?>)</span>
                                </label>
                            </div>
                            <div class="flex items-center gap-1.5 pl-6 sm:pl-0">
                                <input type="number" step="0.01" name="cantidades[<?=$index?>]" id="cant_<?=$ins['id']?>" placeholder="0.00" disabled 
                                       max="<?=$ins['cantidad']?>"
                                       class="input-cantidad w-24 text-center text-xs font-bold bg-white border border-slate-200 rounded-lg px-2 py-1 disabled:opacity-40 disabled:bg-slate-100 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                                <span class="text-xs font-bold text-slate-400 min-w-[35px]"><?=$ins['unidad_medida']?></span>
                            </div>
                        </div>
                    <?php 
                        $index++;
                    endwhile; 
                    ?>
                    
                    <p id="no_resultados" class="hidden text-xs text-slate-400 text-center py-8 font-semibold">⚠️ Ningún artículo coincide con tu búsqueda.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <div class="space-y-1">
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider block">Detalle Adicional / Observaciones:</label>
                    <textarea name="detalle_adicional" rows="3" placeholder="Añade justificaciones del caso o estados de entrega..." 
                              class="w-full text-sm bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400"></textarea>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider block">Responsable de la Entrega:</label>
                    <input type="text" name="responsable_entrega" placeholder="Nombre de quien despacha" required 
                           class="w-full text-sm bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200">
                <button type="submit" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-black text-sm uppercase tracking-wider rounded-xl shadow-md transform active:scale-[0.99] transition duration-150 cursor-pointer">
                    Registrar Entrega y Actualizar Inventario
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleReceptor() {
    const tipo = document.getElementById('tipo_receptor').value;
    const divPersona = document.getElementById('campos_persona');
    const divCentro = document.getElementById('campos_centro');
    
    if(tipo === 'persona') {
        divPersona.style.display = 'block';
        divCentro.style.display = 'none';
        document.getElementById('cedula').required = true;
    } else {
        divPersona.style.display = 'none';
        divCentro.style.display = 'block';
        document.getElementById('cedula').required = false;
    }
}

function toggleCantidad(id) {
    const checkbox = document.getElementById('chk_' + id);
    const inputCant = document.getElementById('cant_' + id);
    
    if (checkbox.checked) {
        inputCant.disabled = false;
        inputCant.required = true;
        inputCant.focus();
    } else {
        inputCant.disabled = true;
        inputCant.required = false;
        inputCant.value = ''; 
    }
}

function filtrarInsumos() {
    const textoBusqueda = document.getElementById('buscador_insumos').value.toLowerCase().trim();
    const filasInsumos = document.querySelectorAll('.item-insumo');
    const mensajeNoResultados = document.getElementById('no_resultados');
    let coincidencias = 0;

    filasInsumos.forEach(fila => {
        const nombreInsumo = fila.getAttribute('data-nombre');
        const checkbox = fila.querySelector('input[type="checkbox"]');
        
        // Mantener visible si coincide con la búsqueda o si ya está seleccionado
        if (nombreInsumo.includes(textoBusqueda) || checkbox.checked) {
            fila.classList.remove('hidden');
            coincidencias++;
        } else {
            fila.classList.add('hidden');
        }
    });

    if (coincidencias === 0 && filasInsumos.length > 0) {
        mensajeNoResultados.classList.remove('hidden');
    } else {
        mensajeNoResultados.classList.add('hidden');
    }
}

// Inicializar vista
toggleReceptor();
</script>

</body>
</html>
<?php $conn->close();?>