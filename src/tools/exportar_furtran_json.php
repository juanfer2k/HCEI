<?php
require_once __DIR__ . '/../conn.php';
header('Content-Type: application/json; charset=UTF-8');

$sql = "SELECT * FROM atenciones ORDER BY id DESC";
$result = $conn->query($sql);
$data = [];

while ($r = $result->fetch_assoc()) {
    $r['localizacion'] = ($r['localizacion'] === 'Rural') ? 'R' : 'U';
    $r['manifestacion_servicios'] = (strtoupper(trim($r['manifestacion_servicios'])) === 'SI') ? 1 : 0;
    $data[] = $r;
}

echo json_encode(['FURTRAN' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$conn->close();
