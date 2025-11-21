<?php
/**
 * guardar_parcial.php
 * Guarda parcialmente una sección del formulario en la tabla `atenciones`.
 * Reutiliza el ID existente o crea uno nuevo si no existe.
 */

require_once '../conn.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'id' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    // Datos recibidos
    $id = isset($_POST['id']) && is_numeric($_POST['id']) ? intval($_POST['id']) : null;
    $data = isset($_POST['data']) ? json_decode($_POST['data'], true) : [];
    if (!$data || !is_array($data)) {
        throw new Exception('Datos inválidos o vacíos.');
    }

    $fields = [];
    $values = [];
    foreach ($data as $k => $v) {
        $k = trim($k);
        if ($k === '' || $v === null) continue;
        $fields[$k] = $v;
    }
    $cols = [];
    if ($rs = $conn->query("SHOW COLUMNS FROM atenciones")) {
        while ($row = $rs->fetch_assoc()) { $cols[$row['Field']] = true; }
        $rs->close();
    }
    $aliases = [
        'municipio_servicio' => 'codigo_municipio_servicio',
    ];
    $normalized = [];
    foreach ($fields as $k => $v) {
        if (isset($cols[$k])) { $normalized[$k] = $v; continue; }
        if (isset($aliases[$k]) && isset($cols[$aliases[$k]])) { $normalized[$aliases[$k]] = $v; }
    }
    $fields = $normalized;
    if (empty($fields) && !$id) {
        throw new Exception('No hay campos válidos para guardar.');
    }

    // Si no existe ID, crear un registro vacío
    if (!$id) {
        $useCreatedAt = isset($cols['created_at']);
        if ($useCreatedAt) {
            $stmt = $conn->prepare("INSERT INTO atenciones (created_at) VALUES (NOW())");
        } else {
            $stmt = $conn->prepare("INSERT INTO atenciones () VALUES ()");
        }
        if (!$stmt || !$stmt->execute()) {
            throw new Exception('No se pudo crear la atención inicial.');
        }
        $id = $conn->insert_id;
        $stmt->close();
    }

    // Generar dinámicamente el SQL UPDATE
    if (!empty($fields) || isset($cols['updated_at'])) {
        $assignments = array_map(fn($f) => "$f = ?", array_keys($fields));
        if (isset($cols['updated_at'])) { $assignments[] = "updated_at = NOW()"; }
        $setClause = implode(', ', $assignments);
        if ($setClause !== '') {
            $sql = "UPDATE atenciones SET $setClause WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Error preparando SQL: ' . $conn->error);
            }
            $types = str_repeat('s', count($fields)) . 'i';
            $values = array_values($fields);
            $values[] = $id;
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                throw new Exception('Error al guardar los datos: ' . $stmt->error);
            }
            $stmt->close();
        }
    }

    $response = [
        'success' => true,
        'message' => 'Sección guardada correctamente.',
        'id' => $id
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
