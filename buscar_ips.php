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
if (strlen($searchTerm) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

// Consulta SQL para buscar IPS receptora
$sql = "SELECT ips_nit AS id, ips_nombre AS text, ips_ciudad AS ciudad 
        FROM ips_receptora 
        WHERE ips_nombre LIKE ? OR ips_nit LIKE ? OR ips_ciudad LIKE ?
        ORDER BY ips_nombre ASC
        LIMIT 50";
$likeTerm = '%' . $searchTerm . '%';
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(json_encode(['error' => 'Error en la preparación de la consulta: ' . $conn->error]));
}

$stmt->bind_param('sss', $likeTerm, $likeTerm, $likeTerm);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'text' => $row['text'] . ' (NIT: ' . $row['id'] . ') - ' . $row['ciudad'],
        'ciudad' => $row['ciudad'],
    ];
}

echo json_encode(['results' => $data], JSON_UNESCAPED_UNICODE);
?>