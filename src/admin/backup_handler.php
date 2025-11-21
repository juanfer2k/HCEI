<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Autenticacion y BASE_URL
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../conn.php';

// --- CONFIGURACIÃ“N ---
$backup_dir = dirname(__DIR__) . '/backups/';

// Asegurate de que el directorio de backups exista y sea escribible
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        die(json_encode(['status' => 'error', 'message' => 'No se pudo crear el directorio de backups.']));
    }
}

if (!is_writable($backup_dir)) {
    die(json_encode(['status' => 'error', 'message' => 'El directorio de backups no tiene permisos de escritura.']));
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'generar':
        if (!in_array($_SESSION['usuario_rol'] ?? '', ['Administrativo','Master'])) { http_response_code(403); exit('No autorizado'); }
        generar_backup($conn, $backup_dir);
        break;
    case 'listar':
        if (!in_array($_SESSION['usuario_rol'] ?? '', ['Administrativo','Master'])) { http_response_code(403); exit('No autorizado'); }
        listar_backups($backup_dir);
        break;
    case 'eliminar':
        if (!in_array($_SESSION['usuario_rol'] ?? '', ['Administrativo','Master'])) { http_response_code(403); exit('No autorizado'); }
        $archivo = $_POST['archivo'] ?? $_GET['archivo'] ?? ''; // Aceptar GET o POST
        eliminar_backup($backup_dir, $archivo);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Accion no valida.']);
        break;
}

function generar_backup($conn, $backup_dir) {
    $fecha = date('Y-m-d_H-i-s');
    $nombre_archivo = "backup_{$fecha}.sql";
    $ruta_completa = $backup_dir . $nombre_archivo;

    $tablas_sql = "SHOW TABLES";
    $resultado_tablas = $conn->query($tablas_sql);

    if (!$resultado_tablas) {
        $_SESSION['message'] = '<div class="alert alert-danger">Error al obtener la lista de tablas.</div>';
        header('Location: ' . BASE_URL . 'consulta_atenciones.php');
        exit;
    }

    $script_sql = "-- Backup de la base de datos HCEI\n";
    $script_sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";

    while ($fila = $resultado_tablas->fetch_row()) {
        $tabla = $fila[0];
        $script_sql .= "--\n-- Estructura de la tabla `{$tabla}`\n--\n";
        
        // Obtener la estructura de la tabla
        $resultado_estructura = $conn->query("SHOW CREATE TABLE `{$tabla}`");
        $fila_estructura = $resultado_estructura->fetch_assoc();
        $script_sql .= $fila_estructura['Create Table'] . ";\n\n";
        $resultado_estructura->free();

        // Obtener los datos de la tabla
        $resultado_datos = $conn->query("SELECT * FROM `{$tabla}`");
        if ($resultado_datos->num_rows > 0) {
            $script_sql .= "--\n-- Volcado de datos para la tabla `{$tabla}`\n--\n";
            while ($fila_datos = $resultado_datos->fetch_assoc()) {
                $script_sql .= "INSERT INTO `{$tabla}` VALUES(";
                $valores = [];
                foreach ($fila_datos as $valor) {
                    if ($valor === null) {
                        $valores[] = "NULL";
                    } else {
                        $valores[] = "'" . $conn->real_escape_string($valor) . "'";
                    }
                }
                $script_sql .= implode(', ', $valores) . ");\n";
            }
            $script_sql .= "\n";
        }
        $resultado_datos->free();
    }

    if (file_put_contents($ruta_completa, $script_sql)) {
        $_SESSION['message'] = '<div class="alert alert-success">Backup generado con exito: ' . htmlspecialchars($nombre_archivo) . '</div>';
    } else {
        $_SESSION['message'] = '<div class="alert alert-danger">Error al generar el backup.</div>';
    }
    
    // Asegurarse de que la sesion se guarde antes de redirigir
    session_write_close();
    header('Location: ' . BASE_URL . 'consulta_atenciones.php');
    exit;
}

function listar_backups($backup_dir) {
    header('Content-Type: application/json');
    $archivos = array_diff(scandir($backup_dir, SCANDIR_SORT_DESCENDING), array('..', '.'));
    echo json_encode(['status' => 'success', 'data' => array_values($archivos)]);
}

function eliminar_backup($backup_dir, $archivo) {
    if (empty($archivo)) {
        $_SESSION['message'] = '<div class="alert alert-danger">Nombre de archivo no proporcionado.</div>';
    } else if (strpos($archivo, '..') !== false || !preg_match('/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.sql$/', $archivo)) {
        $_SESSION['message'] = '<div class="alert alert-danger">Nombre de archivo no valido.</div>';
    } else {
        $ruta_completa = $backup_dir . $archivo;
        if (file_exists($ruta_completa) && unlink($ruta_completa)) {
            $_SESSION['message'] = '<div class="alert alert-success">Backup eliminado con exito.</div>';
        } else {
            $_SESSION['message'] = '<div class="alert alert-danger">No se pudo eliminar el backup.</div>';
        }
    }
    
    // Redirigir de vuelta a la lista de backups en /admin
    session_write_close();
    header('Location: ' . BASE_URL . 'admin/backup_list.php');
    exit;
}
?>
