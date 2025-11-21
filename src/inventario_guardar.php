<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON invalido: ' . json_last_error_msg()]);
    exit;
}

$tipo = isset($payload['tipo']) ? strtoupper(trim($payload['tipo'])) : '';
$tabla = $tipo === 'TAM' ? 'inventario_tam' : ($tipo === 'TAB' ? 'inventario_tab' : null);
if (!$tabla) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de inventario invalido']);
    exit;
}

$header = $payload['header'] ?? [];
$items = $payload['items'] ?? [];

$header['placa'] = strtoupper(trim($header['placa'] ?? ''));
if ($header['placa'] === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Debe seleccionar una placa']);
    exit;
}

$header['fecha'] = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $header['fecha'] ?? '') ? $header['fecha'] : date('Y-m-d');

$header['ambulancia'] = trim($header['ambulancia'] ?? '');
if ($header['ambulancia'] === '') {
    $header['ambulancia'] = $header['placa'];
}

$createdBy = $_SESSION['username'] ?? ($_SESSION['user'] ?? 'sistema');
$header['responsable_general'] = trim($header['responsable_general'] ?? '');
if ($header['responsable_general'] === '') {
    $header['responsable_general'] = $createdBy ?: 'sistema';
}

$auditor = trim($header['auditor'] ?? '');

if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['error' => 'No se recibieron items para guardar']);
    exit;
}

require_once __DIR__ . '/conn.php';

$formUuid = $payload['form_uuid'] ?? null;
if (!$formUuid) {
    $formUuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$conn->begin_transaction();
try {
    $stmtDelete = $conn->prepare("DELETE FROM {$tabla} WHERE form_uuid = ?");
    if ($stmtDelete === false) {
        throw new Exception('No se pudo preparar sentencia de eliminacion: ' . $conn->error);
    }
    $stmtDelete->bind_param('s', $formUuid);
    $stmtDelete->execute();
    $stmtDelete->close();

    $sql = "INSERT INTO {$tabla} (
        form_uuid, ambulancia, placa, fecha, responsable_general,
        responsable_item, seccion, item_codigo, item_nombre,
        cantidad, estado, observaciones, serial, ubicacion,
        fecha_revision, auditor, created_by
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('No se pudo preparar sentencia de insercion: ' . $conn->error);
    }

    foreach ($items as $item) {
        $seccion = $item['seccion'] ?? '';
        $codigo = $item['codigo'] ?? '';
        $nombre = $item['nombre'] ?? '';
        if ($codigo === '' || $nombre === '') {
            continue;
        }

        $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : 0;
        $estado = $item['estado'] ?? 'bueno';
        $observaciones = $item['observaciones'] ?? null;
        $serial = $item['serial'] ?? null;
        $ubicacion = $item['ubicacion'] ?? null;
        $fechaRevision = !empty($item['fecha_revision']) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $item['fecha_revision'])
            ? $item['fecha_revision']
            : null;

        $responsableItem = $item['responsable'] ?? null;

        $stmt->bind_param(
            'sssssssssisssssss',
            $formUuid,
            $header['ambulancia'],
            $header['placa'],
            $header['fecha'],
            $header['responsable_general'],
            $responsableItem,
            $seccion,
            $codigo,
            $nombre,
            $cantidad,
            $estado,
            $observaciones,
            $serial,
            $ubicacion,
            $fechaRevision,
            $auditor,
            $createdBy
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al insertar item: ' . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(['ok' => true, 'form_uuid' => $formUuid]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

