<?php
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/conn.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido';
  exit;
}

// Cargar resumen de la atención (el número de registro visible será el ID)
$stmt = $conn->prepare('SELECT id, nombres_paciente, tipo_identificacion, id_paciente, fecha_hora_atencion FROM atenciones WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$atencion = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$atencion) {
  http_response_code(404);
  echo 'Atención no encontrada';
  exit;
}

$rolRaw = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
$rol = is_string($rolRaw) ? strtolower(trim($rolRaw)) : '';
$allowed = ['tripulacion','dev'];
if (!in_array($rol, $allowed, true)) {
  http_response_code(403);
  echo 'Solo la tripulación puede solicitar anulación.';
  exit;
}

$hasHeader = @include __DIR__ . '/header.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<div class="container mt-4">
  <h2 class="mb-3">Solicitar anulación de la atención #<?php echo h($atencion['id']); ?></h2>

  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title mb-2">Resumen</h5>
      <p class="mb-1"><strong>Registro (ID):</strong> <?php echo h($atencion['id']); ?></p>
      <p class="mb-1"><strong>Paciente:</strong> <?php echo h($atencion['nombres_paciente'] ?? ''); ?></p>
      <p class="mb-1"><strong>Documento:</strong> <?php echo h(($atencion['tipo_identificacion'] ?? '') . ' ' . ($atencion['id_paciente'] ?? '')); ?></p>
      <p class="mb-0"><strong>Fecha atención:</strong> <?php echo h($atencion['fecha_hora_atencion'] ?? ''); ?></p>
    </div>
  </div>

  <form method="post" action="procesos/solicitar_anulacion.php">
    <input type="hidden" name="id" value="<?php echo h($atencion['id']); ?>">

    <div class="card mb-3">
      <div class="card-header">Motivo de la solicitud de anulación</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="motivo" class="form-label">Describa claramente por qué se debe anular este registro</label>
          <textarea class="form-control" id="motivo" name="motivo" rows="4" required></textarea>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
      <a href="obtener_detalle_atencion.php?id=<?php echo h($atencion['id']); ?>" class="btn btn-outline-secondary">Cancelar</a>
      <button type="submit" class="btn btn-danger">Enviar solicitud de anulación</button>
    </div>
  </form>
</div>
<?php
$hasFooter = @include __DIR__ . '/footer.php';
if (!$hasFooter && !$hasHeader) {
  echo '</body></html>';
}
