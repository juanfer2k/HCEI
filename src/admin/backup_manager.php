<?php
// Página de gestión de backups (solo Administrativo / Master)
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../bootstrap.php';

// Normalizar rol
$rolRaw = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
$rol = is_string($rolRaw) ? strtolower(trim($rolRaw)) : '';
$allowed = ['administrativo','master','dev'];
if (!in_array($rol, $allowed, true)) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

function listarBackups() {
    $directorio = dirname(__DIR__) . '/backups/';
    if (!is_dir($directorio)) {
        return [];
    }
    $files = array_diff(scandir($directorio), ['.', '..']);
    // Ordenar descendente por fecha (nombre suele incluir timestamp)
    rsort($files);
    return $files;
}

function eliminarBackup($archivo) {
    $ruta = dirname(__DIR__) . '/backups/' . $archivo;
    if (file_exists($ruta) && is_file($ruta)) {
        return unlink($ruta);
    }
    return false;
}

function generarBackupBD() {
    require_once __DIR__ . '/../conn.php';

    $fecha = date('Y-m-d_H-i-s');
    $archivoBackup = dirname(__DIR__) . "/backups/backup_{$dbname}_{$fecha}.sql";

    // Permitir configurar una ruta específica para mysqldump vía constante
    // (por ejemplo, en bootstrap.php) y usar 'mysqldump' desde el PATH por defecto.
    if (defined('MYSQLDUMP_PATH') && MYSQLDUMP_PATH) {
        $mysqldump = '"' . MYSQLDUMP_PATH . '"';
    } else {
        $mysqldump = 'mysqldump';
    }

    $comando = $mysqldump
        . " --user=\"{$username}\""
        . " --password=\"{$password}\""
        . " --host=\"{$servername}\""
        . " {$dbname}"
        . " --result-file=\"{$archivoBackup}\"";

    system($comando, $resultado);

    $size = (file_exists($archivoBackup) && is_file($archivoBackup)) ? filesize($archivoBackup) : 0;
    if ($resultado === 0 && $size > 0) {
        return $archivoBackup;
    }

    if ($size === 0 && file_exists($archivoBackup)) {
        @unlink($archivoBackup);
    }

    throw new Exception("Error al generar el respaldo de la base de datos. Verifique que 'mysqldump' esté instalado y accesible.");
}

// Controlador simple de acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'generar') {
            $ruta = generarBackupBD();
            $_SESSION['message'] = '<div class="alert alert-success">Backup generado correctamente en: ' . htmlspecialchars(basename($ruta)) . '</div>';
        } elseif ($accion === 'eliminar') {
            $archivo = basename($_POST['archivo'] ?? '');
            if ($archivo && eliminarBackup($archivo)) {
                $_SESSION['message'] = '<div class="alert alert-success">Backup eliminado correctamente.</div>';
            } else {
                $_SESSION['message'] = '<div class="alert alert-warning">No se pudo eliminar el backup seleccionado.</div>';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['message'] = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    header('Location: ' . BASE_URL . 'admin/backup_manager.php');
    exit;
}

$pageTitle = 'Gestión de Backups';
include __DIR__ . '/../header.php';

$backups = listarBackups();
?>

<div class="container mt-4">
  <h2 class="mb-3">Gestión de Backups</h2>

  <?php if (!empty($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } ?>

  <div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <p class="mb-0 small text-muted">
          Desde esta pantalla puedes generar un respaldo de la base de datos y descargar o eliminar respaldos anteriores.
        </p>
      </div>
      <form method="post" class="mb-0">
        <input type="hidden" name="accion" value="generar">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-hdd-network"></i> Generar backup ahora
        </button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Backups disponibles</span>
      <span class="badge bg-secondary"><?= count($backups) ?> archivo(s)</span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($backups)): ?>
        <div class="p-4 text-muted text-center">Aún no hay backups generados.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Archivo</th>
                <th class="text-end">Tamaño</th>
                <th>Fecha</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($backups as $file): 
                  $ruta = dirname(__DIR__) . '/backups/' . $file;
                  $size = is_file($ruta) ? filesize($ruta) : 0;
                  $fecha = is_file($ruta) ? date('Y-m-d H:i:s', filemtime($ruta)) : '-';
              ?>
              <tr>
                <td><?= htmlspecialchars($file) ?></td>
                <td class="text-end small"><?= $size ? number_format($size / 1024, 2, ',', '.') . ' KB' : '-' ?></td>
                <td class="small"><?= htmlspecialchars($fecha) ?></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group">
                    <a class="btn btn-outline-secondary" href="<?= BASE_URL . 'backups/' . rawurlencode($file) ?>" download>
                      <i class="bi bi-download"></i> Descargar
                    </a>
                    <form method="post" onsubmit="return confirm('¿Seguro que deseas eliminar este backup?');">
                      <input type="hidden" name="accion" value="eliminar">
                      <input type="hidden" name="archivo" value="<?= htmlspecialchars($file) ?>">
                      <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i> Eliminar
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>