<?php
session_start();

// Si ya está logueado, redirigir según su rol
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['rol'] === 'administrador_central') {
        header("Location: panel_central.php");
    } else {
        header("Location: formulario_solicitud.php");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Conexión nativa a MariaDB
    require_once('conexion.php');

    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // Buscar usuario y su rol
    $sql = "SELECT u.*, r.nombre as rol_nombre FROM usuarios u 
            JOIN roles r ON u.rol_id = r.id 
            WHERE u.username = '$username'";
    
    $result = $conn->query($sql);

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verificar contraseña con el hash de la base de datos
        if (password_verify($password, $user['password'])) {
            // Guardar datos en sesión
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol_nombre'];
            $_SESSION['centro_id'] = $user['centro_id'];

            // Redirección según rol
            if ($user['rol_nombre'] === 'administrador_central') {
                header("Location: panel_central.php");
            } else {
                header("Location: formulario_solicitud.php");
            }
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "El usuario no existe.";
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - ONG Montalbán</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white w-full max-w-sm rounded-2xl shadow-xl p-6 sm:p-8 border border-slate-200">
        
        <div class="text-center mb-6">
            <span class="text-xs font-bold tracking-wider text-red-600 bg-red-50 px-3 py-1 rounded-full uppercase">Acceso de Personal</span>
            <h2 class="text-2xl font-black text-slate-800 mt-2">ONG MONTALBÁN</h2>
            <p class="text-slate-500 text-sm mt-1">Control de Inventario y Emergencias</p>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-lg font-medium animate-pulse">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-5">
            <div>
                <label for="username" class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Usuario</label>
                <input type="text" id="username" name="username" required 
                       autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false"
                       class="w-full p-3.5 border border-slate-300 rounded-xl bg-slate-50 shadow-sm text-slate-900 text-base focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
            </div>
            
            <div>
                <label for="password" class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Contraseña</label>
                <input type="password" id="password" name="password" required
                       class="w-full p-3.5 border border-slate-300 rounded-xl bg-slate-50 shadow-sm text-slate-900 text-base focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
            </div>
            
            <button type="submit" 
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-md transition duration-150 transform active:scale-98 text-center uppercase text-sm tracking-wider cursor-pointer">
                Ingresar al Sistema
            </button>
        </form>
        
    </div>

</body>
</html>