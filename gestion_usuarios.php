<?php
session_start();
// Control de acceso: Solo administradores centrales
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login");
    exit;
}

include('header.php');

// Habilitar el reporte de excepciones para usar transacciones robustas con try-catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once('conexion.php');

$mensaje = "";
$tipo_mensaje = "";

// 1. PROCESAR ELIMINACIÓN DE USUARIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_eliminar'])) {
    $id_eliminar = intval($_POST['usuario_id']);
    
    if ($id_eliminar === intval($_SESSION['usuario_id'])) {
        $mensaje = "❌ No puedes eliminar tu propio usuario en sesión.";
        $tipo_mensaje = "error";
    } else {
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM usuarios WHERE id = $id_eliminar");
            $conn->commit();
            $mensaje = "✅ Usuario eliminado permanentemente del sistema.";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "❌ Error al intentar eliminar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 2. PROCESAR EDICIÓN / MODIFICACIÓN DE USUARIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_editar'])) {
    $id_editar = intval($_POST['usuario_id']);
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $rol_id = intval($_POST['rol_id']);
    // ADAPTACIÓN: La nueva BD no permite NULL en centro_id. 
    // Si es admin central (1), lo asignamos por defecto a la Sede Central (1).
    $centro_id = ($rol_id == 1) ? 1 : intval($_POST['centro_id']);
    $password = trim($_POST['password']); // input name='password'

    if (empty($nombre) || empty($rol_id)) {
        $mensaje = "⚠️ El nombre y el rol son campos obligatorios.";
        $tipo_mensaje = "error";
    } else {
        $conn->begin_transaction();
        try {
            // ADAPTACIÓN: La columna en la DB para la contraseña ahora se llama 'clave'
            if (!empty($password)) {
                $password_hashed = password_hash($password, PASSWORD_BCRYPT);
                $sql_update = "UPDATE usuarios SET nombre = '$nombre', rol_id = $rol_id, centro_id = $centro_id, clave = '$password_hashed' WHERE id = $id_editar";
            } else {
                $sql_update = "UPDATE usuarios SET nombre = '$nombre', rol_id = $rol_id, centro_id = $centro_id WHERE id = $id_editar";
            }

            $conn->query($sql_update);
            $conn->commit();
            $mensaje = "✅ Datos de usuario actualizados correctamente.";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "❌ Error al actualizar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 3. OBTENER INFORMACIÓN DE SOPORTE PARA LOS MODALES
try {
    $roles_query = $conn->query("SELECT id, nombre FROM roles");
    $roles_db = [];
    while($r = $roles_query->fetch_assoc()) { $roles_db[] = $r; }

    $centros_query = $conn->query("SELECT id, nombre FROM centros_acopio ORDER BY nombre ASC");
    $centros_db = [];
    while($c = $centros_query->fetch_assoc()) { $centros_db[] = $c; }

    // 4. CONSULTA ESTRUCTURADA: ADAPTACIÓN u.username -> u.usuario, y uso de JOIN directo porque centro_id es NOT NULL
    $sql_usuarios = "SELECT u.id, u.usuario, u.nombre, u.rol_id, u.centro_id, 
                            r.nombre AS rol_nombre, 
                            c.nombre AS sede_nombre 
                     FROM usuarios u
                     JOIN roles r ON u.rol_id = r.id
                     JOIN centros_acopio c ON u.centro_id = c.id
                     ORDER BY u.centro_id ASC, u.nombre ASC";
    $resultado_usuarios = $conn->query($sql_usuarios);
} catch (Exception $e) {
    die("Error en la consulta de datos: " . $e->getMessage());
}

$usuarios_por_sede = [];
while($user = $resultado_usuarios->fetch_assoc()) {
    $usuarios_por_sede[$user['sede_nombre']][] = $user;
}
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8" 
     x-data="{ 
        modalEditar: false, 
        modalEliminar: false,
        userEdit: {id: '', nombre: '', usuario: '', rol_id: '', centro_id: ''},
        userDelete: {id: '', nombre: '', usuario: ''}
     }">
    
    <div class="mb-6 border-b border-slate-200 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Control de Personal y Roles</h2>
            <p class="text-sm text-slate-500 font-medium">Administra las credenciales de acceso de cada centro de acopio</p>
        </div>
        <a href="crear_usuario" class="w-full sm:w-auto bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold py-3 px-4 rounded-xl transition text-center shadow-md shadow-sky-600/10">
        Registrar Nuevo Usuario
        </a>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="mb-6 p-4 rounded-xl text-sm font-bold border shadow-xs
            <?php echo $tipo_mensaje === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="space-y-8">
        <?php foreach ($usuarios_por_sede as $sede => $lista_usuarios): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                
                <div class="bg-slate-50 border-b border-slate-200 px-4 py-3.5 sm:px-6">
                    <h3 class="text-sm sm:text-base font-black text-slate-800 flex items-center gap-2">
                        <span><?php echo htmlspecialchars($sede); ?></span>
                        <span class="ml-auto bg-slate-200 text-slate-700 text-xs px-2.5 py-0.5 rounded-full font-bold">
                            <?php echo count($lista_usuarios); ?> usuarios
                        </span>
                    </h3>
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/50 text-slate-400 font-bold text-xs uppercase tracking-wider">
                                <th class="p-4 w-12">ID</th>
                                <th class="p-4">Nombre Completo</th>
                                <th class="p-4">Usuario (Login)</th>
                                <th class="p-4">Rol Asignado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php foreach ($lista_usuarios as $u): ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-4 font-mono text-xs text-slate-400">#<?php echo $u['id']; ?></td>
                                    <td class="p-4 font-bold text-slate-900"><?php echo htmlspecialchars($u['nombre']); ?></td>
                                    <td class="p-4 font-mono text-xs bg-slate-50/80 rounded px-2 py-1 max-w-fit border border-slate-100"><?php echo htmlspecialchars($u['usuario']); ?></td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $u['rol_id'] == 1 ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-amber-50 text-amber-700 border border-amber-200'; ?>">
                                            <?php echo str_replace('_', ' ', $u['rol_nombre']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right space-x-2">
                                        <button @click="userEdit = {id: '<?php echo $u['id']; ?>', nombre: '<?php echo htmlspecialchars($u['nombre'], ENT_QUOTES); ?>', usuario: '<?php echo htmlspecialchars($u['usuario'], ENT_QUOTES); ?>', rol_id: '<?php echo $u['rol_id']; ?>', centro_id: '<?php echo $u['centro_id']; ?>'}; modalEditar = true;" 
                                                class="text-xs bg-slate-100 hover:bg-sky-50 hover:text-sky-700 text-slate-700 font-bold py-1.5 px-3 rounded-lg transition cursor-pointer">
                                            ✏️ Editar
                                        </button>
                                        <button @click="userDelete = {id: '<?php echo $u['id']; ?>', nombre: '<?php echo htmlspecialchars($u['nombre'], ENT_QUOTES); ?>', usuario: '<?php echo htmlspecialchars($u['usuario'], ENT_QUOTES); ?>'}; modalEliminar = true;" 
                                                class="text-xs bg-slate-100 hover:bg-red-50 hover:text-red-700 text-slate-600 font-bold py-1.5 px-3 rounded-lg transition cursor-pointer">
                                            🗑️ Eliminar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 gap-3 p-4 md:hidden">
                    <?php foreach ($lista_usuarios as $u): ?>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col gap-2">
                            <div class="flex justify-between items-center border-b border-slate-200/60 pb-1.5">
                                <span class="font-mono text-xs text-slate-400">ID #<?php echo $u['id']; ?></span>
                                <span class="text-xs font-black uppercase tracking-wider text-slate-700 <?php echo $u['rol_id'] == 1 ? 'text-sky-600' : 'text-amber-600'; ?>">
                                    <?php echo str_replace('_', ' ', $u['rol_nombre']); ?>
                                </span>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-400 uppercase font-black">Nombre</div>
                                <div class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($u['nombre']); ?></div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-400 uppercase font-black">Usuario</div>
                                <div class="text-sm font-mono text-slate-600"><?php echo htmlspecialchars($u['usuario']); ?></div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-200/40">
                                <button @click="userEdit = {id: '<?php echo $u['id']; ?>', nombre: '<?php echo htmlspecialchars($u['nombre'], ENT_QUOTES); ?>', usuario: '<?php echo htmlspecialchars($u['usuario'], ENT_QUOTES); ?>', rol_id: '<?php echo $u['rol_id']; ?>', centro_id: '<?php echo $u['centro_id']; ?>'}; modalEditar = true;"
                                        class="w-full text-center bg-white border border-slate-200 text-slate-700 font-bold py-2.5 rounded-lg text-xs hover:bg-slate-100 transition cursor-pointer">
                                    ✏️ Modificar
                                </button>
                                <button @click="userDelete = {id: '<?php echo $u['id']; ?>', nombre: '<?php echo htmlspecialchars($u['nombre'], ENT_QUOTES); ?>', usuario: '<?php echo htmlspecialchars($u['usuario'], ENT_QUOTES); ?>'}; modalEliminar = true;"
                                        class="w-full text-center bg-white border border-rose-200 text-rose-600 font-bold py-2.5 rounded-lg text-xs hover:bg-rose-50 transition cursor-pointer">
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <div class="fixed inset-0 z-50 overflow-y-auto" x-show="modalEditar" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="modalEditar = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all w-full max-w-lg p-5 sm:p-6 my-8"
                 x-show="modalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="text-lg font-black text-slate-900">Modificar Cuenta: <span class="text-sky-600 font-mono" x-text="'@' + userEdit.usuario"></span></h3>
                    <button @click="modalEditar = false" class="text-slate-400 hover:text-slate-600 text-xl cursor-pointer">&times;</button>
                </div>

                <form action="gestion_usuarios" method="POST" class="space-y-4">
                    <input type="hidden" name="accion_editar" value="1">
                    <input type="hidden" name="usuario_id" :value="userEdit.id">

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Nombre Completo</label>
                        <input type="text" name="nombre" required :value="userEdit.nombre" @input="userEdit.nombre = $target.value"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Rol del Sistema</label>
                        <select name="rol_id" required x-model="userEdit.rol_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition">
                            <?php foreach ($roles_db as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo str_replace('_', ' ', $r['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1" x-show="userEdit.rol_id != 1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Centro de Acopio Asignado</label>
                        <select name="centro_id" :required="userEdit.rol_id != 1" x-model="userEdit.centro_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none focus:border-sky-500 focus:bg-white transition">
                            <option value="" disabled>Seleccionar sede...</option>
                            <?php foreach ($centros_db as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Nueva Contraseña (Opcional)</label>
                        <input type="password" name="password" placeholder="Dejar en blanco para mantener la actual"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-sky-500 focus:bg-white transition">
                        <span class="text-[11px] text-slate-400">Solo escribe aquí si deseas cambiarle la contraseña de forma forzosa.</span>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button type="button" @click="modalEditar = false" class="w-full sm:w-auto bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold py-3 px-4 rounded-xl transition cursor-pointer">Cancelar</button>
                        <button type="submit" class="w-full sm:w-auto bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold py-3 px-5 rounded-xl transition shadow-md cursor-pointer">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 overflow-y-auto" x-show="modalEliminar" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="modalEliminar = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all w-full max-w-md p-5 sm:p-6 my-8"
                 x-show="modalEliminar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                
                <div class="text-center sm:text-left">
                    <div class="mx-auto sm:mx-0 flex h-12 w-12 items-center justify-center rounded-full bg-rose-50 border border-rose-200 text-rose-600 mb-4">
                        ⚠️
                    </div>
                    <h3 class="text-lg font-black text-slate-900 mb-1">¿Eliminar este usuario del sistema?</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Esta acción es definitiva. Se removerá el perfil de <strong class="text-slate-800" x-text="userDelete.nombre"></strong> (<span class="font-mono text-xs" x-text="'@' + userDelete.usuario"></span>) de la base de datos.
                    </p>
                </div>

                <form action="gestion_usuarios" method="POST">
                    <input type="hidden" name="accion_eliminar" value="1">
                    <input type="hidden" name="usuario_id" :value="userDelete.id">

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" @click="modalEliminar = false" class="w-full sm:w-auto bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold py-3 px-4 rounded-xl transition cursor-pointer">No, Cancelar</button>
                        <button type="submit" class="w-full sm:w-auto bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold py-3 px-5 rounded-xl transition shadow-md cursor-pointer">Sí, Eliminar de raíz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

</body>
</html>
<?php $conn->close(); ?>