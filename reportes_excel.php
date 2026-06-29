<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador_central') { 
    header("Location: login"); 
    exit; 
}
include('header.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportación de Datos - ONG Montalbán</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans">

<div class="max-w-4xl mx-auto px-4 py-8 space-y-8">
    
    <div class="border-b border-slate-200 pb-4">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Centro de Descargas y Reportes Globales</h2>
        <p class="text-sm text-slate-500 font-medium">Exportación segura de la base de datos a formato Microsoft Excel (.xlsx).</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Inventario Global de Centros</h3>
                <p class="text-xs text-slate-500 mt-1">Consolidado general de capacidades, estados e insumos totales distribuidos por todo el ecosistema de la organización.</p>
            </div>
            <a href="procesar_excel?reporte=inventario_global" class="mt-5 block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition">
                Generar Inventario Global
            </a>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Insumos Disponibles por Centro</h3>
                <p class="text-xs text-slate-500 mt-1">Desglose pormenorizado de stock actual, nombres de insumos, categorías y cantidades exactas albergadas en cada sede.</p>
            </div>
            <a href="procesar_excel?reporte=insumos_centro" class="mt-5 block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition">
                Generar Desglose por Sede
            </a>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Histórico de Movimientos</h3>
                <p class="text-xs text-slate-500 mt-1">Auditoría completa cronológica de flujos operativos (entradas, donaciones externas, despachos y traslados internos).</p>
            </div>
            <a href="procesar_excel?reporte=historico_movimientos" class="mt-5 block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition">
                Generar Libro de Auditoría
            </a>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Lista Global de Víctimas</h3>
                <p class="text-xs text-slate-500 mt-1">Registro centralizado de personas damnificadas ingresadas al sistema, prioridades de atención médica y estatus de triaje.</p>
            </div>
            <a href="procesar_excel?reporte=lista_victimas" class="mt-5 block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition">
                Generar Listado de Víctimas
            </a>
        </div>

    </div>
</div>

</body>
</html>