<?php
require_once __DIR__ . '/bootstrap.php';

// Destruir todas las variables de sesión.
$_SESSION = array();

// Si se está usando cookies de sesión, borrar la cookie también
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

session_destroy();

// Redirigir a la página de inicio de sesión.
header("Location: " . BASE_URL . "login.php");
exit;
