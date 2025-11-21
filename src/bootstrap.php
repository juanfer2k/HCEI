<?php
ob_start();
// --- 1. Calculate Base Path ---
// This logic needs to run first to determine the correct cookie path.
$basePath = '/'; // Default to root
// Normaliza rutas para Windows/Linux y calcula la ruta publica desde DOCUMENT_ROOT hasta esta carpeta.
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
$appDir  = rtrim(str_replace('\\', '/', __DIR__), '/');
if ($docRoot && strpos($appDir, $docRoot) === 0) {
    $basePath = rtrim(substr($appDir, strlen($docRoot)), '/') . '/';
} else {
    // Fallback cuando DOCUMENT_ROOT no esta disponible: usa la carpeta del script en ejecucion.
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    // Ensure scriptDir is not just '\' or '/' on its own
    if ($scriptDir !== '/' && $scriptDir !== '\\') {
        $basePath = rtrim(str_replace('\\', '/', $scriptDir), '/') . '/';
    }
}


// --- 2. Session Handling ---
if (session_status() === PHP_SESSION_NONE) {
    // Set session save path to a directory that exists and is writable
    $sessionPath = __DIR__ . '/tmp/sessions';
    if (!is_dir($sessionPath)) {
        // Attempt to create the directory. Suppress errors in case of race conditions.
        @mkdir($sessionPath, 0777, true);
    }

    // Only set save path if the directory exists and is writable.
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    // Hacer la cookie de sesión válida para todo el host.
    // Esto asegura que la misma sesión aplique tanto en /HCEI como en /HCEI/admin.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/', // cookie accesible en todo el dominio
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// --- 3. Debugging ---
define('DEBUG', true);
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// --- 3b. Define Base URL ---
// Centraliza el calculo de BASE_URL apuntando a la raiz de la app.
if (!defined('BASE_URL')) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    define('BASE_URL', $protocol . $host . $basePath);
}