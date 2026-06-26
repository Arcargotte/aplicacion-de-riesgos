<?php
session_start();
// Control estricto: Solo administradores de la sede central entran aquí
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') {
    header("Location: login.php");
    exit;
}

// Incluimos el navbar
include('header.php');

$conn = new mysqli("localhost", "root", "", "ong_inventario");
if ($conn->connect_error) { die("Conexión fallida: " . $conn->connect_error); }

// Consulta requerida: Solo insumos disponibles
$inventario_actual = $conn->query("SELECT * FROM insumos WHERE estado = 'disponible' ORDER BY fecha_ingreso DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sede Central - Inventario</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    
    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Sede Central (Montalbán)
        </h2>
        <p class="text-sm text-slate-500 font-medium">Panel General de Control de Inventario</p>
    </div>

    <div class="space-y-6">
        
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
                <h3 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2">
                    <span>📦 Insumos Disponibles Actualmente</span>
                </h3>
                <span class="inline-flex items-center w-fit px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-xs">
                    <?php echo $inventario_actual->num_rows; ?> ítems en stock
                </span>
            </div>

            <?php if ($inventario_actual->num_rows === 0): ?>
                <div class="text-center py-12 text-slate-400 text-sm font-medium">
                    No hay insumos disponibles en almacén.
                </div>
            <?php else: ?>
                
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="p-3">ID</th>
                                <th class="p-3">Nombre Insumo</th>
                                <th class="p-3">Cantidad Disponible</th>
                                <th class="p-3">Unidad</th>
                                <th class="p-3">Estado</th>
                                <th class="p-3">Fecha Ingreso</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php while($row = $inventario_actual->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/80 transition duration-100">
                                    <td class="p-3 font-mono text-xs text-slate-400">#<?php echo $row['id']; ?></td>
                                    <td class="p-3 font-semibold text-slate-900"><?php echo htmlspecialchars($row['nombre']); ?></td>
                                    <td class="p-3 font-bold"><?php echo number_format($row['cantidad'], 2); ?></td>
                                    <td class="p-3 text-slate-500"><?php echo htmlspecialchars($row['unidad_medida']); ?></td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Disponible</span>
                                    </td>
                                    <td class="p-3 text-xs text-slate-500"><?php echo date('d/m/Y g:i A', strtotime($row['fecha_ingreso'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 gap-3 md:hidden">
                    <?php 
                    // Reseteamos el puntero para volver a iterar sobre los registros en la vista móvil
                    $inventario_actual->data_seek(0); 
                    while($row = $inventario_actual->fetch_assoc()): 
                    ?>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col gap-2">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                                <span class="font-mono text-xs text-slate-400">ID #<?php echo $row['id']; ?></span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-green-100 text-green-800 tracking-wide">Disponible</span>
                            </div>
                            
                            <div>
                                <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wide">Artículo</div>
                                <div class="text-base font-black text-slate-900 leading-tight"><?php echo htmlspecialchars($row['nombre']); ?></div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2 bg-white p-2 rounded-lg border border-slate-100 mt-1">
                                <div>
                                    <div class="text-[10px] text-slate-400 uppercase font-bold">Cantidad</div>
                                    <div class="text-sm font-black text-slate-800"><?php echo number_format($row['cantidad'], 2); ?></div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-slate-400 uppercase font-bold">Medida</div>
                                    <div class="text-sm font-medium text-slate-600"><?php echo htmlspecialchars($row['unidad_medida']); ?></div>
                                </div>
                            </div>

                            <div class="text-[11px] text-slate-400 mt-1.5 flex justify-between border-t border-slate-200/40 pt-1.5">
                                <span>Ingreso:</span>
                                <span class="font-medium text-slate-600"><?php echo date('d/m/Y h:i A', strtotime($row['fecha_ingreso'])); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>