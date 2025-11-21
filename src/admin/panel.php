<?php
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../bootstrap.php';

$rolRaw = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
$rol = is_string($rolRaw) ? strtolower(trim($rolRaw)) : '';
$allowed = ['administrativo','master','dev'];
if (!in_array($rol, $allowed, true)) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

$pageTitle = 'Panel Administrativo';
include __DIR__ . '/../header.php';

$rolLabel = $rol === 'master' ? 'Master' : ($rol === 'administrativo' ? 'Administrativo' : strtoupper($rol));
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <div>
      <h2 class="mb-1">Panel Administrativo</h2>
      <p class="text-muted mb-0">Accesos rápidos a las funciones de configuración y reportes.</p>
    </div>
    <span class="badge bg-secondary">Rol actual: <?= htmlspecialchars($rolLabel) ?></span>
  </div>

  <div class="row g-3">
    <div class="col-sm-6 col-lg-4">
      <a href="<?= BASE_URL ?>admin/contrato.php" class="text-decoration-none">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 text-primary fs-2"><i class="bi bi-building-gear"></i></div>
            <div>
              <h5 class="card-title mb-1">Contrato y Empresa</h5>
              <p class="card-text small text-muted mb-0">Datos de la empresa, ambulancias y configuración general.</p>
            </div>
          </div>
        </div>
      </a>
    </div>

    <div class="col-sm-6 col-lg-4">
      <a href="<?= BASE_URL ?>admin/gestion_usuarios.php" class="text-decoration-none">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 text-primary fs-2"><i class="bi bi-people-fill"></i></div>
            <div>
              <h5 class="card-title mb-1">Gestión de Usuarios</h5>
              <p class="card-text small text-muted mb-0">Crear, editar y administrar usuarios del sistema.</p>
            </div>
          </div>
        </div>
      </a>
    </div>

    <div class="col-sm-6 col-lg-4">
      <a href="<?= BASE_URL ?>obtener_detalle_furtran.php" class="text-decoration-none">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 text-primary fs-2"><i class="bi bi-truck"></i></div>
            <div>
              <h5 class="card-title mb-1">FURTRAN</h5>
              <p class="card-text small text-muted mb-0">Consultar atenciones y generar formato FURTRAN.</p>
            </div>
          </div>
        </div>
      </a>
    </div>

    <div class="col-sm-6 col-lg-4">
      <a href="<?= BASE_URL ?>admin/generar_siras.php" class="text-decoration-none">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 text-primary fs-2"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div>
            <div>
              <h5 class="card-title mb-1">Reporte SIRAS</h5>
              <p class="card-text small text-muted mb-0">Exportar registros para reporte SIRAS por rangos de fecha.</p>
            </div>
          </div>
        </div>
      </a>
    </div>

    <div class="col-sm-6 col-lg-4">
      <a href="<?= BASE_URL ?>admin/panel_furips.php" class="text-decoration-none">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 text-primary fs-2"><i class="bi bi-file-earmark-text"></i></div>
            <div>
              <h5 class="card-title mb-1">Reporte FURIPS</h5>
              <p class="card-text small text-muted mb-0">Generar reportes FURIPS para accidentes de tránsito y eventos catastróficos.</p>
            </div>
          </div>
        </div>
      </a>
    </div>

    <div class="col-sm-6 col-lg-4">
      <a href="<?= BASE_URL ?>admin/gestion_anulaciones.php" class="text-decoration-none">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 text-warning fs-2"><i class="bi bi-x-octagon-fill"></i></div>
            <div>
              <h5 class="card-title mb-1">Gestión de Anulaciones</h5>
              <p class="card-text small text-muted mb-0">Revisar y resolver solicitudes de anulación de registros.</p>
            </div>
          </div>
        </div>
      </a>
    </div>

    <div class="col-sm-6 col-lg-4">
      <a href="<?= BASE_URL ?>admin/backup_manager.php" class="text-decoration-none">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 text-success fs-2"><i class="bi bi-database-fill-down"></i></div>
            <div>
              <h5 class="card-title mb-1">Backups</h5>
              <p class="card-text small text-muted mb-0">Gestionar respaldos de la base de datos (generar, descargar, eliminar).</p>
            </div>
          </div>
        </div>
      </a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
