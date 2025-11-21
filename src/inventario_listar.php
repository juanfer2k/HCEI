<?php
header('Content-Type: application/json');
require_once __DIR__ . '/conn.php';

$tipo = isset($_GET['tipo']) ? strtoupper(trim($_GET['tipo'])) : '';
$tabla = $tipo === 'TAM' ? 'inventario_tam' : ($tipo === 'TAB' ? 'inventario_tab' : null);

if (!$tabla) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de inventario invalido']);
    exit;
}

$sql = "SELECT form_uuid,
               MAX(created_at) AS creado,
               MAX(fecha) AS fecha,
               MAX(ambulancia) AS ambulancia,
               MAX(placa) AS placa,
               MAX(responsable_general) AS responsable,
               MAX(auditor) AS auditor
        FROM {$tabla}
        GROUP BY form_uuid
        ORDER BY creado DESC
        LIMIT 50";

$result = $conn->query($sql);

$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

echo json_encode(['ok' => true, 'registros' => $rows]);
?>
