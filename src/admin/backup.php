<?php
function generarBackupBD($host, $usuario, $password, $nombreBD) {
    $fecha = date('Y-m-d_H-i-s');
    $archivoBackup = dirname(__DIR__) . "/backups/backup_{$nombreBD}_{$fecha}.sql";

    $comando = "mysqldump --user={$usuario} --password={$password} --host={$host} {$nombreBD} > {$archivoBackup}";
    system($comando, $resultado);

    if ($resultado === 0) {
        return $archivoBackup;
    } else {
        throw new Exception("Error al generar el respaldo de la base de datos.");
    }
}

try {
    $archivo = generarBackupBD('localhost', 'root', '', 'hcei');
    echo json_encode(['status' => 'success', 'message' => 'Respaldo generado exitosamente.', 'file' => $archivo]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}