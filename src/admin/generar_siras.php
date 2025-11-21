<?php
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../conn.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$rol = strtolower(trim($_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? '')));
$permitidos = ['master','administrativo','dev'];
if (!in_array($rol, $permitidos, true)) {
    http_response_code(403);
    echo 'Acceso no autorizado.';
    exit;
}

// Parámetros de filtro
$tipo_formato = $_GET['tipo_formato'] ?? 'csv';
$busqueda     = isset($_GET['q']) ? trim($_GET['q']) : '';

// Rango fijo: siempre últimas 24 horas (ayer-hoy)
$fecha_fin = date('Y-m-d');
$fecha_inicio = date('Y-m-d', strtotime('-1 day'));

// Siempre consideramos el rango válido porque es interno
$esRangoValido = true;

// Exportar CSV si se solicita
if ($esRangoValido && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sqlExport = "SELECT id, fecha, servicio, pagador, ambulancia, tipo_traslado, nombres_paciente, tipo_identificacion, id_paciente, diagnostico_principal
                  FROM atenciones
                  WHERE fecha BETWEEN ? AND ?";
    $typesExp = 'ss';
    $paramsExp = [$fecha_inicio, $fecha_fin];

    if ($busqueda !== '') {
        $sqlExport .= " AND (id = ? OR nombres_paciente LIKE CONCAT('%', ?, '%') OR id_paciente LIKE CONCAT('%', ?, '%'))";
        $typesExp .= 'iss';
        $paramsExp[] = $busqueda;
        $paramsExp[] = $busqueda;
        $paramsExp[] = $busqueda;
    }

    $sqlExport .= " ORDER BY fecha, id";

    $stmt = $conn->prepare($sqlExport);
    if ($stmt) {
        $stmt->bind_param($typesExp, ...$paramsExp);
        $stmt->execute();
        $res = $stmt->get_result();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="siras_' . $fecha_inicio . '_a_' . $fecha_fin . '.csv"');

        $out = fopen('php://output', 'w');
        // Encabezados básicos SIRAS (pueden ajustarse a un layout oficial más adelante)
        fputcsv($out, [
            'ID_ATENCION', 'FECHA', 'SERVICIO', 'PAGADOR', 'AMBULANCIA', 'TIPO_TRASLADO',
            'NOMBRE_PACIENTE', 'TIPO_ID_PACIENTE', 'ID_PACIENTE', 'DIAGNOSTICO_PRINCIPAL'
        ]);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['id'],
                $row['fecha'],
                $row['servicio'],
                $row['pagador'],
                $row['ambulancia'],
                $row['tipo_traslado'],
                $row['nombres_paciente'],
                $row['tipo_identificacion'],
                $row['id_paciente'],
                $row['diagnostico_principal'],
            ]);
        }
        fclose($out);
        $stmt->close();
        $conn->close();
        exit;
    }
}

// Estadísticas rápidas para alertas (últimas 24h y posibles atrasos)
$alertStats = ['ultimas24' => 0, 'atrasadas' => 0];
try {
    $sqlStats = "SELECT
                   SUM(fecha >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)) AS ultimas24,
                   SUM(fecha < DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND fecha >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)) AS atrasadas
                 FROM atenciones";
    if ($resStats = $conn->query($sqlStats)) {
        $rowStats = $resStats->fetch_assoc();
        $alertStats['ultimas24'] = (int)($rowStats['ultimas24'] ?? 0);
        $alertStats['atrasadas'] = (int)($rowStats['atrasadas'] ?? 0);
        $resStats->close();
    }
} catch (Throwable $e) {
    // Silencioso: si falla la estadística, simplemente no mostramos alertas
}

// Cargar datos para vista previa si hay rango válido
$resultados = [];
if ($esRangoValido) {
    $sqlPrev = "SELECT id, fecha, servicio, pagador, ambulancia, tipo_traslado, nombres_paciente, tipo_identificacion, id_paciente, diagnostico_principal
                FROM atenciones
                WHERE fecha BETWEEN ? AND ?";
    $typesPrev = 'ss';
    $paramsPrev = [$fecha_inicio, $fecha_fin];

    if ($busqueda !== '') {
        $sqlPrev .= " AND (id = ? OR nombres_paciente LIKE CONCAT('%', ?, '%') OR id_paciente LIKE CONCAT('%', ?, '%'))";
        $typesPrev .= 'iss';
        $paramsPrev[] = $busqueda;
        $paramsPrev[] = $busqueda;
        $paramsPrev[] = $busqueda;
    }

    $sqlPrev .= " ORDER BY fecha, id LIMIT 200";

    $stmt = $conn->prepare($sqlPrev);
    if ($stmt) {
        $stmt->bind_param($typesPrev, ...$paramsPrev);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $resultados = $res->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }
}

$pageTitle = 'Generar reporte SIRAS';
include __DIR__ . '/../header.php';
?>
<style>
  .siras-flex-card {
    border: 1px solid #e9ecef;
    border-radius: 1rem;
    padding: 1.5rem;
    background: var(--bs-body-bg);
    box-shadow: 0 0.35rem 1rem rgba(0,0,0,0.04);
  }
  .siras-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(240px,1fr));
    gap: 1rem;
  }
  .siras-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .75rem;
    border-radius: 999px;
    font-size: .85rem;
  }
</style>

<div class="container my-4">
  <div class="siras-flex-card mb-4">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
      <div>
        <h4 class="mb-1">Generación de reporte SIRAS</h4>
        <p class="text-muted mb-0">Anexo Técnico 1 – Resolución 0311 de 2020</p>
      </div>
      <span class="siras-pill bg-light text-muted">
        <i class="bi bi-info-circle"></i> Exportación CSV básica (versión inicial)
      </span>
    </div>
    <form class="row g-3" method="get">
      <div class="col-md-6">
        <label for="q" class="form-label">Buscar por ID / Documento / Nombre</label>
        <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ej: 1023 o CC 123456 o Pérez">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label" for="tipo_formato">Formato requerido</label>
        <select id="tipo_formato" name="tipo_formato" class="form-select">
          <option value="csv" <?= $tipo_formato === 'csv' ? 'selected' : '' ?>>CSV SIRAS (básico)</option>
        </select>
      </div>
      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-outline-primary">
          <i class="bi bi-search"></i> Previsualizar registros (últimas 24 horas)
        </button>
        <?php if ($esRangoValido): ?>
        <a href="?tipo_formato=csv&export=csv" class="btn btn-primary">
          <i class="bi bi-cloud-arrow-down"></i> Generar exportación CSV
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="siras-flex-card">
    <div class="d-flex justify-content-between flex-wrap align-items-center mb-2">
      <h5 class="mb-2 mb-sm-0">Vista previa de registros SIRAS</h5>
      <div class="d-flex flex-wrap gap-2 small">
        <?php if ($alertStats['ultimas24'] > 0): ?>
          <span class="badge bg-info text-dark">Últimas 24h: <?= $alertStats['ultimas24'] ?> atenciones</span>
        <?php endif; ?>
        <?php if ($alertStats['atrasadas'] > 0): ?>
          <span class="badge bg-danger">Posibles atrasos (24–72h): <?= $alertStats['atrasadas'] ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($alertStats['atrasadas'] > 0 && in_array($rol, ['administrativo','master'], true)): ?>
      <div class="alert alert-danger py-2 small">
        <strong>Alerta:</strong> Hay atenciones con más de 24 horas y hasta 72 horas de antigüedad que podrían no haber sido reportadas a la plataforma SIRAS.
        Revisa y genera los reportes correspondientes.
      </div>
    <?php elseif ($alertStats['ultimas24'] > 0 && in_array($rol, ['administrativo','master'], true)): ?>
      <div class="alert alert-info py-2 small">
        Hay atenciones registradas en las últimas 24 horas. Recuerda generar el reporte SIRAS dentro del plazo máximo.
      </div>
    <?php endif; ?>
    <?php if ($esRangoValido && !empty($resultados)): ?>
      <p class="small text-muted mb-2">Mostrando hasta 200 registros de las últimas 24 horas.</p>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Fecha</th>
              <th>Servicio</th>
              <th>Pagador</th>
              <th>Ambulancia</th>
              <th>Tipo traslado</th>
              <th>Paciente</th>
              <th>Documento</th>
              <th>Diagnóstico</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($resultados as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['id'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['fecha'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['servicio'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['pagador'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['ambulancia'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['tipo_traslado'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['nombres_paciente'] ?? '') ?></td>
              <td><?= htmlspecialchars(trim(($row['tipo_identificacion'] ?? '') . ' ' . ($row['id_paciente'] ?? ''))) ?></td>
              <td><?= htmlspecialchars($row['diagnostico_principal'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($esRangoValido): ?>
      <p class="text-muted mb-0">No se encontraron atenciones en el rango seleccionado.</p>
    <?php else: ?>
      <p class="text-muted mb-0">Selecciona un rango de fechas y haz clic en "Previsualizar registros" para ver las atenciones candidatas al SIRAS.</p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
