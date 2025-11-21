<?php
require_once 'bootstrap.php';
require_once 'access_control.php';

header('Content-Type: application/json');

if (!isset($_GET['q']) || strlen($_GET['q']) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$search = '%' . $_GET['q'] . '%';

$sql = "SELECT 
            CONCAT(nombres, ' ', apellidos) as text,
            id_cc as id,
            CONCAT(nombres, ' ', apellidos, ' · ', id_cc) as full_text
        FROM tripulacion 
        WHERE (nombres LIKE ? OR apellidos LIKE ? OR id_cc LIKE ?)
        AND rol = 'Conductor'
        LIMIT 10";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => $row['id'],
            'text' => $row['full_text']
        ];
    }
    
    echo json_encode([
        'results' => $items
    ]);
} catch (Exception $e) {
    error_log("Error en búsqueda de conductor: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
