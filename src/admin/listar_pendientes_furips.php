<?php
/**
 * Listar Atenciones Pendientes de Reporte FURIPS
 */

require_once '../conn.php';
require_once '../access_control.php';

// Solo administradores (Master, Administrador, Administrativo)
$rol = $_SESSION['rol'] ?? $_SESSION['usuario_rol'] ?? $_SESSION['role'] ?? '';
$rolesPermitidos = ['Master', 'Administrador', 'Administrativo', 'master', 'administrador', 'administrativo'];

if (!in_array($rol, $rolesPermitidos)) {
    die(json_encode(['error' => 'Acceso denegado']));
}

// Consultar atenciones pendientes
$query = "
    SELECT 
        a.id,
        a.fecha,
        a.nombres_paciente,
        a.tipo_evento,
        a.condicion_victima,
        DATEDIFF(NOW(), a.fecha) as dias_pendiente
    FROM atenciones a
    LEFT JOIN atenciones_extra ae ON a.id = ae.atencion_id
    WHERE (
        a.tipo_evento = 'Accidente de tránsito'
        OR a.tipo_evento LIKE '%catastrófico%'
        OR a.tipo_evento LIKE '%terrorista%'
    )
    AND (ae.furips_reportado IS NULL OR ae.furips_reportado = 0)
    ORDER BY a.fecha ASC
    LIMIT 100
";

$result = $conn->query($query);
$atenciones = [];

while ($row = $result->fetch_assoc()) {
    $atenciones[] = $row;
}

header('Content-Type: application/json');
echo json_encode($atenciones);
?>
