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

$conn = new mysqli("localhost", "root", "", "ong_inventario");
if ($conn->connect_error) { 
    die("Conexión fallida: " . $conn->connect_error); 
}

// 1. Obtener los roles disponibles desde la BD para mapeo
$roles_query = $conn->query("SELECT id, nombre FROM roles");
$roles_db = [];
while($r = $roles_query->fetch_assoc()){
    $roles_db[$r['nombre']] = $r['id'];
}

// 2. Obtener centros de acopio para el desplegable dinámico
$centros_query = $conn->query("SELECT id, nombre FROM centros_acopio ORDER BY nombre ASC");

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitización e igualación exacta con los campos de la BD
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $username = $conn->real_escape_string(trim($_POST['username'])); // Corregido: antes usuario
    $rol_nombre = $_POST['rol_nombre']; 
    $password = trim($_POST['password']);
    
    // Asignación de IDs basada en la BD
    $rol_id = isset($roles_db[$rol_nombre]) ? $roles_db[$rol_nombre] : null;
    
    // Si es voluntario necesita centro_id, si es admin central es NULL
    $centro_id = ($rol_nombre === 'voluntario_centro' && !empty($_POST['centro_id'])) 
        ? intval($_POST['centro_id']) 
        : "NULL";

    if (empty($nombre) || empty($username) || empty($rol_id) || empty($password)) {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
        $tipo_mensaje = "error";
    } else {
        // Verificar disponibilidad del username exacto
        $check_usuario = $conn->query("SELECT id FROM usuarios WHERE username = '$username'");
        if ($check_usuario->num_rows > 0) {
            $mensaje = "❌ El nombre de usuario ya se encuentra registrado.";
            $tipo_mensaje = "error";
        } else {
            $password_hashed = password_hash($password, PASSWORD_BCRYPT);
            
            // Query armada respetando los tipos de datos numéricos y el NULL limpio
            $sql = "INSERT INTO usuarios (username, password, nombre, rol_id, centro_id) 
                    VALUES ('$username', '$password_hashed', '$nombre', $rol_id, $centro_id)";
            
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

<div class="max-w-3xl mx-auto px-4 py-6 sm:px-6 lg:px-8" x-data="{ rol: 'administrador_central' }">
    
    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            👤 Registrar Nuevo Usuario
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
                    <label for="rol_nombre" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Rol del Sistema
                    </label>
                    <div class="relative">
                        <select name="rol_nombre" id="rol_nombre" required x-model="rol"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-800 focus:outline-none focus:border-sky-500 focus:bg-white transition appearance-none cursor-pointer">
                            <option value="administrador_central">Administrador Central (Montalbán)</option>
                            <option value="voluntario_centro">Voluntario de Centro de Acopio</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="username" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Nombre de Usuario (Login)
                    </label>
                    <input type="text" name="username" id="username" required
                           placeholder="Ej. juan_catia"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-800 focus:outline-none focus:border-sky-500 focus:bg-white transition font-mono">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Contraseña Inicial
                    </label>
                    <input type="password" name="password" id="password" required
                           placeholder="••••••••"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-800 focus:outline-none focus:border-sky-500 focus:bg-white transition font-mono">
                </div>

                <div class="flex flex-col gap-1.5 col-span-1 md:col-span-2" 
                     x-show="rol === 'voluntario_centro'" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-cloak>
                    <label for="centro_id" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Asignar Centro de Acopio Obligatorio
                    </label>
                    <div class="relative">
                        <select name="centro_id" id="centro_id" ::required="rol === 'voluntario_centro'"
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
                <a href="panel_central.php" 
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