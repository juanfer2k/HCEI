<?php
require_once __DIR__ . '/../access_control.php';
// Solo perfiles administrativos
if (!in_array($_SESSION['usuario_rol'] ?? '', ['Administrativo','Master'])) {
    http_response_code(403);
    exit('No autorizado');
}

if (isset($_GET['file'])) {
    $archivo_solicitado = basename($_GET['file']); // Limpia la entrada para evitar ataques de path traversal

    // Define la ruta al directorio de backups
    $backup_dir = dirname(__DIR__) . '/backups/';
    $ruta_completa = $backup_dir . $archivo_solicitado;

    // Validar que el nombre del archivo tenga el formato esperado
    if (!preg_match('/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.sql$/', $archivo_solicitado)) {
        http_response_code(400); // Bad Request
        die("Nombre de archivo no valido.");
    }

    // Verificar que el archivo exista en la ubicacion correcta
    if (file_exists($ruta_completa)) {
        // Establecer cabeceras para forzar la descarga
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $archivo_solicitado . '"');
        header('Content-Length: ' . filesize($ruta_completa));
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Leer el archivo y enviarlo al navegador
        readfile($ruta_completa);
        exit;
    } else {
        http_response_code(404); // Not Found
        die("Archivo no encontrado.");
    }
}
