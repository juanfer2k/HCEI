<?php
// CONFIGURACIÓN DE BASE DE DATOS - PRODUCCIÓN (cPanel)
// Renombrar este archivo a 'conn.php' en el servidor

$DB_HOST = 'localhost';     // Generalmente es localhost en cPanel
$DB_USER = 'juanfer2k_rap'; // Tu usuario de base de datos de cPanel
$DB_PASS = 'TuContraseñaAqui'; // Tu contraseña de base de datos
$DB_NAME = 'juanfer2k_hcei'; // El nombre de tu base de datos en cPanel

$APP_DEBUG = false; // false para producción

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    if ($conn->connect_errno) {
        throw new mysqli_sql_exception('Connect error: ' . $conn->connect_error, $conn->connect_errno);
    }

    $conn->set_charset("utf8mb4");

} catch (Throwable $e) {
    error_log('[DB CONNECT] ' . $e->getMessage());
    if ($APP_DEBUG) {
        die("Error de conexión: " . $e->getMessage());
    } else {
        http_response_code(500);
        die("Error de conexión a la base de datos.");
    }
}
?>
