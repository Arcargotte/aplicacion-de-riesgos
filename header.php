<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; color: #333; }
        
        .main-header { background-color: #1f2937; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .logo-section h1 { font-size: 20px; font-weight: 600; color: #38bdf8; }
        .nav-links { display: flex; gap: 20px; align-items: center; list-style: none; }
        .nav-links a { color: #f3f4f6; text-decoration: none; font-size: 14px; transition: color 0.2s; }
        .nav-links a:hover { color: #38bdf8; }
        
        .user-profile { background-color: #374151; padding: 6px 12px; border-radius: 20px; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .user-role { background-color: #0284c7; color: white; font-size: 11px; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; font-weight: bold; }
        .btn-logout { background-color: #ef4444; color: white !important; padding: 6px 12px; border-radius: 4px; font-weight: 500; }
        .btn-logout:hover { background-color: #dc2626 !important; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    </style>
</head>
<body>

<header class="main-header">
    <div class="logo-section">
        <h1>ONG Montalbán — Sistema Gestión</h1>
    </div>
    
    <nav>
        <ul class="nav-links">
            <?php if ($_SESSION['rol'] === 'administrador_central'): ?>
                <li><a href="panel_central.php">🏠 Panel Central</a></li>
                <li><a href="formulario_donacion.php">🎁 Registrar Donación</a></li>
                <li><a href="historico_donaciones.php">📜 Historial Entregas</a></li>
            <?php else: ?>
                <li><a href="formulario_solicitud.php">📋 Solicitar Insumos</a></li>
            <?php endif; ?>
            
            <li class="user-profile">
                <span>👤 <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></span>
                <span class="user-role"><?php echo str_replace('_', ' ', $_SESSION['rol']); ?></span>
            </li>
            
            <li><a href="?action=logout" class="btn-logout">Cerrar Sesión</a></li>
        </ul>
    </nav>
</header>