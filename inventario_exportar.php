<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/titulos.php';
require_once __DIR__ . '/inventario_detalle_utils.php';

$tipo = isset($_GET['tipo']) ? strtoupper(trim($_GET['tipo'])) : '';
$formato = isset($_GET['formato']) ? strtolower(trim($_GET['formato'])) : 'xls';
$formUuid = $_GET['form_uuid'] ?? '';

if ($formato !== 'xls') {
    http_response_code(400); // Solo permitimos XLS
    echo 'Formato no soportado';
    exit;
}

if (!$formUuid) {
    http_response_code(400);
    echo 'form_uuid requerido';
    exit;
}

$tabla = $tipo === 'TAM' ? 'inventario_tam' : ($tipo === 'TAB' ? 'inventario_tab' : null);
if (!$tabla) {
    http_response_code(400);
    echo 'Tipo invalido';
    exit;
}

list($header, $items) = obtenerInventario($conn, $tabla, $formUuid);

if (!$header) {
    http_response_code(404);
    echo 'Inventario no encontrado';
    exit;
}

$titulo = $tipo === 'TAM' ? 'Inventario TAM' : 'Inventario TAB';
$filenameBase = strtolower($titulo . '_' . preg_replace('/[^A-Za-z0-9]/', '-', $header['ambulancia'] ?? 'sin-nombre'));

$nombreEmpresa = $empresa['nombre']; // Tomado desde titulos.php

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filenameBase . '.xls');
echo "<table border='1'>";
echo '<thead>';
echo '<tr><th colspan="12" style="font-size: 16px; font-weight: bold;">' . htmlspecialchars($nombreEmpresa) . '</th></tr>';
echo '<tr><th colspan="12" style="font-size: 14px; font-weight: bold;">' . htmlspecialchars($titulo) . '</th></tr>';
echo '<tr>';
echo '<td colspan="6"><strong>Ambulancia:</strong> ' . htmlspecialchars($header['ambulancia'] ?? '') . '</td>';
echo '<td colspan="6"><strong>Placa:</strong> ' . htmlspecialchars($header['placa'] ?? '') . '</td>';
echo '</tr>';
echo '<tr><td colspan="12"><strong>Fecha:</strong> ' . htmlspecialchars($header['fecha'] ?? '') . '</td></tr>';
echo '<tr><td colspan="12"><strong>Responsable:</strong> ' . htmlspecialchars($header['responsable_general'] ?? '') . '</td></tr>';
echo '<tr style="background-color:#f0f0f0; font-weight: bold;">';
echo '<th>Seccion</th><th>Codigo</th><th>Item</th><th>Cantidad</th><th>Estado</th><th>Serial</th><th>Ubicacion</th><th>Registro Invima</th><th>Lote</th><th>Fecha Vencimiento/Revision</th><th>Responsable</th><th>Observaciones</th>';
echo '</tr>';
echo '</thead><tbody>';

foreach ($items as $item) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($item['seccion']) . '</td>';
    echo '<td>' . htmlspecialchars($item['codigo']) . '</td>';
    echo '<td>' . htmlspecialchars($item['nombre']) . '</td>';
    echo '<td>' . (int)$item['cantidad'] . '</td>';
    echo '<td>' . htmlspecialchars(strtoupper($item['estado'])) . '</td>';
    echo '<td>' . htmlspecialchars($item['serial'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($item['ubicacion'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($item['registro_invima'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($item['lote'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars(($item['fecha_vencimiento'] ?? $item['fecha_revision']) ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($item['responsable'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($item['observaciones'] ?? '') . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
exit;
?>
