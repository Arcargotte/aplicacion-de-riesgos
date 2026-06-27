<?php
session_start();
// Control de acceso: solo administradores o personal autorizado
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

// --- 1. ENDPOINT AJAX: BUSCADOR INTERNO PARA LAS FILAS ---
if (isset($_GET['sugerir_insumo'])) {
    header('Content-Type: application/json');
    $conn = new mysqli("localhost", "root", "", "ong_inventario");
    if ($conn->connect_error) { echo json_encode([]); exit; }
    
    $busqueda = $conn->real_escape_string(trim($_GET['sugerir_insumo']));
    if (empty($busqueda)) { echo json_encode([]); exit; }

    $sql = "SELECT id, nombre, unidad_medida, categoria FROM insumos WHERE nombre LIKE '%$busqueda%' LIMIT 5";
    $resultado = $conn->query($sql);
    
    $datos = [];
    while ($row = $resultado->fetch_assoc()) { $datos[] = $row; }
    echo json_encode($datos);
    $conn->close();
    exit;
}

// --- 2. PROCESAR EL GUARDADO DE LA RECEPCIÓN (POST) ---
$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli("localhost", "root", "", "ong_inventario");
        $conn->begin_transaction(); // Activamos la transacción SQL

        $origen_donante = $conn->real_escape_string($_POST['origen_donante']); 
        $nombre_donante = $conn->real_escape_string(trim($_POST['nombre_donante']));
        $detalles_recepcion = $conn->real_escape_string(trim($_POST['detalles_recepcion']));
        $usuario_id = intval($_SESSION['usuario_id']);

        if (empty($nombre_donante)) {
            throw new Exception("El nombre o razón social del donante es obligatorio.");
        }

        // A. Insertar la cabecera en tu nueva tabla de ingresos
        $sql_cabecera = "INSERT INTO ingresos_donaciones (origen_donante, nombre_donante, detalles, usuario_id) 
                         VALUES ('$origen_donante', '$nombre_donante', '$detalles_recepcion', $usuario_id)";
        $conn->query($sql_cabecera);
        $ingreso_id = $conn->insert_id;

        // B. Procesar las filas dinámicas enviadas desde la interfaz
        if (isset($_POST['filas_insumos']) && is_array($_POST['filas_insumos'])) {
            foreach ($_POST['filas_insumos'] as $fila) {
                $nombre_insumo = $conn->real_escape_string(trim($fila['nombre']));
                $cantidad_ingresar = floatval($fila['cantidad']);
                
                if (empty($nombre_insumo) || $cantidad_ingresar <= 0) continue;

                // Bloqueo preventivo FOR UPDATE para mitigar concurrencia
                $check = $conn->query("SELECT id, cantidad FROM insumos WHERE LOWER(nombre) = LOWER('$nombre_insumo') LIMIT 1 FOR UPDATE");

                if ($check->num_rows > 0) {
                    // EL INSUMO YA EXISTE: Sumamos el stock
                    $insumo_db = $check->fetch_assoc();
                    $insumo_id = $insumo_db['id'];
                    $nueva_cantidad = floatval($insumo_db['cantidad']) + $cantidad_ingresar;

                    $conn->query("UPDATE insumos SET cantidad = $nueva_cantidad, estado = 'disponible' WHERE id = $insumo_id");
                } else {
                    // EL INSUMO NO EXISTE: Creación limpia en la BD
                    $categoria = $conn->real_escape_string($fila['categoria']);
                    $unidad_medida = $conn->real_escape_string($fila['unidad_medida']);

                    if (empty($categoria) || empty($unidad_medida)) {
                        throw new Exception("El insumo '$nombre_insumo' es nuevo. Debe especificar su categoría y unidad de medida.");
                    }

                    $conn->query("INSERT INTO insumos (nombre, categoria, cantidad, unidad_medida, estado) 
                                  VALUES ('$nombre_insumo', '$categoria', $cantidad_ingresar, '$unidad_medida', 'disponible')");
                    $insumo_id = $conn->insert_id;
                }

                // C. Insertar el desglose en el detalle de la donación
                $conn->query("INSERT INTO detalle_ingresos_donaciones (ingreso_id, insumo_id, cantidad_recibida) 
                              VALUES ($ingreso_id, $insumo_id, $cantidad_ingresar)");
            }
        }

        // Si ninguna query falló y no saltó ninguna excepción, consolidamos los datos
        $conn->commit();
        $mensaje = "🎉 Entrada por donación registrada de forma íntegra en las nuevas tablas de control.";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        if (isset($conn)) { $conn->rollback(); }
        $mensaje = "❌ Error crítico al procesar la recepción: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
    if (isset($conn)) { $conn->close(); }
}

include('header.php');
?>

<div class="max-w-4xl mx-auto py-6 px-4 sm:px-6" 
     x-data="{
        filas: [ { id: Date.now(), nombre: '', cantidad: '', existe: false, categoria: '', unidad_medida: '', sugerencias: [], mostrarLista: false } ],
        
        añadirFila() {
            this.filas.push({ id: Date.now(), nombre: '', cantidad: '', existe: false, categoria: '', unidad_medida: '', sugerencias: [], mostrarLista: false });
        },
        eliminarFila(index) {
            if(this.filas.length > 1) this.filas.splice(index, 1);
        },
        async buscarInsumo(fila) {
            if (fila.nombre.trim().length < 2) {
                fila.sugerencias = [];
                fila.existe = false;
                fila.mostrarLista = false;
                return;
            }
            try {
                let response = await fetch(`recepcion_donaciones.php?sugerir_insumo=${encodeURIComponent(fila.nombre)}`);
                fila.sugerencias = await response.json();
                fila.mostrarLista = fila.sugerencias.length > 0;
                
                let exacta = fila.sugerencias.find(i => i.nombre.toLowerCase() === fila.nombre.trim().toLowerCase());
                if(exacta) {
                    this.seleccionarSugerencia(fila, exacta);
                } else {
                    fila.existe = false;
                }
            } catch (e) { console.error(e); }
        },
        seleccionarSugerencia(fila, sugerencia) {
            fila.nombre = sugerencia.nombre;
            fila.categoria = sugerencia.categoria;
            fila.unidad_medida = sugerencia.unidad_medida;
            fila.existe = true;
            fila.mostrarLista = false;
        }
     }">

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        <div class="bg-slate-50 border-b border-slate-200 px-6 py-4">
            <h2 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-2">
                📥 Recepción de Donaciones de Terceros
            </h2>
            <p class="text-xs text-slate-500 font-medium">Registra de forma centralizada los insumos donados por entes gubernamentales, privados o civiles.</p>
        </div>

        <div class="p-6">
            <?php if (!empty($mensaje)): ?>
                <div class="mb-5 p-4 rounded-xl text-xs font-bold border shadow-xs
                    <?php echo $tipo_mensaje === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <form action="recepcion_donaciones.php" method="POST" class="space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Tipo de Donante</label>
                        <select name="origen_donante" required class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold focus:outline-none focus:border-sky-500">
                            <option value="Persona Natural">👤 Persona Natural</option>
                            <option value="Empresa Privada">🏢 Empresa Privada</option>
                            <option value="Gobierno">🏛️ Entidad Gubernamental</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 md:col-span-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Nombre / Razón Social del Donante</label>
                        <input type="text" name="nombre_donante" required placeholder="Ej. Juan Pérez, Farmatodo, Gobernación"
                               class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold focus:outline-none focus:border-sky-500">
                    </div>
                    
                    <div class="flex flex-col gap-1 md:col-span-3">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Detalles o Notas Adicionales</label>
                        <input type="text" name="detalles_recepcion" placeholder="Ej. Insumos entregados bajo acta firmada, cajas selladas"
                               class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold focus:outline-none focus:border-sky-500">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400">Desglose de Bienes Recibidos</h3>
                        <button type="button" @click="añadirFila()" class="bg-sky-50 text-sky-600 hover:bg-sky-100 text-xs font-black px-3 py-1.5 rounded-lg transition cursor-pointer">
                            ➕ Añadir Insumo
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(fila, index) in filas" :key="fila.id">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 p-3 bg-white border rounded-xl items-center relative"
                                 :class="fila.existe ? 'border-amber-200 bg-amber-50/20' : 'border-slate-200'">
                                
                                <div class="md:col-span-4 relative">
                                    <input type="text" :name="`filas_insumos[${index}][nombre]`" required x-model="fila.nombre"
                                           @input.debounce.300ms="buscarInsumo(fila)" placeholder="Escribe para buscar o crear..."
                                           @click.away="fila.mostrarLista = false"
                                           class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 text-xs font-bold focus:outline-none focus:border-sky-500">
                                    
                                    <ul class="absolute z-50 left-0 right-0 bg-white border border-slate-200 rounded-lg shadow-lg max-h-40 overflow-y-auto list-none p-0 m-0"
                                        x-show="fila.mostrarLista" x-cloak>
                                        <template x-for="sug in fila.sugerencias">
                                            <li>
                                                <button type="button" @click="seleccionarSugerencia(fila, sug)"
                                                        class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-sky-50 transition flex justify-between cursor-pointer">
                                                    <span x-text="sug.nombre"></span>
                                                    <span class="text-[10px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-400" x-text="sug.categoria"></span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div class="md:col-span-2">
                                    <input type="number" step="0.01" :name="`filas_insumos[${index}][cantidad]`" required min="0.01" placeholder="Cant."
                                           x-model="fila.cantidad" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 text-xs font-mono focus:outline-none focus:border-sky-500">
                                </div>

                                <div class="md:col-span-2.5">
                                    <select :name="`filas_insumos[${index}][categoria]`" :required="!fila.existe" :disabled="fila.existe" x-model="fila.categoria"
                                            class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 text-xs font-semibold focus:outline-none disabled:bg-slate-100 disabled:text-slate-500">
                                        <option value="">Categoría...</option>
                                        <option value="Alimentos">Alimentos</option>
                                        <option value="Medicamentos">Medicamentos</option>
                                        <option value="Material Médico">Material Médico</option>
                                        <option value="Ropa">Ropa</option>
                                        <option value="Higiene">Higiene</option>
                                        <option value="Otros">Otros</option>
                                    </select>
                                    <template x-if="fila.existe">
                                        <input type="hidden" :name="`filas_insumos[${index}][categoria]`" :value="fila.categoria">
                                    </template>
                                </div>

                                <div class="md:col-span-2.5">
                                    <select :name="`filas_insumos[${index}][unidad_medida]`" :required="!fila.existe" :disabled="fila.existe" x-model="fila.unidad_medida"
                                            class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 text-xs font-semibold focus:outline-none disabled:bg-slate-100 disabled:text-slate-500">
                                        <option value="">Medida...</option>
                                        <option value="Unidades">Unidades</option>
                                        <option value="Kilogramos">Kilogramos</option>
                                        <option value="Litros">Litros</option>
                                        <option value="Cajas">Cajas</option>
                                        <option value="Paquetes">Paquetes</option>
                                    </select>
                                    <template x-if="fila.existe">
                                        <input type="hidden" :name="`filas_insumos[${index}][unidad_medida]`" :value="fila.unidad_medida">
                                    </template>
                                </div>

                                <div class="md:col-span-1 text-right">
                                    <button type="button" @click="eliminarFila(index)" :disabled="filas.length === 1"
                                            class="text-red-500 hover:text-red-700 p-2 text-sm disabled:opacity-30 cursor-pointer">
                                        🗑️
                                    </button>
                                </div>

                                <div class="absolute -top-2 right-10 md:right-12 px-2 py-0.5 text-[9px] font-black rounded-md uppercase tracking-wider shadow-xs"
                                     :class="fila.existe ? 'bg-amber-500 text-white' : 'bg-emerald-600 text-white'">
                                    <span x-text="fila.existe ? '📈 Sumará Stock' : '✨ Nuevo Insumo'"></span>
                                </div>

                            </div>
                        </template>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-black py-4 px-4 rounded-xl transition text-center shadow-lg uppercase tracking-wider cursor-pointer">
                        📥 Procesar Lote e Incrementar Inventario
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

</body>
</html>