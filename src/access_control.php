<?php
require_once __DIR__ . '/bootstrap.php';

// En modo desarrollo (DEV_MODE true), se permite el acceso sin forzar login
if (!defined('DEV_MODE') || !DEV_MODE) {
    // Si no existe la variable de sesión del usuario, redirigir al login
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}
?>