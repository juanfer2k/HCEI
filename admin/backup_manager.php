<?php
function listarBackups() {
    $directorio = __DIR__ . '/backups/';
    return array_diff(scandir($directorio), ['.', '..']);
}

function eliminarBackup($archivo) {
    $ruta = __DIR__ . '/backups/' . $archivo;
    if (file_exists($ruta)) {
        unlink($ruta);
        return true;
    }
    return false;
}

function generarBackupBD() {
    // Usa las credenciales de conn.php
    require_once '../conn.php';

    $fecha = date('Y-m-d_H-i-s');
    $archivoBackup = __DIR__ . "/backups/backup_{$dbname}_{$fecha}.sql";

    // Comando mysqldump
    $comando = "mysqldump --user={$username} --password={$password} --host={$servername} {$dbname} > {$archivoBackup}";
    system($comando, $resultado);

    if ($resultado === 0) {
        return $archivoBackup;
    } else {
        throw new Exception("Error al generar el respaldo de la base de datos.");
    }
}