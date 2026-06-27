<?php
session_start();
// Control de acceso: solo voluntarios de centro
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once('conexion.php');
    
    $usuario_id = $_SESSION['usuario_id'];
    $centro_id = $_SESSION['centro_id']; 

    // 1. PROCESAR EL ENVÍO DEL FORMULARIO (POST)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Iniciar transacción explícita
        $conn->begin_transaction();

        // Insertar cabecera de la solicitud con estado 'pendiente'
        $conn->query("INSERT INTO solicitudes (usuario_id, centro_id, estado) VALUES ($usuario_id, $centro_id, 'pendiente')");
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
        
        echo "<script>alert('¡Solicitud enviada a Montalbán correctamente!'); window.location.href='formulario_solicitud.php';</script>";
        exit;
    }

    // 2. OBTENER EL HISTORIAL DE SOLICITUDES DE ESTE CENTRO
    $historial = $conn->query("SELECT * FROM solicitudes 
                               WHERE centro_id = $centro_id 
                               ORDER BY fecha_hora DESC");

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    echo "<script>alert('Error crítico en el sistema: " . addslashes($e->getMessage()) . "'); window.location.href='formulario_solicitud.php';</script>";
    exit;
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

<div class="max-w-3xl mx-auto px-4 py-6 sm:px-6 lg:px-8 space-y-6">
    
    <div class="mb-4 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
        Nueva Solicitud de Insumos
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
                <span class="font-mono font-semibold text-slate-700"><?php echo date("d/m/Y g:i A"); ?></span>
            </div>
        </div>

        <form action="" method="POST" class="space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <label class="text-xs sm:text-sm font-black text-slate-800 uppercase tracking-wider">
                    Insumos Solicitados (Entrada Libre):
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
                    Enviar Requerimiento a Sede Central
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8 space-y-4">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-sm sm:text-base font-black text-slate-900 uppercase tracking-wider">Historial de Requerimientos Enviados</h3>
            <p class="text-xs text-slate-400">Estado de tus solicitudes gestionadas por la Sede Central</p>
        </div>

        <?php if ($historial->num_rows === 0): ?>
            <p class="text-center text-slate-400 text-xs py-6 font-medium">Aún no has registrado ninguna solicitud desde este centro de acopio.</p>
        <?php else: ?>
            
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200">
                            <th class="p-3">ID</th>
                            <th class="p-3">Fecha</th>
                            <th class="p-3">Carga Solicitada</th>
                            <th class="p-3 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                        <?php while ($sol = $historial->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="p-3 font-mono text-slate-400">#<?= $sol['id'] ?></td>
                                <td class="p-3 text-slate-500"><?= date('d/m/Y h:i A', strtotime($sol['fecha_hora'])) ?></td>
                                <td class="p-3">
                                    <ul class="list-disc list-inside space-y-0.5 text-slate-600">
                                        <?php 
                                        $sid = $sol['id'];
                                        $detalles = $conn->query("SELECT * FROM detalle_solicitudes WHERE solicitud_id = $sid");
                                        while ($d = $detalles->fetch_assoc()):
                                        ?>
                                            <li><strong><?= htmlspecialchars($d['nombre_insumo']) ?></strong> (<?= htmlspecialchars($d['cantidad']) ?>)</li>
                                        <?php endwhile; ?>
                                    </ul>
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <?php if ($sol['estado'] === 'pendiente'): ?>
                                        <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 rounded-md">Pendiente</span>
                                    <?php elseif ($sol['estado'] === 'aprobado'): ?>
                                        <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 rounded-md">Aprobado</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider bg-rose-100 text-rose-800 rounded-md block mb-1">Rechazado</span>
                                        <span class="text-[10px] text-rose-600 italic block max-w-xs overflow-hidden text-ellipsis" title="<?= htmlspecialchars($sol['motivo_rechazo']) ?>">
                                            "<?= htmlspecialchars($sol['motivo_rechazo']) ?>"
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 gap-3 md:hidden">
                <?php 
                $historial->data_seek(0); // Reiniciar el puntero de la consulta para el bucle móvil
                while ($sol = $historial->fetch_assoc()): 
                ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3">
                        <div class="flex justify-between items-center border-b border-slate-200/60 pb-1.5">
                            <span class="font-mono text-xs text-slate-400">Solicitud #<?= $sol['id'] ?></span>
                            <span class="text-[11px] font-mono text-slate-500"><?= date('d/m/Y h:i A', strtotime($sol['fecha_hora'])) ?></span>
                        </div>
                        
                        <div class="bg-white p-3 border border-slate-100 rounded-lg text-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wide block">Insumos requeridos:</span>
                            <ul class="list-disc list-inside text-slate-700 space-y-0.5">
                                <?php 
                                $sid = $sol['id'];
                                $detalles = $conn->query("SELECT * FROM detalle_solicitudes WHERE solicitud_id = $sid");
                                while ($d = $detalles->fetch_assoc()):
                                        ?>
                                    <li><strong><?= htmlspecialchars($d['nombre_insumo']) ?></strong> (<?= htmlspecialchars($d['cantidad']) ?>)</li>
                                <?php endwhile; ?>
                            </ul>
                        </div>

                        <div class="pt-1 flex flex-col gap-1">
                            <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wide">Estado de la evaluación:</span>
                            <div>
                                <?php if ($sol['estado'] === 'pendiente'): ?>
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded">Pendiente de Revisión</span>
                                <?php elseif ($sol['estado'] === 'aprobado'): ?>
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 rounded">✅ Aprobado / Despachado</span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-rose-100 text-rose-800 rounded mb-1">❌ Rechazado</span>
                                    <p class="text-xs text-rose-600 italic bg-white p-2 rounded border border-rose-100">
                                        <strong>Motivo:</strong> "<?= htmlspecialchars($sol['motivo_rechazo']) ?>"
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
function agregarFila() {
    const contenedor = document.getElementById('contenedor-insumos');
    const nuevaFila = document.createElement('div');
    nuevaFila.className = 'fila-insumo flex items-center gap-2 bg-slate-50/50 p-2 rounded-xl border border-slate-200/40 dynamic-row';
    
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
    if(filas.length > 1) {
        btn.parentElement.remove();
    } else {
        alert("Operación inválida: Debes solicitar al menos un insumo en tu reporte.");
    }
}
</script>

</body>
</html>
<?php $conn->close(); ?>