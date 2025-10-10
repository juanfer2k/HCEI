<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Mostrar errores para depuración
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conn.php';

// Verificar conexión a la base de datos
if ($conn->connect_error) {
    die(json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]));
}

$searchTerm = trim($_GET['q'] ?? '');
if (strlen($searchTerm) < 3) {
    echo json_encode(['results' => []]);
    exit;
}

// Depurar el término de búsqueda
file_put_contents('debug.log', "Término de búsqueda: $searchTerm\n", FILE_APPEND);

// Ajustar la consulta SQL según las columnas disponibles
$sql = "SELECT codigo_municipio AS id, nombre_municipio AS text FROM municipios WHERE nombre_municipio LIKE ? LIMIT 50";
$likeTerm = '%' . $searchTerm . '%';
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(json_encode(['error' => 'Error en la preparación de la consulta: ' . $conn->error]));
}

$stmt->bind_param('s', $likeTerm);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die(json_encode(['error' => 'Error en la ejecución de la consulta: ' . $stmt->error]));
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(['results' => $data], JSON_UNESCAPED_UNICODE);
?>
