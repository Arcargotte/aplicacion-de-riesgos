<?php
session_start();
// Control de acceso: Solo administradores centrales
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

include('header.php');

$mensaje = "";
$tipo_mensaje = "";

require_once('conexion.php');

// 1. Obtener los roles disponibles desde la BD de forma dinámica (Corregido)
$roles_query = $conn->query("SELECT id, nombre FROM roles");
$roles_db = [];
while($r = $roles_query->fetch_assoc()){
    $roles_db[] = $r;
}

// 2. Obtener centros de acopio para el desplegable dinámico
$centros_query = $conn->query("SELECT id, nombre FROM centros_acopio ORDER BY nombre ASC");

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitización e igualación exacta con los campos de la BD
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $usuario = $conn->real_escape_string(trim($_POST['usuario'])); 
    $rol_id = intval($_POST['rol_id']); // Recibimos directamente el ID del rol (Numérico)
    $clave = trim($_POST['clave']);
    
    // ADAPTACIÓN: Si es diferente al rol 1 (Administrador), asigna el centro seleccionado,
    // de lo contrario (si es Admin), se le asigna por defecto a la Sede Central (1)
    $centro_id = ($rol_id != 1 && !empty($_POST['centro_id'])) 
        ? intval($_POST['centro_id']) 
        : 1; 

    if (empty($nombre) || empty($usuario) || empty($rol_id) || empty($clave)) {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
        $tipo_mensaje = "error";
    } else {
        // Verificar disponibilidad del usuario exacto en la columna 'usuario'
        $check_usuario = $conn->query("SELECT id FROM usuarios WHERE usuario = '$usuario'");
        if ($check_usuario->num_rows > 0) {
            $mensaje = "❌ El nombre de usuario ya se encuentra registrado.";
            $tipo_mensaje = "error";
        } else {
            $clave_hashed = password_hash($clave, PASSWORD_BCRYPT);
            
            // Insertar el usuario con los datos verificados
            $sql = "INSERT INTO usuarios (usuario, clave, nombre, rol_id, centro_id) 
                    VALUES ('$usuario', '$clave_hashed', '$nombre', $rol_id, $centro_id)";
            
            if ($conn->query($sql) === TRUE) {
                $mensaje = "✅ Usuario creado exitosamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "❌ Error al registrar el usuario: " . $conn->error;
                $tipo_mensaje = "error";
            }
        }
    }
}
?>

<div class="max-w-3xl mx-auto px-4 py-6 sm:px-6 lg:px-8" x-data="{ rol_id: '1' }">
    
    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Registrar Nuevo Usuario
        </h2>
        <p class="text-sm text-slate-500 font-medium">Asigna credenciales y dependencias según el esquema oficial</p>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="mb-6 p-4 rounded-xl text-sm font-bold border transition-all duration-200 shadow-xs
            <?php echo $tipo_mensaje === 'success' 
                ? 'bg-emerald-50 text-emerald-800 border-emerald-200' 
                : 'bg-rose-50 text-rose-800 border-rose-200'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="flex flex-col gap-1.5">
                    <label for="nombre" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Nombre Completo
                    </label>
                    <input type="text" name="nombre" id="nombre" required
                           placeholder="Ej. Juan Pérez"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-800 focus:outline-none focus:border-sky-500 focus:bg-white transition">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="rol_id" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Rol del Sistema
                    </label>
                    <div class="relative">
                        <select name="rol_id" id="rol_id" required x-model="rol_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-800 focus:outline-none focus:border-sky-500 focus:bg-white transition appearance-none cursor-pointer">
                            <?php foreach ($roles_db as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo str_replace('_', ' ', $r['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="usuario" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Nombre de Usuario (Login)
                    </label>
                    <input type="text" name="usuario" id="usuario" required
                           placeholder="Ej. juan_catia"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-800 focus:outline-none focus:border-sky-500 focus:bg-white transition font-mono">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="clave" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Contraseña Inicial
                    </label>
                    <input type="password" name="clave" id="clave" required
                           placeholder="••••••••"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-800 focus:outline-none focus:border-sky-500 focus:bg-white transition font-mono">
                </div>

                <div class="flex flex-col gap-1.5 col-span-1 md:col-span-2" 
                     x-show="rol_id != '1'" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-cloak>
                    <label for="centro_id" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Asignar Centro de Acopio Obligatorio
                    </label>
                    <div class="relative">
                        <select name="centro_id" id="centro_id" ::required="rol_id != '1'"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-800 focus:outline-none focus:border-sky-500 focus:bg-white transition appearance-none cursor-pointer">
                            <option value="" disabled selected>Selecciona la sede asignada...</option>
                            <?php 
                            $centros_query->data_seek(0);
                            while($centro = $centros_query->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $centro['id']; ?>"><?php echo htmlspecialchars($centro['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                            </svg>
                        </div>
                    </div>
                </div>

            </div>

            <div class="border-t border-slate-100 pt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="gestion_usuarios.php" 
                   class="w-full sm:w-auto text-center bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-bold py-3 px-6 rounded-xl transition cursor-pointer">
                    Cancelar
                </a>
                <button type="submit" 
                        class="w-full sm:w-auto bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold py-3 px-8 rounded-xl transition shadow-md shadow-sky-600/10 cursor-pointer">
                    Guardar Usuario
                </button>
            </div>

        </form>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>