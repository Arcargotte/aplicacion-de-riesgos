<?php
session_start();
// Control de acceso: solo voluntarios (o administradores de centro)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once('conexion.php');
    
    $usuario_id = $_SESSION['usuario_id'];
    $centro_id = $_SESSION['centro_id']; 

    // 1. OBTENER EL INVENTARIO DE LA SEDE PRINCIPAL (Para el Autocompletado JS)
    $sql_inventario_central = "
        SELECT i.insumo_id, ins.nombre, ins.unidad_medida, i.cantidad 
        FROM inventario i
        JOIN insumos ins ON i.insumo_id = ins.id
        JOIN centros_acopio c ON i.centro_id = c.id
        WHERE c.es_sede_principal = 1 
        ORDER BY ins.nombre ASC
    ";
    $res_inventario = $conn->query($sql_inventario_central);
    
    // Armamos un array para convertirlo a JSON
    $inventario_array = [];
    if ($res_inventario) {
        while ($item = $res_inventario->fetch_assoc()) {
            $inventario_array[] = [
                'id' => $item['insumo_id'],
                // Ej: "Harina de maíz (Disp: 50.5 kg)"
                'texto' => $item['nombre'] . ' (Disp: ' . floatval($item['cantidad']) . ' ' . $item['unidad_medida'] . ')'
            ];
        }
    }
    // Pasamos los datos a formato JSON seguro para Javascript
    $json_insumos = json_encode($inventario_array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    // 2. PROCESAR EL ENVÍO DEL FORMULARIO (POST)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $conn->begin_transaction();

        $conn->query("INSERT INTO solicitudes (usuario_id, centro_id, estado) VALUES ($usuario_id, $centro_id, 'pendiente')");
        $solicitud_id = $conn->insert_id;

        // Recuperar los IDs ocultos y las cantidades enviadas
        $insumos_ids = $_POST['insumo_id'];
        $cantidades = $_POST['cantidad_insumo'];

        if (is_array($insumos_ids)) {
            for ($i = 0; $i < count($insumos_ids); $i++) {
                $insumo_id = intval($insumos_ids[$i]);
                $cantidad = floatval($cantidades[$i]);

                if ($insumo_id > 0 && $cantidad > 0) {
                    $conn->query("INSERT INTO detalle_solicitudes (solicitud_id, insumo_id, cantidad) VALUES ($solicitud_id, $insumo_id, $cantidad)");
                }
            }
        }
        
        $conn->commit();
        echo "<script>alert('¡Solicitud enviada a Sede Central correctamente!'); window.location.href='formulario_solicitud.php';</script>";
        exit;
    }

    // 3. OBTENER EL HISTORIAL DE SOLICITUDES DE ESTE CENTRO
    $historial = $conn->query("SELECT * FROM solicitudes WHERE centro_id = $centro_id ORDER BY id DESC");

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) { $conn->rollback(); }
    echo "<script>alert('Error en el sistema: " . addslashes($e->getMessage()) . "'); window.location.href='formulario_solicitud.php';</script>";
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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Estilos personalizados para el scroll del autocompletado */
        .autocomplete-results::-webkit-scrollbar { width: 6px; }
        .autocomplete-results::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
        .autocomplete-results::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-3xl mx-auto px-4 py-6 sm:px-6 lg:px-8 space-y-6">
    
    <div class="mb-4 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Nueva Solicitud de Insumos</h2>
        <p class="text-sm text-slate-500 font-medium">Requerimiento de carga dirigido a la Sede Principal</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8">
        
        <div class="bg-slate-50 border border-slate-200/60 rounded-xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs sm:text-sm text-slate-600">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Responsable emisor:</span>
                <span class="font-bold text-slate-800">👤 <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span> 
            </div>
            <div class="sm:text-right">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Fecha del registro:</span>
                <span class="font-mono font-semibold text-slate-700"><?php echo date("d/m/Y g:i A"); ?></span>
            </div>
        </div>

        <form action="" method="POST" class="space-y-6" id="form-solicitud">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <label class="text-xs sm:text-sm font-black text-slate-800 uppercase tracking-wider">
                    Insumos Solicitados:
                </label>
                <button type="button" onclick="agregarFila()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-slate-900 text-white hover:bg-slate-800 rounded-lg shadow-xs transition duration-150 cursor-pointer">
                    ➕ Añadir Ítem
                </button>
            </div>
            
            <div id="contenedor-insumos" class="space-y-3">
                </div>

            <div class="pt-4 border-t border-slate-200">
                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm uppercase tracking-wider rounded-xl shadow-md transition duration-150 cursor-pointer">
                    Enviar Requerimiento
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8 space-y-4">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-sm sm:text-base font-black text-slate-900 uppercase tracking-wider">Historial de Requerimientos</h3>
        </div>

        <?php if ($historial->num_rows === 0): ?>
            <p class="text-center text-slate-400 text-xs py-6 font-medium">Aún no has registrado ninguna solicitud.</p>
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
                                <td class="p-3 text-slate-500"><?= isset($sol['fecha_hora']) ? date('d/m/Y h:i A', strtotime($sol['fecha_hora'])) : 'Reciente' ?></td>
                                <td class="p-3">
                                    <ul class="list-disc list-inside space-y-0.5 text-slate-600">
                                        <?php 
                                        $sid = $sol['id'];
                                        $detalles = $conn->query("SELECT d.cantidad, i.nombre, i.unidad_medida FROM detalle_solicitudes d JOIN insumos i ON d.insumo_id = i.id WHERE d.solicitud_id = $sid");
                                        while ($d = $detalles->fetch_assoc()):
                                        ?>
                                            <li><strong><?= htmlspecialchars($d['nombre']) ?></strong> (<?= floatval($d['cantidad']) ?> <?= htmlspecialchars($d['unidad_medida']) ?>)</li>
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
                                        <?php if (!empty($sol['motivo_rechazo'])): ?>
                                        <span class="text-[10px] text-rose-600 italic block max-w-xs overflow-hidden text-ellipsis" title="<?= htmlspecialchars($sol['motivo_rechazo']) ?>">"<?= htmlspecialchars($sol['motivo_rechazo']) ?>"</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 gap-3 md:hidden">
                <?php 
                $historial->data_seek(0); 
                while ($sol = $historial->fetch_assoc()): 
                ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3">
                        <div class="flex justify-between items-center border-b border-slate-200/60 pb-1.5">
                            <span class="font-mono text-xs text-slate-400">Sol #<?= $sol['id'] ?></span>
                            <span class="text-[11px] font-mono text-slate-500"><?= isset($sol['fecha_hora']) ? date('d/m/Y', strtotime($sol['fecha_hora'])) : '' ?></span>
                        </div>
                        <div class="bg-white p-3 border border-slate-100 rounded-lg text-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wide block">Requerimientos:</span>
                            <ul class="list-disc list-inside text-slate-700 space-y-0.5">
                                <?php 
                                $sid = $sol['id'];
                                $detalles = $conn->query("SELECT d.cantidad, i.nombre, i.unidad_medida FROM detalle_solicitudes d JOIN insumos i ON d.insumo_id = i.id WHERE d.solicitud_id = $sid");
                                while ($d = $detalles->fetch_assoc()):
                                ?>
                                    <li><strong><?= htmlspecialchars($d['nombre']) ?></strong> (<?= floatval($d['cantidad']) ?> <?= htmlspecialchars($d['unidad_medida']) ?>)</li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                        <div class="pt-1 flex flex-col gap-1">
                            <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wide">Estado:</span>
                            <div>
                                <?php if ($sol['estado'] === 'pendiente'): ?>
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded">Pendiente</span>
                                <?php elseif ($sol['estado'] === 'aprobado'): ?>
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 rounded">✅ Aprobado</span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-rose-100 text-rose-800 rounded mb-1">❌ Rechazado</span>
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
// JSON importado desde PHP
const inventarioCentral = <?php echo $json_insumos; ?>;

// Iniciar con una fila vacía
document.addEventListener('DOMContentLoaded', () => {
    agregarFila();
});

function agregarFila() {
    const contenedor = document.getElementById('contenedor-insumos');
    const rowDiv = document.createElement('div');
    rowDiv.className = 'fila-insumo flex flex-col sm:flex-row items-start sm:items-center gap-2 bg-slate-50/50 p-3 rounded-xl border border-slate-200/40';
    
    rowDiv.innerHTML = `
        <div class="w-full sm:flex-2 relative autocomplete-container">
            <input type="text" placeholder="Escribe para buscar insumo..." 
                   class="search-input w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition" required autocomplete="off">
            <input type="hidden" name="insumo_id[]" class="hidden-id" required>
            <div class="autocomplete-results hidden absolute z-50 left-0 right-0 top-full mt-1 bg-white border border-slate-200 shadow-xl rounded-lg max-h-48 overflow-y-auto"></div>
        </div>
        <div class="w-full sm:flex-1 flex gap-2">
            <input type="number" step="0.01" min="0.01" name="cantidad_insumo[]" placeholder="Cantidad..." 
                   class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition" required>
            <button type="button" onclick="eliminarFila(this)" 
                    class="px-3 py-2 text-sm font-bold bg-white text-rose-600 border border-slate-200 hover:bg-rose-50 hover:border-rose-200 rounded-lg shadow-sm transition duration-150 cursor-pointer">
                🗑️
            </button>
        </div>
    `;
    
    contenedor.appendChild(rowDiv);
    configurarAutocompletado(rowDiv.querySelector('.autocomplete-container'));
}

function configurarAutocompletado(container) {
    const input = container.querySelector('.search-input');
    const hidden = container.querySelector('.hidden-id');
    const resultsDiv = container.querySelector('.autocomplete-results');

    // Evento al escribir
    input.addEventListener('input', function() {
        const val = this.value.toLowerCase().trim();
        resultsDiv.innerHTML = '';
        
        if (!val) {
            resultsDiv.classList.add('hidden');
            hidden.value = '';
            return;
        }

        // Filtrar insumos buscando coincidencias
        const coincidencias = inventarioCentral.filter(item => item.texto.toLowerCase().includes(val));
        
        if (coincidencias.length > 0) {
            coincidencias.forEach(match => {
                const div = document.createElement('div');
                div.className = 'px-4 py-2 text-sm text-slate-700 cursor-pointer hover:bg-sky-50 hover:text-sky-700 border-b border-slate-100 last:border-0 transition-colors';
                div.textContent = match.texto;
                
                // Evento al seleccionar una opción
                div.addEventListener('mousedown', function(e) {
                    e.preventDefault(); // Evita que se dispare el blur antes del click
                    input.value = match.texto;
                    hidden.value = match.id;
                    resultsDiv.classList.add('hidden');
                });
                
                resultsDiv.appendChild(div);
            });
            resultsDiv.classList.remove('hidden');
        } else {
            resultsDiv.innerHTML = '<div class="px-4 py-2 text-sm text-slate-400 italic">No se encontró en Sede Central</div>';
            resultsDiv.classList.remove('hidden');
            hidden.value = ''; // Vaciar ID oculto porque no hay coincidencia
        }
    });

    // Validar al salir del campo (Si no escogió nada válido de la lista, borrar)
    input.addEventListener('blur', function() {
        resultsDiv.classList.add('hidden');
        if (!hidden.value) {
            input.value = ''; // Borra el texto si no tiene ID asignado
        }
    });
}

function eliminarFila(btn) {
    const filas = document.querySelectorAll('.fila-insumo');
    if(filas.length > 1) {
        btn.closest('.fila-insumo').remove();
    } else {
        alert("Operación inválida: Debes solicitar al menos un insumo.");
    }
}
</script>

</body>
</html>
<?php $conn->close(); ?>