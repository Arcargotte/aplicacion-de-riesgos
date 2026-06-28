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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans text-slate-800 m-0 p-0">

<header class="bg-slate-900 text-white shadow-md" x-data="{ mobileMenuOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16 gap-4">
            
            <div class="flex-shrink-0">
                <a href="panel_central.php" class="flex items-center">
                    <img src="assets/logo_app.png" alt="Logo ONG" class="h-10 w-auto object-contain">
                </a>
            </div>
            
            <nav class="hidden md:flex flex-1 items-center justify-between ml-6">
                
                <ul class="flex items-center gap-x-2 lg:gap-x-4 list-none m-0 p-0">
                    
                    <li class="relative" x-data="{ open: false }" @click.away="open = false">
                        <button @click="open = !open" class="text-slate-300 hover:text-sky-400 text-xs lg:text-sm font-bold tracking-wide transition flex items-center gap-1 py-2 px-2 rounded-lg hover:bg-slate-800 cursor-pointer">
                            Logística 
                            <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute left-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50">
                            <a href="panel_central.php" class="block px-4 py-2 text-xs lg:text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold">Inventario de Insumos</a>
                            <?php if ($_SESSION['rol'] === 'administrador_central'): ?>
                                <a href="gestion_solicitudes.php" class="block px-4 py-2 text-xs lg:text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold">Bandeja de Solicitudes</a>
                            <?php else: ?>
                                <a href="formulario_solicitud.php" class="block px-4 py-2 text-xs lg:text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold">Solicitar Insumos</a>
                            <?php endif; ?>
                        </div>
                    </li>

                    <li class="relative" x-data="{ open: false }" @click.away="open = false">
                        <button @click="open = !open" class="text-slate-300 hover:text-sky-400 text-xs lg:text-sm font-bold tracking-wide transition flex items-center gap-1 py-2 px-2 rounded-lg hover:bg-slate-800 cursor-pointer">
                            Donaciones
                            <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute left-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50">
                            <a href="formulario_donacion.php" class="block px-4 py-2 text-xs lg:text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold">Registrar Donación</a>
                            <a href="recepcion_donaciones.php" class="block px-4 py-2 text-xs lg:text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold">Recepción en Tránsito</a>
                            <a href="historico_donaciones.php" class="block px-4 py-2 text-xs lg:text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold">Historial de Entregas</a>
                        </div>
                    </li>

                    <li>
                        <a href="atencion_victimas.php" class="text-slate-300 hover:text-sky-400 text-xs lg:text-sm font-bold tracking-wide transition py-2 px-2 rounded-lg hover:bg-slate-800 whitespace-nowrap flex items-center gap-1">
                            Triaje y Víctimas
                        </a>
                    </li>

                    <?php if ($_SESSION['rol'] === 'administrador_central'): ?>
                        <li class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open" class="text-slate-300 hover:text-sky-400 text-xs lg:text-sm font-bold tracking-wide transition flex items-center gap-1 py-2 px-2 rounded-lg hover:bg-slate-800 cursor-pointer">
                                Estructura
                                <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-cloak class="absolute left-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50">
                                <a href="gestion_usuarios.php" class="block px-4 py-2 text-xs lg:text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold">Gestionar Personal</a>
                                <a href="gestion_centros.php" class="block px-4 py-2 text-xs lg:text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold">Centros de Acopio</a>
                                <div class="border-t border-slate-100 my-1"></div>
                                <a href="reportes_excel.php" class="block px-4 py-2 text-xs lg:text-sm text-emerald-700 hover:bg-emerald-50 font-bold flex items-center gap-2">
                                    Descarga de Reportes
                                </a>
                            </div>
                        </li>
                    <?php endif; ?>

                </ul>
                
                <div class="flex items-center gap-x-3 lg:gap-x-4 border-l border-slate-800 pl-4 lg:pl-6 flex-shrink-0">
                    <div class="bg-slate-800/90 px-3 py-1.5 rounded-xl flex items-center gap-x-2 border border-slate-700/50">
                        <span class="text-xs font-black text-slate-200 tracking-wide max-w-[120px] lg:max-w-none truncate">
                             <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                        </span>
                        <span class="bg-sky-600 text-[9px] lg:text-[10px] text-white px-2 py-0.5 rounded-md font-black uppercase tracking-wider whitespace-nowrap">
                            <?php echo str_replace('_', ' ', $_SESSION['rol']); ?>
                        </span>
                    </div>
                    <a href="?action=logout" class="bg-red-600 hover:bg-red-700 text-white text-xs font-black py-2 px-3 rounded-xl transition shadow-xs whitespace-nowrap">
                        Salir
                    </a>
                </div>
            </nav>

            <div class="flex md:hidden">
                <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="inline-flex items-center justify-center p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none transition cursor-pointer">
                    <span class="sr-only">Abrir menú</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" x-cloak />
                    </svg>
                </button>
            </div>

        </div>
    </div>

    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         class="md:hidden border-t border-slate-800 bg-slate-900 pb-4 px-4" x-cloak>
        
        <div class="my-4 p-3 bg-slate-800/80 rounded-xl border border-slate-700 flex flex-col gap-1.5">
            <span class="text-sm font-bold text-slate-100"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <span class="w-fit bg-sky-600 text-[10px] text-white px-2 py-0.5 rounded-md font-bold uppercase">
                <?php echo str_replace('_', ' ', $_SESSION['rol']); ?>
            </span>
        </div>

        <nav class="space-y-2">
            <ul class="flex flex-col gap-1 list-none m-0 p-0">
                <?php if ($_SESSION['rol'] === 'administrador_central'): ?>
                    <li><a href="panel_central.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Insumos</a></li>
                    <li><a href="formulario_donacion.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Registrar Donación</a></li>
                    <li><a href="gestion_solicitudes.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Solicitudes</a></li>
                    <li><a href="recepcion_donaciones.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Recepción de Donaciones</a></li>
                    <li><a href="historico_donaciones.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Historial Entregas</a></li>
                    <li><a href="gestion_usuarios.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Gestionar Personal</a></li>
                    <li><a href="gestion_centros.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Centros de Acopio</a></li>
                    <li><a href="atencion_victimas.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Atención a las víctimas</a></li> 
                    <li><a href="reportes_excel.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">
                        Descarga de Reportes
                    </a></li>   
                <?php else: ?>
                    <li><a href="panel_central.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Insumos</a></li>
                    <li><a href="formulario_donacion.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Registrar Donación</a></li>
                    <li><a href="recepcion_donaciones.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Recepción de Donaciones</a></li>
                    <li><a href="formulario_solicitud.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Solicitar Insumos</a></li>
                    <li><a href="historico_donaciones.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Historial Entregas</a></li>
                    <li><a href="atencion_victimas.php" class="block p-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white font-medium transition">Atención a las víctimas</a></li>    
                <?php endif; ?>
                
                <li class="pt-2">
                    <a href="?action=logout" class="block w-full text-center bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-xl shadow-md transition">
                        Cerrar Sesión
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">