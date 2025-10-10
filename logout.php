<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Destruir todas las variables de sesión.
$_SESSION = array();
session_destroy();

// Redirigir a la página de inicio de sesión.
header("location: login.php");
exit;