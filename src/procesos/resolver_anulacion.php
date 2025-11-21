<?php
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../conn.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$accion = isset($_POST['accion']) ? strtolower(trim($_POST['accion'])) : '';
$motivo = trim($_POST['motivo_respuesta'] ?? '');

if ($id <= 0 || ($accion !== 'aprobar' && $accion !== 'rechazar')) {
  $_SESSION['message'] = '<div class="alert alert-danger">Solicitud inválida.</div>';
  header('Location: ../admin/gestion_anulaciones.php');
  exit;
}

if ($motivo === '') {
  $_SESSION['message'] = '<div class="alert alert-danger">El motivo es obligatorio.</div>';
  header('Location: ../admin/gestion_anulaciones.php');
  exit;
}

$rolRaw = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
$rol = is_string($rolRaw) ? strtolower(trim($rolRaw)) : '';
$allowed = ['administrativo','master','dev'];
if (!in_array($rol, $allowed, true)) {
  http_response_code(403);
  echo 'Acceso no autorizado';
  exit;
}

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

// Determinar nuevo estado
if ($accion === 'aprobar') {
  $nuevoEstado = 'ANULADA';
} else { // rechazar
  $nuevoEstado = 'ACTIVA';
}

$sql = "UPDATE atenciones
        SET estado_registro = ?,
            motivo_respuesta_anulacion = ?,
            usuario_responde_anulacion = ?,
            fecha_respuesta_anulacion = NOW()
        WHERE id = ?";

$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param('ssii', $nuevoEstado, $motivo, $usuarioId, $id);
  $stmt->execute();
  $stmt->close();

  if ($accion === 'aprobar') {
    $_SESSION['message'] = '<div class="alert alert-success">La anulación fue APROBADA y el registro ha sido marcado como ANULADO.</div>';
  } else {
    $_SESSION['message'] = '<div class="alert alert-info">La solicitud de anulación fue RECHAZADA y el registro continúa ACTIVO.</div>';
  }
} else {
  $_SESSION['message'] = '<div class="alert alert-danger">Error al procesar la solicitud de anulación.</div>';
}

header('Location: ../admin/gestion_anulaciones.php');
exit;
