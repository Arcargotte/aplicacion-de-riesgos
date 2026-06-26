<?php
session_start();
// Control de acceso: solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "ong_inventario");

// 1. PROCESAR EL FORMULARIO CUANDO SE ENVÍA (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipo_receptor = $_POST['tipo_receptor'];
    $responsable = $conn->real_escape_string($_POST['responsable_entrega']);
    $detalle = $conn->real_escape_string($_POST['detalle_adicional']);
    
    $persona_id = "NULL";
    $centro_id = "NULL";

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
    
    if ($conn->query($sql_entrega)) {
        $entrega_id = $conn->insert_id;
        $error_stock = false;
        
        // Procesar los insumos y las cantidades ingresadas
        if (isset($_POST['insumos']) && isset($_POST['cantidades'])) {
            foreach ($_POST['insumos'] as $index => $insumo_id) {
                $insumo_id = intval($insumo_id);
                // Obtenemos la cantidad que el usuario escribió en el input correspondiente
                $cantidad_a_donar = floatval($_POST['cantidades'][$index]);

                if ($cantidad_a_donar > 0) {
                    // Validar stock actual
                    $res_ins = $conn->query("SELECT cantidad FROM insumos WHERE id = $insumo_id");
                    $ins_data = $res_ins->fetch_assoc();
                    $stock_actual = floatval($ins_data['cantidad']);

                    if ($cantidad_a_donar <= $stock_actual) {
                        $nuevo_stock = $stock_actual - $cantidad_a_donar;
                        
                        // Si el stock llega a 0, cambiamos el estado a donado, sino, solo actualizamos cantidad
                        $estado = ($nuevo_stock == 0) ? 'donado' : 'disponible';
                        
                        $conn->query("UPDATE insumos SET cantidad = $nuevo_stock, estado = '$estado' WHERE id = $insumo_id");
                        $conn->query("INSERT INTO detalle_entregas (entrega_id, insumo_id, cantidad_donada) VALUES ($entrega_id, $insumo_id, $cantidad_a_donar)");
                    } else {
                        $error_stock = true; // Intentó donar más de lo que hay
                    }
                }
            }
        }
        
        if ($error_stock) {
            echo "<script>alert('Entrega registrada parcialmente. Algunos insumos no tenían stock suficiente para la cantidad solicitada.'); window.location.href='panel_central.php';</script>";
        } else {
            echo "<script>alert('¡Donación registrada con éxito y stock actualizado!'); window.location.href='panel_central.php';</script>";
        }
    }
}

// 2. OBTENER DATOS DE LA BD PARA LOS DROPDOWNS
$centros = $conn->query("SELECT * FROM centros_acopio");
$insumos_disponibles = $conn->query("SELECT * FROM insumos WHERE estado = 'disponible'");

include('header.php');
?>

<div class="container" style="max-width: 800px;">
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2 style="margin-bottom: 20px;">Registrar Nueva Entrega / Donación</h2>
        <form action="" method="POST">
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Tipo de Receptor:</label>
                <select name="tipo_receptor" id="tipo_receptor" onchange="toggleReceptor()" required style="width:100%; padding:8px;">
                    <option value="persona">Persona Natural</option>
                    <option value="centro">Centro de Acopio</option>
                </select>
            </div>

            <div id="campos_persona">
                <h3 style="margin: 15px 0 10px;">Datos del Beneficiario</h3>
                <div style="margin-bottom: 15px;"><label>Cédula:</label><input type="text" name="cedula" id="cedula" style="width:100%; padding:8px;"></div>
                <div style="margin-bottom: 15px;"><label>Nombre:</label><input type="text" name="nombre" style="width:100%; padding:8px;"></div>
                <div style="margin-bottom: 15px;"><label>Apellido:</label><input type="text" name="apellido" style="width:100%; padding:8px;"></div>
                <div style="margin-bottom: 15px;"><label>Teléfono:</label><input type="text" name="telefono" style="width:100%; padding:8px;"></div>
            </div>

            <div id="campos_centro" style="display:none;">
                <h3 style="margin: 15px 0 10px;">Seleccionar Centro de Acopio</h3>
                <div style="margin-bottom: 15px;">
                    <label>Centro Destino:</label>
                    <select name="centro_id" id="centro_id" style="width:100%; padding:8px;">
                        <?php while($c = $centros->fetch_assoc()): ?>
                            <option value="<?=$c['id']?>"><?=$c['nombre']?> (<?=$c['ubicacion']?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <hr style="margin: 20px 0;">
            <h3 style="margin-bottom: 15px;">Detalle de la Entrega (Insumos)</h3>

            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:10px;">Selecciona los Insumos y la Cantidad a Donar:</label>
                
                <div style="margin-bottom: 12px; display: flex; gap: 8px;">
                    <input type="text" id="buscador_insumos" onkeyup="filtrarInsumos()" placeholder="🔍 Buscar insumo por nombre..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                </div>

                <div style="max-height: 280px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; border-radius: 4px;" id="lista_contenedor_insumos">
                    
                    <?php if($insumos_disponibles->num_rows == 0): ?>
                        <p style="color:gray;" id="sin_insumos_msg">No hay insumos en el inventario actual.</p>
                    <?php endif; ?>
                    
                    <?php 
                    $index = 0;
                    while($ins = $insumos_disponibles->fetch_assoc()): 
                    ?>
                        <div class="item-insumo" data-nombre="<?=strtolower($ins['nombre'])?>" style="background: #f8f9fa; padding: 10px; margin-bottom: 10px; border-radius: 4px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #e9ecef;">
                            <div style="flex: 2;">
                                <input type="checkbox" name="insumos[<?=$index?>]" value="<?=$ins['id']?>" id="chk_<?=$ins['id']?>" onchange="toggleCantidad(<?=$ins['id']?>)"> 
                                <label for="chk_<?=$ins['id']?>" style="cursor:pointer; font-weight:normal;">
                                    <strong><?=$ins['nombre']?></strong> (Disp: <?=$ins['cantidad']?> <?=$ins['unidad_medida']?>)
                                </label>
                            </div>
                            <div style="flex: 1; text-align: right;">
                                <input type="number" step="0.01" name="cantidades[<?=$index?>]" id="cant_<?=$ins['id']?>" placeholder="Cantidad" disabled style="width: 100px; padding: 6px; border: 1px solid #ccc; border-radius: 4px;" max="<?=$ins['cantidad']?>">
                                <span style="font-size: 12px; color: #666;"><?=$ins['unidad_medida']?></span>
                            </div>
                        </div>
                    <?php 
                        $index++;
                    endwhile; 
                    ?>
                    
                    <p id="no_resultados" style="display: none; color: #64748b; text-align: center; padding: 10px;">No se encontraron insumos que coincidan.</p>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Detalle Adicional / Observaciones:</label>
                <textarea name="detalle_adicional" rows="3" style="width:100%; padding:8px;"></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Responsable de la Entrega:</label>
                <input type="text" name="responsable_entrega" required style="width:100%; padding:8px;">
            </div>

            <button type="submit" style="background: #007bff; color: white; border: none; padding: 12px 20px; cursor: pointer; border-radius: 4px; font-size: 16px; width: 100%;">Registrar Entrega y Actualizar Inventario</button>
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

// FUNCIÓN JS PARA FILTRAR EN TIEMPO REAL
function filtrarInsumos() {
    const textoBusqueda = document.getElementById('buscador_insumos').value.toLowerCase().trim();
    const filasInsumos = document.querySelectorAll('.item-insumo');
    const mensajeNoResultados = document.getElementById('no_resultados');
    let coincidencias = 0;

    filasInsumos.forEach(fila => {
        const nombreInsumo = fila.getAttribute('data-nombre');
        
        // Si el usuario marcó el insumo o si coincide con el texto, se mantiene visible
        const checkbox = fila.querySelector('input[type="checkbox"]');
        
        if (nombreInsumo.includes(textoBusqueda) || checkbox.checked) {
            fila.style.setProperty('display', 'flex', 'important');
            coincidencias++;
        } else {
            fila.style.setProperty('display', 'none', 'important');
        }
    });

    // Mostrar un mensaje claro si la búsqueda vacía la lista
    if (coincidencias === 0 && filasInsumos.length > 0) {
        mensajeNoResultados.style.display = 'block';
    } else {
        mensajeNoResultados.style.display = 'none';
    }
}

// Inicializar vista
toggleReceptor();
</script>

</body>
</html>