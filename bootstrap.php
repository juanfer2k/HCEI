<?php
// Centraliza el calculo de BASE_URL apuntando a la raiz de la app,
// independientemente del script que se ejecute o la subcarpeta (/admin, etc.).
if (!defined('BASE_URL')) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Normaliza rutas para Windows/Linux y calcula la ruta publica desde DOCUMENT_ROOT hasta esta carpeta.
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
    $appDir  = rtrim(str_replace('\\', '/', __DIR__), '/');
    if ($docRoot && strpos($appDir, $docRoot) === 0) {
        $basePath = rtrim(substr($appDir, strlen($docRoot)), '/') . '/';
    } else {
        // Fallback cuando DOCUMENT_ROOT no esta disponible: usa la carpeta del script en ejecucion.
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $basePath = rtrim(str_replace('\\', '/', $scriptDir), '/') . '/';
    }

    define('BASE_URL', $protocol . $host . $basePath);
}
