<?php
session_start();
// Control de acceso: Administradores o Personal autorizado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// --- 1. ENDPOINT AJAX: BUSQUEDA EN TIEMPO REAL (AUTOCOMPLETADO) ---
if (isset($_GET['buscar_insumo'])) {
    header('Content-Type: application/json');
    require_once('conexion.php');
    
    $busqueda = $conn->real_escape_string(trim($_GET['buscar_insumo']));
    
    // Si la búsqueda está vacía, no devolvemos nada
    if (empty($busqueda)) {
        echo json_encode([]);
        exit;
    }

    // Buscamos coincidencias parciales (LIKE) para sugerir en la lista (Límite 5 para no saturar la red)
    $sql = "SELECT id, nombre, cantidad, unidad_medida, categoria 
            FROM insumos 
            WHERE nombre LIKE '%$busqueda%' 
            LIMIT 5";
    $resultado = $conn->query($sql);
    
    $sugerencias = [];
    while ($row = $resultado->fetch_assoc()) {
        $sugerencias[] = $row;
    }
    
    echo json_encode($sugerencias);
    $conn->close();
    exit;
}

// --- 2. PROCESAR GUARDADO FORMULARIO (POST) ---
$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli("localhost", "root", "", "ong_inventario");
        $conn->begin_transaction(); 

        $nombre = $conn->real_escape_string(trim($_POST['nombre']));
        $cantidad_ingresar = floatval($_POST['cantidad']);

        if (empty($nombre) || $cantidad_ingresar <= 0) {
            throw new Exception("El nombre y una cantidad válida mayor a 0 son obligatorios.");
        }

        // Verificación estricta en el Backend por nombre exacto (Seguridad Concurrente)
        $check = $conn->query("SELECT id, cantidad FROM insumos WHERE LOWER(nombre) = LOWER('$nombre') LIMIT 1 FOR UPDATE");
        
        if ($check->num_rows > 0) {
            // EL INSUMO EXISTE: Se incrementa la cantidad
            $insumo_db = $check->fetch_assoc();
            $id_actualizar = $insumo_db['id'];
            $nueva_cantidad = floatval($insumo_db['cantidad']) + $cantidad_ingresar;

            $conn->query("UPDATE insumos SET cantidad = $nueva_cantidad, estado = 'disponible' WHERE id = $id_actualizar");
            $mensaje = "✅ Stock actualizado. Se añadieron $cantidad_ingresar unidades a '" . htmlspecialchars($nombre) . "'.";
        } else {
            // EL INSUMO NO EXISTE: Registro nuevo de raíz
            $categoria = $conn->real_escape_string($_POST['categoria']);
            $unidad_medida = $conn->real_escape_string($_POST['unidad_medida']);
            
            if (empty($categoria) || empty($unidad_medida)) {
                throw new Exception("Para insumos nuevos, la categoría y unidad de medida son obligatorias.");
            }

            $conn->query("INSERT INTO insumos (nombre, categoria, cantidad, unidad_medida, estado) 
                          VALUES ('$nombre', '$categoria', $cantidad_ingresar, '$unidad_medida', 'disponible')");
            $mensaje = "🎉 Nuevo insumo '" . htmlspecialchars($nombre) . "' registrado con éxito en el inventario.";
        }

        $conn->commit();
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        if (isset($conn)) { $conn->rollback(); }
        $mensaje = "❌ Error al procesar el insumo: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
    if (isset($conn)) { $conn->close(); }
}

include('header.php');
?>

<div class="max-w-xl mx-auto py-6 px-4 sm:px-6" 
     x-data="{
        nombreInsumo: '',
        sugerencias: [],
        existe: false,
        cargando: false,
        mostrarLista: false,
        insumoData: { cantidad: 0, unidad_medida: '', categoria: '' },
        
        async buscarSugerencias() {
            if (this.nombreInsumo.trim().length < 2) {
                this.sugerencias = [];
                this.existe = false;
                this.mostrarLista = false;
                return;
            }
            this.cargando = true;
            try {
                let response = await fetch(`agregar_insumo.php?buscar_insumo=${encodeURIComponent(this.nombreInsumo)}`);
                this.sugerencias = await response.json();
                this.mostrarLista = this.sugerencias.length > 0;
                
                // Si hay sugerencias, verificamos si alguna es una coincidencia exacta escrita a mano
                let coincidenciaExacta = this.sugerencias.find(i => i.nombre.toLowerCase() === this.nombreInsumo.trim().toLowerCase());
                if (coincidenciaExacta) {
                    this.seleccionarInsumo(coincidenciaExacta);
                } else {
                    this.existe = false;
                }
            } catch (error) {
                console.error('Error buscando sugerencias:', error);
            } finally {
                this.cargando = false;
            }
        },

        seleccionarInsumo(insumo) {
            this.nombreInsumo = insumo.nombre;
            this.insumoData = insumo;
            this.existe = true;
            this.mostrarLista = false;
        },

        limpiarSeleccion() {
            this.nombreInsumo = '';
            this.existe = false;
            this.sugerencias = [];
            this.insumoData = { cantidad: 0, unidad_medida: '', categoria: '' };
        }
     }"
     @click.away="mostrarLista = false">

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        <div class="bg-slate-50 border-b border-slate-200 px-6 py-4">
            <h2 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-2">
                Entrada de Insumos al Almacén
            </h2>
            <p class="text-xs text-slate-500 font-medium">Busca y selecciona un insumo existente o escribe uno nuevo para registrarlo.</p>
        </div>

        <div class="p-6">
            <?php if (!empty($mensaje)): ?>
                <div class="mb-5 p-4 rounded-xl text-xs font-bold border shadow-xs
                    <?php echo $tipo_mensaje === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <form action="agregar_insumo.php" method="POST" class="space-y-4">
                
                <div class="flex flex-col gap-1 relative">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Buscar o Escribir Insumo</label>
                    <div class="relative">
                        <input type="text" name="nombre" required x-model="nombreInsumo" 
                               @input.debounce.300ms="buscarSugerencias()" 
                               @focus="if(sugerencias.length > 0) mostrarLista = true"
                               autocomplete="off" placeholder="Escribe para buscar... (Ej. Harina, Gasas)"
                               :readonly="existe"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:border-sky-500 focus:bg-white transition pr-10 disabled:bg-slate-100">
                        
                        <div class="absolute inset-y-0 right-3 flex items-center" x-show="existe">
                            <button type="button" @click="limpiarSeleccion()" class="text-xs font-bold text-red-500 hover:text-red-700 bg-red-50 px-2 py-1 rounded-md transition">
                                Cambiar
                            </button>
                        </div>

                        <div class="absolute inset-y-0 right-3 flex items-center" x-show="cargando">
                            <svg class="animate-spin h-5 w-5 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>

                    <ul class="absolute z-50 left-0 right-0 top-[100%] mt-1 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden list-none p-0"
                        x-show="mostrarLista" x-cloak>
                        <template x-for="insumo in sugerencias" :key="insumo.id">
                            <li>
                                <button type="button" @click="seleccionarInsumo(insumo)"
                                        class="w-full text-left px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-sky-50 hover:text-sky-900 border-b border-slate-100 last:border-0 transition flex justify-between items-center cursor-pointer">
                                    <span x-text="insumo.nombre"></span>
                                    <span class="text-xs font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md" x-text="insumo.cantidad + ' ' + insumo.unidad_medida"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                <div x-show="existe" x-cloak 
                     class="p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl flex items-start gap-3 transition">
                    <span class="text-lg">💡</span>
                    <div class="text-xs">
                        <p class="font-bold">Insumo Seleccionado (Aumento de Inventario)</p>
                        <p class="mt-0.5 text-amber-700">Stock actual: <strong x-text="insumoData.cantidad + ' ' + insumoData.unidad_medida"></strong> bajo la categoría <strong x-text="insumoData.categoria"></strong>. La cantidad ingresada se **sumará** al stock existente.</p>
                    </div>
                </div>

                <div x-show="!existe && nombreInsumo.trim().length >= 2 && !cargando && sugerencias.length === 0" x-cloak 
                     class="p-3 bg-sky-50 border border-sky-200 text-sky-900 rounded-xl flex items-start gap-3 transition">
                    <span class="text-lg">✨</span>
                    <div class="text-xs">
                        <p class="font-bold">No hay coincidencias. ¿Es un insumo nuevo?</p>
                        <p class="mt-0.5 text-sky-700">Puedes continuar escribiendo el nombre completo y rellenar la categoría / unidad de medida de abajo para guardarlo por primera vez.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4" x-show="!existe" x-transition>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Categoría</label>
                        <select name="categoria" :required="!existe"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition">
                            <option value="">Seleccionar...</option>
                            <option value="Alimentos">Alimentos</option>
                            <option value="Medicamentos">Medicamentos</option>
                            <option value="Material Médico">Material Médico</option>
                            <option value="Ropa">Ropa</option>
                            <option value="Higiene">Higiene</option>
                            <option value="Otros">Otros</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Unidad de Medida</label>
                        <select name="unidad_medida" :required="!existe"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition">
                            <option value="">Seleccionar...</option>
                            <option value="unidades">Unidades (Kits / Piezas)</option>
                            <option value="kilogramos">Kilogramos (Kg)</option>
                            <option value="litros">Litros (L)</option>
                            <option value="cajas">Cajas</option>
                            <option value="paquetes">Paquetes</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Cantidad a <span x-text="existe ? 'Incrementar' : 'Registrar'">Registrar</span>
                    </label>
                    <div class="relative">
                        <input type="number" step="0.01" name="cantidad" required min="0.01" placeholder="0.00"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:outline-none focus:border-sky-500 focus:bg-white transition">
                        <div class="absolute inset-y-0 right-4 flex items-center text-xs font-bold text-slate-400" x-show="existe">
                            <span x-text="insumoData.unidad_medida"></span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <button type="submit" 
                            :class="existe ? 'bg-amber-600 hover:bg-amber-700 shadow-amber-600/10' : 'bg-sky-600 hover:bg-sky-700 shadow-sky-600/10'"
                            class="w-full text-white text-xs font-black py-3.5 px-4 rounded-xl transition text-center shadow-lg uppercase tracking-wider cursor-pointer">
                        <span x-text="existe ? ' Incrementar Stock Existente' : ' Registrar Nuevo Insumo'">Registrar Insumo</span>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

</body>
</html>