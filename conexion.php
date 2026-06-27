<?php
// Configuración de la base de datos
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "ong_bd";

// Habilitar reporte de errores estricto para Mysqli (Previene fallos silenciosos)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Inicializar la conexión global
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Forzar el set de caracteres a UTF-8 para evitar problemas con eñes o acentos
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    // Si la conexión falla, detiene la ejecución de la página de forma limpia
    die("<div style='font-family:sans-serif; padding:20px; text-align:center; color:#e11d48;'>
            <strong>Error Crítico:</strong> No se pudo conectar al servidor de inventario.
         </div>");
}
// Nota: Dejamos la conexión abierta ($conn), no cerramos el tag de PHP si es un archivo puro de lógica