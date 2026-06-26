<?php
session_start();
// Control de acceso: solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli("localhost", "root", "", "ong_inventario");
} catch (Exception $e) {
    die("Conexión fallida: " . $e->getMessage());
}

// 1. PROCESAR EL FORMULARIO CUANDO SE ENVÍA (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipo_receptor = $_POST['tipo_receptor'];
    $responsable = $conn->real_escape_string($_POST['responsable_entrega']);
    $detalle = $conn->real_escape_string($_POST['detalle_adicional']);
    
    $persona_id = "NULL";
    $centro_id = "NULL";

    // CORRECCIÓN: Inicializamos la variable aquí arriba para evitar advertencias de "variable no definida" en el catch
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
                        // Lanzamos una excepción manual para activar el rollback automático y no procesar de manera parcial
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
        // Ante cualquier error o excepción de stock, revertimos todo al estado inicial
        $conn->rollback();
        
        $msj_alerta = ($error_stock) 
            ? '❌ Error: Uno o más insumos no contaban con stock suficiente. No se guardó ninguna modificación.' 
            : '❌ Error interno en la transacción de donación: ' . $e->getMessage();
            
        echo "<script>alert('" . addslashes($msj_alerta) . "'); window.location.href='panel_central.php';</script>";
        exit;
    }
}

// 2. OBTENER DATOS DE LA BD PARA LOS DROPDOWNS (Fuera de la transacción de envío)
try {
    $centros = $conn->query("SELECT * FROM centros_acopio");
    $insumos_disponibles = $conn->query("SELECT * FROM insumos WHERE estado = 'disponible'");
} catch (Exception $e) {
    die("Error recuperando catálogos: " . $e->getMessage());
}

include('header.php');
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
    /* Estilos responsivos añadidos de forma nativa */
    .form-container {
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        padding: 15px;
        box-sizing: border-box;
    }
    .form-card {
        background: white; 
        padding: 25px; 
        border-radius: 8px; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .grid-form {
        display: grid;
        grid-template-columns: 1fr;
        gap: 15px;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block; 
        font-weight: bold; 
        margin-bottom: 5px;
    }
    .form-control {
        width: 100%; 
        padding: 10px; 
        border: 1px solid #ccc; 
        border-radius: 4px; 
        box-sizing: border-box;
    }
    
    /* Adaptación de los items de insumos */
    .item-insumo {
        background: #f8f9fa; 
        padding: 12px; 
        margin-bottom: 10px; 
        border-radius: 4px; 
        display: flex; 
        flex-direction: row;
        align-items: center; 
        justify-content: space-between; 
        border: 1px solid #e9ecef;
        gap: 10px;
    }
    .insumo-info {
        flex: 2;
    }
    .insumo-action {
        flex: 1; 
        text-align: right;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
    }
    .input-cantidad {
        width: 100px; 
        padding: 8px; 
        border: 1px solid #ccc; 
        border-radius: 4px;
        box-sizing: border-box;
    }

    /* Media Queries para pantallas medianas/grandes (Tablets y Escritorio) */
    @media (min-width: 600px) {
        .grid-form {
            grid-template-columns: 1fr 1fr; /* Dos columnas para los datos personales */
        }
        .full-width-md {
            grid-column: span 2;
        }
    }

    /* Media Queries para pantallas pequeñas (Móviles) */
    @media (max-width: 500px) {
        .form-card {
            padding: 15px;
        }
        .item-insumo {
            flex-direction: column; /* Apila el checkbox y el input de cantidad verticalmente */
            align-items: flex-start;
        }
        .insumo-action {
            width: 100%;
            justify-content: flex-start;
            margin-top: 5px;
        }
        .input-cantidad {
            width: 70%; /* El input toma más espacio en pantallas de móvil */
        }
    }
</style>

<div class="form-container">
    <div class="form-card">
        <h2 style="margin-bottom: 20px; font-size: 1.6rem;">Registrar Nueva Entrega / Donación</h2>
        <form action="" method="POST">
            
            <div class="form-group">
                <label for="tipo_receptor">Tipo de Receptor:</label>
                <select name="tipo_receptor" id="tipo_receptor" onchange="toggleReceptor()" required class="form-control">
                    <option value="persona">Persona Natural</option>
                    <option value="centro">Centro de Acopio</option>
                </select>
            </div>

            <div id="campos_persona">
                <h3 style="margin: 20px 0 10px; font-size: 1.3rem;">Datos del Beneficiario</h3>
                <div class="grid-form">
                    <div class="form-group"><label>Cédula:</label><input type="text" name="cedula" id="cedula" class="form-control"></div>
                    <div class="form-group"><label>Nombre:</label><input type="text" name="nombre" class="form-control"></div>
                    <div class="form-group"><label>Apellido:</label><input type="text" name="apellido" class="form-control"></div>
                    <div class="form-group"><label>Teléfono:</label><input type="text" name="telefono" class="form-control"></div>
                </div>
            </div>

            <div id="campos_centro" style="display:none;">
                <h3 style="margin: 20px 0 10px; font-size: 1.3rem;">Seleccionar Centro de Acopio</h3>
                <div class="form-group">
                    <label>Centro Destino:</label>
                    <select name="centro_id" id="centro_id" class="form-control">
                        <?php while($c = $centros->fetch_assoc()): ?>
                            <option value="<?=$c['id']?>"><?=$c['nombre']?> (<?=$c['ubicacion']?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">
            <h3 style="margin-bottom: 15px; font-size: 1.3rem;">Detalle de la Entrega (Insumos)</h3>

            <div class="form-group">
                <label style="margin-bottom:10px;">Selecciona los Insumos y la Cantidad a Donar:</label>
                
                <div style="margin-bottom: 12px;">
                    <input type="text" id="buscador_insumos" onkeyup="filtrarInsumos()" placeholder="🔍 Buscar insumo por nombre..." class="form-control" style="padding: 10px; font-size: 14px;">
                </div>

                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;" id="lista_contenedor_insumos">
                    
                    <?php if($insumos_disponibles->num_rows == 0): ?>
                        <p style="color:gray;" id="sin_insumos_msg">No hay insumos en el inventario actual.</p>
                    <?php endif; ?>
                    
                    <?php 
                    $index = 0;
                    while($ins = $insumos_disponibles->fetch_assoc()): 
                    ?>
                        <div class="item-insumo" data-nombre="<?=strtolower($ins['nombre'])?>">
                            <div class="insumo-info">
                                <input type="checkbox" name="insumos[<?=$index?>]" value="<?=$ins['id']?>" id="chk_<?=$ins['id']?>" onchange="toggleCantidad(<?=$ins['id']?>)"> 
                                <label for="chk_<?=$ins['id']?>" style="cursor:pointer; font-weight:normal; display: inline;">
                                    <strong><?=$ins['nombre']?></strong> <span style="font-size: 0.9rem; color:#555;">(Disp: <?=$ins['cantidad']?> <?=$ins['unidad_medida']?>)</span>
                                </label>
                            </div>
                            <div class="insumo-action">
                                <input type="number" step="0.01" name="cantidades[<?=$index?>]" id="cant_<?=$ins['id']?>" placeholder="Cantidad" disabled class="input-cantidad" max="<?=$ins['cantidad']?>">
                                <span style="font-size: 12px; color: #666; min-width: 30px; text-align: left;"><?=$ins['unidad_medida']?></span>
                            </div>
                        </div>
                    <?php 
                        $index++;
                    endwhile; 
                    ?>
                    
                    <p id="no_resultados" style="display: none; color: #64748b; text-align: center; padding: 10px;">No se encontraron insumos que coincidan.</p>
                </div>
            </div>

            <div class="form-group">
                <label>Detalle Adicional / Observaciones:</label>
                <textarea name="detalle_adicional" rows="3" class="form-control"></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label>Responsable de la Entrega:</label>
                <input type="text" name="responsable_entrega" required class="form-control">
            </div>

            <button type="submit" style="background: #007bff; color: white; border: none; padding: 14px 20px; cursor: pointer; border-radius: 4px; font-size: 16px; width: 100%; font-weight: bold;">Registrar Entrega y Actualizar Inventario</button>
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
        
        if (nombreInsumo.includes(textoBusqueda) || checkbox.checked) {
            // Se usa el display adecuado según tamaño de pantalla a través de remoción de la propiedad inline obsoleta
            fila.style.display = ''; 
            coincidencias++;
        } else {
            fila.style.display = 'none';
        }
    });

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
<?php $conn->close();?>