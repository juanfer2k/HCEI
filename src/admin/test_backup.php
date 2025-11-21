<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backup_manager.php';

try {
    $archivo = generarBackupBD(); // Llama a la funcion corregida
    echo "Respaldo generado exitosamente: $archivo";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}