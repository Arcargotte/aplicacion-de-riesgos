<?php
session_start();
// Control de acceso: solo voluntarios de centro
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    try {
        $conn = new mysqli("localhost", "root", "", "ong_inventario");
        
        $usuario_id = $_SESSION['usuario_id'];
        $centro_id = $_SESSION['centro_id']; 

        // Iniciar transacción explícita
        $conn->begin_transaction();

        // Insertar cabecera de la solicitud
        $conn->query("INSERT INTO solicitudes (usuario_id, centro_id) VALUES ($usuario_id, $centro_id)");
        $solicitud_id = $conn->insert_id;

        // Recuperar los arreglos dinámicos enviados por JS
        $nombres_insumos = $_POST['nombre_insumo'];
        $cantidades = $_POST['cantidad_insumo'];

        if (is_array($nombres_insumos)) {
            for ($i = 0; $i < count($nombres_insumos); $i++) {
                $nombre = $conn->real_escape_string($nombres_insumos[$i]);
                $cantidad = $conn->real_escape_string($cantidades[$i]);

                if (!empty($nombre) && !empty($cantidad)) {
                    $conn->query("INSERT INTO detalle_solicitudes (solicitud_id, nombre_insumo, cantidad) VALUES ($solicitud_id, '$nombre', '$cantidad')");
                }
            }
        }
        
        // Todo ocurrió bien, guardamos definitivamente
        $conn->commit();
        $conn->close();
        
        echo "<script>alert('¡Solicitud enviada a Montalbán correctamente!'); window.location.href='formulario_solicitud.php';</script>";
        exit;
        
    } catch (Exception $e) {
        // Deshacer cualquier inserción previa de este intento
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
            $conn->close();
        }
        echo "<script>alert('❌ Error crítico al procesar la solicitud. Intente nuevamente.'); window.location.href='formulario_solicitud.php';</script>";
        exit;
    }
}

include('header.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Solicitud de Insumos - Centro de Acopio</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-3xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    
    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            📝 Nueva Solicitud de Insumos
        </h2>
        <p class="text-sm text-slate-500 font-medium">Requerimiento de carga dirigido a la Sede Central (Montalbán)</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8">
        
        <div class="bg-slate-50 border border-slate-200/60 rounded-xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs sm:text-sm text-slate-600">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Responsable emisor:</span>
                <span class="font-bold text-slate-800">👤 <?php echo htmlspecialchars($_SESSION['nombre']); ?></span> 
                <span class="text-slate-400 font-medium">(Voluntario)</span>
            </div>
            <div class="sm:text-right">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Fecha del registro:</span>
                <span class="font-mono font-semibold text-slate-700">🕒 <?php echo date("d/m/Y g:i A"); ?></span>
            </div>
        </div>

        <form action="" method="POST" class="space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <label class="text-xs sm:text-sm font-black text-slate-800 uppercase tracking-wider">
                    📋 Insumos Solicitados (Entrada Libre):
                </label>
                <button type="button" onclick="agregarFila()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-slate-900 text-white hover:bg-slate-800 rounded-lg shadow-xs transition duration-150 cursor-pointer">
                    ➕ Añadir Ítem
                </button>
            </div>
            
            <div id="contenedor-insumos" class="space-y-3">
                <div class="fila-insumo flex items-center gap-2 bg-slate-50/50 p-2 rounded-xl border border-slate-200/40">
                    <div class="flex-2">
                        <input type="text" name="nombre_insumo[]" placeholder="Ej: Harina de maíz, Pañales G..." 
                               class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400 focus:border-transparent transition" required>
                    </div>
                    <div class="flex-1">
                        <input type="text" name="cantidad_insumo[]" placeholder="Ej: 50 cajas, 20 kg..." 
                               class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400 focus:border-transparent transition" required>
                    </div>
                    <button type="button" onclick="eliminarFila(this)" 
                            class="px-2.5 py-2 text-sm font-bold bg-white text-rose-600 border border-slate-200 hover:bg-rose-50 hover:border-rose-200 rounded-lg shadow-xs transition duration-150 cursor-pointer">
                        🗑️
                    </button>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200">
                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm uppercase tracking-wider rounded-xl shadow-md hover:shadow-lg transform active:scale-[0.99] transition duration-150 cursor-pointer">
                    🚀 Enviar Requerimiento a Sede Central
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function agregarFila() {
    const contenedor = document.getElementById('contenedor-insumos');
    const nuevaFila = document.createElement('div');
    nuevaFila.className = 'fila-insumo flex items-center gap-2 bg-slate-50/50 p-2 rounded-xl border border-slate-200/40 dynamic-row';
    
    // Inyectamos la estructura limpia mapeando exactamente las clases de Tailwind de la fila base
    nuevaFila.innerHTML = `
        <div class="flex-2">
            <input type="text" name="nombre_insumo[]" placeholder="Ej: Insumo..." 
                   class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400 focus:border-transparent transition" required>
        </div>
        <div class="flex-1">
            <input type="text" name="cantidad_insumo[]" placeholder="Ej: Cantidad..." 
                   class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-slate-400 focus:border-transparent transition" required>
        </div>
        <button type="button" onclick="eliminarFila(this)" 
                class="px-2.5 py-2 text-sm font-bold bg-white text-rose-600 border border-slate-200 hover:bg-rose-50 hover:border-rose-200 rounded-lg shadow-xs transition duration-150 cursor-pointer">
            🗑️
        </button>
    `;
    contenedor.appendChild(nuevaFila);
}

function eliminarFila(btn) {
    const filas = document.querySelectorAll('.fila-insumo');
    // Evitar desmantelar la única fila obligatoria básica
    if(filas.length > 1) {
        btn.parentElement.remove();
    } else {
        alert("⚠️ Operación inválida: Debes solicitar al menos un insumo en tu reporte.");
    }
}
</script>

</body>
</html>