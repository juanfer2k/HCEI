<?php
header('Content-Type: application/json');
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/inventario_detalle_utils.php';

$tipo = isset($_GET['tipo']) ? strtoupper(trim($_GET['tipo'])) : '';
$formUuid = $_GET['form_uuid'] ?? '';

if (!$formUuid) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta form_uuid']);
    exit;
}

$tabla = $tipo === 'TAM' ? 'inventario_tam' : ($tipo === 'TAB' ? 'inventario_tab' : null);

if (!$tabla) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de inventario invalido']);
    exit;
}

try {
    list($header, $items) = obtenerInventario($conn, $tabla, $formUuid);
    echo json_encode(['ok' => true, 'header' => $header, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
