<?php
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../conn.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$motivo = trim($_POST['motivo'] ?? '');

if ($id <= 0 || $motivo === '') {
  $_SESSION['message'] = '<div class="alert alert-danger">ID inválido o motivo vacío.</div>';
  header('Location: ../consulta_atenciones.php');
  exit;
}

$rolRaw = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
$rol = is_string($rolRaw) ? strtolower(trim($rolRaw)) : '';
if (!in_array($rol, ['tripulacion','dev'], true)) {
  http_response_code(403);
  echo 'No autorizado.';
  exit;
}

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

// Actualizar estado y datos de la solicitud de anulación
$sql = "UPDATE atenciones SET estado_registro = 'ANULACION_SOLICITADA', motivo_solicitud_anulacion = ?, usuario_solicita_anulacion = ?, fecha_solicitud_anulacion = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param('sii', $motivo, $usuarioId, $id);
  $stmt->execute();
  $stmt->close();
  $_SESSION['message'] = '<div class="alert alert-warning">Solicitud de anulación enviada para revisión administrativa.</div>';
} else {
  $_SESSION['message'] = '<div class="alert alert-danger">Error al registrar la solicitud de anulación.</div>';
}

header('Location: ../obtener_detalle_atencion.php?id=' . urlencode($id));
exit;
