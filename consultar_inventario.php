<?php
$pageTitle = 'Consulta de Inventarios';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/inventario_config.php';
require_once __DIR__ . '/inventario_detalle_utils.php';

$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$form_uuid = $_GET['form_uuid'] ?? '';

$registros = [];
$detalle_header = null;
$detalle_items = [];

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    // Lógica de búsqueda por fecha
    $stmt_tam = $conn->prepare("SELECT DISTINCT form_uuid, ambulancia, placa, fecha, responsable_general, created_at FROM inventario_tam WHERE fecha BETWEEN ? AND ? ORDER BY fecha DESC, created_at DESC");
    $stmt_tam->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt_tam->execute();
    $result_tam = $stmt_tam->get_result();
    while ($row = $result_tam->fetch_assoc()) {
        $row['tipo'] = 'TAM';
        $registros[] = $row;
    }
    $stmt_tam->close();

    $stmt_tab = $conn->prepare("SELECT DISTINCT form_uuid, ambulancia, placa, fecha, responsable_general, created_at FROM inventario_tab WHERE fecha BETWEEN ? AND ? ORDER BY fecha DESC, created_at DESC");
    $stmt_tab->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt_tab->execute();
    $result_tab = $stmt_tab->get_result();
    while ($row = $result_tab->fetch_assoc()) {
        $row['tipo'] = 'TAB';
        $registros[] = $row;
    }
    $stmt_tab->close();

    // Ordenar resultados combinados
    usort($registros, function($a, $b) {
        return strtotime($b['fecha'] . ' ' . $b['created_at']) - strtotime($a['fecha'] . ' ' . $a['created_at']);
    });

} elseif (!empty($form_uuid)) {
    // Lógica para mostrar un detalle específico
    $stmt_tam_check = $conn->prepare("SELECT COUNT(*) FROM inventario_tam WHERE form_uuid = ?");
    $stmt_tam_check->bind_param('s', $form_uuid);
    $stmt_tam_check->execute();
    $stmt_tam_check->bind_result($count_tam);
    $stmt_tam_check->fetch();
    $stmt_tam_check->close();

    $tabla = ($count_tam > 0) ? 'inventario_tam' : 'inventario_tab';
    $tipo_inventario = ($count_tam > 0) ? 'TAM' : 'TAB';
    list($detalle_header, $detalle_items) = obtenerInventario($conn, $tabla, $form_uuid);

    // Ordenar los items según la configuración para mantener el orden del formulario
    if (!empty($detalle_items) && isset($inventarioConfig[$tipo_inventario])) {
        $secciones_ordenadas = array_keys($inventarioConfig[$tipo_inventario]);
        usort($detalle_items, function($a, $b) use ($secciones_ordenadas) {
            $pos_a = array_search($a['seccion'], $secciones_ordenadas);
            $pos_b = array_search($b['seccion'], $secciones_ordenadas);
            if ($pos_a === $pos_b) {
                return strcmp($a['codigo'], $b['codigo']);
            }
            return ($pos_a < $pos_b) ? -1 : 1;
        });
    }
}

?>
<style>
  .card-detalle {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }
  .detalle-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
  }
  .section-card {
    border: 1px solid #e2e9f4;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    background: #ffffff;
  }
  .section-card h5 {
    padding: 0.9rem 1.25rem;
    margin: 0;
    background: #f2f7fc;
    color: #123a63;
    font-size: 0.95rem;
  }
  .inventory-table thead th {
    background: #eef4fb;
    color: #123a63;
    font-size: 0.78rem;
  }
  .inventory-table tbody td {
    vertical-align: middle;
    font-size: 0.85rem;
  }
  .estado-badge {
    padding: 0.3em 0.6em;
    border-radius: 0.25rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75em;
  }
  .estado-bueno { background-color: #d1e7dd; color: #0f5132; }
  .estado-regular { background-color: #fff3cd; color: #664d03; }
  .estado-malo { background-color: #f8d7da; color: #842029; }
</style>

<div class="container-fluid py-4">
  <h2 class="mb-4">Consulta de Inventarios</h2>

  <!-- Formulario de Búsqueda -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" action="consultar_inventario.php">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
          </div>
          <div class="col-md-4">
            <label for="fecha_fin" class="form-label">Fecha Fin</label>
            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Buscar</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <?php if (!empty($form_uuid) && $detalle_header): ?>
    <!-- Vista de Detalle -->
    <div class="card card-detalle">
      <div class="detalle-header d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-0">Detalle de Inventario: <?= htmlspecialchars($detalle_header['ambulancia']) ?></h4>
          <p class="mb-0 text-muted">
            Placa: <?= htmlspecialchars($detalle_header['placa']) ?> | 
            Fecha: <?= htmlspecialchars($detalle_header['fecha']) ?> | 
            Responsable: <?= htmlspecialchars($detalle_header['responsable_general']) ?>
          </p>
        </div>
        <div>
          <a href="consultar_inventario.php" class="btn btn-outline-secondary">Volver a la búsqueda</a>
          <a href="inventario_exportar.php?tipo=<?= $count_tam > 0 ? 'TAM' : 'TAB' ?>&formato=xls&form_uuid=<?= $form_uuid ?>" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Exportar a XLS</a>
        </div>
      </div>
      <div class="card-body">
        <?php
        $seccion_actual = '';
        foreach ($detalle_items as $item):
            if ($item['seccion'] !== $seccion_actual):
                if ($seccion_actual !== ''):
                    echo '</tbody></table></div></div>'; // Cierra la tabla y la tarjeta de la sección anterior
                endif;
                $seccion_actual = $item['seccion'];
        ?>
          <div class="section-card">
            <h5><?= htmlspecialchars($seccion_actual) ?></h5>
            <div class="table-responsive">
              <table class="table table-sm table-striped table-hover inventory-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Cantidad</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                    <th>Serial</th>
                    <th>Ubicación</th>
                    <th>Registro Invima</th>
                    <th>Lote</th>
                    <th>Fecha Venc/Rev</th>
                  </tr>
                </thead>
                <tbody>
        <?php
            endif;
        ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                      <div class="small text-muted"><?= htmlspecialchars($item['codigo']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($item['cantidad']) ?></td>
                    <td><span class="estado-badge estado-<?= strtolower($item['estado']) ?>"><?= htmlspecialchars($item['estado']) ?></span></td>
                    <td><?= htmlspecialchars($item['observaciones']) ?></td>
                    <td><?= htmlspecialchars($item['serial']) ?></td>
                    <td><?= htmlspecialchars($item['ubicacion']) ?></td>
                    <td><?= htmlspecialchars($item['registro_invima']) ?></td>
                    <td><?= htmlspecialchars($item['lote']) ?></td>
                    <td><?= htmlspecialchars($item['fecha_vencimiento'] ?: $item['fecha_revision']) ?></td>
                  </tr>
        <?php
        endforeach;
        if ($seccion_actual !== ''):
            echo '</tbody></table></div></div>'; // Cierra la última tabla y tarjeta
        endif;
        ?>
      </div>
    </div>

  <?php elseif (!empty($registros)): ?>
    <!-- Vista de Lista de Resultados -->
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Tipo</th>
                <th>Ambulancia</th>
                <th>Placa</th>
                <th>Fecha</th>
                <th>Responsable</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registros as $reg): ?>
                <tr>
                  <td><span class="badge bg-<?= $reg['tipo'] === 'TAM' ? 'info' : 'secondary' ?>"><?= htmlspecialchars($reg['tipo']) ?></span></td>
                  <td><?= htmlspecialchars($reg['ambulancia']) ?></td>
                  <td><?= htmlspecialchars($reg['placa']) ?></td>
                  <td><?= htmlspecialchars($reg['fecha']) ?></td>
                  <td><?= htmlspecialchars($reg['responsable_general']) ?></td>
                  <td>
                    <a href="consultar_inventario.php?form_uuid=<?= htmlspecialchars($reg['form_uuid']) ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-eye"></i> Ver Detalle
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php elseif (!empty($fecha_inicio)): ?>
    <div class="alert alert-info">No se encontraron registros para el rango de fechas seleccionado.</div>
  <?php endif; ?>

</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Configuración de Flatpickr para los campos de fecha
    flatpickr("#fecha_inicio", {
        dateFormat: "Y-m-d",
        locale: "es",
        onChange: function(selectedDates, dateStr, instance) {
            // Opcional: Lógica adicional al cambiar la fecha
        }
    });
    flatpickr("#fecha_fin", {
        dateFormat: "Y-m-d",
        locale: "es",
        onChange: function(selectedDates, dateStr, instance) {
            // Opcional: Lógica adicional al cambiar la fecha
        }
    });

    // Establecer fechas por defecto si están vacías
    const fechaInicioInput = document.getElementById('fecha_inicio');
    const fechaFinInput = document.getElementById('fecha_fin');
    if (!fechaInicioInput.value) {
        const unaSemanaAtras = new Date();
        unaSemanaAtras.setDate(unaSemanaAtras.getDate() - 7);
        fechaInicioInput.value = unaSemanaAtras.toISOString().slice(0, 10);
    }
    if (!fechaFinInput.value) {
        fechaFinInput.value = new Date().toISOString().slice(0, 10);
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>