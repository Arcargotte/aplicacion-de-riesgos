<?php
session_start();
// Control de acceso: Solo administradores centrales
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

include('header.php');

// Habilitar el reporte de excepciones
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once('conexion.php');

$mensaje = "";
$tipo_mensaje = "";

// 1. PROCESAR CREACIÓN DE NUEVO CENTRO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_crear'])) {
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $direccion = $conn->real_escape_string(trim($_POST['direccion']));
    $telefono = $conn->real_escape_string(trim($_POST['telefono']));
    $es_sede_principal = isset($_POST['es_sede_principal']) ? 1 : 0;
    
    if (empty($nombre)) {
        $mensaje = "⚠️ El nombre del centro es obligatorio.";
        $tipo_mensaje = "error";
    } else {
        try {
            $sql_insert = "INSERT INTO centros_acopio (nombre, direccion, telefono, es_sede_principal, activo) 
                           VALUES ('$nombre', '$direccion', '$telefono', $es_sede_principal, 1)";
            $conn->query($sql_insert);
            $mensaje = "✅ Centro de acopio registrado exitosamente.";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $mensaje = "❌ Error al registrar el centro: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 2. PROCESAR EDICIÓN DE CENTRO EXISTENTE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_editar'])) {
    $id_editar = intval($_POST['centro_id']);
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $direccion = $conn->real_escape_string(trim($_POST['direccion']));
    $telefono = $conn->real_escape_string(trim($_POST['telefono']));
    $es_sede_principal = isset($_POST['es_sede_principal']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre)) {
        $mensaje = "⚠️ El nombre del centro es obligatorio.";
        $tipo_mensaje = "error";
    } else {
        try {
            $sql_update = "UPDATE centros_acopio 
                           SET nombre = '$nombre', direccion = '$direccion', telefono = '$telefono', 
                               es_sede_principal = $es_sede_principal, activo = $activo 
                           WHERE id = $id_editar";
            $conn->query($sql_update);
            $mensaje = "✅ Datos del centro actualizados correctamente.";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $mensaje = "❌ Error al actualizar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 3. OBTENER TODOS LOS CENTROS DE ACOPIO
try {
    $sql_centros = "SELECT * FROM centros_acopio ORDER BY es_sede_principal DESC, nombre ASC";
    $resultado_centros = $conn->query($sql_centros);
    $centros = [];
    while($c = $resultado_centros->fetch_assoc()) {
        $centros[] = $c;
    }
} catch (Exception $e) {
    die("Error en la consulta de datos: " . $e->getMessage());
}
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8" 
     x-data="{ 
        modalCrear: false, 
        modalEditar: false,
        centroEdit: {id: '', nombre: '', direccion: '', telefono: '', es_sede_principal: 0, activo: 1}
     }">
    
    <div class="mb-6 border-b border-slate-200 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Gestión de Centros de Acopio</h2>
            <p class="text-sm text-slate-500 font-medium">Administra las sedes, ubicaciones y estados operativos de la ONG</p>
        </div>
        <button @click="modalCrear = true" class="w-full sm:w-auto bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold py-3 px-4 rounded-xl transition text-center shadow-md shadow-sky-600/10 cursor-pointer">
            + Registrar Nuevo Centro
        </button>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="mb-6 p-4 rounded-xl text-sm font-bold border shadow-xs
            <?php echo $tipo_mensaje === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/50 text-slate-400 font-bold text-xs uppercase tracking-wider">
                        <th class="p-4 w-12">ID</th>
                        <th class="p-4">Nombre del Centro</th>
                        <th class="p-4">Dirección</th>
                        <th class="p-4">Contacto</th>
                        <th class="p-4 text-center">Estado</th>
                        <th class="p-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php foreach ($centros as $c): ?>
                        <tr class="hover:bg-slate-50/50 transition <?php echo $c['activo'] == 0 ? 'opacity-60 bg-slate-50' : ''; ?>">
                            <td class="p-4 font-mono text-xs text-slate-400">#<?php echo $c['id']; ?></td>
                            <td class="p-4 font-bold text-slate-900">
                                <div class="flex items-center gap-2">
                                    <?php echo htmlspecialchars($c['nombre']); ?>
                                    <?php if($c['es_sede_principal'] == 1): ?>
                                        <span class="bg-amber-100 text-amber-800 text-[10px] px-2 py-0.5 rounded-full font-black tracking-wider border border-amber-200">SEDE PRINCIPAL</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 text-xs text-slate-500 max-w-xs truncate" title="<?php echo htmlspecialchars($c['direccion']); ?>">
                                <?php echo htmlspecialchars($c['direccion']) ?: '<span class="italic text-slate-300">No registrada</span>'; ?>
                            </td>
                            <td class="p-4 font-mono text-xs text-slate-500">
                                <?php echo htmlspecialchars($c['telefono']) ?: '-'; ?>
                            </td>
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $c['activo'] == 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                                    <?php echo $c['activo'] == 1 ? 'Operativo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button @click="centroEdit = {id: '<?php echo $c['id']; ?>', nombre: '<?php echo htmlspecialchars($c['nombre'], ENT_QUOTES); ?>', direccion: '<?php echo htmlspecialchars($c['direccion'], ENT_QUOTES); ?>', telefono: '<?php echo htmlspecialchars($c['telefono'], ENT_QUOTES); ?>', es_sede_principal: <?php echo $c['es_sede_principal']; ?>, activo: <?php echo $c['activo']; ?>}; modalEditar = true;" 
                                        class="text-xs bg-slate-100 hover:bg-sky-50 hover:text-sky-700 text-slate-700 font-bold py-1.5 px-3 rounded-lg transition cursor-pointer">
                                    ✏️ Gestionar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 gap-3 p-4 md:hidden">
            <?php foreach ($centros as $c): ?>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col gap-2 <?php echo $c['activo'] == 0 ? 'opacity-70' : ''; ?>">
                    <div class="flex justify-between items-start border-b border-slate-200/60 pb-2">
                        <div>
                            <span class="font-mono text-xs text-slate-400">ID #<?php echo $c['id']; ?></span>
                            <div class="text-base font-bold text-slate-900 mt-0.5"><?php echo htmlspecialchars($c['nombre']); ?></div>
                        </div>
                        <?php if($c['es_sede_principal'] == 1): ?>
                            <span class="bg-amber-100 text-amber-800 text-[9px] px-2 py-0.5 rounded-full font-black tracking-wider border border-amber-200">PRINCIPAL</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-xs text-slate-500 mt-1">
                        <strong class="text-slate-700">Dir:</strong> <?php echo htmlspecialchars($c['direccion']) ?: '-'; ?>
                    </div>
                    <div class="text-xs text-slate-500">
                        <strong class="text-slate-700">Tel:</strong> <?php echo htmlspecialchars($c['telefono']) ?: '-'; ?>
                    </div>
                    <div class="text-xs mt-1">
                        Estado: <span class="font-bold <?php echo $c['activo'] == 1 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?php echo $c['activo'] == 1 ? 'Operativo' : 'Inactivo'; ?></span>
                    </div>

                    <div class="mt-2 pt-2 border-t border-slate-200/40">
                        <button @click="centroEdit = {id: '<?php echo $c['id']; ?>', nombre: '<?php echo htmlspecialchars($c['nombre'], ENT_QUOTES); ?>', direccion: '<?php echo htmlspecialchars($c['direccion'], ENT_QUOTES); ?>', telefono: '<?php echo htmlspecialchars($c['telefono'], ENT_QUOTES); ?>', es_sede_principal: <?php echo $c['es_sede_principal']; ?>, activo: <?php echo $c['activo']; ?>}; modalEditar = true;"
                                class="w-full text-center bg-white border border-slate-200 text-slate-700 font-bold py-2.5 rounded-lg text-xs hover:bg-sky-50 hover:text-sky-700 transition cursor-pointer">
                            ✏️ Configurar Centro
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="fixed inset-0 z-50 overflow-y-auto" x-show="modalCrear" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="modalCrear = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all w-full max-w-lg p-5 sm:p-6 my-8"
                 x-show="modalCrear" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="text-lg font-black text-slate-900">Registrar Nuevo Centro</h3>
                    <button @click="modalCrear = false" class="text-slate-400 hover:text-slate-600 text-xl cursor-pointer">&times;</button>
                </div>

                <form action="gestion_centros.php" method="POST" class="space-y-4">
                    <input type="hidden" name="accion_crear" value="1">

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Nombre del Centro *</label>
                        <input type="text" name="nombre" required placeholder="Ej. Sede Petare"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Dirección Física</label>
                        <textarea name="direccion" rows="2" placeholder="Ubicación exacta..."
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition resize-none"></textarea>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Teléfono de Contacto</label>
                        <input type="text" name="telefono" placeholder="Ej. 0412-1234567"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-sky-500 focus:bg-white transition">
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" name="es_sede_principal" id="crear_principal" class="w-4 h-4 text-sky-600 border-slate-300 rounded focus:ring-sky-500 cursor-pointer">
                        <label for="crear_principal" class="text-sm font-bold text-slate-700 cursor-pointer">Marcar como Sede Principal</label>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button type="button" @click="modalCrear = false" class="w-full sm:w-auto bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold py-3 px-4 rounded-xl transition cursor-pointer">Cancelar</button>
                        <button type="submit" class="w-full sm:w-auto bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold py-3 px-5 rounded-xl transition shadow-md cursor-pointer">Guardar Centro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 overflow-y-auto" x-show="modalEditar" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="modalEditar = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all w-full max-w-lg p-5 sm:p-6 my-8"
                 x-show="modalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="text-lg font-black text-slate-900">Configurar Centro: <span class="text-sky-600" x-text="centroEdit.nombre"></span></h3>
                    <button @click="modalEditar = false" class="text-slate-400 hover:text-slate-600 text-xl cursor-pointer">&times;</button>
                </div>

                <form action="gestion_centros.php" method="POST" class="space-y-4">
                    <input type="hidden" name="accion_editar" value="1">
                    <input type="hidden" name="centro_id" :value="centroEdit.id">

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Nombre del Centro *</label>
                        <input type="text" name="nombre" required :value="centroEdit.nombre" @input="centroEdit.nombre = $target.value"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Dirección Física</label>
                        <textarea name="direccion" rows="2" :value="centroEdit.direccion" @input="centroEdit.direccion = $target.value"
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition resize-none"></textarea>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Teléfono de Contacto</label>
                        <input type="text" name="telefono" :value="centroEdit.telefono" @input="centroEdit.telefono = $target.value"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-sky-500 focus:bg-white transition">
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-2 bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="es_sede_principal" id="editar_principal" class="w-4 h-4 text-sky-600 border-slate-300 rounded cursor-pointer" :checked="centroEdit.es_sede_principal == 1">
                            <label for="editar_principal" class="text-xs font-bold text-slate-700 cursor-pointer">Sede Principal</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="activo" id="editar_activo" class="w-4 h-4 text-emerald-600 border-slate-300 rounded cursor-pointer" :checked="centroEdit.activo == 1">
                            <label for="editar_activo" class="text-xs font-bold text-slate-700 cursor-pointer">Centro Activo / Operativo</label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button type="button" @click="modalEditar = false" class="w-full sm:w-auto bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold py-3 px-4 rounded-xl transition cursor-pointer">Cancelar</button>
                        <button type="submit" class="w-full sm:w-auto bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold py-3 px-5 rounded-xl transition shadow-md cursor-pointer">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

</body>
</html>
<?php $conn->close(); ?>