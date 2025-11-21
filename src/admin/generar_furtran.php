<?php
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../conn.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$rol = strtolower(trim($_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? '')));
$permitidos = ['master','administrativo','secretaria','dev'];
if (!in_array($rol, $permitidos, true)) {
    http_response_code(403);
    echo 'Acceso no autorizado.';
    exit;
}

$pageTitle = 'Generar FURTRAN';
$busqueda = trim($_GET['q'] ?? '');
$resultados = [];
$total = 0;

if ($conn instanceof mysqli) {
    if ($busqueda !== '') {
        $like = '%' . $busqueda . '%';
        // Buscar por ID exacto o radicado
        if (ctype_digit($busqueda)) {
            $stmt = $conn->prepare("SELECT id, fecha, servicio, pagador, nombres_paciente, tipo_identificacion, id_paciente FROM atenciones WHERE id = ? ORDER BY id DESC LIMIT 50");
            if ($stmt) {
                $stmt->bind_param('i', $busqueda);
                $stmt->execute();
                $res = $stmt->get_result();
                $resultados = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                $total = $res ? $res->num_rows : 0;
                $stmt->close();
            }
        }
        // Si no hubo resultados, buscar por documento/nombre
        if ($total === 0) {
            $stmt = $conn->prepare("SELECT id, fecha, servicio, pagador, nombres_paciente, tipo_identificacion, id_paciente FROM atenciones WHERE nombres_paciente LIKE ? OR id_paciente LIKE ? ORDER BY id DESC LIMIT 50");
            if ($stmt) {
                $stmt->bind_param('ss', $like, $like);
                $stmt->execute();
                $res = $stmt->get_result();
                $resultados = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                $total = $res ? $res->num_rows : 0;
                $stmt->close();
            }
        }
    } else {
        $sql = "SELECT id, fecha, servicio, pagador, nombres_paciente, tipo_identificacion, id_paciente FROM atenciones ORDER BY id DESC LIMIT 25";
        if ($res = $conn->query($sql)) {
            $resultados = $res->fetch_all(MYSQLI_ASSOC);
            $total = $res->num_rows;
            $res->close();
        }
    }
}

include __DIR__ . '/../header.php';
?>
<div class="container my-4">
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
      <h4 class="mb-0">Generar FURTRAN</h4>
    </div>
    <div class="card-body">
      <p class="text-muted">Busca la atención que deseas exportar y luego usa el botón <strong>Ver FURTRAN</strong> para descargar el PDF oficial.</p>
      <form class="row g-3" method="get">
        <div class="col-md-8">
          <label for="busqueda" class="form-label">Buscar por ID, Radicado, Documento o Nombre</label>
          <input type="text" name="q" id="busqueda" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ej. 1523 o CC 123456 o Juan Perez">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Buscar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Resultados</span>
      <span class="badge bg-secondary"><?= $total ?> registro(s)</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Paciente</th>
            <th>Documento</th>
            <th>Servicio</th>
            <th>Pagador</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($total === 0): ?>
          <tr>
            <td colspan="7" class="text-center text-muted">No se encontraron registros.</td>
          </tr>
          <?php endif; ?>
          <?php foreach ($resultados as $row): ?>
          <tr>
            <td>#<?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['fecha'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['nombres_paciente'] ?? '-') ?></td>
            <td><?= htmlspecialchars(($row['tipo_identificacion'] ?? '') . ' ' . ($row['id_paciente'] ?? '')) ?></td>
            <td><?= htmlspecialchars($row['servicio'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['pagador'] ?? '-') ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= BASE_URL ?>generar_FURTRAN.php?id=<?= urlencode($row['id']) ?>">
                <i class="bi bi-file-earmark-pdf"></i> Ver FURTRAN
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
