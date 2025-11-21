<?php
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../conn.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$rolRaw = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
$rol = is_string($rolRaw) ? strtolower(trim($rolRaw)) : '';
$allowed = ['administrativo','master','dev'];
if (!in_array($rol, $allowed, true)) {
  http_response_code(403);
  echo 'Acceso no autorizado';
  exit;
}

// Filtro simple por texto (ID, registro, paciente, documento)
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$whereExtra = '';
if ($q !== '') {
  $qEsc = $conn->real_escape_string($q);
  $like = "'%" . $qEsc . "%'";
  $whereExtra = " AND (id LIKE $like OR registro LIKE $like OR nombres_paciente LIKE $like OR id_paciente LIKE $like)";
}

// Cargar atenciones con solicitud de anulación (pendientes)
$sqlPend = "SELECT id, registro, nombres_paciente, tipo_identificacion, id_paciente, fecha_hora_atencion,
                   motivo_solicitud_anulacion, usuario_solicita_anulacion, fecha_solicitud_anulacion,
                   estado_registro
            FROM atenciones
            WHERE estado_registro = 'ANULACION_SOLICITADA' $whereExtra
            ORDER BY fecha_solicitud_anulacion DESC, id DESC";
$resPend = $conn->query($sqlPend);

// Historial de decisiones (registros donde ya hubo respuesta)
$sqlHist = "SELECT id, registro, nombres_paciente, tipo_identificacion, id_paciente, fecha_hora_atencion,
                   estado_registro, motivo_solicitud_anulacion, fecha_solicitud_anulacion,
                   motivo_respuesta_anulacion, usuario_responde_anulacion, fecha_respuesta_anulacion
            FROM atenciones
            WHERE estado_registro IN ('ANULADA','ACTIVA')
              AND motivo_respuesta_anulacion IS NOT NULL $whereExtra
            ORDER BY fecha_respuesta_anulacion DESC, id DESC";
$resHist = $conn->query($sqlHist);

$hasHeader = @include __DIR__ . '/../header.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><div class="container mt-4">
  <h2 class="mb-3">Gestión de solicitudes de anulación</h2>

  <?php if (!empty($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } ?>

  <form method="get" class="mb-3">
    <div class="input-group">
      <input type="text" name="q" class="form-control" placeholder="Filtrar por ID, registro, paciente o documento" value="<?php echo h($q); ?>">
      <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
    </div>
  </form>

  <div class="card">
    <div class="card-body">
      <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-pendientes-tab" data-bs-toggle="tab" data-bs-target="#tab-pendientes" type="button" role="tab">Pendientes</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-historial-tab" data-bs-toggle="tab" data-bs-target="#tab-historial" type="button" role="tab">Historial</button>
        </li>
      </ul>

      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-pendientes" role="tabpanel" aria-labelledby="tab-pendientes-tab">
          <?php if ($resPend && $resPend->num_rows > 0): ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Registro</th>
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Fecha atención</th>
                    <th>Motivo solicitud</th>
                    <th>Usuario solicita</th>
                    <th>Fecha solicitud</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $resPend->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo h($row['id']); ?></td>
                      <td><?php echo h($row['registro']); ?></td>
                      <td><?php echo h($row['nombres_paciente']); ?></td>
                      <td><?php echo h(($row['tipo_identificacion'] ?: '') . ' ' . ($row['id_paciente'] ?: '')); ?></td>
                      <td><?php echo h($row['fecha_hora_atencion']); ?></td>
                      <td style="max-width:250px; white-space:normal;"><?php echo nl2br(h($row['motivo_solicitud_anulacion'])); ?></td>
                      <td><?php echo h($row['usuario_solicita_anulacion']); ?></td>
                      <td><?php echo h($row['fecha_solicitud_anulacion']); ?></td>
                      <td>
                        <a href="../obtener_detalle_atencion.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-sm btn-outline-secondary mb-1">Ver</a>
                        <form method="post" action="../procesos/resolver_anulacion.php" class="d-inline">
                          <input type="hidden" name="id" value="<?php echo h($row['id']); ?>">
                          <input type="hidden" name="accion" value="aprobar">
                          <button type="button" class="btn btn-sm btn-success mb-1" onclick="resolverAnulacion(this, 'aprobar')">Aprobar</button>
                        </form>
                        <form method="post" action="../procesos/resolver_anulacion.php" class="d-inline">
                          <input type="hidden" name="id" value="<?php echo h($row['id']); ?>">
                          <input type="hidden" name="accion" value="rechazar">
                          <button type="button" class="btn btn-sm btn-danger mb-1" onclick="resolverAnulacion(this, 'rechazar')">Rechazar</button>
                        </form>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted mb-0">No hay solicitudes de anulación pendientes.</p>
          <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="tab-historial" role="tabpanel" aria-labelledby="tab-historial-tab">
          <?php if ($resHist && $resHist->num_rows > 0): ?>
            <div class="table-responsive mt-3">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Registro</th>
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Fecha atención</th>
                    <th>Estado final</th>
                    <th>Motivo solicitud</th>
                    <th>Fecha solicitud</th>
                    <th>Motivo respuesta</th>
                    <th>Usuario responde</th>
                    <th>Fecha respuesta</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($hrow = $resHist->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo h($hrow['id']); ?></td>
                      <td><?php echo h($hrow['registro']); ?></td>
                      <td><?php echo h($hrow['nombres_paciente']); ?></td>
                      <td><?php echo h(($hrow['tipo_identificacion'] ?: '') . ' ' . ($hrow['id_paciente'] ?: '')); ?></td>
                      <td><?php echo h($hrow['fecha_hora_atencion']); ?></td>
                      <td>
                        <?php if ($hrow['estado_registro'] === 'ANULADA'): ?>
                          <span class="badge bg-danger">ANULADA</span>
                        <?php else: ?>
                          <span class="badge bg-success">ACTIVA</span>
                        <?php endif; ?>
                      </td>
                      <td style="max-width:220px; white-space:normal;"><?php echo nl2br(h($hrow['motivo_solicitud_anulacion'])); ?></td>
                      <td><?php echo h($hrow['fecha_solicitud_anulacion']); ?></td>
                      <td style="max-width:220px; white-space:normal;"><?php echo nl2br(h($hrow['motivo_respuesta_anulacion'])); ?></td>
                      <td><?php echo h($hrow['usuario_responde_anulacion']); ?></td>
                      <td><?php echo h($hrow['fecha_respuesta_anulacion']); ?></td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted mt-3 mb-0">Aún no hay histórico de anulaciones procesadas para el filtro actual.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function resolverAnulacion(btn, accion) {
  var motivo = prompt('Ingrese el motivo para ' + (accion === 'aprobar' ? 'APROBAR' : 'RECHAZAR') + ' la anulación:');
  if (motivo === null) return; // Cancelado
  motivo = motivo.trim();
  if (!motivo) {
    alert('El motivo es obligatorio.');
    return;
  }
  var form = btn.closest('form');
  var input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'motivo_respuesta';
  input.value = motivo;
  form.appendChild(input);
  form.submit();
}
</script>
<?php
$hasFooter = @include __DIR__ . '/../footer.php';
if (!$hasFooter && !$hasHeader) {
  echo '</body></html>';
}
