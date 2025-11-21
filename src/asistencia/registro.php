<?php
function registrarAsistencia($usuarioId, $tipo) {
    global $conn;
    $fechaHora = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO asistencia (usuario_id, tipo, fecha_hora) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $usuarioId, $tipo, $fechaHora);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}